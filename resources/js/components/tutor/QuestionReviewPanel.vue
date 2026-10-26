<template>
    <section class="review-panel" aria-labelledby="review-title">
        <header class="review-header"><div><p class="eyebrow">Lesson question bank</p><h2 id="review-title">Review questions</h2><p>Check the extraction once. Correct questions can then be used in student tests.</p></div><button type="button" :disabled="loading || saving" @click="load()">Refresh</button></header>
        <div v-if="message" class="feedback" role="status">{{ message }}</div>
        <div v-if="error" class="error" role="alert">{{ error }} <button type="button" @click="load()">Retry</button></div>
        <div class="filters">
            <label>Grade<select v-model="grade" @change="changeFilters"><option value="">All grades</option><option v-for="g in grades" :key="g.id" :value="g.id">{{ g.name }}</option></select></label>
            <label>Subject<select v-model="subject" @change="changeFilters"><option value="">All subjects</option><option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option></select></label>
            <label>Lesson<select v-model="lesson" @change="page = 1; load()"><option value="">All lessons</option><option v-for="l in visibleLessons" :key="l.id" :value="l.id">{{ l.title }}</option></select></label>
        </div>
        <nav class="review-tabs" aria-label="Review status"><button v-for="tab in tabs" :key="tab.key" type="button" :aria-pressed="status === tab.key" @click="status = tab.key; page = 1; load()">{{ tab.label }} <span>{{ counts[tab.key] || 0 }}</span></button></nav>
        <p v-if="loading" role="status">Loading the review queue…</p>
        <div v-else-if="!question && !error" class="empty"><h3>{{ status === 'approved' ? 'No verified questions yet' : status === 'reprocessing' ? 'No extractions running' : 'No questions to review' }}</h3><p>{{ status === 'pending' ? 'New and changed questions appear here after their lesson PDFs are processed.' : 'Choose another status or change the filters.' }}</p></div>
        <template v-else-if="question">
            <div class="question-navigation"><button type="button" :disabled="saving || (index === 0 && page === 1)" @click="previous">Previous</button><span>{{ (page - 1) * 20 + index + 1 }} of {{ total }}</span><button type="button" :disabled="saving || (index === items.length - 1 && page >= lastPage)" @click="next">Next</button></div>
            <div class="review-workspace" :class="{ 'has-source': sourceUrl }">
                <article class="question-card" aria-labelledby="question-heading">
                    <div class="card-heading"><h3 id="question-heading" tabindex="-1">Extracted question</h3><span class="status-label">{{ statusLabel(question.status) }}</span></div>
                    <p v-if="question.status === 'review_again'" class="notice" role="status">The extractor returned the same content. It has not been verified. Check the original again before choosing Yes.</p>
                    <p v-if="question.status === 'reprocessing'" class="notice" role="status">Reprocessing the source PDF. This question will need another review when extraction finishes.</p>
                    <p v-if="question.status === 'needs_attention'" class="notice" role="status">This question is missing from the latest extraction. It cannot be approved. Inspect the original; any newly extracted version appears separately.</p>
                    <StructuredQuestion :content="question.content" />
                    <div class="source-controls"><label>Original sheet<select v-model="originIndex" :disabled="sourceLoading" @change="closeSource"><option v-for="(origin, n) in question.origins" :key="`${origin.source_id}-${origin.page}`" :value="n">{{ origin.lesson_title }} · page {{ origin.page }}</option></select></label><button type="button" :disabled="sourceLoading" @click="sourceUrl ? closeSource() : openSource()">{{ sourceLoading ? 'Loading PDF…' : sourceUrl ? 'Close original PDF' : 'Compare original PDF' }}</button></div>
                    <p v-if="sourceError" class="error" role="alert">{{ sourceError }} <button type="button" @click="openSource">Retry PDF</button></p>
                    <template v-if="canDecide">
                        <label class="review-note">What looks wrong? <span>(optional, used with No)</span><textarea v-model="note" rows="2" maxlength="1000" placeholder="For example: denominator missing, wrong sign, incomplete instructions" /></label>
                        <p class="decision-prompt">Does this match the original question?</p>
                        <div class="decisions"><button type="button" class="yes" :disabled="saving" @click="decide(true)">{{ saving ? 'Saving…' : 'Yes, correct' }}</button><button type="button" class="no" :disabled="saving" @click="decide(false)">No, reprocess</button></div>
                    </template>
                    <p v-else-if="question.status === 'approved'" class="verified">Verified. This exact question will not be asked again.</p>
                </article>
                <aside v-if="sourceUrl" class="source-card" aria-label="Original PDF"><h3>Original PDF · page {{ origin?.page }}</h3><a :href="sourceUrl" target="_blank" rel="noopener">Open full PDF to zoom</a><PdfDocumentViewer :url="sourceUrl" :page-number="origin?.page" /></aside>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import api from '../../api';
import StructuredQuestion from '../common/StructuredQuestion.vue';
const PdfDocumentViewer = defineAsyncComponent(() => import('../common/PdfDocumentViewer.vue'));
const items = ref([]), counts = ref({}), grades = ref([]), subjects = ref([]), lessons = ref([]);
const grade = ref(''), subject = ref(''), lesson = ref(''), status = ref('pending');
const page = ref(1), lastPage = ref(1), total = ref(0), index = ref(0), originIndex = ref(0);
const loading = ref(false), saving = ref(false), error = ref(''), message = ref(''), note = ref('');
const sourceUrl = ref(''), sourceLoading = ref(false), sourceError = ref('');
let loadSerial = 0, sourceSerial = 0, poll;
const tabs = [{ key: 'pending', label: 'To review' }, { key: 'approved', label: 'Verified' }, { key: 'reprocessing', label: 'Reprocessing' }];
const question = computed(() => items.value[index.value]);
const origin = computed(() => question.value?.origins?.[originIndex.value]);
const canDecide = computed(() => question.value?.current && ['pending', 'review_again'].includes(question.value?.status));
const visibleLessons = computed(() => lessons.value.filter(l => (!grade.value || Number(l.grade_id) === Number(grade.value)) && (!subject.value || Number(l.subject_id) === Number(subject.value))));
const statusLabel = value => ({ pending: 'Needs review', approved: 'Verified', reprocessing: 'Reprocessing', review_again: 'Review again', needs_attention: 'Needs attention' })[value] || value;

async function load(silent = false) {
    const serial = ++loadSerial, previousId = question.value?.id;
    if (!silent) loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get('/admin/question-reviews', { params: { grade_id: grade.value || undefined, subject_id: subject.value || undefined, lesson_id: lesson.value || undefined, status: status.value, page: page.value } });
        if (serial !== loadSerial) return;
        items.value = data.data; counts.value = data.counts; grades.value = data.grades; subjects.value = data.subjects; lessons.value = data.lessons;
        total.value = data.total; lastPage.value = data.last_page;
        index.value = silent ? Math.max(0, items.value.findIndex(q => q.id === previousId)) : 0;
        if (page.value > lastPage.value) { page.value = lastPage.value; return load(); }
    } catch (e) { if (serial === loadSerial) error.value = e.response?.data?.message || 'Could not load questions. Try again.'; }
    finally { if (serial === loadSerial) loading.value = false; }
}
function changeFilters() { lesson.value = ''; page.value = 1; load(); }
function closeSource() { sourceSerial++; if (sourceUrl.value) URL.revokeObjectURL(sourceUrl.value); sourceUrl.value = ''; sourceLoading.value = false; sourceError.value = ''; }
async function openSource() {
    if (!origin.value) return;
    const selected = origin.value, serial = ++sourceSerial;
    sourceLoading.value = true; sourceError.value = '';
    try {
        const { data } = await api.get(`/lessons/${selected.lesson_id}/pdf/${selected.slot}`, { responseType: 'blob', timeout: 60000 });
        if (serial !== sourceSerial) return;
        sourceUrl.value = URL.createObjectURL(data);
    } catch { if (serial === sourceSerial) sourceError.value = 'The original PDF could not be loaded.'; }
    finally { if (serial === sourceSerial) sourceLoading.value = false; }
}
async function decide(approved) {
    if (saving.value || !canDecide.value) return;
    saving.value = true; error.value = ''; message.value = '';
    try {
        await api.put(`/admin/question-reviews/${question.value.id}`, { approved, revision: question.value.revision, note: note.value || null });
        message.value = approved ? 'Verified. This exact question is approved once.' : 'Reprocessing requested. The result will need another review; it will not be approved automatically.';
        await load(); await nextTick(); document.getElementById('question-heading')?.focus();
    } catch (e) { error.value = e.response?.data?.message || 'Could not save your review. Try again.'; }
    finally { saving.value = false; }
}
function previous() { if (index.value > 0) index.value--; else if (page.value > 1) { page.value--; load(); } }
function next() { if (index.value < items.value.length - 1) index.value++; else if (page.value < lastPage.value) { page.value++; load(); } }
watch(() => question.value?.id, () => { closeSource(); note.value = ''; originIndex.value = 0; });
onMounted(() => { load(); poll = setInterval(() => { if (counts.value.reprocessing && !saving.value && !loading.value) load(true); }, 5000); });
onBeforeUnmount(() => { loadSerial++; clearInterval(poll); closeSource(); });
</script>

<style scoped>
.review-panel { min-width:0; color:var(--app-text); }
.review-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:20px; }
.review-header h2 { font-size:1.6rem; margin:0 0 8px; }.review-header p { margin:0; line-height:1.5; }.eyebrow { color:var(--app-accent); font-size:.75rem; text-transform:uppercase; letter-spacing:.08em; }
button,select,textarea { font:inherit; }button { cursor:pointer; border:1px solid var(--app-border); border-radius:10px; padding:10px 15px; background:var(--app-panel); color:var(--app-text); }button:disabled { cursor:wait; opacity:.55; }button:focus-visible,select:focus-visible,textarea:focus-visible { outline:3px solid var(--app-accent); outline-offset:2px; }
.filters { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }.filters label,.source-controls label,.review-note { display:flex; flex-direction:column; gap:7px; font-size:.85rem; min-width:0; }select,textarea { width:100%; min-width:0; color:var(--app-text); background:var(--app-panel); border:1px solid var(--app-border); border-radius:9px; padding:10px; }option { background:var(--app-bg,#111827); }
.review-tabs { display:flex; gap:8px; margin:18px 0; flex-wrap:wrap; }.review-tabs button[aria-pressed=true] { border-color:var(--app-accent); color:var(--app-accent); }.review-tabs span { margin-left:5px; font-variant-numeric:tabular-nums; }
.feedback,.notice,.error { padding:12px 15px; border-radius:10px; margin:12px 0; line-height:1.55; }.feedback { background:rgba(34,197,94,.12); }.notice { background:rgba(245,158,11,.12); }.error { background:rgba(239,68,68,.12); }
.question-navigation { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:12px 0; }.question-navigation span { font-size:.85rem; }
.review-workspace { display:grid; grid-template-columns:minmax(0,1fr); gap:18px; }.review-workspace.has-source { grid-template-columns:minmax(0,1fr) minmax(0,1fr); }
.question-card,.source-card,.empty { min-width:0; background:var(--app-glass); border:1px solid var(--app-border); border-radius:16px; padding:22px; }.card-heading { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:22px; }.card-heading h3,.source-card h3 { margin:0; font-size:1.05rem; }.status-label { font-size:.8rem; color:var(--app-accent); }.source-controls { display:flex; align-items:flex-end; gap:10px; margin:26px 0 20px; flex-wrap:wrap; }.source-controls label { flex:1 1 180px; }.source-card { min-height:450px; max-height:720px; overflow:hidden; }.source-card h3 { margin-bottom:15px; }.source-card :deep(.pdf-document-viewer) { height:calc(100% - 40px); max-height:640px; }
.review-note { margin:20px 0; }.review-note span { opacity:.7; }.decision-prompt { font-weight:600; }.decisions { display:flex; gap:12px; flex-wrap:wrap; }.decisions button { min-width:145px; }.yes { background:#166534; color:white; border-color:#166534; }.no { border-color:#f59e0b; }.verified { color:#4ade80; }
@media(max-width:1000px) { .review-workspace.has-source { grid-template-columns:minmax(0,1fr); } }
@media(max-width:580px) { .review-header { flex-direction:column; }.filters { grid-template-columns:minmax(0,1fr); }.question-card,.source-card { padding:16px; }.decisions button { flex:1; min-width:0; }.review-tabs button { padding:9px 11px; }.source-card { min-height:330px; max-height:550px; } }
</style>
