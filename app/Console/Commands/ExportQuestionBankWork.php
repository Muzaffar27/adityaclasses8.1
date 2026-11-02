<?php

namespace App\Console\Commands;

use App\Services\LessonQuestionBankService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ExportQuestionBankWork extends Command
{
    protected $signature = 'question-bank:export {--processor= : Extractor signature} {--limit=5}';
    protected $description = 'Claim a bounded batch of lesson PDF processing requests for the background worker';

    public function handle(LessonQuestionBankService $service): int
    {
        if (!app()->environment(['local', 'testing']) && !config('question_bank.worker_enabled')) { $this->error('The question-bank worker is not enabled.'); return self::FAILURE; }
        if (!Schema::hasTable('lesson_question_sources')) { $this->error('Apply the local question-bank source migration first.'); return self::FAILURE; }
        $processor = (string) $this->option('processor');
        if (!preg_match('/^[a-f0-9]{64}$/', $processor)) { $this->error('A valid processor signature is required.'); return self::FAILURE; }
        $this->line(json_encode($service->exportWork($processor, max(1, min(20, (int) $this->option('limit')))), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
