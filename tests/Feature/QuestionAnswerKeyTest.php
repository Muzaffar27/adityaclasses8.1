<?php

namespace Tests\Feature;

use App\Models\AssessmentBatch;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonAccess;
use App\Models\LessonQuestionSource;
use App\Models\QuestionAnswerKey;
use App\Models\QuestionReview;
use App\Models\Subject;
use App\Models\TestPaper;
use App\Models\User;
use App\Services\ExactMathAnswerService;
use App\Services\QuestionAnswerKeyService;
use App\Services\TestPaperReadinessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesIsolatedLearningDatabase;
use Tests\TestCase;

class QuestionAnswerKeyTest extends TestCase
{
    use UsesIsolatedLearningDatabase;
    private User $tutor;
    private User $student;
    private string $question;
    private array $input;

    protected function setUp(): void
    {
        parent::setUp(); $this->prepareLearningDatabase();
        foreach (['2026_10_19_000001_create_lesson_question_sources_table.php', '2026_10_19_000002_create_question_reviews_table.php',
            '2026_11_01_000001_create_question_corrections_table.php', '2026_11_12_000001_create_saved_test_papers.php',
            '2026_11_12_000002_create_question_answer_keys.php'] as $file) (require database_path('migrations/'.$file))->up();
        Storage::fake('local');
        $this->tutor = User::factory()->create(['role' => 'tutor']); $this->student = User::factory()->create(['role' => 'student']);
        $grade = Grade::create(['name' => 'Grade 8'])->id; $subject = Subject::create(['name' => 'Mathematics'])->id;
        LessonAccess::create(['user_id' => $this->student->id, 'grade_id' => $grade, 'subject_id' => $subject, 'status' => 'accepted']);
        $this->input = ['student_id' => $this->student->id, 'grade_id' => $grade, 'subject_id' => $subject, 'purpose' => 'main',
            'topics' => ['A', 'B', 'C'], 'batch_key' => (string) Str::uuid()];
        $bank = [];
        foreach (['A', 'B', 'C'] as $index => $topic) {
            $lesson = Lesson::create(['grade_id' => $grade, 'subject_id' => $subject, 'topic' => $topic, 'title' => "Lesson $topic",
                'vimeo_url' => 'https://player.vimeo.com/video/1', 'is_active' => true]);
            $path = "lesson-pdfs/$topic.pdf"; Storage::disk('local')->put($path, "%PDF-1.4 $topic"); $lesson->update(['question_pdf_path' => $path]);
            $source = LessonQuestionSource::where('lesson_id', $lesson->id)->sole(); $ids = [];
            for ($i = 0; $i < 5; $i++) {
                $id = hash('sha256', "$topic:$i"); $ids[] = $id;
                $content = ['instructions' => 'Calculate:', 'math' => ['type' => 'number', 'value' => (string) ($index * 5 + $i + 1)]];
                if ($topic === 'A' && $i === 0) {
                    $this->question = $id;
                    $content['math'] = ['type' => 'expression', 'children' => [
                        ['type' => 'fraction', 'numerator' => ['type' => 'number', 'value' => '3'], 'denominator' => ['type' => 'number', 'value' => '4']],
                        ['type' => 'operator', 'value' => '+'],
                        ['type' => 'fraction', 'numerator' => ['type' => 'number', 'value' => '1'], 'denominator' => ['type' => 'number', 'value' => '2']]]];
                }
                $bank[$id] = $content + ['id' => $id, 'active' => true, 'grade_id' => $grade, 'subject_id' => $subject,
                    'origins' => [['source_key' => "$grade:$subject:{$lesson->id}:question", 'source_sha256' => hash('sha256', "%PDF-1.4 $topic"), 'page' => 1, 'label' => (string) $i]]];
                QuestionReview::create(['id' => $id, 'grade_id' => $grade, 'subject_id' => $subject, 'status' => 'approved', 'content' => $content, 'origins' => []]);
            }
            $source->update(['status' => 'processed', 'source_sha256' => hash('sha256', "%PDF-1.4 $topic"), 'question_ids' => $ids]);
        }
        Storage::disk('local')->put('structured/bank.json', json_encode(['questions' => $bank]));
        Sanctum::actingAs($this->tutor);
    }

    protected function tearDown(): void { $this->closeLearningDatabase(); parent::tearDown(); }

    private function answer(): array
    {
        return ['final_answer' => '5/4', 'accepted_answers' => ['1.25', '1 1/4'], 'working' => ['3/4 + 2/4 = 5/4'],
            'marks' => '2.00', 'marking_method' => 'exact_numeric', 'answer_form' => 'equivalent_numeric', 'unit' => null, 'rubric' => []];
    }

    private function draft(?array $answer = null, ?string $request = null): array
    {
        $service = app(QuestionAnswerKeyService::class);
        $hash = $service->contentHash($service->currentQuestion($this->question)['content']);
        return $this->postJson('/api/admin/question-answer-keys/questions/'.$this->question,
            ['answer' => $answer ?? $this->answer(), 'request_key' => $request ?? (string) Str::uuid(), 'question_content_hash' => $hash])->assertOk()->json();
    }

    private function approve(array $key): void
    {
        $this->postJson('/api/admin/question-answer-keys/'.$key['id'].'/approve', ['question_content_hash' => $key['question_content_hash']])->assertOk()->assertJsonPath('status', 'approved');
    }

    public function test_generation_is_a_reusable_unapproved_draft_without_invented_marks(): void
    {
        $url = '/api/admin/question-answer-keys/questions/'.$this->question.'/generate';
        $key = $this->postJson($url)->assertOk()->assertJsonPath('key.status', 'draft')->assertJsonPath('key.answer.final_answer', '5/4')
            ->assertJsonPath('key.answer.marks', null)->json('key');
        $this->postJson($url)->assertOk()->assertJsonPath('reused', true)->assertJsonPath('key.id', $key['id']);
        $this->assertSame(1, QuestionAnswerKey::count());
        $this->postJson('/api/admin/question-answer-keys/'.$key['id'].'/approve', ['question_content_hash' => $key['question_content_hash']])->assertUnprocessable();
        $approved = $this->draft(); $this->approve($approved);
        $this->postJson($url)->assertOk()->assertJsonPath('reused', true)->assertJsonPath('key.id', $approved['id']);
    }

    public function test_manual_draft_idempotency_and_revisions_preserve_previous_versions(): void
    {
        $request = (string) Str::uuid(); $key = $this->draft(null, $request);
        $this->assertSame($key['id'], $this->draft(null, $request)['id']);
        $changed = $this->answer(); $changed['marks'] = '3.00';
        $service = app(QuestionAnswerKeyService::class); $hash = $key['question_content_hash'];
        $this->postJson('/api/admin/question-answer-keys/questions/'.$this->question,
            ['answer' => $changed, 'request_key' => $request, 'question_content_hash' => $hash])->assertConflict();
        $new = $this->draft($changed); $this->assertSame(2, $new['version']);
        $this->assertSame('2.00', QuestionAnswerKey::findOrFail($key['id'])->answer['marks']);
        $this->assertSame([], $service->approvedFor([$this->question => $service->currentQuestion($this->question)]));
    }

    public function test_approval_is_separate_idempotent_and_requires_the_same_current_question(): void
    {
        $key = $this->draft(); $this->approve($key); $this->approve($key);
        $this->assertSame($this->tutor->id, QuestionAnswerKey::findOrFail($key['id'])->approved_by);
        Storage::disk('local')->put('lesson-pdfs/A.pdf', 'changed');
        $this->postJson('/api/admin/question-answer-keys/'.$key['id'].'/approve', ['question_content_hash' => $key['question_content_hash']])->assertConflict();
    }

    public function test_wrong_numerical_answers_alternatives_and_rubric_totals_are_rejected(): void
    {
        $hash = app(QuestionAnswerKeyService::class)->contentHash(app(QuestionAnswerKeyService::class)->currentQuestion($this->question)['content']);
        $wrong = $this->answer(); $wrong['final_answer'] = '6/4';
        $variants = $this->answer(); $variants['accepted_answers'] = ['1.5'];
        $rubric = $this->answer(); $rubric['rubric'] = [['description' => 'Final answer', 'marks' => 3]];
        $form = $this->answer(); $form['answer_form'] = 'lowest_terms_fraction';
        foreach ([$wrong, $variants, $rubric, $form] as $answer) $this->postJson('/api/admin/question-answer-keys/questions/'.$this->question,
            ['answer' => $answer, 'request_key' => (string) Str::uuid(), 'question_content_hash' => $hash])->assertUnprocessable();
        $this->assertSame(0, QuestionAnswerKey::count());
    }

    public function test_revoking_latest_published_key_does_not_silently_fall_back_to_an_older_key(): void
    {
        $old = $this->draft(); $this->approve($old);
        $new = $this->draft(); $this->approve($new);
        $this->postJson('/api/admin/question-answer-keys/'.$new['id'].'/revoke', ['reason' => 'Needs review'])->assertOk()->assertJsonPath('status', 'revoked');
        $service = app(QuestionAnswerKeyService::class);
        $this->assertSame([], $service->approvedFor([$this->question => $service->currentQuestion($this->question)]));
        $this->postJson('/api/admin/question-answer-keys/'.$new['id'].'/approve', ['question_content_hash' => $new['question_content_hash']])->assertConflict();
    }

    public function test_discarding_an_unapproved_draft_keeps_the_published_key(): void
    {
        $old = $this->draft(); $this->approve($old); $draft = $this->draft();
        $this->postJson('/api/admin/question-answer-keys/'.$draft['id'].'/revoke', ['reason' => 'Discard this draft'])->assertOk();
        $service = app(QuestionAnswerKeyService::class);
        $keys = $service->approvedFor([$this->question => $service->currentQuestion($this->question)]);
        $this->assertSame($old['id'], $keys[$this->question]->id);
    }

    public function test_students_cannot_read_create_approve_or_revoke_any_answer_key(): void
    {
        $key = $this->draft(); Sanctum::actingAs($this->student);
        foreach (['/api/admin/question-answer-keys', '/api/admin/question-answer-keys/questions/'.$this->question] as $url) $this->getJson($url)->assertForbidden();
        $this->postJson('/api/admin/question-answer-keys/questions/'.$this->question.'/generate')->assertForbidden();
        $this->postJson('/api/admin/question-answer-keys/questions/'.$this->question, [])->assertForbidden();
        foreach (['approve', 'revoke'] as $action) $this->postJson('/api/admin/question-answer-keys/'.$key['id'].'/'.$action, [])->assertForbidden();
    }

    public function test_pending_questions_stale_hashes_and_unsupported_content_are_not_generated_as_answers(): void
    {
        $this->postJson('/api/admin/question-answer-keys/questions/'.$this->question,
            ['answer' => $this->answer(), 'request_key' => (string) Str::uuid(), 'question_content_hash' => str_repeat('f', 64)])->assertConflict();
        QuestionReview::whereKey($this->question)->update(['status' => 'pending']);
        $this->postJson('/api/admin/question-answer-keys/questions/'.$this->question.'/generate')->assertConflict();
        QuestionReview::whereKey($this->question)->update(['status' => 'approved']);
        $bank = json_decode(Storage::disk('local')->get('structured/bank.json'), true);
        $bank['questions'][$this->question]['instructions'] = 'Leave your answer in index form';
        Storage::disk('local')->put('structured/bank.json', json_encode($bank));
        $this->postJson('/api/admin/question-answer-keys/questions/'.$this->question.'/generate')->assertOk()->assertJsonPath('needs_tutor', true);
        $this->assertSame(0, QuestionAnswerKey::count());
    }

    public function test_readiness_counts_only_approved_keys_and_changed_content_invalidates_them(): void
    {
        $key = $this->draft();
        $this->assertSame(0, app(TestPaperReadinessService::class)->snapshot()['report']['approved_answer_keys']);
        $this->approve($key);
        $this->assertSame(1, app(TestPaperReadinessService::class)->snapshot()['report']['approved_answer_keys']);
        $bank = json_decode(Storage::disk('local')->get('structured/bank.json'), true);
        $bank['questions'][$this->question]['instructions'] = 'Simplify:';
        Storage::disk('local')->put('structured/bank.json', json_encode($bank));
        $this->assertSame(0, app(TestPaperReadinessService::class)->snapshot()['report']['approved_answer_keys']);
    }

    public function test_approved_keys_are_reused_across_students_and_frozen_into_new_papers_only(): void
    {
        $old = $this->postJson('/api/admin/test-papers', $this->input)->assertOk()->assertJsonPath('marking_ready', false)->json('id');
        $service = app(QuestionAnswerKeyService::class);
        foreach (app(TestPaperReadinessService::class)->snapshot()['approved'] as $q) {
            $answer = app(ExactMathAnswerService::class)->suggest($q['content'])['answer']; $answer['marks'] = '2.00';
            $key = $service->draft($this->tutor, $q['id'], (string) Str::uuid(), $service->contentHash($q['content']), $answer);
            $service->approve($this->tutor, $key->id, $key->question_content_hash);
        }
        $input = $this->input; $input['batch_key'] = (string) Str::uuid();
        $paper = $this->postJson('/api/admin/test-papers', $input)->assertOk()->assertJsonPath('marking_ready', true)->json('id');
        $this->assertSame('30.00', TestPaper::findOrFail($paper)->attempt->total_marks);
        $this->assertFalse(TestPaper::findOrFail($old)->marking_ready);
        $this->assertNull(TestPaper::findOrFail($old)->questions->first()->marking_snapshot);
        $other = User::factory()->create(['role' => 'student']);
        LessonAccess::create(['user_id' => $other->id, 'grade_id' => $input['grade_id'], 'subject_id' => $input['subject_id'], 'status' => 'accepted']);
        $input['student_id'] = $other->id;
        $this->postJson('/api/admin/test-papers', $input)->assertOk()->assertJsonPath('marking_ready', true);
        $this->assertSame(15, QuestionAnswerKey::count());
        $snapshot = TestPaper::findOrFail($paper)->questions->where('source_question_id', $this->question)->first()->marking_snapshot;
        $newAnswer = $this->answer(); $newAnswer['marks'] = '3.00'; $new = $this->draft($newAnswer); $this->approve($new);
        $this->assertSame($snapshot, TestPaper::findOrFail($paper)->questions->where('source_question_id', $this->question)->first()->marking_snapshot);
        Sanctum::actingAs($this->student);
        $response = $this->getJson('/api/test-papers/'.$paper)->assertOk();
        $this->assertStringNotContainsString('final_answer', $response->getContent());
        $this->assertStringNotContainsString('marking_snapshot', $response->getContent());
    }

    public function test_answer_content_is_immutable_and_private_headers_are_present(): void
    {
        $key = $this->draft();
        $response = $this->getJson('/api/admin/question-answer-keys')->assertOk()->assertJsonPath('total', 15);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->expectException(\LogicException::class);
        QuestionAnswerKey::findOrFail($key['id'])->update(['answer' => $this->answer() + ['changed' => true]]);
    }
}
