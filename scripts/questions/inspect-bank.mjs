import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { readBank, loadCurrentQuestions } from './lesson-question-bank.mjs';
import { currentProcessorVersion } from './processor-version.mjs';

const root = path.resolve(process.argv[2] || 'storage/app/ocr-samples/grade-8-mathematics');
const manifest = JSON.parse((await readFile(path.join(root, 'manifest.json'), 'utf8')).replace(/^\uFEFF/, ''));
const bank = await readBank(root);
const current = await loadCurrentQuestions(root, manifest, await currentProcessorVersion());
const pending = new Map(current.pending.map(s => [s.source_key, s.status]));
console.log(JSON.stringify({ unique_current_questions: new Set(current.questions.map(q => q.id)).size, last_run: bank.last_run, lessons: Object.entries(bank.sources).map(([key, s]) => ({ lesson_id: s.lesson_id, topic: s.topic, status: pending.get(key) || s.status, unique_questions: new Set((s.occurrences || []).map(o => o.question_id)).size, processed_at: s.processed_at || null })) }, null, 2));
