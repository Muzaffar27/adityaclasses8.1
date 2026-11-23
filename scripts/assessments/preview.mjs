// Local-only approved-bank preview. No extraction, approval writes, AI calls or student attempts.
import { spawnSync } from 'node:child_process';
import { readFile, mkdir, writeFile, rm } from 'node:fs/promises';
import { createHash, randomUUID } from 'node:crypto';
import path from 'node:path';
import { renderStructuredTest } from '../questions/structured-maths.mjs';

const [grade, subject, ...topics] = process.argv.slice(2);
if (!/^\d+$/.test(grade || '') || !/^\d+$/.test(subject || '') || topics.length !== 3) {
    throw new Error('Usage: node scripts/assessments/preview.mjs <grade-id> <subject-id> "Topic 1" "Topic 2" "Topic 3"');
}
const result = spawnSync('docker', ['compose', 'exec', '-T', '--user', 'www-data', 'app', 'php', 'artisan',
    'assessments:preview', `--grade=${grade}`, `--subject=${subject}`, ...topics.map(t => `--topic=${t}`), '--json', '--no-ansi'],
{ encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
if (result.error) throw result.error;
if (result.status !== 0) throw new Error(result.stdout || result.stderr || 'Preview command failed. Start the local Docker app and database.');
const paper = JSON.parse(result.stdout);
if (!paper.ready || paper.count !== 15 || paper.purpose !== 'approved_bank_preview') throw new Error('No valid approved-bank preview returned.');
const id = randomUUID();
const previewRoot = path.resolve('storage/app/assessment-previews');
const output = path.resolve(previewRoot, id);
if (path.dirname(output) !== previewRoot) throw new Error('Preview output must stay in its private directory.');
const escape = value => String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
await mkdir(output, { recursive: true });
try {
    for (const q of paper.questions) for (const d of q.content.diagrams || []) {
        if (!/^[a-f0-9]{64}$/.test(d.sha256) || d.path !== `structured/diagrams/${d.sha256}.png`) throw new Error('Invalid diagram reference.');
        const bytes = await readFile(path.resolve('storage/app', d.path));
        if (createHash('sha256').update(bytes).digest('hex') !== d.sha256) throw new Error('Diagram changed; generate another preview.');
        await mkdir(path.join(output, 'diagrams'), { recursive: true });
        await writeFile(path.join(output, 'diagrams', `${d.sha256}.png`), bytes);
    }
    const test = { count: paper.count, questions: paper.questions.map(q => q.content) };
    let html = renderStructuredTest(test);
    html = html.replace('<h1>General test</h1>', '<h1>Approved question sample</h1>');
    html = html.replace('<p>15 questions</p>', `<p>${escape(paper.grade)} · ${escape(paper.subject)} · 15 questions</p><p>Preview only — answering and marking are not available yet.</p>`);
    await writeFile(path.join(output, 'paper.json'), JSON.stringify({ ...paper, created_at: new Date().toISOString() }, null, 2));
    await writeFile(path.join(output, 'index.html'), html);
} catch (error) {
    // Remove only the UUID directory created by this invocation, never an input path.
    await rm(output, { recursive: true, force: true });
    throw error;
}
console.log(`Preview: ${output}`);
console.log('Serve: node scripts/questions/serve-local.mjs storage/app/assessment-previews');
console.log(`Open: http://127.0.0.1:8091/${id}/index.html`);
