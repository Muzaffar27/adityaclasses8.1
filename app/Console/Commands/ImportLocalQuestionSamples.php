<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Services\LessonQuestionBankService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportLocalQuestionSamples extends Command
{
    protected $signature = 'question-bank:attach-local-samples';
    protected $description = 'Attach copied sample PDFs to matching empty LOCAL lesson PDF slots';

    public function handle(): int
    {
        if (!app()->environment('local')) { $this->error('Sample attachment is local-only.'); return self::FAILURE; }
        $disk = Storage::disk('local');
        $root = realpath($disk->path('ocr-samples/grade-8-mathematics'));
        if (!$root) { $this->error('The copied local sample collection is missing.'); return self::FAILURE; }
        $manifest = json_decode(ltrim(file_get_contents($root . '/manifest.json'), "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
        $attached = 0;
        foreach ($manifest as $sample) {
            $lesson = Lesson::find($sample['lesson_id']);
            if (!$lesson || (int) $lesson->grade_id !== $sample['grade_id'] || (int) $lesson->subject_id !== $sample['subject_id'] || $lesson->topic !== $sample['topic']) continue;
            $slot = $sample['slot'] ?? (str_starts_with(basename($sample['local_pdf']), 'question2-') ? 'question2' : 'question');
            $field = LessonQuestionBankService::FIELDS[$slot];
            if ($lesson->{$field}) continue; // Never replace a locally attached PDF.
            $file = realpath($root . '/' . $sample['local_pdf']);
            if (!$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !hash_equals($sample['sha256'], hash_file('sha256', $file))) throw new \RuntimeException('A sample PDF failed validation.');
            $path = 'lesson-pdfs/' . $lesson->id . '/' . $slot . '-' . Str::uuid() . '.pdf';
            if (!$disk->put($path, file_get_contents($file))) throw new \RuntimeException('Could not copy local lesson PDF.');
            $lesson->update([$field => $path]);
            $attached++;
        }
        $this->info("Attached {$attached} local sample PDFs; existing PDFs and original samples were preserved.");
        return self::SUCCESS;
    }
}
