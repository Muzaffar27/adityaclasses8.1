<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonAccess;
use App\Models\LessonProgress;
use App\Models\StudentLearningDay;
use App\Models\Subject;
use App\Models\User;
use App\Services\StudentLearningActivityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesIsolatedLearningDatabase;
use Tests\TestCase;

class StudentLearningActivityTest extends TestCase
{
    use UsesIsolatedLearningDatabase;

    private User $student;
    private Lesson $lesson;
    private LessonAccess $access;
    private string $session;
    private string $loginPassword;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareLearningDatabase(true);
        $this->travelTo(Carbon::parse('2026-10-02 12:00:00', 'Indian/Mauritius'));
        Storage::fake('local');
        $this->loginPassword = Str::random(30);
        // Keep per-user throttling enabled, with a different requester for each isolated test.
        $this->student = User::factory()->create(['id' => random_int(1000000, 2000000), 'role' => 'student', 'password' => $this->loginPassword]);
        $this->lesson = Lesson::create([
            'grade_id' => Grade::create(['name' => 'Grade 10'])->id,
            'subject_id' => Subject::create(['name' => 'Mathematics'])->id,
            'title' => 'Quadratics', 'topic' => 'Algebra', 'is_active' => true,
            'vimeo_url' => 'https://player.vimeo.com/video/1', 'answer_vimeo_url' => 'https://player.vimeo.com/video/2',
            'question_pdf_path' => 'question.pdf', 'question_pdf_2_path' => 'question2.pdf',
            'lesson_pdf_path' => 'lesson.pdf',
        ]);
        foreach (['question.pdf', 'question2.pdf', 'lesson.pdf'] as $path) Storage::disk('local')->put($path, '%PDF-1.4');
        $this->access = LessonAccess::create([
            'user_id' => $this->student->id, 'grade_id' => $this->lesson->grade_id,
            'subject_id' => $this->lesson->subject_id, 'status' => 'accepted', 'expires_at' => now()->addMonth(),
        ]);
        $this->session = (string) Str::uuid();
        Sanctum::actingAs($this->student);
    }

    protected function tearDown(): void
    {
        $this->closeLearningDatabase();
        $this->travelBack();
        parent::tearDown();
    }

    private function video(int $position, bool $active = true, string $type = 'lesson', ?string $session = null)
    {
        return $this->putJson('/api/lesson-progress/' . $this->lesson->id, [
            'video_type' => $type, 'position_seconds' => $position, 'duration_seconds' => 3600,
            'activity_session_id' => $session ?? $this->session, 'activity_active' => $active,
        ]);
    }

    private function pdf(bool $active = true, string $type = 'question')
    {
        return $this->putJson('/api/lessons/' . $this->lesson->id . '/pdf/' . $type . '/activity', [
            'activity_session_id' => $this->session, 'activity_active' => $active,
        ]);
    }

    private function summary(): array
    {
        return $this->getJson('/api/student/dashboard')->assertOk()->json('learning_activity');
    }

    public function test_ten_minutes_of_video_qualifies_once_and_opens_do_not_count(): void
    {
        $this->putJson('/api/lesson-progress/' . $this->lesson->id, ['video_type' => 'lesson', 'viewed_only' => true])->assertOk();
        $this->getJson('/api/lessons/' . $this->lesson->id . '/pdf/question')->assertOk();
        $this->assertSame(0, $this->summary()['active_days']);
        $this->video(0)->assertOk();
        for ($i = 1; $i <= 29; $i++) { $this->travel(20)->seconds(); $this->video($i * 20)->assertOk(); }
        $this->assertSame(0, $this->summary()['active_days']);
        $this->travel(20)->seconds(); $this->video(600)->assertOk();
        $this->video(600)->assertOk();
        $this->assertSame(1, $this->summary()['active_days']);
        $this->assertSame(600, $this->summary()['today_playback_seconds']);
        $this->assertSame(1, StudentLearningDay::where('user_id', $this->student->id)->count());
    }

    public function test_question_pdf_qualifies_after_ten_minutes_and_stops_while_inactive(): void
    {
        $this->pdf()->assertOk();
        for ($i = 1; $i <= 30; $i++) { $this->travel(20)->seconds(); $this->pdf()->assertOk(); }
        $this->assertTrue($this->summary()['today_active']);
        $this->assertSame(600, $this->summary()['today_question_pdf_seconds']);
        $this->pdf(false)->assertOk();
        $this->travel(10)->minutes(); $this->pdf(false)->assertOk();
        $this->assertSame(600, $this->summary()['today_question_pdf_seconds']);
        $this->pdf(true)->assertOk();
        $this->travel(20)->seconds(); $this->pdf(false)->assertOk();
        $this->assertSame(620, $this->summary()['today_question_pdf_seconds']);
    }

    public function test_five_minutes_of_each_category_do_not_qualify(): void
    {
        $this->video(0)->assertOk();
        for ($i = 1; $i <= 15; $i++) { $this->travel(20)->seconds(); $this->video($i * 20)->assertOk(); }
        $this->video(300, false)->assertOk();
        $this->pdf()->assertOk();
        for ($i = 1; $i <= 15; $i++) { $this->travel(20)->seconds(); $this->pdf()->assertOk(); }
        $summary = $this->summary();
        $this->assertSame(300, $summary['today_playback_seconds']);
        $this->assertSame(300, $summary['today_question_pdf_seconds']);
        $this->assertFalse($summary['today_active']);
    }

    public function test_pause_seek_long_gaps_and_new_sessions_do_not_inflate_video_time(): void
    {
        $this->video(0)->assertOk();
        $this->travel(20)->seconds(); $this->video(20, false)->assertOk();
        $this->travel(10)->minutes(); $this->video(20)->assertOk();
        $this->travel(20)->seconds(); $this->video(1000)->assertOk();
        $this->travel(60)->seconds(); $this->video(1060)->assertOk();
        $this->travel(20)->seconds(); $this->video(1080, true, 'lesson', (string) Str::uuid())->assertOk();
        $this->assertSame(20, $this->summary()['today_playback_seconds']);
    }

    public function test_overlapping_tabs_and_resource_types_credit_time_only_once(): void
    {
        $this->video(0)->assertOk();
        $this->pdf()->assertOk();
        $this->travel(20)->seconds();
        $this->video(20)->assertOk();
        $this->pdf()->assertOk();
        $this->assertSame(20, $this->summary()['playback_seconds']);
        $this->assertSame(0, $this->summary()['question_pdf_seconds']);
    }

    public function test_heartbeat_interval_splits_at_mauritius_midnight(): void
    {
        $this->travelTo(Carbon::parse('2026-10-02 23:59:50', 'Indian/Mauritius'));
        $this->video(0)->assertOk();
        $this->travel(20)->seconds(); $this->video(20)->assertOk();
        $this->assertSame(10, StudentLearningDay::where('activity_date', '2026-10-02')->value('playback_seconds'));
        $this->assertSame(10, StudentLearningDay::where('activity_date', '2026-10-03')->value('playback_seconds'));
    }

    public function test_login_records_timestamps_without_counting_a_study_day(): void
    {
        $this->postJson('/api/login', ['email' => $this->student->email, 'password' => 'incorrect-test-value'])->assertUnprocessable();
        $this->assertSame(0, StudentLearningDay::count());
        $this->postJson('/api/login', ['email' => $this->student->email, 'password' => $this->loginPassword])->assertOk();
        $this->travel(20)->seconds();
        $this->postJson('/api/login', ['email' => $this->student->email, 'password' => $this->loginPassword])->assertOk();
        $this->getJson('/api/me')->assertOk();
        $day = StudentLearningDay::firstOrFail();
        $this->assertSame(2, $day->login_count);
        $this->assertTrue($day->last_login_at->greaterThan($day->first_login_at));
        $this->assertSame(0, $this->summary()['active_days']);
    }

    public function test_tutor_and_student_summaries_match_and_students_cannot_inspect_others(): void
    {
        $day = StudentLearningDay::create(['user_id' => $this->student->id, 'activity_date' => '2026-10-02']);
        $day->playback_seconds = 600; $day->save();
        $studentSummary = $this->summary();
        $this->getJson('/api/students/' . $this->student->id . '/learning-activity')->assertForbidden();
        $other = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($other);
        $this->assertSame(0, $this->summary()['active_days']);
        $this->getJson('/api/students/' . $this->student->id . '/learning-activity')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'tutor']));
        $this->assertSame($studentSummary, $this->getJson('/api/students/' . $this->student->id . '/learning-activity')->assertOk()->json());
        $this->getJson('/api/students/' . $this->student->id . '/learning-activity?from=2026-10-02&to=2026-10-01')->assertUnprocessable();
    }

    public function test_inaccessible_content_and_non_question_pdfs_cannot_credit_time(): void
    {
        $this->pdf(true, 'lesson')->assertUnprocessable();
        $this->pdf(true, 'answer')->assertUnprocessable();
        $this->access->update(['expires_at' => now()->subSecond()]);
        $this->pdf()->assertForbidden(); $this->video(0)->assertForbidden();
        $this->access->update(['expires_at' => null]);
        $this->lesson->update(['is_active' => false]);
        $this->pdf()->assertNotFound(); $this->video(0)->assertNotFound();
        $this->assertSame(0, StudentLearningDay::count());
    }

    public function test_completed_main_and_answer_videos_are_counted_separately_from_pdf_opens(): void
    {
        $this->video(0)->assertOk();
        $this->video(0, true, 'answer')->assertOk();
        LessonProgress::where('user_id', $this->student->id)->update(['completed_at' => now()]);
        $this->getJson('/api/lessons/' . $this->lesson->id . '/pdf/question')->assertOk();
        $summary = $this->summary();
        $this->assertSame(2, $summary['completed_videos']);
        $this->assertSame(1, $summary['completed_lessons']);
        $this->assertSame(0, $summary['active_days']);
        $this->lesson->update(['answer_vimeo_url' => 'https://player.vimeo.com/video/3']);
        $this->assertSame(1, $this->summary()['completed_videos']);
    }

    public function test_replaced_video_and_legacy_clients_reset_activity_baselines(): void
    {
        $this->video(0)->assertOk();
        $this->travel(20)->seconds(); $this->video(20)->assertOk();
        $this->lesson->update(['vimeo_url' => 'https://player.vimeo.com/video/3']);
        $this->putJson('/api/lesson-progress/' . $this->lesson->id, ['video_type' => 'lesson', 'viewed_only' => true])->assertOk();
        $this->travel(20)->seconds(); $this->video(40)->assertOk();
        $this->assertSame(20, $this->summary()['today_playback_seconds']);
        $this->travel(20)->seconds();
        $this->putJson('/api/lesson-progress/' . $this->lesson->id, ['video_type' => 'lesson', 'position_seconds' => 60, 'duration_seconds' => 3600])->assertOk();
        $this->travel(20)->seconds(); $this->video(80)->assertOk();
        $this->assertSame(20, $this->summary()['today_playback_seconds']);
    }

    public function test_both_question_sheets_share_a_category_and_the_reporting_period_is_inclusive(): void
    {
        $this->pdf()->assertOk();
        for ($i = 1; $i <= 10; $i++) { $this->travel(30)->seconds(); $this->pdf()->assertOk(); }
        $this->pdf(false)->assertOk();
        $this->pdf(true, 'question2')->assertOk();
        for ($i = 1; $i <= 10; $i++) { $this->travel(30)->seconds(); $this->pdf(true, 'question2')->assertOk(); }
        $this->assertSame(600, $this->summary()['today_question_pdf_seconds']);
        $service = app(StudentLearningActivityService::class);
        $this->assertSame(1, $service->summary($this->student->id, '2026-10-02', '2026-10-02')['active_days']);
        $this->assertSame(0, $service->summary($this->student->id, '2026-10-01', '2026-10-01')['active_days']);
    }
}
