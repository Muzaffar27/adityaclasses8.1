<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;
use App\Models\LessonAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LessonController extends Controller
{

    public function all()
    {
        return Lesson::with(['grade', 'subject'])
            ->orderBy('topic')
            ->get();
    }

    // LIST
    public function index(Request $request)
    {
        return Lesson::with(['grade', 'subject'])
            ->when($request->grade_id, fn($q) => $q->where('grade_id', $request->grade_id))
            ->when($request->subject_id, fn($q) => $q->where('subject_id', $request->subject_id))
            ->get();
    }

    // CREATE
    public function store(Request $request)
    {
        $validated = $request->validate([
            'grade_id' => 'required',
            'subject_id' => 'required',
            'title' => 'required',
            'topic' => 'nullable',
            'sub_topic' => 'nullable',
            'part_number' => 'nullable',
            'description' => 'nullable',
            'vimeo_url' => 'nullable',
            'duration' => 'nullable',
        ]);

        return Lesson::create($validated);
    }

    // UPDATE
    public function update(Request $request, Lesson $lesson)
    {
        $lesson->update($request->only([
            'grade_id',
            'subject_id',
            'topic',
            'sub_topic',
            'title',
            'part_number',
            'description',
            'vimeo_url',
            'duration',
            'is_active',
        ]));

        return $lesson;
    }

    public function renameTopic(Request $request)
    {
        $validated = $request->validate([
            'lesson_ids' => 'required|array|min:1',
            'lesson_ids.*' => 'integer|exists:lessons,id',
            'old_topic' => 'required|string',
            'new_topic' => 'required|string|max:255',
        ]);

        $updated = Lesson::whereIn('id', $validated['lesson_ids'])
            ->where('topic', $validated['old_topic'])
            ->update(['topic' => $validated['new_topic']]);

        return response()->json([
            'message' => 'Topic renamed',
            'updated' => $updated,
        ]);
    }

    public function moveTopic(Request $request)
    {
        $validated = $request->validate([
            'lesson_ids' => 'required|array|min:1',
            'lesson_ids.*' => 'integer|exists:lessons,id',
            'grade_id' => 'required|integer|exists:grades,id',
            'subject_id' => 'required|integer|exists:subjects,id',
        ]);

        $updated = Lesson::whereIn('id', $validated['lesson_ids'])
            ->update([
                'grade_id' => $validated['grade_id'],
                'subject_id' => $validated['subject_id'],
            ]);

        return response()->json([
            'message' => 'Topic moved',
            'updated' => $updated,
        ]);
    }

    public function copyTopic(Request $request)
    {
        $validated = $request->validate([
            'lesson_ids' => 'required|array|min:1',
            'lesson_ids.*' => 'integer|exists:lessons,id',
            'grade_id' => 'required|integer|exists:grades,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'new_topic' => 'nullable|string|max:255',
        ]);

        $topic = isset($validated['new_topic']) ? trim($validated['new_topic']) : null;

        $lessons = Lesson::whereIn('id', $validated['lesson_ids'])
            ->orderBy('part_number')
            ->orderBy('id')
            ->get();

        $copied = DB::transaction(function () use ($lessons, $validated, $topic) {
            return $lessons->map(function (Lesson $lesson) use ($validated, $topic) {
                $copy = $lesson->replicate();
                $copy->grade_id = $validated['grade_id'];
                $copy->subject_id = $validated['subject_id'];
                if ($topic) {
                    $copy->topic = $topic;
                }
                $copy->question_pdf_path = null;
                $copy->answer_pdf_path = null;
                $copy->save();

                return $copy;
            });
        });

        return response()->json([
            'message' => 'Topic copied',
            'copied' => $copied->count(),
        ], 201);
    }

    public function deleteTopic(Request $request)
    {
        $validated = $request->validate([
            'lesson_ids' => 'required|array|min:1',
            'lesson_ids.*' => 'integer|exists:lessons,id',
        ]);

        $deleted = Lesson::whereIn('id', $validated['lesson_ids'])->delete();

        return response()->json([
            'message' => 'Topic deleted',
            'deleted' => $deleted,
        ]);
    }

    // DELETE
    public function destroy(Lesson $lesson)
    {
        Storage::disk('local')->delete(array_filter([
            $lesson->question_pdf_path,
            $lesson->answer_pdf_path,
        ]));
        $lesson->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function uploadPdf(Request $request, Lesson $lesson)
    {
        $this->ensureTutor($request);
        $type = $this->pdfType($request->input('type'));
        $request->validate([
            'type' => 'required|in:question,answer',
            'pdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        $field = $type . '_pdf_path';
        $oldPath = $lesson->{$field};
        $path = $request->file('pdf')->storeAs(
            'lesson-pdfs/' . $lesson->id,
            $type . '-' . Str::uuid() . '.pdf',
            'local'
        );

        $lesson->update([$field => $path]);
        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json([
            'has_question_pdf' => $lesson->has_question_pdf,
            'has_answer_pdf' => $lesson->has_answer_pdf,
        ]);
    }

    public function viewPdf(Request $request, Lesson $lesson, string $type)
    {
        $type = $this->pdfType($type);
        $this->ensurePdfAccess($request, $lesson);
        $path = $lesson->{$type . '_pdf_path'};

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $type . '-lesson-' . $lesson->id . '.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function removePdf(Request $request, Lesson $lesson, string $type)
    {
        $this->ensureTutor($request);
        $type = $this->pdfType($type);
        $field = $type . '_pdf_path';

        if ($lesson->{$field}) {
            Storage::disk('local')->delete($lesson->{$field});
            $lesson->update([$field => null]);
        }

        return response()->json([
            'has_question_pdf' => $lesson->has_question_pdf,
            'has_answer_pdf' => $lesson->has_answer_pdf,
        ]);
    }

    private function pdfType(?string $type): string
    {
        abort_unless(in_array($type, ['question', 'answer'], true), 404);

        return $type;
    }

    private function ensureTutor(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['tutor', 'admin'], true), 403);
    }

    private function ensurePdfAccess(Request $request, Lesson $lesson): void
    {
        if (in_array($request->user()->role, ['tutor', 'admin'], true)) {
            return;
        }

        $hasAccess = LessonAccess::where([
            'user_id' => $request->user()->id,
            'subject_id' => $lesson->subject_id,
            'grade_id' => $lesson->grade_id,
            'status' => 'accepted',
        ])->where(function ($query) {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->exists();

        abort_unless($hasAccess, 403);
    }
    /**
     * Get all lessons for a specific grade and subject
     */
    public function get(Request $request)
    {
        $userId = auth()->id();

        // 1. Get lessons (NO access logic here anymore)
        $lessons = Lesson::where('subject_id', $request->subject_id)
            ->where('is_active', 1)
            ->where('grade_id', $request->grade_id)
            ->get();

        // 2. Get access for this subject + grade
        $access = null;

        if ($userId) {
            $access = LessonAccess::where([
                'user_id' => $userId,
                'subject_id' => $request->subject_id,
                'grade_id' => $request->grade_id,
            ])
                ->orderByRaw("CASE status WHEN 'accepted' THEN 0 WHEN 'pending' THEN 1 WHEN 'refused' THEN 2 ELSE 3 END")
                ->latest('updated_at')
                ->first();
        }

        // 3. Return structured response
        return response()->json([
            'lessons' => $lessons,
            'access' => [
                'has_access' => $access
                    && $access->status === 'accepted'
                    && (!$access->expires_at || $access->expires_at->isFuture()),
                'status' => $access->status ?? null,
                'expires_at' => $access->expires_at ?? null,
            ]
        ]);
    }

    public function myCourses()
    {
        return LessonAccess::with(['subject', 'grade'])
            ->where('user_id', auth()->id())
            ->where('status', 'accepted')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->select('lesson_access.*')
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('lesson_access')
                    ->where('user_id', auth()->id())
                    ->where('status', 'accepted')
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->groupBy('subject_id', 'grade_id');
            })
            ->get();
    }
}
