import assert from 'node:assert/strict';
import { readFile, writeFile, mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { processLessonBank, loadCurrentQuestions } from './lesson-question-bank.mjs';
import { extractPdfSource } from './structured-pdf-source.mjs';
import { currentProcessorVersion } from './processor-version.mjs';

const collection = path.resolve(process.argv[2] || 'storage/app/ocr-samples/grade-8-mathematics');
const sources = JSON.parse((await readFile(path.join(collection, 'manifest.json'), 'utf8')).replace(/^\uFEFF/, ''));
const source = sources.find(s => s.lesson_id === 20), replacement = sources.find(s => s.lesson_id === 15);
assert.ok(source && replacement, 'This smoke check needs the two copied Percentage PDFs.');
const root = await mkdtemp(path.join(tmpdir(), 'aditya-real-bank-check-'));
try {
    const file = path.join(root, 'question.pdf');
    const originalBytes = await readFile(path.join(collection, source.local_pdf));
    await writeFile(file, originalBytes);
    const args = { root, manifest: [{ ...source, local_pdf: 'question.pdf' }], processorVersion: await currentProcessorVersion(), extract: extractPdfSource };
    const first = await processLessonBank(args);
    const repeat = await processLessonBank(args);
    assert.equal(first.stats.scanned_pdfs, 1);
    assert.equal(repeat.stats.scanned_pdfs, 0);
    // Change only the disposable PDF copy, leaving all originals untouched.
    await writeFile(file, Buffer.concat([originalBytes, Buffer.from('\n% local cache smoke-check version\n')]));
    assert.equal((await loadCurrentQuestions(root, args.manifest, args.processorVersion)).questions.length, 0);
    const changed = await processLessonBank(args);
    assert.equal(changed.stats.scanned_pdfs, 1);
    assert.equal(changed.stats.added_questions, 0);
    assert.equal(changed.stats.duplicate_occurrences, first.stats.active_unique_questions);
    await writeFile(file, await readFile(path.join(collection, replacement.local_pdf)));
    const newVersion = await processLessonBank(args);
    assert.ok(newVersion.stats.added_questions > 0);
    assert.ok(newVersion.stats.archived_questions > 0);
    console.log(JSON.stringify({ initial: first.stats, unchanged: repeat.stats, changed_bytes_same_questions: changed.stats, new_pdf_questions: newVersion.stats }, null, 2));
} finally {
    if (!path.resolve(root).startsWith(path.resolve(tmpdir()) + path.sep + 'aditya-real-bank-check-')) throw new Error('Unsafe smoke-check cleanup target.');
    await rm(root, { recursive: true, force: true });
}
