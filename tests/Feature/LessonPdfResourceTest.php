<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonAccess;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonPdfResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutor_can_manage_both_pdfs_and_student_with_course_access_can_view_them(): void
    {
        Storage::fake('local');
        $tutor = User::factory()->create(['role' => 'tutor']);
        $student = User::factory()->create(['role' => 'student']);
        $grade = Grade::create(['name' => 'Grade 10']);
        $subject = Subject::create(['name' => 'Mathematics']);
        $lesson = Lesson::create([
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'topic' => 'Algebra',
            'title' => 'Revision',
            'vimeo_url' => 'https://player.vimeo.com/video/1',
            'is_active' => true,
        ]);

        Sanctum::actingAs($tutor);
        foreach (['question', 'answer'] as $type) {
            $this->post("/api/admin/lessons/{$lesson->id}/pdf", [
                'type' => $type,
                'pdf' => UploadedFile::fake()->createWithContent("{$type}.pdf", "%PDF-1.4\n%%EOF"),
            ])->assertOk();
        }

        $lesson->refresh();
        Storage::disk('local')->assertExists($lesson->question_pdf_path);
        Storage::disk('local')->assertExists($lesson->answer_pdf_path);
        $this->getJson('/api/admin/lessons')
            ->assertOk()
            ->assertJsonPath('0.has_question_pdf', true)
            ->assertJsonPath('0.has_answer_pdf', true)
            ->assertJsonMissingPath('0.question_pdf_path')
            ->assertJsonMissingPath('0.answer_pdf_path');

        Sanctum::actingAs($student);
        $this->get("/api/lessons/{$lesson->id}/pdf/question")->assertForbidden();
        LessonAccess::create([
            'user_id' => $student->id,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'status' => 'accepted',
            'expires_at' => now()->addMonth(),
        ]);

        foreach (['question', 'answer'] as $type) {
            $view = $this->get("/api/lessons/{$lesson->id}/pdf/{$type}")
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('inline;', $view->headers->get('content-disposition'));
        }

        Sanctum::actingAs($tutor);
        $answerPath = $lesson->answer_pdf_path;
        $this->deleteJson("/api/admin/lessons/{$lesson->id}/pdf/answer")
            ->assertOk()
            ->assertJsonPath('has_answer_pdf', false);
        Storage::disk('local')->assertMissing($answerPath);
    }

    public function test_student_cannot_upload_or_remove_lesson_pdfs(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $grade = Grade::create(['name' => 'Grade 11']);
        $subject = Subject::create(['name' => 'Physics']);
        $lesson = Lesson::create([
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'topic' => 'Mechanics',
            'title' => 'Forces',
            'vimeo_url' => 'https://player.vimeo.com/video/2',
        ]);

        Sanctum::actingAs($student);
        $this->post("/api/admin/lessons/{$lesson->id}/pdf", [
            'type' => 'question',
            'pdf' => UploadedFile::fake()->createWithContent('question.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertForbidden();
        $this->deleteJson("/api/admin/lessons/{$lesson->id}/pdf/question")->assertForbidden();
    }
}
