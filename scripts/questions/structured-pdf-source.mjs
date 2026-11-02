import { createHash } from 'node:crypto';
import { getDocument } from 'pdfjs-dist/legacy/build/pdf.mjs';
import { transcribeMath } from './structured-maths.mjs';
import { cleanInstructions, joinPdfText, questionRegions, textLines, withoutPageFooter } from './pdf-question-core.mjs';
import { drawingBounds, diagramRegions, extractDiagramRegion } from './diagram-extraction.mjs';

export function skippedPageEvidence(pdfItems, height, pageNumber) {
    const rawText = textLines(pdfItems, height).map(row => joinPdfText(row.items)).join('\n');
    const hasMarkers = /(?:^|\n)(?:Question\s*)?(?:\d{1,2}[.)]|\([a-zivx]+\)|[a-z]\))\s*/i.test(rawText);
    const auditHint = hasMarkers ? 'question_markers_found' : /exercise/i.test(rawText) ? 'exercise_heading_found'
        : /example|solution|revision/i.test(rawText) ? 'examples_or_revision'
        : !rawText.trim() ? 'no_readable_text' : pageNumber === 1 && /name|grade|website/i.test(rawText) && rawText.length < 400 ? 'possible_cover' : 'inspection_required';
    return { audit_hint: auditHint, raw_text: rawText.slice(0, 3000) };
}

export async function extractPdfSource(source, bytes, sha256, { root } = {}) {
    const questions = [], rejected = [], skippedPages = [];
    let diagramInstructions = '';
    let continuation = null;
    const document = await getDocument({ data: new Uint8Array(bytes), useSystemFonts: true }).promise;
    try {
        for (let pageNumber = 1; pageNumber <= document.numPages; pageNumber++) {
            const page = await document.getPage(pageNumber);
            const viewport = page.getViewport({ scale: 1 });
            const pdfItems = withoutPageFooter((await page.getTextContent()).items, viewport.width, viewport.height, pageNumber);
            const items = pdfItems.filter(i => i.str?.trim()).map(i => ({ text: i.str, x: i.transform[4], y: viewport.height - i.transform[5], width: i.width, height: i.height }));
            const pageText = pdfItems.map(i => i.str || '').join(' ');
            const graphics = drawingBounds(await page.getOperatorList(), viewport);
            const diagramPage = /pythagoras|diagram|triangle|shape|graph|sketch|rectangle|ladder|figure|circle|polygon|geometry/i.test(`${source.topic} ${pageText}`)
                || graphics.some(g => g.y > 70 && g.y + g.height < viewport.height - 40);
            const result = diagramPage ? diagramRegions(pdfItems, viewport.width, viewport.height, diagramInstructions) : questionRegions(pdfItems, viewport.width, viewport.height, continuation);
            continuation = diagramPage ? null : result.continuation || null;
            diagramInstructions = diagramPage && result.regions.length ? result.instructions : '';
            if (!result.regions.length) {
                skippedPages.push({ lesson_id: source.lesson_id, page: pageNumber, reason: result.reason, ...skippedPageEvidence(pdfItems, viewport.height, pageNumber) });
                continue;
            }
            for (const [index, region] of result.regions.entries()) {
                const id = createHash('sha256').update(`${sha256}:${pageNumber}:${index}`).digest('hex').slice(0, 24);
                try {
                    const instructions = diagramPage ? result.instructions : cleanInstructions(region.contextText) || (source.topic.includes('BODMAS') ? 'Evaluate the following:' : '');
                    const common = { id, grade_id: source.grade_id, subject_id: source.subject_id, topic: source.topic, lesson_id: source.lesson_id, page: pageNumber, label: region.label, source_sha256: sha256, instructions, status: 'structured_unverified' };
                    if (diagramPage) {
                        questions.push({ ...common, ...await extractDiagramRegion({root,bytes,page:pageNumber,region,graphics,items:pdfItems,instructions,width:viewport.width,height:viewport.height}) });
                        continue;
                    }
                    if (region.questionText) { questions.push({ ...common, text: region.questionText }); continue; }
                    const rect = region.rect;
                    const body = items.filter(i => i.x >= rect.x - 1 && i.x + i.width <= rect.x + rect.width + 2 && i.y >= rect.y && i.y <= rect.y + rect.height);
                    if (result.continued && /\b(and|or)\b/i.test(joinPdfText([...body].sort((a, b) => a.y - b.y || a.x - b.x)))) throw new Error('unsupported_list_maths');
                    questions.push({ ...common, math: transcribeMath(body, region.label) });
                } catch (error) {
                    const rect = region.rect;
                    const rawText = textLines(pdfItems, viewport.height).map(row => joinPdfText(row.items.filter(item => item.x >= rect.x - 1 && item.x <= rect.x + rect.width && row.y >= rect.y && row.y <= rect.y + rect.height))).filter(Boolean).join('\n');
                    rejected.push({ id, lesson_id: source.lesson_id, page: pageNumber, label: region.label, reason: error.message, rect, raw_text: rawText.slice(0, 3000) });
                }
            }
        }
    } finally { await document.destroy(); }
    return { questions, rejected, skipped_pages: skippedPages };
}
