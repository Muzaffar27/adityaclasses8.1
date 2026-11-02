import { createHash } from 'node:crypto';
import { readFile, realpath, writeFile } from 'node:fs/promises';
import path from 'node:path';

const root = await realpath(path.resolve(process.argv[2] || 'storage/app'));
const bank = JSON.parse(await readFile(path.join(root, 'structured/bank.json'), 'utf8'));
const rejected = [], skipped = [], stale = [];
for (const [key, source] of Object.entries(bank.sources)) {
    if (!['processed', 'processed_with_issues'].includes(source.status)) continue;
    try {
        const file = await realpath(path.resolve(root, source.local_pdf));
        if (!file.startsWith(root + path.sep) || createHash('sha256').update(await readFile(file)).digest('hex') !== source.sha256) throw new Error('Source changed');
    } catch { stale.push(key); continue; }
    for (const [field, rows] of [['rejected', rejected], ['skipped_pages', skipped]]) {
        for (const issue of source[field] || []) rows.push({ source_key: key, lesson_id: source.lesson_id, topic: source.topic, source_sha256: source.sha256, ...issue });
    }
}
if (rejected.some(r => !r.rect || !['x', 'y', 'width', 'height'].every(k => Number.isFinite(r.rect[k])))) throw new Error('Rejected-region coordinates missing; reprocess with the current extractor.');
if (skipped.some(r => !r.audit_hint || typeof r.raw_text !== 'string')) throw new Error('Skipped-page audit evidence missing; reprocess with the current extractor.');
const countBy = (rows, key) => Object.fromEntries([...new Set(rows.map(r => r[key]))].map(value => [value, rows.filter(r => r[key] === value).length]));
const report = { generated_at: new Date().toISOString(), human_acceptance: 'pending', active_questions: Object.values(bank.questions).filter(q => q.active).length,
    rejected_regions: rejected.length, skipped_source_page_records: skipped.length,
    distinct_skipped_pdf_pages: new Set(skipped.map(r => `${r.source_sha256}:${r.page}`)).size,
    rejected_reasons: countBy(rejected, 'reason'), skipped_reasons: countBy(skipped, 'reason'), skipped_audit_hints: countBy(skipped, 'audit_hint'),
    stale_sources: stale, rejected, skipped };
const escape = value => String(value).replaceAll('|', '\\|').replaceAll('\n', ' ');
const rows = (items, fields) => items.map(r => '| ' + fields.map(k => escape(r[k] ?? '')).join(' | ') + ' |').join('\n');
const markdown = `# Extraction coverage audit\n\nGenerated: ${report.generated_at}\n\nHuman acceptance: pending. Hints do not dismiss pages or prove that all questions were found. Counts of regions/pages are not counts of missing questions. Shared PDF pages can appear under multiple source slots.\n\n- Active bank questions: ${report.active_questions}\n- Rejected regions: ${rejected.length}\n- Skipped source/page records: ${skipped.length}\n- Distinct skipped PDF pages: ${report.distinct_skipped_pdf_pages}\n- Stale sources excluded: ${stale.length}\n\n## Skipped pages requiring inspection\n\n| Lesson | Topic | Page | Exclusion reason | Automated hint |\n|---|---|---|---|---|\n${rows(skipped, ['lesson_id', 'topic', 'page', 'reason', 'audit_hint'])}\n\n## Rejected regions requiring extraction repair\n\n| Lesson | Topic | Page | Label | Reason |\n|---|---|---|---|---|\n${rows(rejected, ['lesson_id', 'topic', 'page', 'label', 'reason'])}\n`;
await writeFile(path.join(root, 'structured/extraction-coverage-audit.json'), JSON.stringify(report, null, 2));
await writeFile(path.join(root, 'structured/extraction-coverage-audit.md'), markdown);
console.log(JSON.stringify({ ...report, rejected: undefined, skipped: undefined }, null, 2));
