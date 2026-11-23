// Read-only demonstration: no saved answer keys, approvals, student records or AI calls.
import { spawnSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import { randomUUID } from 'node:crypto';
import path from 'node:path';
import { renderStructuredTest } from '../questions/structured-maths.mjs';

const result = spawnSync('docker', ['compose', 'exec', '-T', '--user', 'www-data', 'app', 'php', 'artisan',
    'assessments:answer-key-preview', '--limit=5', '--no-ansi'], { encoding: 'utf8', maxBuffer: 5 * 1024 * 1024 });
if (result.error) throw result.error;
if (result.status !== 0) throw new Error(result.stdout || result.stderr || 'Local answer preview failed.');
const preview = JSON.parse(result.stdout);
if (preview.saved !== false || preview.ai_used !== false || !preview.samples.length) throw new Error('No local read-only arithmetic samples available.');
const escape = value => String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
let html = renderStructuredTest({ count: preview.samples.length, questions: preview.samples.map(s => s.question.content) });
html = html.replace('<h1>General test</h1>', '<h1>Draft answer preview</h1>');
html = html.replace('</header>', `<p>Calculated locally with exact arithmetic. These are drafts, not AI-generated or tutor-approved answers.</p><p>${preview.supported_count} approved questions can be calculated locally; ${preview.needs_tutor_count} need tutor input or a reviewed AI solution. No answer keys or student records were saved.</p></header>`);
html = html.replace('</style>', 'details{margin-top:20px;border-top:1px solid #ddd;padding-top:8px}summary{min-height:44px;display:list-item;cursor:pointer;padding:10px 0;font-weight:600}details p{margin:12px 0}li{overflow-wrap:anywhere}.final-answer{font-size:1.3rem;font-weight:700}</style>');
let index = 0;
html = html.replace(/<\/article>/g, () => {
    const draft = preview.samples[index++].draft;
    const form = { equivalent_numeric: 'Any equivalent number', lowest_terms_fraction: 'Fraction in lowest terms', decimal: 'Decimal', tutor_review: 'Tutor review' }[draft.answer_form];
    return `<details><summary>Show draft answer and working</summary><p class="final-answer">Answer: ${escape(draft.final_answer)}</p><ol>${draft.working.map(step => `<li>${escape(step)}</li>`).join('')}</ol><p>Required answer form: ${escape(form)}</p><p>Marks: tutor to set. Separate approval is required before use.</p></details></article>`;
});
const root = path.resolve('storage/app/assessment-previews');
const id = randomUUID(); const output = path.resolve(root, id);
if (path.dirname(output) !== root) throw new Error('Invalid preview directory.');
await mkdir(output, { recursive: true });
await writeFile(path.join(output, 'index.html'), html);
await writeFile(path.join(output, 'drafts.json'), JSON.stringify(preview, null, 2));
console.log(`Draft preview: ${output}`);
console.log('Serve if needed: node scripts/questions/serve-local.mjs storage/app/assessment-previews');
console.log(`Open: http://127.0.0.1:8091/${id}/index.html`);
