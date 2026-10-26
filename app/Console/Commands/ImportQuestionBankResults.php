<?php

namespace App\Console\Commands;

use App\Services\LessonQuestionBankService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportQuestionBankResults extends Command
{
    protected $signature = 'question-bank:import {file : Relative private result JSON path}';
    protected $description = 'Acknowledge local worker results only for the current lesson PDF revision';

    public function handle(LessonQuestionBankService $service): int
    {
        if (!app()->environment(['local', 'testing'])) { $this->error('This worker bridge is local-only.'); return self::FAILURE; }
        $root = realpath(Storage::disk('local')->path('question-bank-worker/results'));
        $file = realpath(Storage::disk('local')->path((string) $this->argument('file')));
        if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !str_ends_with($file, '.json')) {
            $this->error('Result file must be in the private worker results directory.'); return self::FAILURE;
        }
        $payload = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $results = [];
        foreach ($payload['results'] ?? [] as $result) $results[] = ['id' => $result['id'], 'status' => $service->complete($result)];
        app(\App\Services\QuestionReviewService::class)->synchronize();
        $this->line(json_encode(['results' => $results], JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
