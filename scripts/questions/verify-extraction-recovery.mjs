import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { copyFile, mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { processLessonBank } from './lesson-question-bank.mjs';
import { extractPdfSource } from './structured-pdf-source.mjs';
import { currentProcessorVersion } from './processor-version.mjs';

const sourceRoot = path.resolve('storage/app');
const bank = JSON.parse(await readFile(path.join(sourceRoot, 'structured/bank.json'), 'utf8'));
const baselineFile = path.join(sourceRoot, 'structured/recovery-baseline-20261027.json');
let baseline;
try { baseline = JSON.parse(await readFile(baselineFile, 'utf8')); }
catch (error) {
    if (error.code !== 'ENOENT') throw error;
    // Preserve the original baseline if an earlier disposable preview already identified newly recovered candidates.
    let previous;
    try { previous = JSON.parse(await readFile(path.join(sourceRoot, 'structured/recovery-preview.json'), 'utf8')); } catch {}
    const additions = new Set((previous?.recovered || []).map(q => q.id));
    baseline = { ids: Object.values(bank.questions).filter(q => q.active && !additions.has(q.id)).map(q => q.id) };
    await writeFile(baselineFile, JSON.stringify(baseline));
}
const prefix = path.join(tmpdir(), 'aditya-recovery-check-');
const root = await mkdtemp(prefix);
try {
    await mkdir(path.join(root, 'structured'));
    await writeFile(path.join(root, 'structured/bank.json'), JSON.stringify(bank));
    const manifest = Object.entries(bank.sources).filter(([, s]) => ['processed', 'processed_with_issues'].includes(s.status))
        .map(([key, s]) => ({ ...s, slot: key.split(':').at(-1), force_reprocess: true }));
    for (const source of manifest) {
        const original = path.resolve(sourceRoot, source.local_pdf);
        assert.ok(original.startsWith(sourceRoot + path.sep));
        assert.equal(createHash('sha256').update(await readFile(original)).digest('hex'), source.sha256);
        const target = path.join(root, source.local_pdf);
        await mkdir(path.dirname(target), { recursive: true }); await copyFile(original, target);
    }
    const { bank: after, stats } = await processLessonBank({ root, manifest, processorVersion: await currentProcessorVersion(), extract: extractPdfSource });
    assert.equal(stats.failed_pdfs, 0);
    assert.ok(baseline.ids.every(id => after.questions[id]?.active), 'Original bank questions must be preserved by this recovery refinement.');
    const questions = Object.values(after.questions).filter(q => q.active);
    const recoveredIndex = questions.find(q => q.origins.some(o => o.lesson_id === 25 && o.page === 2 && o.label === '10.'));
    assert.ok(recoveredIndex);
    assert.deepEqual(recoveredIndex.math.children.map(n => n.type === 'power' ? [n.base.value, n.exponent.value] : n.value), [['4', '5'], '×', ['4', '7'], '×', ['4', '8']]);
    const hcf = after.sources['2:1:385:question'];
    assert.ok(['(g)', '(h)'].every(label => hcf.rejected.some(r => r.page === 5 && r.label === label && r.reason === 'unsupported_list_maths')));
    const recovered = questions.filter(q => !baseline.ids.includes(q.id));
    const report = { baseline_questions: baseline.ids.length, stats,
        rejected_regions: Object.values(after.sources).reduce((n, s) => n + (s.rejected?.length || 0), 0),
        skipped_pages: Object.values(after.sources).reduce((n, s) => n + (s.skipped_pages?.length || 0), 0), recovered };
    await writeFile(path.join(sourceRoot, 'structured/recovery-preview.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ ...report, recovered: recovered.map(q => ({ id: q.id, origins: q.origins })) }, null, 2));
} finally {
    assert.ok(path.resolve(root).startsWith(path.resolve(prefix)));
    await rm(root, { recursive: true, force: true });
}
