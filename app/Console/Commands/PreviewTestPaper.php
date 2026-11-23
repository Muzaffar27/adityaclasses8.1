<?php

namespace App\Console\Commands;

use App\Services\TestPaperReadinessService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

class PreviewTestPaper extends Command
{
    protected $signature = 'assessments:preview {--grade=} {--subject=} {--topic=* : Exactly three full topic names} {--json}';
    protected $description = 'Preview 15 current approved questions without issuing a student paper or saving an attempt';

    public function handle(TestPaperReadinessService $service): int
    {
        if (!ctype_digit((string) $this->option('grade')) || !ctype_digit((string) $this->option('subject'))) {
            $this->error('Numeric --grade and --subject IDs are required.'); return self::FAILURE;
        }
        try { $paper = $service->preview((int) $this->option('grade'), (int) $this->option('subject'), $this->option('topic')); }
        catch (InvalidArgumentException | RuntimeException $e) { $this->error($e->getMessage()); return self::FAILURE; }
        if ($this->option('json')) $this->line(json_encode($paper, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        else {
            $this->table(['Topic', 'Available', 'Selected', 'Need for minimum'], array_map(fn ($t) => [
                $t['topic'], $t['available'], $t['selected'] ?? 0, $t['minimum_shortage']], $paper['topics']));
            if (!$paper['ready']) {
                $this->error('No paper created: '.$paper['reason']);
                $this->line('Additional distinct questions needed overall: '.($paper['additional_unique_needed'] ?? 0));
                foreach ($paper['conflicts'] ?? [] as $conflict) $this->line(implode(' + ', $conflict['topics']).': '.$conflict['additional_unique_needed'].' additional distinct questions needed across these topics.');
            } else $this->info('15 distinct questions selected. Use --json for content or scripts/assessments/preview.mjs for a local HTML sample.');
            $this->line('Preview only: no student paper or attempt saved; answer keys and marks are not ready.');
        }
        return $paper['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
