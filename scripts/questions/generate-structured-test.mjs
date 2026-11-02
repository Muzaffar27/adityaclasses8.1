import { readFile, mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { createHash, randomUUID } from 'node:crypto';
import { selectTest } from './pdf-question-core.mjs';
import { renderStructuredTest } from './structured-maths.mjs';
import { loadCurrentQuestions } from './lesson-question-bank.mjs';
import { currentProcessorVersion } from './processor-version.mjs';

const root = path.resolve(process.argv[2] || 'storage/app/ocr-samples/grade-8-mathematics');
const topics = process.argv[3]?.split('|') || ['2. Fractions', '4. BODMAS', '11. Indices'];
const manifest = JSON.parse((await readFile(path.join(root, 'manifest.json'), 'utf8')).replace(/^\uFEFF/, ''));
const bank = await loadCurrentQuestions(root, manifest.filter(source => topics.includes(source.topic)), await currentProcessorVersion());
const questions = selectTest(bank.questions, topics, Number(process.argv[4] || 15));
const id = randomUUID();
const output = path.join(root, 'tests', id);
await mkdir(output, { recursive: true });
for (const question of questions) for (const diagram of question.diagrams || []) {
    if (diagram.path !== `structured/diagrams/${diagram.sha256}.png` || !/^[a-f0-9]{64}$/.test(diagram.sha256)) throw new Error('Invalid diagram path.');
    await mkdir(path.join(output, 'diagrams'), { recursive: true });
    const bytes = await readFile(path.join(root, diagram.path));
    if (createHash('sha256').update(bytes).digest('hex') !== diagram.sha256) throw new Error('Diagram asset changed.');
    await writeFile(path.join(output, 'diagrams', `${diagram.sha256}.png`), bytes);
}
const test = { id, count: questions.length, topics, created_at: new Date().toISOString(), status: 'structured_local_preview', pending_sources: bank.pending, questions };
await writeFile(path.join(output, 'test.json'), JSON.stringify(test, null, 2));
await writeFile(path.join(output, 'index.html'), renderStructuredTest(test));
console.log(output);
