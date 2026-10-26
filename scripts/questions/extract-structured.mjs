import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { processLessonBank } from './lesson-question-bank.mjs';
import { extractPdfSource } from './structured-pdf-source.mjs';
import { currentProcessorVersion } from './processor-version.mjs';

const root = path.resolve(process.argv[2] || 'storage/app/ocr-samples/grade-8-mathematics');
const manifest = JSON.parse((await readFile(path.join(root, 'manifest.json'), 'utf8')).replace(/^\uFEFF/, ''));
const { stats } = await processLessonBank({ root, manifest, processorVersion: await currentProcessorVersion(), extract: extractPdfSource });
console.log(JSON.stringify(stats, null, 2));
if (stats.failed_pdfs) process.exitCode = 1;
