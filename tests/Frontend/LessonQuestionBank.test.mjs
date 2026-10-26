import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, writeFile, mkdir, rm, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { processLessonBank, loadCurrentQuestions, questionFingerprint } from '../../scripts/questions/lesson-question-bank.mjs';
import { selectTest } from '../../scripts/questions/pdf-question-core.mjs';

async function fixture(t, count = 1) {
    const root = await mkdtemp(path.join(tmpdir(), 'aditya-bank-test-'));
    t.after(async () => {
        if (!path.resolve(root).startsWith(path.resolve(tmpdir()) + path.sep + 'aditya-bank-test-')) throw new Error('Unsafe test cleanup target.');
        await rm(root, { recursive: true, force: true });
    });
    const manifest = Array.from({ length: count }, (_, i) => ({ lesson_id: i + 1, grade_id: 2, subject_id: 1, topic: 'Fractions', local_pdf: `lesson-${i + 1}.pdf` }));
    for (const source of manifest) await writeFile(path.join(root, source.local_pdf), 'original');
    return { root, manifest, processorVersion: 'v1' };
}
const candidate = (source, text) => ({ grade_id: source.grade_id, subject_id: source.subject_id, instructions: 'Calculate:', text, page: 1, label: text });
const result = (source, texts) => ({ questions: texts.map(text => candidate(source, text)), rejected: [], skipped_pages: [] });

test('an unchanged PDF is not extracted a second time', async t => {
    const f = await fixture(t); let calls = 0;
    const extract = async source => { calls++; return result(source, ['A']); };
    const first = await processLessonBank({ ...f, extract });
    const second = await processLessonBank({ ...f, extract });
    assert.equal(calls, 1);
    assert.equal(first.stats.scanned_pdfs, 1);
    assert.equal(second.stats.scanned_pdfs, 0);
    assert.equal(second.stats.skipped_unchanged_pdfs, 1);
});
test('a rejected extraction forces an unchanged PDF to be scanned again without duplicating content', async t => {
    const f = await fixture(t); let calls = 0;
    const extract = async source => { calls++; return result(source, ['A']); };
    await processLessonBank({ ...f, extract });
    const retry = await processLessonBank({ ...f, manifest: [{ ...f.manifest[0], force_reprocess: true }], extract });
    assert.equal(calls, 2);
    assert.equal(retry.stats.scanned_pdfs, 1);
    assert.equal(retry.stats.added_questions, 0);
    assert.equal(Object.keys(retry.bank.questions).length, 1);
    await processLessonBank({ ...f, extract });
    assert.equal(calls, 2);
});

test('changed PDFs reuse duplicates, add new questions and retire removed questions', async t => {
    const f = await fixture(t);
    const first = await processLessonBank({ ...f, extract: async source => result(source, ['common', 'removed']) });
    const oldId = Object.values(first.bank.questions).find(q => q.text === 'removed').id;
    await writeFile(path.join(f.root, f.manifest[0].local_pdf), 'updated');
    const pending = await loadCurrentQuestions(f.root, f.manifest, f.processorVersion);
    assert.equal(pending.questions.length, 0);
    assert.equal(pending.pending[0].status, 'pending');
    const changed = await processLessonBank({ ...f, extract: async source => result(source, ['common', 'new', 'new']) });
    assert.equal(changed.stats.scanned_pdfs, 1);
    assert.equal(changed.stats.added_questions, 1);
    assert.equal(changed.stats.duplicate_occurrences, 2);
    assert.equal(changed.stats.active_unique_questions, 2);
    assert.equal(changed.bank.questions[oldId].active, false);
    assert.equal(changed.stats.archived_questions, 1);
});
test('the same question in different lessons is stored once and retains both origins', async t => {
    const f = await fixture(t, 2);
    const extract = async source => result(source, ['shared']);
    const first = await processLessonBank({ ...f, extract });
    assert.equal(Object.keys(first.bank.questions).length, 1);
    assert.equal(Object.values(first.bank.questions)[0].origins.length, 2);
    await writeFile(path.join(f.root, f.manifest[0].local_pdf), 'changed');
    const current = await loadCurrentQuestions(f.root, f.manifest, f.processorVersion);
    assert.equal(current.pending.length, 1);
    assert.equal(current.questions.length, 1);
    assert.equal(current.questions[0].lesson_id, 2);
    const removed = await processLessonBank({ ...f, manifest: [f.manifest[1]], extract });
    assert.equal(removed.stats.scanned_pdfs, 0);
    assert.equal(Object.values(removed.bank.questions)[0].origins.length, 1);
    assert.equal(Object.values(removed.bank.questions)[0].active, true);
});
test('failed updates cannot serve old questions, and the next run retries them', async t => {
    const f = await fixture(t);
    await processLessonBank({ ...f, extract: async source => result(source, ['old']) });
    await writeFile(path.join(f.root, f.manifest[0].local_pdf), 'broken');
    const failed = await processLessonBank({ ...f, extract: async () => { throw new Error('cannot_extract'); } });
    assert.equal(failed.stats.failed_pdfs, 1);
    assert.equal((await loadCurrentQuestions(f.root, f.manifest, f.processorVersion)).questions.length, 0);
    const retry = await processLessonBank({ ...f, extract: async source => result(source, ['fixed']) });
    assert.equal(retry.stats.scanned_pdfs, 1);
    assert.equal(retry.stats.active_unique_questions, 1);
});
test('partial processing is recorded without repeatedly parsing an unchanged unsupported PDF', async t => {
    const f = await fixture(t); let calls = 0;
    const extract = async () => { calls++; return { questions: [], rejected: [{ reason: 'diagram_requires_separate_extraction' }], skipped_pages: [] }; };
    const first = await processLessonBank({ ...f, extract });
    assert.equal(Object.values(first.bank.sources)[0].status, 'processed_with_issues');
    await processLessonBank({ ...f, extract });
    assert.equal(calls, 1);
});
test('processor or topic changes invalidate the old processed mark', async t => {
    const f = await fixture(t); let calls = 0;
    const extract = async source => { calls++; return result(source, ['A']); };
    await processLessonBank({ ...f, extract });
    const updated = [{ ...f.manifest[0], topic: 'New topic' }];
    assert.equal((await loadCurrentQuestions(f.root, updated, 'v1')).questions.length, 0);
    await processLessonBank({ ...f, manifest: updated, extract });
    assert.equal((await loadCurrentQuestions(f.root, updated, 'v2')).questions.length, 0);
    await processLessonBank({ ...f, manifest: updated, processorVersion: 'v2', extract });
    assert.equal(calls, 3);
});
test('concurrent processing is rejected without deleting another run lock', async t => {
    const f = await fixture(t);
    await mkdir(path.join(f.root, 'structured'));
    const lock = path.join(f.root, 'structured/bank.lock');
    await writeFile(lock, 'another_run');
    await assert.rejects(processLessonBank({ ...f, extract: async source => result(source, ['A']) }), /already active/);
    assert.equal(await readFile(lock, 'utf8'), 'another_run');
});
test('deduplication normalises formatting but preserves context, course and mathematical distinctions', () => {
    const a = { grade_id: 2, subject_id: 1, instructions: 'Prices decrease by 20%.', text: 'An item costs Rs  500.' };
    assert.equal(questionFingerprint(a), questionFingerprint({ ...a, text: 'An item costs Rs 500.' }));
    assert.notEqual(questionFingerprint(a), questionFingerprint({ ...a, instructions: 'Prices decrease by 30%.' }));
    assert.notEqual(questionFingerprint(a), questionFingerprint({ ...a, grade_id: 3 }));
    const variable = value => ({ grade_id: 2, subject_id: 1, math: { type: 'variable', value } });
    assert.notEqual(questionFingerprint(variable('A')), questionFingerprint(variable('a')));
});
test('shared topic questions can be reassigned without duplicate selections or false shortages', () => {
    const make = (topic, id) => ({ topic, id, grade_id: 2, subject_id: 1 });
    const pools = [make('A', 'shared'), ...Array.from({ length: 4 }, (_, n) => make('A', `A${n}`)), make('B', 'shared'), ...Array.from({ length: 2 }, (_, n) => make('B', `B${n}`)), ...Array.from({ length: 3 }, (_, n) => make('C', `C${n}`))];
    const selected = selectTest(pools, ['A', 'B', 'C'], 10, () => 0);
    assert.equal(selected.length, 10);
    assert.equal(new Set(selected.map(q => q.id)).size, 10);
    assert.equal(selected.filter(q => q.topic === 'A').length, 4);
    assert.equal(selected.filter(q => q.topic === 'B').length, 3);
});
