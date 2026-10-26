import { execFileSync } from 'node:child_process';
import { mkdir, writeFile, realpath } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { randomUUID } from 'node:crypto';
import { processLessonBank, sourceKey } from './lesson-question-bank.mjs';
import { extractPdfSource } from './structured-pdf-source.mjs';
import { currentProcessorVersion } from './processor-version.mjs';

const workspace = await realpath(fileURLToPath(new URL('../../', import.meta.url)));
const root = path.join(workspace, 'storage/app');
const watch = process.argv.includes('--watch');
if (watch) console.log(JSON.stringify({ worker: 'local_lesson_question_bank', pid: process.pid, started_at: new Date().toISOString() }));
function artisan(args) {
    return JSON.parse(execFileSync('docker', ['compose', 'exec', '-T', 'app', 'php', 'artisan', ...args, '--no-ansi'], { cwd: workspace, encoding: 'utf8', timeout: 120000, maxBuffer: 8 * 1024 * 1024 }));
}
async function run() {
    const processorVersion = await currentProcessorVersion();
    const batch = artisan(['question-bank:export', `--processor=${processorVersion}`]);
    if (!batch.jobs.length) return { pending_jobs: 0 };
    let bank, stats;
    try { ({ bank, stats } = await processLessonBank({ root, manifest: batch.manifest, processorVersion, extract: extractPdfSource })); }
    catch (error) { console.error(`Local bank processing failed: ${error.message}`); }
    const results = batch.jobs.map(job => {
        if (job.action === 'remove') return { ...job, status: bank ? 'removed' : 'failed' };
        const source = batch.manifest.find(s => s.lesson_id === job.lesson_id && s.slot === job.slot);
        const processed = source && bank?.sources[sourceKey(source)];
        if (!processed || processed.status === 'failed') return { ...job, status: 'failed' };
        return { ...job, status: processed.status, source_sha256: processed.sha256, processor_version: processorVersion,
            question_ids: [...new Set(processed.occurrences.map(o => o.question_id))], rejected_count: processed.rejected?.length || 0, skipped_count: processed.skipped_pages?.length || 0 };
    });
    const relative = `question-bank-worker/results/${randomUUID()}.json`;
    await mkdir(path.dirname(path.join(root, relative)), { recursive: true });
    await writeFile(path.join(root, relative), JSON.stringify({ results }));
    const acknowledged = artisan(['question-bank:import', relative]);
    return { requested_jobs: batch.jobs.length, ...stats, acknowledgements: acknowledged.results };
}
do {
    try {
        const result = await run();
        if (!watch || result.pending_jobs !== 0) console.log(JSON.stringify({ at: new Date().toISOString(), ...result }));
    } catch (error) { console.error(`Local worker error: ${error.message}`); if (!watch) process.exitCode = 1; }
    if (watch) await new Promise(resolve => setTimeout(resolve, 5000));
} while (watch);
