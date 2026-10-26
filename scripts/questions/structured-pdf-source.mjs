import { createHash } from 'node:crypto';
import { getDocument } from 'pdfjs-dist/legacy/build/pdf.mjs';
import { transcribeMath } from './structured-maths.mjs';
import { cleanInstructions, questionRegions } from './pdf-question-core.mjs';

export async function extractPdfSource(source, bytes, sha256) {
    const questions = [], rejected = [], skippedPages = [];
    const document = await getDocument({ data: new Uint8Array(bytes), useSystemFonts: true }).promise;
    try {
        for (let pageNumber = 1; pageNumber <= document.numPages; pageNumber++) {
            const page = await document.getPage(pageNumber);
            const viewport = page.getViewport({ scale: 1 });
            const pdfItems = (await page.getTextContent()).items;
            const items = pdfItems.filter(i => i.str?.trim()).map(i => ({ text: i.str, x: i.transform[4], y: viewport.height - i.transform[5], width: i.width, height: i.height }));
            const result = questionRegions(pdfItems, viewport.width, viewport.height);
            if (!result.regions.length) { skippedPages.push({ lesson_id: source.lesson_id, page: pageNumber, reason: result.reason }); continue; }
            for (const [index, region] of result.regions.entries()) {
                const id = createHash('sha256').update(`${sha256}:${pageNumber}:${index}`).digest('hex').slice(0, 24);
                try {
                    const instructions = cleanInstructions(region.contextText) || (source.topic.includes('BODMAS') ? 'Evaluate the following:' : '');
                    if (/pythagoras|diagram|triangle|shape|graph|draw|sketch/i.test(`${source.topic} ${instructions}`)) throw new Error('diagram_requires_separate_extraction');
                    const common = { id, grade_id: source.grade_id, subject_id: source.subject_id, topic: source.topic, lesson_id: source.lesson_id, page: pageNumber, label: region.label, source_sha256: sha256, instructions, status: 'structured_unverified' };
                    if (region.questionText) { questions.push({ ...common, text: region.questionText }); continue; }
                    const rect = region.rect;
                    const body = items.filter(i => i.x >= rect.x - 1 && i.x + i.width <= rect.x + rect.width + 2 && i.y >= rect.y && i.y <= rect.y + rect.height);
                    questions.push({ ...common, math: transcribeMath(body, region.label) });
                } catch (error) { rejected.push({ id, lesson_id: source.lesson_id, page: pageNumber, label: region.label, reason: error.message }); }
            }
        }
    } finally { await document.destroy(); }
    return { questions, rejected, skipped_pages: skippedPages };
}
