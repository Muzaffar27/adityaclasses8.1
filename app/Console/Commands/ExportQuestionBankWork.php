<?php

namespace App\Console\Commands;

use App\Services\LessonQuestionBankService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ExportQuestionBankWork extends Command
{
    protected $signature = 'question-bank:export {--processor= : Local extractor signature}';
    protected $description = 'Claim pending lesson PDF processing requests for the local worker';

    public function handle(LessonQuestionBankService $service): int
    {
        if (!app()->environment(['local', 'testing'])) { $this->error('This worker bridge is local-only.'); return self::FAILURE; }
        if (!Schema::hasTable('lesson_question_sources')) { $this->error('Apply the local question-bank source migration first.'); return self::FAILURE; }
        $processor = (string) $this->option('processor');
        if (!preg_match('/^[a-f0-9]{64}$/', $processor)) { $this->error('A valid processor signature is required.'); return self::FAILURE; }
        $this->line(json_encode($service->exportWork($processor), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
