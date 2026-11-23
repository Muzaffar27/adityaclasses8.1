<?php

namespace App\Console\Commands;

use App\Models\StudentAssessmentEnrolment;
use App\Services\AutomaticAssessmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ReconcileAssessments extends Command
{
    protected $signature = 'assessments:reconcile {--limit=20} {--dry-run : Report enrolments without changing data}';
    protected $description = 'Discover completed learning courses and reconcile a bounded rotation of topic tests';

    public function handle(AutomaticAssessmentService $service): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 100) { $this->error('Limit must be between 1 and 100.'); return self::FAILURE; }
        if (!Schema::hasTable('student_assessment_enrolments')) { $this->warn('Automatic assessment setup is pending.'); return self::SUCCESS; }
        if (!$this->option('dry-run')) { $service->ensureReady(); $service->discoverCourses($limit); }
        $rows = StudentAssessmentEnrolment::where('enabled', true)->orderByDesc('needs_reconcile')->orderBy('last_reconciled_at')->orderBy('id')->limit($limit)->get();
        $failed = false;
        foreach ($rows as $row) {
            try {
                $result = $this->option('dry-run') ? ['status' => $row->last_status ?? 'not_checked', 'dry_run' => true] : $service->reconcile($row->id);
                $this->line(json_encode(['enrolment_id' => $row->id] + $result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            } catch (\Throwable $e) {
                $failed = true;
                // Do not let a failed row starve every other enrolled course.
                $row->update(['last_reconciled_at' => now(), 'last_status' => 'error', 'needs_reconcile' => false]);
                $this->error('Enrolment '.$row->id.' failed ('.class_basename($e).'). Retry after checking the test-paper setup.');
            }
        }
        if ($rows->isEmpty()) $this->line('No completed learning courses to process.');
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
