<?php

namespace App\Console\Commands;

use App\Services\ExactMathAnswerService;
use App\Services\TestPaperReadinessService;
use Illuminate\Console\Command;

class PreviewAnswerKeys extends Command
{
    protected $signature = 'assessments:answer-key-preview {--limit=5}';
    protected $description = 'Read-only local exact-arithmetic draft answer preview; no AI call or approvals';

    public function handle(TestPaperReadinessService $readiness, ExactMathAnswerService $maths): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 20) { $this->error('Limit must be between 1 and 20.'); return self::FAILURE; }
        $supported = []; $unsupported = []; $count = 0;
        foreach ($readiness->snapshot()['approved'] as $q) {
            $result = $maths->suggest($q['content']);
            if (!$result['supported']) $unsupported[] = ['id' => $q['id'], 'reason' => $result['reason']];
            else {
                $count++;
                if (count($supported) < $limit) $supported[] = ['question' => $q, 'draft' => $result['answer']];
            }
        }
        $this->line(json_encode(['generator' => ExactMathAnswerService::VERSION, 'ai_used' => false, 'saved' => false,
            'supported_count' => $count, 'needs_tutor_count' => count($unsupported), 'samples' => $supported,
            'unsupported' => $unsupported], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
