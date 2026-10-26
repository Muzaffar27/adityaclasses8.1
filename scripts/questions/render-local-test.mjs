import { cleanInstructions } from './pdf-question-core.mjs';

const escape = value => String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

export function renderTest(test) {
    return `<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" href="data:,"><title>General test</title>
<style>
*{box-sizing:border-box}body{font:17px/1.5 system-ui;color:#18233b;background:#eef2f8;margin:0}main{max-width:850px;margin:auto;padding:24px}header{margin-bottom:24px}header p{color:#56647b;margin:4px 0}article{background:white;border-radius:14px;padding:24px;margin:18px 0;break-inside:avoid}h1{font-size:1.6rem;margin:0}h2{font-size:1.05rem;margin:0 0 16px}.instructions{white-space:pre-line;margin:0 0 18px}.question-image{display:block;max-width:100%;height:auto}.question-link{display:block;width:fit-content;max-width:100%;border-radius:4px}.question-link:focus-visible{outline:3px solid #2563eb;outline-offset:5px}@media(max-width:480px){main{padding:14px}article{padding:18px}h1{font-size:1.35rem}}@media print{body{background:white}main{padding:0}article{border:1px solid #ddd}a{color:inherit}}
</style><main><header><h1>General test</h1><p>${test.count} questions</p></header>${test.questions.map((q, i) => {
        const instructions = cleanInstructions(q.context_text);
        return `<article aria-labelledby="question-${i + 1}"><h2 id="question-${i + 1}">Question ${i + 1}</h2>${instructions ? `<p class="instructions">${escape(instructions)}</p>` : ''}${q.question_text ? `<p class="question-prose">${escape(q.question_text)}</p>` : `<a class="question-link" href="${escape(q.image)}" target="_blank" rel="noopener" aria-label="Open question ${i + 1} at full size" title="Open at full size"><img class="question-image" alt="Question ${i + 1}" src="${escape(q.image)}"></a>`}</article>`;
    }).join('')}</main></html>`;
}
