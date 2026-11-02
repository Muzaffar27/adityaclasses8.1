<template>
    <section class="pdf-review" aria-label="Review by PDF">
        <p v-if="error" class="error" role="alert">{{ error }} <button :disabled="loading" @click="reload">Reload PDF list</button></p>
        <label class="pdf-picker">PDF to review
            <select v-model="selected" :disabled="loading" @change="loadPdf">
                <option value="">Choose a PDF</option>
                <option v-for="source in availableSources" :key="source.source_id" :value="source.source_id">{{ source.lesson_title }} · {{ source.pending_count }} to review</option>
            </select>
        </label>
        <p v-if="loading" role="status">Loading PDF review…</p>
        <p v-else-if="!hasSources && !error && !queue.pending.value.length && !queue.failures.value.length">{{ scope === 'starter' && selection?.flagged_for_goal ? 'Starter reviewed. Finish flagged questions in Needs correction.' : 'No more questions in this selection.' }}</p>
        <template v-if="batch && !loading">
            <div class="batch-workspace">
                <div class="question-list">
                    <article v-for="question in visibleQuestions" :key="question.id" class="batch-question" :data-question-id="question.id">
                        <header><h3 :id="`pdf-question-${question.id}`" tabindex="-1">Page {{ question.origin.page }} · {{ question.origin.label }}</h3><span v-if="question.status !== 'pending'">{{ question.status === 'approved' ? 'Verified' : 'Review again' }}</span></header>
                        <ReviewQuestionContent>
                            <StructuredQuestion :content="question.content" />
                            <QuestionDiagrams v-if="question.content.diagrams?.length" :question-id="question.id" :diagrams="question.content.diagrams" @ready="ready[question.id] = $event" />
                        </ReviewQuestionContent>
                        <div class="question-actions">
                            <div v-if="eligible(question)" class="flag-controls">
                                <label><input type="checkbox" :checked="false" :disabled="queue.flaggedIds.value.has(question.id)" @change="flagQuestion(question)" /> Flag incorrect</label>
                                <details><summary>Add note</summary><textarea v-model="reasons[question.id]" rows="2" maxlength="1000" placeholder="What looks wrong?" /></details>
                            </div>
                            <button @click="comparePage(question.origin.page)">Compare page {{ question.origin.page }}</button>
                        </div>
                    </article>
                </div>
                <aside ref="original" class="original" aria-label="Original PDF">
                    <button class="original-toggle" :aria-expanded="originalExpanded" @click="originalExpanded = !originalExpanded">{{ originalExpanded ? 'Hide original PDF' : 'Show original PDF' }} · page {{ pdfPage }}</button>
                    <div v-show="originalExpanded" class="original-content">
                    <p v-if="pdfLoading" role="status">Loading original PDF…</p>
                    <p v-if="pdfError" role="alert">{{ pdfError }} <button @click="loadOriginal">Retry PDF</button></p>
                    <template v-if="pdfUrl"><a :href="pdfUrl" target="_blank" rel="noopener">Open full PDF to zoom</a><PdfDocumentViewer :url="pdfUrl" :page-number="pdfPage" @ready="pdfReady = true" /></template>
                    </div>
                </aside>
            </div>
            <div class="approval-bar">
                <p role="status">{{ approvalQuestions.length }} ready · {{ displayedFlagCount }} flagged</p>
                <p v-if="!allDiagramsReady && approvalQuestions.length">Waiting for question diagrams to load. Flag a question to leave it pending if its diagram cannot load.</p>
                <button class="approve" :disabled="!!error || hasFailedSource || !approvalQuestions.length || !allDiagramsReady || !pdfReady" @click="approve">Approve {{ approvalQuestions.length }}</button>
                <span class="approval-hint">Matches original PDF</span>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import api from '../../api';
import { useQuestionReviewSaves } from '../../composables/questionReviewSaves';
const queue = useQuestionReviewSaves();
const depleted = new Set();
let refreshWhenEmpty = false;
import StructuredQuestion from '../common/StructuredQuestion.vue';
import ReviewQuestionContent from './ReviewQuestionContent.vue';
const QuestionDiagrams = defineAsyncComponent(() => import('./QuestionDiagrams.vue'));
const PdfDocumentViewer = defineAsyncComponent(() => import('../common/PdfDocumentViewer.vue'));
const props = defineProps({ grade: [String, Number], subject: [String, Number], lesson: [String, Number], topic: String, scope: { type: String, default: 'all' } });
const selection = ref(null);
const sources = ref([]), selected = ref(''), batch = ref(null), flagged = ref([]), ready = ref({});
const loading = ref(false), error = ref('');
const pdfUrl = ref(''), pdfPage = ref(1), pdfLoading = ref(false), pdfError = ref(''), pdfReady = ref(false);
const original = ref(null), reasons = ref({});

const originalExpanded = ref(!window.matchMedia('(max-width:800px)').matches);
let serial = 0, pdfSerial = 0;
function savedFlags() {
    try { const ids = JSON.parse(sessionStorage.getItem(`pdf-review-flags:${selected.value}`) || '[]'); return Array.isArray(ids) ? ids : []; }
    catch { return []; }
}
function rememberFlags() {
    try {
        const unsaved = savedFlags().filter(id => batch.value?.questions.some(q => q.id === id && eligible(q)));
        sessionStorage.setItem(`pdf-review-flags:${selected.value}`, JSON.stringify([...new Set([...flagged.value, ...unsaved])]));
    } catch { /* Server-saved flags do not depend on browser storage. */ }
}
function comparePage(number) {
    originalExpanded.value = true;
    pdfPage.value = number;
    if (window.matchMedia('(max-width:800px)').matches) nextTick(() => original.value?.scrollIntoView({ block: 'start' }));
}
const eligible = question => ['pending', 'review_again'].includes(question.status);
const scopeQuestions = computed(() => (batch.value?.questions || []).filter(q => props.scope !== 'starter' || selection.value?.ids.includes(q.id)));
const visibleQuestions = computed(() => scopeQuestions.value.filter(q => eligible(q) && !queue.hiddenIds.value.has(q.id)));
const approvalQuestions = computed(() => visibleQuestions.value.filter(q => !queue.flaggedIds.value.has(q.id)));
const hasFailedSource = computed(() => queue.failures.value.some(job => job.sourceId === selected.value));
const displayedFlagCount = computed(() => new Set([...flagged.value, ...queue.jobs.value.filter(job => job.kind === 'flag' && job.sourceId === selected.value && job.status !== 'failed').map(job => job.questionId)]).size);
const sourceAvailable = source => source.pending_count > 0 && !depleted.has(source.source_id) && !queue.pending.value.some(job => job.kind === 'approve' && job.sourceId === source.source_id);
const availableSources = computed(() => sources.value.filter(sourceAvailable));
const hasSources = computed(() => availableSources.value.length > 0);
async function advance() {
    if (hasFailedSource.value || error.value) return;
    if (selected.value) { depleted.add(selected.value); const source = sources.value.find(s => s.source_id === selected.value); if (source) source.pending_count = 0; }
    const next = sources.value.find(sourceAvailable);
    if (next) { selected.value = next.source_id; await loadPdf(); await nextTick(); const first = visibleQuestions.value[0]; if (first) { const target = document.getElementById(`pdf-question-${first.id}`); target?.focus({ preventScroll: true }); target?.scrollIntoView({ block: 'start' }); } }
    else { batch.value = null; selected.value = ''; closeOriginal(); await refreshCompleted(); }
}
async function refreshCompleted() {
    if (!refreshWhenEmpty || queue.pending.value.length || loading.value || queue.failures.value.length || (batch.value && approvalQuestions.value.length)) return;
    refreshWhenEmpty = false; depleted.clear(); await reload();
}
const allDiagramsReady = computed(() => approvalQuestions.value.every(q => !q.content.diagrams?.length || ready.value[q.id]));
function closeOriginal() { pdfSerial++; if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = ''; pdfReady.value = false; pdfLoading.value = false; pdfError.value = ''; }
async function reload(preferredSource) {
    if (Number.isInteger(preferredSource)) depleted.delete(preferredSource);
    const request = ++serial;
    loading.value = true; error.value = ''; batch.value = null; closeOriginal();
    try {
        const { data } = await api.get('/admin/question-reviews/pdfs', { params: { grade_id: props.grade || undefined, subject_id: props.subject || undefined, lesson_id: props.lesson || undefined, review_scope: props.scope, topic: props.topic || undefined } });
        if (request !== serial) return;
        sources.value = data.data;
        selection.value = data.selection || null;
        const preferred = Number.isInteger(preferredSource) ? preferredSource : selected.value;
        selected.value = sources.value.some(s => s.source_id === preferred && sourceAvailable(s)) ? preferred : sources.value.find(sourceAvailable)?.source_id || '';
        if (selected.value) await loadPdf();
    } catch (e) { if (request === serial) error.value = e.response?.data?.message || 'Could not load PDFs. Try again.'; }
    finally { if (request === serial) loading.value = false; }
}
async function loadPdf() {
    const request = ++serial;
    loading.value = true; error.value = ''; batch.value = null; flagged.value = []; ready.value = {}; closeOriginal();
    try {
        if (!selected.value) return;
        const { data } = await api.get(`/admin/question-reviews/pdfs/${selected.value}`);
        if (request !== serial) return;
        batch.value = data; pdfPage.value = data.questions[0]?.origin.page || 1;
        const legacy = savedFlags().filter(id => data.questions.some(q => q.id === id && eligible(q)));
        flagged.value = data.questions.filter(q => q.status === 'needs_correction').map(q => q.id);
        for (const id of legacy) { if (request !== serial) return; flagQuestion(batch.value.questions.find(q => q.id === id)); }
        rememberFlags();
        loadOriginal();
    } catch (e) { if (request === serial) error.value = e.response?.data?.message || 'Could not load this PDF review. Try again.'; }
    finally { if (request === serial) { loading.value = false; if (batch.value && !approvalQuestions.value.length && !hasFailedSource.value && !error.value) await advance(); } }
}
async function loadOriginal() {
    if (!batch.value) return;
    closeOriginal(); const request = ++pdfSerial, source = batch.value.source;
    pdfLoading.value = true;
    try {
        const { data } = await api.get(`/lessons/${source.lesson_id}/pdf/${source.slot}`, { responseType: 'blob', timeout: 60000 });
        if (request === pdfSerial) pdfUrl.value = URL.createObjectURL(data);
    } catch { if (request === pdfSerial) pdfError.value = 'The original PDF could not be loaded.'; }
    finally { if (request === pdfSerial) pdfLoading.value = false; }
}
function flagQuestion(question) {
    if (!question || !batch.value || queue.flaggedIds.value.has(question.id)) return;
    const position = visibleQuestions.value.findIndex(q => q.id === question.id);
    const source = batch.value.source;
    if (!queue.enqueue({ kind: 'flag', questionId: question.id, revision: question.revision, reason: reasons.value[question.id] || null, sourceId: source.id, lessonId: source.lesson_id, label: source.lesson_title })) return;
    refreshWhenEmpty = true;
    if (!approvalQuestions.value.length && !loading.value) void advance();
    else nextTick(() => { const next = visibleQuestions.value[Math.min(Math.max(position, 0), visibleQuestions.value.length - 1)]; if (next) document.getElementById(`pdf-question-${next.id}`)?.focus({ preventScroll: true }); });
}
function approve() {
    if (!batch.value || error.value || hasFailedSource.value || !approvalQuestions.value.length || !allDiagramsReady.value || !pdfReady.value) return;
    const source = batch.value.source;
    if (!queue.enqueue({ kind: 'approve', sourceId: source.id, lessonId: source.lesson_id, label: source.lesson_title, snapshot: batch.value.snapshot, questionIds: approvalQuestions.value.map(q => q.id) })) return;
    refreshWhenEmpty = true; void advance();
}
watch(queue.result, result => {
    if (!result) return;
    const { job, data } = result;
    if (batch.value?.source.id === job.sourceId) {
        if (job.kind === 'flag') { batch.value = data.pdf; flagged.value = data.pdf.questions.filter(q => q.status === 'needs_correction').map(q => q.id); rememberFlags(); }
        else for (const question of batch.value.questions) if (job.questionIds.includes(question.id)) question.status = 'approved';
    }
});
watch(() => queue.pending.value.length, count => { if (!count) void nextTick(refreshCompleted); });
watch(() => [props.grade, props.subject, props.lesson, props.scope, props.topic], reload, { immediate: true });
defineExpose({ reload });
onBeforeUnmount(() => { serial++; closeOriginal(); });
</script>

<style scoped>
.pdf-review { min-width:0; }
.pdf-picker { display:grid; grid-template-columns:minmax(0,1fr); gap:6px; margin:8px 0 12px; font-size:.8rem; color:var(--app-muted); }
button,select,textarea { font:inherit; }select,textarea { width:100%; min-width:0; padding:9px; background:var(--app-panel); color:var(--app-text); border:1px solid var(--app-border); border-radius:8px; }
button { padding:8px 12px; min-height:44px; cursor:pointer; border:1px solid var(--app-border); border-radius:8px; background:var(--app-panel); color:var(--app-text); }button:disabled { opacity:.55; cursor:default; }
button:focus-visible,select:focus-visible,input:focus-visible,summary:focus-visible { outline:3px solid var(--app-accent); outline-offset:2px; }
.feedback,.error { padding:10px 12px; margin:8px 0; border-radius:8px; line-height:1.5; }.feedback { background:rgba(34,197,94,.12); scroll-margin-top:110px; }.feedback p { display:inline; margin-left:8px; }.feedback:focus { outline:2px solid var(--app-accent); outline-offset:2px; }.error { background:rgba(239,68,68,.12); }
.batch-workspace { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:16px; align-items:start; }.question-list,.original { min-width:0; }
.batch-question { padding:0 0 12px; margin-bottom:14px; border-bottom:1px solid var(--app-border); overflow-wrap:anywhere; }header { display:flex; justify-content:space-between; align-items:center; gap:8px; }h3 { font-size:.85rem; margin:0; }header span { font-size:.8rem; color:var(--app-muted); }
.question-actions { display:flex; align-items:center; flex-wrap:wrap; justify-content:space-between; gap:6px; }.question-actions>button { font-size:.8rem; padding:6px 9px; }.flag-controls { display:flex; align-items:center; flex-wrap:wrap; gap:6px 14px; min-width:0; flex:1 1 220px; font-size:.85rem; }.flag-controls label { display:flex; align-items:center; gap:7px; min-height:44px; }.flag-controls details { min-width:0; max-width:100%; }.flag-controls summary { cursor:pointer; padding:10px 0; }.flag-controls details[open] { flex-basis:100%; }.flag-controls textarea { display:block; }.flag-controls input { width:18px; height:18px; flex-shrink:0; }
.original { position:sticky; top:110px; scroll-margin-top:110px; padding:10px; border:1px solid var(--app-border); border-radius:10px; }.original-toggle { width:100%; text-align:left; background:none; border:0; padding:4px; font-size:.85rem; }.original-content a { display:inline-block; padding:6px 4px; font-size:.8rem; }.original :deep(.pdf-document-viewer) { height:65vh; margin-top:4px; }
.approval-bar { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:2px 12px; position:sticky; bottom:8px; z-index:5; background:var(--app-panel,#162033); border:1px solid var(--app-border); border-radius:10px; padding:10px 12px; box-shadow:0 -3px 14px rgb(0 0 0 / .15); }.approval-bar p { margin:0; font-size:.85rem; }.approve { grid-column:2; grid-row:1 / 3; background:#166534; color:white; }.approval-hint { grid-column:1; font-size:.75rem; color:var(--app-muted); }
@media(max-width:800px) { .batch-workspace { grid-template-columns:minmax(0,1fr); }.original { position:static; grid-row:1; padding:2px 8px; }.original :deep(.pdf-document-viewer) { height:45vh; } }
@media(max-width:500px) { .approval-bar { display:flex; flex-wrap:wrap; gap:6px 10px; }.approve { flex:1 1 180px; font-size:.85rem; }.approval-hint { flex-basis:100%; }}
</style>
