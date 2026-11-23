<template>
    <Layout title="">
        <main class="papers-page">
            <header class="page-heading"><div><h1>Test papers</h1><p>{{ staff ? 'Manage automatic topic tests and view saved papers.' : 'Your saved papers stay the same each time you open them.' }}</p><router-link :to="{ name: 'testPaperDemo' }">Try the maths keypad demo</router-link></div><button type="button" :disabled="loading || busy || saving || dirty.size > 0" @click="load">Refresh</button></header>
            <p v-if="error" class="error" role="alert">{{ error }} <button type="button" @click="load">Try again</button></p>
            <p v-if="notice" class="notice" role="status">{{ notice }}</p>
            <section v-if="staff" class="activation" aria-labelledby="automatic-heading">
                <h2 id="automatic-heading">Automatic topic tests</h2>
                <div class="fields">
                    <label>Student<select v-model="studentId" aria-label="Student" :disabled="loading || busy" @change="selectStudent"><option value="">My papers</option><option v-for="student in students" :key="student.id" :value="String(student.id)">{{ student.name }}</option></select></label>
                    <label>Grade<select v-model="gradeId" aria-label="Grade"><option value="">Choose a grade</option><option v-for="grade in grades" :key="grade.id" :value="String(grade.id)">{{ grade.name }}</option></select></label>
                    <label>Subject<select v-model="subjectId" aria-label="Subject"><option value="">Choose a subject</option><option v-for="subject in subjects" :key="subject.id" :value="String(subject.id)">{{ subject.name }}</option></select></label>
                </div>
                <p>After activation, finishing all main lesson videos in three new topics prepares a 15-question paper. Topics already finished at activation stay out. Answer videos and PDF viewing do not complete a topic.</p>
                <button type="button" :disabled="busy || !studentId || !gradeId || !subjectId" @click="activate">{{ busy ? 'Saving…' : 'Check this course' }}</button>
                <p v-if="!studentId" class="muted">Your papers are shown below. Tests are prepared automatically after three completed topics. Choose a student to review their papers.</p>
                <article v-for="item in enrolments" :key="item.enrolment.id" class="enrolment">
                    <div class="row"><strong>{{ gradeName(item.enrolment.grade_id) }} · {{ subjectName(item.enrolment.subject_id) }}</strong><span>{{ item.enrolment.enabled ? 'Enabled' : 'Paused' }}</span></div>
                    <p>{{ statusLabel(item.enrolment.last_status || 'waiting_topics') }}</p>
                    <div class="actions"><button type="button" :disabled="busy" @click="reconcile(item.enrolment.id)">Check now</button><button type="button" :disabled="busy" @click="toggle(item.enrolment)">{{ item.enrolment.enabled ? 'Pause' : 'Resume' }}</button></div>
                    <p v-if="item.enrolment.issues?.renamed_topics_need_review?.length" class="error">A topic name or grouping changed. Review the affected lessons before issuing another test.</p>
                    <ul v-if="item.batches.length" class="batch-list"><li v-for="batch in item.batches" :key="batch.id"><strong>Batch {{ batch.sequence }}</strong> — {{ statusLabel(batch.status) }}<p>{{ batch.topics.map(t => t.replace(/^\d+\.\s*/, '')).join(', ') }}</p><details v-if="batch.details"><summary>Details for tutor</summary><p>{{ batchDetail(batch) }}</p></details></li></ul>
                </article>
            </section>
            <section v-if="paper" class="paper" aria-labelledby="paper-heading">
                <div class="row"><h2 id="paper-heading">{{ paper.grade }} · {{ paper.subject }}</h2><button type="button" :disabled="busy" @click="closePaper">Back to papers</button></div>
                <p class="notice">{{ statusLabel(paper.status) }}. {{ paper.status === 'cancelled' ? 'This paper was cancelled. Saved responses are read-only.' : ['submitted', 'awaiting_review'].includes(paper.status) ? 'Your answers are saved and awaiting review. No score has been calculated.' : reviewing ? 'Tutor view — student responses are read-only.' : 'Use the maths keypad for your final answer. Add your steps under Working.' }}</p>
                <button v-if="paper.status === 'available' && paper.answering_available" type="button" :disabled="busy" @click="startPaper">Start answering</button>
                <nav class="question-nav" aria-label="Question navigation"><button type="button" :disabled="activeIndex === 0 || busy" @click="navigateQuestion(-1)">Previous</button><label>Question<select :value="activeIndex" :disabled="busy" @change="selectQuestion"><option v-for="(q, index) in paper.questions" :key="q.number" :value="index">{{ q.number }} of {{ paper.questions.length }}{{ drafts[q.number]?.answer ? ' · Answer entered' : '' }}</option></select></label><button type="button" :disabled="activeIndex >= paper.questions.length - 1 || busy" @click="navigateQuestion(1)">Next</button></nav>
                <article v-for="question in visibleQuestions" :key="question.number" class="question">
                    <h3>Question {{ question.number }}</h3><StructuredQuestion :content="question.content" />
                    <div v-for="diagram in question.content.diagrams || []" :key="diagram.url" class="diagram">
                        <a v-if="images[diagram.url]" :href="images[diagram.url]" target="_blank" rel="noopener"><img :src="images[diagram.url]" :alt="`Diagram for question ${question.number}`" @error="badImage(diagram.url)" /></a>
                        <p v-else-if="imageErrors[diagram.url]" class="error">Diagram could not load. <button type="button" @click="loadImage(diagram.url)">Retry</button></p><p v-else role="status">Loading diagram…</p>
                    </div>
                    <MathsAnswerEditor v-if="drafts[question.number] && (paper.status !== 'available' || reviewing)" :key="`${paper.id}-${question.number}`" :model-value="drafts[question.number]" :number="question.number" :read-only="!canEdit || busy" :handwriting-available="Boolean(paper.handwriting_available)" @update:model-value="editAnswer(question.number, $event)" />
                </article>
                <section v-if="canEdit" class="save-panel" aria-label="Answer saving and submission">
                    <p role="status">{{ saving ? 'Saving…' : dirty.size ? 'Changes not saved yet' : 'All changes saved' }}</p>
                    <p v-if="saveError" class="error" role="alert">{{ saveError }} Your draft is still on this page. Keep this page open until it saves.</p>
                    <button v-if="saveError" type="button" :disabled="saving || busy" @click="retrySave">{{ conflict ? 'Retry using this draft' : 'Retry save' }}</button>
                    <p>{{ answeredCount }} of {{ paper.questions.length }} final answers entered. Working is saved too.</p>
                    <button v-if="!confirmSubmission" type="button" :disabled="busy || saving" @click="prepareSubmit">Submit answers</button>
                    <div v-else class="notice" role="region" aria-label="Confirm submission"><p>{{ paper.questions.length - answeredCount }} questions have no final answer. After submission, answers cannot be edited. Results will stay unmarked pending review.</p><div class="actions"><button type="button" :disabled="busy" @click="submitPaper">Submit now</button><button type="button" :disabled="busy" @click="confirmSubmission = false">Keep working</button></div></div>
                </section>
            </section>
            <section v-else aria-labelledby="saved-heading">
                <h2 id="saved-heading">{{ staff && studentId ? 'Saved papers' : 'Your papers' }}</h2>
                <p v-if="loading" role="status">Loading papers…</p>
                <p v-else-if="!papers.length" class="empty">No papers yet. A paper appears automatically after three completed topics and enough approved questions.</p>
                <button v-for="item in papers" :key="item.id" class="paper-row" type="button" :disabled="busy" @click="openPaper(item.id)"><span><strong>{{ item.grade }} · {{ item.subject }}</strong><small>{{ statusLabel(item.status) }} · Version {{ item.version }}</small></span><span>Open →</span></button>
                <div v-if="lastPage > 1" class="actions"><button type="button" :disabled="page <= 1 || loading" @click="changePage(-1)">Previous</button><span>Page {{ page }} of {{ lastPage }}</span><button type="button" :disabled="page >= lastPage || loading" @click="changePage(1)">Next</button></div>
            </section>
        </main>
    </Layout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router';
import Layout from './common/Layout.vue';
import StructuredQuestion from './common/StructuredQuestion.vue';
import MathsAnswerEditor from './common/MathsAnswerEditor.vue';
import { useAuthStore } from '../stores/auth';
import { useTestPaperAttentionStore } from '../stores/testPaperAttention';
import api from '../api';
const auth = useAuthStore(), route = useRoute(), router = useRouter();
const attention = useTestPaperAttentionStore();
const staff = computed(() => ['tutor', 'admin'].includes(auth.user?.role));
const students = ref([]), grades = ref([]), subjects = ref([]), papers = ref([]), enrolments = ref([]), paper = ref(null);
const studentId = ref(''), gradeId = ref(''), subjectId = ref(''), page = ref(1), lastPage = ref(1);
const loading = ref(false), busy = ref(false), error = ref(''), notice = ref(''), images = ref({}), imageErrors = ref({});
const drafts = ref({}), revision = ref(0), activeIndex = ref(0), dirty = ref(new Set()), saving = ref(false), saveError = ref(''), conflict = ref(false), confirmSubmission = ref(false);
const reviewing = computed(() => staff.value && Number(paper.value?.student_id) !== auth.user?.id);
const canEdit = computed(() => paper.value?.status === 'started' && paper.value?.answering_available);
const visibleQuestions = computed(() => paper.value?.questions.slice(activeIndex.value, activeIndex.value + 1) || []);
const answeredCount = computed(() => Object.values(drafts.value).filter(a => a.answer.trim()).length);
let saveTimer, saveFlight;
let imageGeneration = 0, listGeneration = 0, paperGeneration = 0;
const statusLabel = status => ({ pending: 'Waiting to prepare', available: 'Ready to open', started: 'Started', submitted: 'Submitted', awaiting_review: 'Awaiting review', marked: 'Marked', cancelled: 'Cancelled', issued: 'Paper ready', waiting_topics: 'Waiting for three new topics', waiting_questions: 'More approved questions are needed', waiting_curriculum: 'Lesson changes need tutor review', waiting_access: 'Course access needs attention', waiting_staff: 'A tutor must reactivate this course', waiting_retry: 'Waiting to retry', disabled: 'Paused', error: 'Check failed — retry needed' }[status] || status);
const gradeName = id => grades.value.find(g => g.id === id)?.name || 'Grade';
const subjectName = id => subjects.value.find(s => s.id === id)?.name || 'Subject';
const message = e => Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'Could not load this information. Please try again.';
function batchDetail(batch) {
    if (batch.details.message || batch.details.reason) return batch.details.message || batch.details.reason;
    try { const detail = JSON.parse(batch.details.errors.topics[0].replace(/^No paper saved: /, '')); return `${detail.available_unique} distinct approved questions available; 15 needed. ` + detail.topics.map(t => `${t.topic.replace(/^\d+\.\s*/, '')}: ${t.available} available`).join('; '); }
    catch { return 'The approved question pool is not ready for this batch. Review the question bank, then check again.'; }
}
async function selectStudent() { page.value = 1; paperGeneration++; clearImages(); paper.value = null; papers.value = []; enrolments.value = []; error.value = ''; notice.value = ''; await router.replace({ name: 'testPapers' }); loading.value = true; await loadLists(); loading.value = false; }
async function loadLists() {
    const generation = ++listGeneration;
    const params = { page: page.value, ...(studentId.value ? { student_id: Number(studentId.value) } : {}) };
    try {
        const [list, automatic] = await Promise.all([api.get(staff.value && studentId.value ? '/admin/test-papers' : '/test-papers', { params }), staff.value && studentId.value ? api.get('/admin/automatic-test-papers', { params }) : Promise.resolve(null)]);
        if (generation !== listGeneration) return;
        papers.value = list.data.data; lastPage.value = list.data.last_page; enrolments.value = automatic?.data.data || [];
    } catch (e) { if (generation === listGeneration) error.value = message(e); }
}
async function load() {
    loading.value = true; error.value = '';
    try {
        if (staff.value && !students.value.length) {
            const [users, gradeList, subjectList] = await Promise.all([api.get('/getStudents'), api.get('/grades'), api.get('/subjects')]);
            students.value = users.data; grades.value = gradeList.data; subjects.value = subjectList.data;
        }
        await loadLists();
        if (route.params.paper) await openPaper(String(route.params.paper), false);
    } catch (e) { error.value = message(e); }
    finally { loading.value = false; }
}
async function action(fn) { busy.value = true; error.value = ''; notice.value = ''; try { await fn(); await loadLists(); } catch (e) { error.value = message(e); } finally { busy.value = false; } }
function activate() { return action(async () => { const { data } = await api.post(`/admin/students/${studentId.value}/automatic-test-papers`, { grade_id: Number(gradeId.value), subject_id: Number(subjectId.value) }); notice.value = data.enabled ? 'Course checked. Completed topics are included automatically.' : 'This course is configured and paused. Use Resume to enable it.'; }); }
function reconcile(id) { return action(async () => { const { data } = await api.post(`/admin/automatic-test-papers/${id}/reconcile`); notice.value = statusLabel(data.status); }); }
function toggle(item) { return action(async () => { await api.put(`/admin/automatic-test-papers/${item.id}`, { enabled: !item.enabled }); notice.value = item.enabled ? 'Automatic generation paused.' : 'Automatic generation resumed.'; }); }
function clearImages() { imageGeneration++; Object.values(images.value).forEach(url => URL.revokeObjectURL(url)); images.value = {}; imageErrors.value = {}; }
function badImage(url) { if (images.value[url]) URL.revokeObjectURL(images.value[url]); delete images.value[url]; imageErrors.value[url] = true; }
async function loadImage(url) {
    const generation = imageGeneration; imageErrors.value[url] = false;
    try { const { data } = await api.get(url.replace(/^\/api/, ''), { responseType: 'blob' }); if (generation !== imageGeneration) return; if (images.value[url]) URL.revokeObjectURL(images.value[url]); images.value[url] = URL.createObjectURL(data); }
    catch { if (generation === imageGeneration) imageErrors.value[url] = true; }
}
async function openPaper(id, navigate = true) {
    const generation = ++paperGeneration; busy.value = true; error.value = '';
    try { const { data } = await api.get(`/test-papers/${id}`); if (generation !== paperGeneration) return; clearImages(); setPaper(data); if (navigate) await router.push({ name: 'testPaper', params: { paper: id } }); data.questions.forEach(q => (q.content.diagrams || []).forEach(d => loadImage(d.url))); }
    catch (e) { if (generation === paperGeneration) error.value = message(e); }
    finally { if (generation === paperGeneration) busy.value = false; }
}
async function closePaper() { if (!(await leaveSafely())) return; paperGeneration++; clearImages(); paper.value = null; busy.value = false; router.push({ name: 'testPapers' }); }
function setPaper(data) { paper.value = data; revision.value = data.responses?.revision || 0; const saved = Object.fromEntries((data.responses?.answers || []).map(a => [a.number, a])); drafts.value = Object.fromEntries(data.questions.map(q => [q.number, { answer: saved[q.number]?.answer || '', working: saved[q.number]?.working || '', answer_ink: saved[q.number]?.answer_ink ?? null, working_ink: saved[q.number]?.working_ink ?? null }])); dirty.value.clear(); saveError.value = ''; conflict.value = false; confirmSubmission.value = false; activeIndex.value = 0; }
function editAnswer(number, value) { if (!canEdit.value) return; drafts.value[number] = value; dirty.value.add(number); confirmSubmission.value = false; clearTimeout(saveTimer); saveTimer = setTimeout(() => flushAnswers().catch(() => {}), 1500); }
function sameDraft(first, second) {
    return first.answer === second.answer && first.working === second.working
        && JSON.stringify(first.working_ink?.strokes ?? []) === JSON.stringify(second.working_ink?.strokes ?? []);
}
function flushAnswers() {
    clearTimeout(saveTimer);
    if (saveFlight) return saveFlight;
    if (!dirty.value.size || !canEdit.value) return Promise.resolve();
    const generation = paperGeneration, id = paper.value.id; saving.value = true;
    saveFlight = (async () => {
        while (dirty.value.size && generation === paperGeneration) {
            const patch = [...dirty.value].map(number => { const draft = drafts.value[number]; return { number, answer: draft.answer, working: draft.working, ...(paper.value.handwriting_available ? { working_ink: draft.working_ink } : {}) }; });
            try { const { data } = await api.put(`/test-papers/${id}/answers`, { revision: revision.value, answers: patch }); if (generation !== paperGeneration) return; revision.value = data.revision; patch.forEach(a => { if (sameDraft(drafts.value[a.number], a)) dirty.value.delete(a.number); }); saveError.value = ''; conflict.value = false; }
            catch (e) { if (generation === paperGeneration) { saveError.value = message(e); conflict.value = e.response?.status === 409; } throw e; }
        }
    })().finally(() => { saveFlight = null; saving.value = false; });
    return saveFlight;
}
async function retrySave() { try { if (conflict.value) { const { data } = await api.get(`/test-papers/${paper.value.id}`); if (data.status !== 'started') { saveError.value = 'This paper was submitted or cancelled in another tab. Copy any unsaved work before leaving.'; return; } revision.value = data.responses?.revision || 0; (data.responses?.answers || []).forEach(a => { if (!dirty.value.has(a.number)) drafts.value[a.number] = { answer: a.answer, working: a.working, answer_ink: a.answer_ink ?? null, working_ink: a.working_ink ?? null }; }); } await flushAnswers(); } catch { /* Draft stays visible with the saving error. */ } }
async function goToQuestion(index) { try { await flushAnswers(); activeIndex.value = index; } catch { /* Stay on the question so failed saves cannot hide work. */ } }
function navigateQuestion(delta) { return goToQuestion(activeIndex.value + delta); }
async function selectQuestion(event) { const element = event.target; await goToQuestion(Number(element.value)); element.value = String(activeIndex.value); }
async function startPaper() { busy.value = true; try { const { data } = await api.post(`/test-papers/${paper.value.id}/start`); setPaper(data); await attention.refresh(true); } catch (e) { error.value = message(e); } finally { busy.value = false; } }
async function prepareSubmit() { try { await flushAnswers(); confirmSubmission.value = true; } catch { /* Save before offering final submission. */ } }
async function submitPaper() { busy.value = true; try { await flushAnswers(); const { data } = await api.post(`/test-papers/${paper.value.id}/submit`, { revision: revision.value }); setPaper(data); await loadLists(); await attention.refresh(true); } catch (e) { error.value = message(e); } finally { busy.value = false; } }
async function leaveSafely() { if (!dirty.value.size) return true; try { await flushAnswers(); return true; } catch { return window.confirm('Your latest work is not saved. Leave without saving these changes?'); } }
function warnUnsaved(event) { if (dirty.value.size) { event.preventDefault(); event.returnValue = ''; } }
async function changePage(delta) { page.value += delta; loading.value = true; await loadLists(); loading.value = false; }
watch(() => route.params.paper, id => { if (id && paper.value?.id !== id) openPaper(String(id), false); else if (!id) { paperGeneration++; clearImages(); paper.value = null; dirty.value.clear(); drafts.value = {}; busy.value = false; } });
onBeforeRouteLeave(leaveSafely); onBeforeRouteUpdate((to, from) => to.params.paper !== from.params.paper ? leaveSafely() : true);
onMounted(() => { window.addEventListener('beforeunload', warnUnsaved); load(); }); onBeforeUnmount(() => { clearTimeout(saveTimer); window.removeEventListener('beforeunload', warnUnsaved); paperGeneration++; listGeneration++; clearImages(); });
</script>

<style scoped>
.papers-page{max-width:960px;margin:auto;padding:20px;min-width:0}.page-heading,.row{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}h1{font-size:1.7rem}h2{font-size:1.25rem}h3{font-size:1.1rem}.papers-page p{line-height:1.6;overflow-wrap:anywhere}.fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:16px 0}label{display:grid;gap:6px;min-width:0}button,select{font:inherit;box-sizing:border-box;min-height:44px;max-width:100%;border:1px solid #bac4d2;border-radius:8px;padding:10px 14px;background:#fff;color:#172b45}button{cursor:pointer}button:disabled{opacity:.55;cursor:default}button:focus-visible,select:focus-visible,summary:focus-visible{outline:3px solid #2563eb;outline-offset:3px}select{width:100%}.activation,.question,.enrolment{padding:20px;margin:18px 0;background:#fff;border:1px solid #dce2eb;border-radius:12px;min-width:0}.question{background:#fffef8}.actions{display:flex;flex-wrap:wrap;align-items:center;gap:10px}.question-nav{display:flex;justify-content:space-between;align-items:end;gap:8px;margin:18px 0}.question-nav label{flex:1;max-width:230px}.save-panel{padding:16px;background:#edf4fb;border-radius:12px}.paper-row{display:flex;justify-content:space-between;gap:12px;align-items:center;text-align:left;width:100%;margin:12px 0}.paper-row span{min-width:0;overflow-wrap:anywhere}.paper-row small{display:block;margin-top:6px}.notice,.empty{padding:14px;background:#edf4fb;border-radius:8px}.error{color:#8c2430}.muted{color:#526076}.batch-list{padding-left:22px}.batch-list li{margin:16px 0}.batch-list p{margin:4px 0}summary{cursor:pointer;min-height:44px;padding:10px 0}.diagram img{max-width:100%;height:auto}.diagram{min-width:0}.question :deep(math){max-width:100%}@media(max-width:650px){.papers-page{padding:12px}.fields{grid-template-columns:1fr}.activation,.question,.enrolment{padding:14px}}@media(min-width:700px){.paper .question,.paper .save-panel,.paper .question-nav{max-width:620px;margin-left:auto;margin-right:auto}}@media(max-width:380px){.papers-page{padding:8px}.question{padding:10px}.question-nav button{padding:8px;font-size:13px}}
</style>
<style scoped>
.activation,.question,.enrolment,.notice,.empty,.save-panel{--app-text:#172a44;--app-muted:#526479;color:#172a44}.activation strong,.enrolment strong{color:#172a44}.question :deep(.instructions),.question :deep(.question-text),.question :deep(.question-maths),.question :deep(math){color:#172a44}
</style>
