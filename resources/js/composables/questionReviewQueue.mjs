import { computed, ref, shallowRef } from 'vue';

// Keep explicit tutor decisions separate from whichever PDF is currently on screen.
export function createQuestionReviewQueue({ api, storage, key }) {
    const fingerprint = value => typeof value === 'string' && /^[a-f0-9]{64}$/.test(value);
    function valid(job) {
        if (!job || !Number.isInteger(job.sourceId) || job.sourceId < 1) return false;
        if (job.kind === 'flag') return fingerprint(job.questionId) && Number.isInteger(job.revision) && job.revision > 0;
        return job.kind === 'approve' && fingerprint(job.snapshot) && Array.isArray(job.questionIds) && job.questionIds.length > 0 && job.questionIds.length <= 5000 && job.questionIds.every(fingerprint);
    }
    let restored = [];
    try { restored = JSON.parse(storage.getItem(key) || '[]'); } catch { /* An unavailable journal must not prevent review. */ }
    const jobs = ref(Array.isArray(restored) ? restored.filter(job => valid(job) && typeof job.id === 'string' && ['saving', 'queued', 'failed'].includes(job.status)).map(job => ({ ...job, status: job.status === 'saving' ? 'queued' : job.status })) : []);
    const result = shallowRef(null);
    const pending = computed(() => jobs.value.filter(job => job.status !== 'failed'));
    const failures = computed(() => jobs.value.filter(job => job.status === 'failed'));
    const hiddenIds = computed(() => new Set(pending.value.flatMap(job => job.kind === 'flag' ? [job.questionId] : job.questionIds)));
    const flaggedIds = computed(() => new Set(jobs.value.filter(job => job.kind === 'flag').map(job => job.questionId)));
    let running = false;
    function persist() { try { storage.setItem(key, JSON.stringify(jobs.value)); } catch { /* Pending decisions also remain in memory. */ } }
    async function run(job) {
        if (job.kind === 'flag') {
            const { data } = await api.post(`/admin/question-reviews/${job.questionId}/flag`, { revision: job.revision, source_id: job.sourceId, reason: job.reason });
            // Only our acknowledged flags can update an already captured approval snapshot.
            for (const next of jobs.value) if (next.kind === 'approve' && next.sourceId === job.sourceId && next.status === 'queued') next.snapshot = data.pdf.snapshot;
            return data;
        }
        try {
            const { data } = await api.post(`/admin/question-reviews/pdfs/${job.sourceId}/approve`, { snapshot: job.snapshot, flagged: [], question_ids: job.questionIds });
            return data;
        } catch (error) {
            // A tab may have closed after the server committed but before it acknowledged.
            // Confirm that exact set; never substitute a newer snapshot and approve again.
            try {
                const { data } = await api.get(`/admin/question-reviews/pdfs/${job.sourceId}`);
                if (job.questionIds.every(id => data.questions.some(q => q.id === id && q.status === 'approved'))) return { approved_count: job.questionIds.length };
            } catch { /* Preserve the original save error. */ }
            throw error;
        }
    }
    async function drain() {
        if (running) return;
        running = true;
        try {
            let job;
            while ((job = jobs.value.find(item => item.status === 'queued'))) {
                job.status = 'saving'; persist();
                try {
                    const data = await run(job);
                    jobs.value = jobs.value.filter(item => item.id !== job.id);
                    result.value = { job: { ...job }, data };
                } catch (error) {
                    job.status = 'failed';
                    job.error = error.response?.data?.message || 'Save failed. Retry or review this PDF again.';
                }
                persist();
            }
        } finally { running = false; }
    }
    function enqueue(decision) {
        if (!valid(decision)) throw new Error('The review decision is incomplete. Reload this PDF.');
        if (jobs.value.some(job => job.kind === decision.kind && (job.kind === 'flag' ? job.questionId === decision.questionId : job.sourceId === decision.sourceId))) return false;
        jobs.value.push({ ...JSON.parse(JSON.stringify(decision)), id: globalThis.crypto.randomUUID(), status: 'queued', error: '' });
        persist(); void drain(); return true;
    }
    function retry(id) { const job = jobs.value.find(item => item.id === id && item.status === 'failed'); if (job) { job.status = 'queued'; job.error = ''; persist(); void drain(); } }
    function reviewAgain(id) { jobs.value = jobs.value.filter(job => job.id !== id || job.status !== 'failed'); persist(); }
    persist(); queueMicrotask(drain);
    return { jobs, pending, failures, hiddenIds, flaggedIds, result, enqueue, retry, reviewAgain };
}
