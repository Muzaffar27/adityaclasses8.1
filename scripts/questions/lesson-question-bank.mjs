import { createHash, randomUUID } from 'node:crypto';
import { readFile, writeFile, mkdir, open, rename, unlink, realpath } from 'node:fs/promises';
import path from 'node:path';

const hash = value => createHash('sha256').update(value).digest('hex');
const normalise = value => typeof value === 'string' ? value.normalize('NFKC').replace(/[–−]/g, '-').replace(/\s+/g, ' ').trim()
    : Array.isArray(value) ? value.map(normalise)
        : value && typeof value === 'object' ? Object.fromEntries(Object.keys(value).sort().map(key => [key, normalise(value[key])])) : value;

export function questionContent(question) {
    if (Boolean(question.text) === Boolean(question.math)) throw new Error('A bank question needs either text or structured maths.');
    return { grade_id: question.grade_id, subject_id: question.subject_id, instructions: question.instructions || '', ...(question.text ? { text: question.text } : { math: question.math }) };
}

export function questionFingerprint(question) {
    return hash(JSON.stringify(normalise(questionContent(question))));
}

export function sourceKey(source) {
    const slot = source.slot || (/question2[-.]/i.test(source.local_pdf) ? 'question2' : 'question');
    if (!['question', 'question2'].includes(slot)) throw new Error('Unsupported question PDF slot.');
    return `${source.grade_id}:${source.subject_id}:${source.lesson_id}:${slot}`;
}

function bindingHash(source) {
    return hash(JSON.stringify([source.grade_id, source.subject_id, source.lesson_id, source.topic]));
}

async function sourceBytes(root, source) {
    const file = await realpath(path.resolve(root, source.local_pdf));
    if (!file.startsWith(root + path.sep)) throw new Error('PDF must remain inside the local collection.');
    const bytes = await readFile(file);
    return { bytes, sha256: hash(bytes) };
}

export async function readBank(root) {
    try {
        const bank = JSON.parse(await readFile(path.join(root, 'structured/bank.json'), 'utf8'));
        if (bank.version !== 1 || !bank.sources || !bank.questions) throw new Error('Unsupported or invalid question bank.');
        return bank;
    } catch (error) {
        if (error.code !== 'ENOENT') throw error;
        return { version: 1, sources: {}, questions: {} };
    }
}

async function atomicJson(file, value) {
    const temporary = `${file}.${randomUUID()}.tmp`;
    try { await writeFile(temporary, JSON.stringify(value, null, 2)); await rename(temporary, file); }
    finally { await unlink(temporary).catch(error => { if (error.code !== 'ENOENT') throw error; }); }
}

function rebuildOrigins(bank, activeKeys) {
    for (const question of Object.values(bank.questions)) { question.origins = []; question.active = false; }
    for (const [key, source] of Object.entries(bank.sources)) {
        if (!activeKeys.has(key) || !['processed', 'processed_with_issues'].includes(source.status)) continue;
        for (const occurrence of source.occurrences || []) {
            const question = bank.questions[occurrence.question_id];
            if (!question) throw new Error('Broken bank question reference.');
            question.active = true;
            question.origins.push({ source_key: key, lesson_id: source.lesson_id, topic: source.topic, source_sha256: source.sha256, page: occurrence.page, label: occurrence.label });
        }
    }
}

export function eligibleQuestions(bank, currentKeys = new Set(Object.keys(bank.sources))) {
    const result = [];
    for (const question of Object.values(bank.questions)) {
        if (!question.active) continue;
        const topics = new Set();
        for (const origin of question.origins.filter(origin => currentKeys.has(origin.source_key))) {
            if (topics.has(origin.topic)) continue;
            topics.add(origin.topic);
            result.push({ ...questionContent(question), id: question.id, ...origin, origins: question.origins, status: 'structured_unverified' });
        }
    }
    return result;
}

export async function processLessonBank({ root, manifest, processorVersion, extract }) {
    root = await realpath(root);
    await mkdir(path.join(root, 'structured'), { recursive: true });
    const lockFile = path.join(root, 'structured/bank.lock');
    let lock;
    try { lock = await open(lockFile, 'wx'); }
    catch (error) { if (error.code === 'EEXIST') throw new Error('A question-bank processing run is already active.'); throw error; }
    try {
        const keys = manifest.map(sourceKey);
        if (new Set(keys).size !== keys.length) throw new Error('The manifest contains duplicate lesson PDF slots.');
        const activeKeys = new Set(keys);
        const bank = await readBank(root);
        const stats = { scanned_pdfs: 0, skipped_unchanged_pdfs: 0, failed_pdfs: 0, added_questions: 0, duplicate_occurrences: 0 };
        for (const source of manifest) {
            const key = sourceKey(source), previous = bank.sources[key];
            let current;
            try {
                current = await sourceBytes(root, source);
                const binding = bindingHash(source);
                if (!source.force_reprocess && previous && previous.sha256 === current.sha256 && previous.binding_hash === binding && previous.processor_version === processorVersion && ['processed', 'processed_with_issues'].includes(previous.status)) {
                    previous.local_pdf = source.local_pdf;
                    stats.skipped_unchanged_pdfs++; continue;
                }
                // Clear the processed state before extraction. Nothing in the generator rescans a PDF.
                bank.sources[key] = { lesson_id: source.lesson_id, grade_id: source.grade_id, subject_id: source.subject_id, topic: source.topic, local_pdf: source.local_pdf, status: 'processing', sha256: current.sha256, binding_hash: binding, processor_version: processorVersion, occurrences: [] };
                await atomicJson(path.join(root, 'structured/bank.json'), bank);
                stats.scanned_pdfs++;
                const extracted = await extract(source, current.bytes, current.sha256);
                const occurrences = [];
                for (const candidate of extracted.questions) {
                    if (candidate.grade_id !== source.grade_id || candidate.subject_id !== source.subject_id) throw new Error('Extracted question does not match its source course.');
                    const id = questionFingerprint(candidate);
                    if (!bank.questions[id]) {
                        bank.questions[id] = { id, ...questionContent(candidate), created_at: new Date().toISOString(), active: false, origins: [], status: 'structured_unverified' };
                        stats.added_questions++;
                    } else stats.duplicate_occurrences++;
                    occurrences.push({ question_id: id, page: candidate.page, label: candidate.label });
                }
                bank.sources[key] = { ...bank.sources[key], status: extracted.rejected.length || !occurrences.length ? 'processed_with_issues' : 'processed', processed_at: new Date().toISOString(), occurrences, rejected: extracted.rejected, skipped_pages: extracted.skipped_pages };
            } catch (error) {
                stats.failed_pdfs++;
                bank.sources[key] = { lesson_id: source.lesson_id, grade_id: source.grade_id, subject_id: source.subject_id, topic: source.topic, local_pdf: source.local_pdf, sha256: current?.sha256 || null, processor_version: processorVersion, status: 'failed', error: error.message, occurrences: [], failed_at: new Date().toISOString() };
            }
        }
        for (const [key, source] of Object.entries(bank.sources)) if (!activeKeys.has(key)) source.status = 'removed';
        rebuildOrigins(bank, activeKeys);
        bank.updated_at = new Date().toISOString();
        bank.processor_version = processorVersion;
        bank.last_run = { ...stats, active_unique_questions: Object.values(bank.questions).filter(q => q.active).length, archived_questions: Object.values(bank.questions).filter(q => !q.active).length };
        await atomicJson(path.join(root, 'structured/bank.json'), bank);
        const questions = eligibleQuestions(bank);
        await atomicJson(path.join(root, 'structured/questions.json'), { version: 2, method: 'persistent_lesson_bank', questions, rejected: Object.values(bank.sources).flatMap(s => s.rejected || []), skipped_pages: Object.values(bank.sources).flatMap(s => s.skipped_pages || []) });
        return { bank, stats: bank.last_run };
    } finally { await lock.close(); await unlink(lockFile); }
}

// Hash checks are cheap file reads, not OCR/PDF parsing. Never serve stale questions after an upload changes.
export async function loadCurrentQuestions(root, manifest, processorVersion) {
    root = await realpath(root);
    const bank = await readBank(root), currentKeys = new Set(), pending = [];
    for (const source of manifest) {
        const key = sourceKey(source), record = bank.sources[key];
        try {
            const current = await sourceBytes(root, source);
            if (record && ['processed', 'processed_with_issues'].includes(record.status) && record.sha256 === current.sha256 && record.binding_hash === bindingHash(source) && record.processor_version === processorVersion) currentKeys.add(key);
            else pending.push({ lesson_id: source.lesson_id, source_key: key, status: record?.status === 'failed' ? 'failed' : 'pending' });
        } catch { pending.push({ lesson_id: source.lesson_id, source_key: key, status: 'unavailable' }); }
    }
    return { questions: eligibleQuestions(bank, currentKeys), pending };
}
