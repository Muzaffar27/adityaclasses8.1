import { readFile, writeFile, copyFile } from 'node:fs/promises';
import path from 'node:path';
import { renderTest } from './render-local-test.mjs';

const root = path.resolve(process.argv[2] || 'storage/app/ocr-samples/grade-8-mathematics');
const id = process.argv[3];
if (!/^[a-f0-9-]{36}$/.test(id || '')) throw new Error('Supply a local test UUID.');
const output = path.join(root, 'tests', id);
const test = JSON.parse(await readFile(path.join(output, 'test.json'), 'utf8'));
const bank = JSON.parse(await readFile(path.join(root, 'extracted/questions.json'), 'utf8'));
// Repair crops for the same source questions; preserve test order rather than drawing another random test.
const questions = test.questions.map(old => {
    const pageQuestions = bank.questions.filter(q => q.source_sha256 === old.source_sha256 && q.page === old.page);
    let question = pageQuestions.find(q => q.label === old.label);
    if (!question) {
        // Old equation-result labels may be false. Accept only one unambiguous overlapping source region.
        const overlapping = pageQuestions.filter(q => {
            const centerY = q.rect.y + q.rect.height / 2;
            return centerY >= old.rect.y && centerY <= old.rect.y + old.rect.height
                && Math.min(q.rect.x + q.rect.width, old.rect.x + old.rect.width) > Math.max(q.rect.x, old.rect.x);
        });
        if (overlapping.length === 1) question = overlapping[0];
    }
    if (!question) throw new Error(`Cannot safely rematch question from lesson ${old.lesson_id}, page ${old.page}, label ${old.label}.`);
    return question;
});
for (const file of new Set(questions.flatMap(q => [q.image, q.context_image]).filter(Boolean))) {
    if (path.basename(file) !== file) throw new Error('Invalid question image path.');
    await copyFile(path.join(root, 'extracted', file), path.join(output, file));
}
const refreshed = { ...test, questions, presentation_updated_at: new Date().toISOString() };
await writeFile(path.join(output, 'test.json'), JSON.stringify(refreshed, null, 2));
await writeFile(path.join(output, 'index.html'), renderTest(refreshed));
console.log(`Updated presentation for ${id}; source questions and order preserved, faulty extraction labels repaired.`);
