import { readFile, mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { getDocument } from 'pdfjs-dist/legacy/build/pdf.mjs';
import { questionRegions } from './pdf-question-core.mjs';

const root = path.resolve(process.argv[2] || 'storage/app/ocr-samples/grade-8-mathematics');
const output = path.join(root, 'extracted');
const manifest = JSON.parse((await readFile(path.join(root, 'manifest.json'), 'utf8')).replace(/^\uFEFF/, ''));
await mkdir(output, { recursive: true });
const questions = [], skipped = [];
const selectedIds = process.argv[3] && !process.argv[3].startsWith('--') ? process.argv[3].split(',').map(Number) : null;
const metadataOnly = process.argv.includes('--metadata-only');
if (selectedIds) {
    if (selectedIds.some(id => !Number.isInteger(id) || !manifest.some(source => source.lesson_id === id))) throw new Error('Unknown local lesson ID.');
    const previous = JSON.parse(await readFile(path.join(output, 'questions.json'), 'utf8'));
    questions.push(...previous.questions.filter(q => !selectedIds.includes(q.lesson_id)));
    skipped.push(...previous.skipped.filter(q => !selectedIds.includes(q.lesson_id)));
}
const dpi = 144, scale = dpi / 72;
function crop(file, page, rect, name) {
    if (metadataOnly) {
        if (!existsSync(path.join(output, `${name}.png`))) throw new Error('Run a full extraction before refreshing metadata.');
        return;
    }
    execFileSync('pdftoppm', ['-f', String(page), '-l', String(page), '-r', String(dpi), '-x', String(Math.floor(rect.x * scale)), '-y', String(Math.floor(rect.y * scale)), '-W', String(Math.ceil(rect.width * scale)), '-H', String(Math.ceil(rect.height * scale)), '-singlefile', '-png', file, path.join(output, name)], { stdio: ['ignore', 'ignore', 'pipe'] });
}
for (const source of manifest) {
    if (selectedIds && !selectedIds.includes(source.lesson_id)) continue;
    const file = path.resolve(root, source.local_pdf);
    if (!file.startsWith(root + path.sep)) throw new Error('Source must stay inside the local sample directory.');
    const bytes = await readFile(file);
    if (createHash('sha256').update(bytes).digest('hex') !== source.sha256) throw new Error(`Source changed: lesson ${source.lesson_id}`);
    const document = await getDocument({ data: new Uint8Array(bytes), useSystemFonts: true }).promise;
    try {
        for (let pageNumber = 1; pageNumber <= document.numPages; pageNumber++) {
            const page = await document.getPage(pageNumber);
            const viewport = page.getViewport({ scale: 1 });
            const result = questionRegions((await page.getTextContent()).items, viewport.width, viewport.height);
            if (!result.regions.length) { skipped.push({ lesson_id: source.lesson_id, page: pageNumber, reason: result.reason }); continue; }
            const prefix = `lesson-${source.lesson_id}-page-${pageNumber}`;
            if (result.regions[0].context.height > 10) crop(file, pageNumber, result.regions[0].context, `${prefix}-context`);
            for (const [index, region] of result.regions.entries()) {
                const id = createHash('sha256').update(`${source.sha256}:${pageNumber}:${index}`).digest('hex').slice(0, 24);
                const image = `${prefix}-question-${index + 1}.png`;
                crop(file, pageNumber, region.rect, image.slice(0, -4));
                questions.push({ id, grade_id: source.grade_id, subject_id: source.subject_id, topic: source.topic, lesson_id: source.lesson_id, lesson_title: source.title, source_sha256: source.sha256, page: pageNumber, label: region.label, image, question_text: region.questionText || null, context_image: region.context.height > 10 ? `${prefix}-context.png` : null, context_text: region.contextText, rect: region.rect, status: 'extracted_unverified' });
            }
        }
    } finally { await document.destroy(); }
    console.log(`Lesson ${source.lesson_id}: ${questions.filter(q => q.lesson_id === source.lesson_id).length} question regions`);
}
await writeFile(path.join(output, 'questions.json'), JSON.stringify({ version: 1, questions, skipped }, null, 2));
console.log(`Extracted ${questions.length} regions; ${skipped.length} pages explicitly skipped. These are unverified extraction candidates.`);
