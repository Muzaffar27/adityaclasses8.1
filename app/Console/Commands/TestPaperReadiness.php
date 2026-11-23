<?php

namespace App\Console\Commands;

use App\Services\TestPaperReadinessService;
use Illuminate\Console\Command;
use RuntimeException;

class TestPaperReadiness extends Command
{
    protected $signature = 'assessments:bank-readiness {--json : Include source lesson counts and metadata gaps as JSON}';
    protected $description = 'Read-only approved question availability by grade, subject, topic and lesson';

    public function handle(TestPaperReadinessService $service): int
    {
        try { $report = $service->snapshot()['report']; }
        catch (RuntimeException $e) { $this->error($e->getMessage()); return self::FAILURE; }
        if ($this->option('json')) $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        else {
            $this->info("Current usable approvals: {$report['current_approved_unique']}; historical/unusable: {$report['historical_or_unusable_approvals']}");
            $this->table(['Grade (ID)', 'Subject (ID)', 'Topic', 'Approved', 'Need for 3', 'Need for 5'], array_map(fn ($t) => [
                "{$t['grade']} ({$t['grade_id']})", "{$t['subject']} ({$t['subject_id']})", $t['topic'],
                $t['approved_count'], $t['needed_for_minimum'], $t['needed_for_five']], $report['topics']));
            $this->warn($report['metadata_status']);
            $this->line('Counts can overlap across topics. Use assessments:preview to validate a specific three-topic batch. No records changed.');
        }
        return self::SUCCESS;
    }
}
