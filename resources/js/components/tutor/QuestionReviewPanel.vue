<template>
    <section class="review-panel" aria-labelledby="review-title">
        <header class="review-header"><h2 id="review-title">Review questions</h2><button type="button" :disabled="loading || saving" @click="refresh">Refresh</button></header>
        <QuestionReviewSaveStatus @review="reviewFailedSave" />
        <div v-if="message" class="feedback" role="status">{{ message }}</div>
        <div v-if="error" class="error" role="alert">{{ error }} <button type="button" @click="load()">Retry</button></div>
        <section v-if="recovery" ref="recoveryNotice" class="recovery-status notice" aria-label="Extraction recovery" role="status" tabindex="-1">
            <strong>{{ recovery.lesson_title }}</strong>
            <p v-if="recovery.status === 'pending'">{{ queuedLong ? 'Still queued. The background worker may be busy or stopped; it has not picked up this PDF yet.' : 'Queued successfully. Waiting for the background worker.' }}</p>
            <p v-else-if="recovery.status === 'processing'">Processing this PDF in the background.</p>
            <p v-else-if="['processed', 'processed_with_issues'].includes(recovery.status)">Finished: {{ recovery.question_count }} questions (was {{ recovery.before_count }}), {{ recovery.rejected_count }} rejected (was {{ recovery.before_rejected }}), {{ recovery.skipped_count }} skipped (was {{ recovery.before_skipped }}). {{ countsUnchanged ? 'Counts are unchanged; unresolved content still needs inspection.' : 'Review the extracted results before approval.' }}</p>
            <p v-else-if="recovery.status === 'failed'">{{ recovery.error || 'Extraction failed.' }} {{ recovery.retry_scheduled ? 'A background retry is scheduled.' : 'No automatic retry is scheduled; the worker needs attention.' }}</p>
            <p v-else>This PDF changed. Refresh to inspect its latest extraction.</p>
            <p v-if="recoveryError" role="alert">{{ recoveryError }}</p>
            <button v-if="['processed', 'processed_with_issues'].includes(recovery.status)" type="button" @click="reviewRecovered">Review extracted questions</button>
            <button v-else type="button" :disabled="recoveryChecking" @click="checkRecovery">{{ recoveryChecking ? 'Checking…' : 'Check progress' }}</button>
            <button v-if="!['pending', 'processing'].includes(recovery.status)" type="button" @click="recovery = null">Dismiss</button>
        </section>
        <nav class="review-tabs" aria-label="Review status"><button v-for="tab in tabs.slice(0, 3)" :key="tab.key" type="button" :data-status="tab.key" :disabled="saving" :aria-pressed="status === tab.key" @click="selectStatus(tab.key)">{{ tab.label }} <span>{{ counts[tab.key] || 0 }}</span></button></nav>
        <StarterBankOverview v-if="status === 'pending' && reviewSelection" :selection="reviewSelection" :scope="reviewScope" :disabled="saving || loading" @scope="selectScope" />
        <div v-if="status !== 'corrections'" class="selection-toolbar">
            <span>{{ selectionSummary }}</span>
            <button type="button" :disabled="saving" @click="filterDialog.showModal()">Filter</button>
        </div>
        <dialog ref="filterDialog" class="filter-dialog" aria-labelledby="filter-title" @click="closeFilterBackdrop">
            <header><h3 id="filter-title">Choose questions</h3><button type="button" class="filter-close" aria-label="Close filters" @click="filterDialog.close()">Close</button></header>
            <div class="filters">
                <label>Grade<select :disabled="saving" v-model="grade" @change="changeFilters"><option value="">All grades</option><option v-for="g in grades" :key="g.id" :value="g.id">{{ g.name }}</option></select></label>
                <label>Subject<select :disabled="saving" v-model="subject" @change="changeFilters"><option value="">All subjects</option><option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option></select></label>
                <label class="full-field">Topic<select :disabled="saving" :value="activeTopicKey" @change="selectTopic(reviewSelection?.topics.find(item => item.key === $event.target.value))"><option value="">All topics</option><option v-for="item in visibleTopics" :key="item.key" :value="item.key">{{ item.topic.replace(/^\d+\.\s*/, '') }} ({{ item.approved }}/{{ item.target }})</option></select></label>
                <label class="full-field">Lesson<select :disabled="saving" v-model="lesson" @change="changeLesson"><option value="">All lessons</option><option v-for="l in visibleLessons" :key="l.id" :value="l.id">{{ l.title }}</option></select></label>
            </div>
            <section v-if="status === 'pending'" class="dialog-section" aria-label="Review layout"><h4>Review layout</h4><nav class="review-tabs"><button :disabled="saving" :aria-pressed="pdfMode" @click="pdfMode = true">By PDF</button><button :disabled="saving" :aria-pressed="!pdfMode" @click="pdfMode = false">One at a time</button></nav></section>
            <section class="dialog-section" aria-label="PDF processing"><h4>PDF processing</h4><nav class="review-tabs" aria-label="Extraction status"><button v-for="tab in tabs.slice(3)" :key="tab.key" type="button" :disabled="saving" :aria-pressed="status === tab.key" @click="selectStatus(tab.key)">{{ tab.label }} <span>{{ counts[tab.key] || 0 }}</span></button></nav></section>
            <footer><button type="button" :disabled="saving" @click="resetFilters">Reset filters</button><button type="button" class="filter-done" @click="filterDialog.close()">Done</button></footer>
        </dialog>
        <p v-if="isCoverage" class="coverage-intro">{{ status === 'rejected' ? 'Rejected regions' : 'Skipped pages' }} · {{ total }} to inspect</p>
        <QuestionCorrectionPanel v-if="status === 'corrections'" @busy="saving = $event" @changed="load(true, true)" />
        <PdfQuestionReview v-else-if="status === 'pending' && pdfMode" ref="pdfReview" :grade="grade" :subject="subject" :lesson="lesson" :scope="reviewScope" :topic="topic" />
        <p v-else-if="loading" role="status">Loading the review queue…</p>
        <div v-else-if="!question && !error" class="empty"><h3>{{ isCoverage ? 'No extraction issues match these filters' : status === 'approved' ? 'No verified questions yet' : status === 'reprocessing' ? 'No extractions running' : 'No questions to review' }}</h3><p>{{ status === 'pending' ? 'New and changed questions appear here after their lesson PDFs are processed.' : 'Choose another status or change the filters.' }}</p></div>
        <template v-else-if="question">
            <div class="question-navigation"><button type="button" :disabled="saving || (index === 0 && page === 1)" @click="previous">Previous</button><span>{{ (page - 1) * 20 + index + 1 }} of {{ total }}</span><button type="button" :disabled="saving || (index === items.length - 1 && page >= lastPage)" @click="next">Next</button></div>
            <div class="review-workspace" :class="{ 'has-source': sourceUrl }">
                <article class="question-card" :class="{ 'coverage-card': isCoverage }" aria-labelledby="question-heading">
                    <div class="card-heading"><h3 id="question-heading" tabindex="-1">{{ isCoverage ? origin?.lesson_title : 'Extracted question' }}</h3><span class="status-label">{{ isCoverage ? `Page ${origin?.page} · ${origin?.label}` : statusLabel(question.status) }}</span></div>
                    <section v-if="!isCoverage" class="question-placement" aria-label="Question placement">
                        <dl class="course-details">
                            <div><dt>Grade</dt><dd>{{ questionGrade }}</dd></div>
                            <div><dt>Subject</dt><dd>{{ questionSubject }}</dd></div>
                        </dl>
                        <dl v-for="link in questionLinks" :key="link.lesson_id" class="lesson-details">
                            <div><dt>Topic</dt><dd>{{ link.topic || 'Topic unavailable' }}</dd></div>
                            <div><dt>Lesson</dt><dd>{{ link.lesson_title || 'Lesson unavailable' }}</dd></div>
                        </dl>
                        <p v-if="!questionLinks.length">Source topic and lesson unavailable.</p>
                    </section>
                    <p v-if="question.status === 'review_again'" class="notice" role="status">The extractor returned the same content. It has not been verified. Check the original again before choosing Yes.</p>
                    <p v-if="question.status === 'reprocessing'" class="notice" role="status">Reprocessing the source PDF. This question will need another review when extraction finishes.</p>
                    <p v-if="question.status === 'needs_attention'" class="notice" role="status">This question is missing from the latest extraction. It cannot be approved. Inspect the original; any newly extracted version appears separately.</p>
                    <section v-if="question.coverage" class="coverage-details" aria-label="Extraction issue">
                        <p class="coverage-reason">{{ reasonLabel(question.coverage.reason) }}</p>
                        <ReviewQuestionContent label="Unverified PDF text — not saved" end-label="End of source text">
                            <pre v-if="question.coverage.raw_text" class="coverage-text">{{ question.coverage.raw_text }}</pre>
                            <p v-else class="coverage-empty">No readable text was extracted. Check the original PDF.</p>
                        </ReviewQuestionContent>
                        <div class="coverage-actions"><button type="button" class="retry-extraction" :disabled="saving || ['pending', 'processing'].includes(recovery?.status)" @click="retryExtraction">{{ saving ? 'Queueing…' : recovery?.status === 'pending' ? 'Queued — awaiting worker' : recovery?.status === 'processing' ? 'Processing PDF…' : 'Retry extraction' }}</button><button type="button" :disabled="sourceLoading" @click="sourceUrl ? closeSource() : openSource()">{{ sourceLoading ? 'Loading PDF…' : sourceUrl ? 'Hide original PDF' : 'Compare original PDF' }}</button></div>
                        <details class="coverage-options"><summary>Extraction details</summary>
                            <p>{{ questionGrade }} · {{ questionSubject }} · {{ origin?.topic }}</p>
                            <p v-if="status === 'skipped'">{{ auditLabel(question.coverage.audit_hint) }}</p>
                            <p v-else-if="question.coverage.rect">The orange outline marks the rejected region in the original PDF.</p>
                            <p>Source text can lose layout, fractions and powers. Retry processes the whole PDF and may return the same result. Recovered questions require approval; counts represent regions/pages, not confirmed missing questions.</p>
                        </details>
                    </section>
                    <ReviewQuestionContent v-else>
                        <StructuredQuestion :content="question.content" />
                        <QuestionDiagrams v-if="question.content.diagrams?.length" :key="question.id" :question-id="question.id" :diagrams="question.content.diagrams" @ready="diagramsReady = $event" />
                    </ReviewQuestionContent>
                    <div v-if="!isCoverage" class="source-controls"><label v-if="question.origins.length > 1">Original sheet<select v-model="originIndex" :disabled="sourceLoading" @change="closeSource"><option v-for="(origin, n) in question.origins" :key="`${origin.source_id}-${origin.page}`" :value="n">{{ origin.lesson_title }} · page {{ origin.page }}</option></select></label><span v-else-if="origin" class="source-page">Original PDF · page {{ origin.page }}</span><button type="button" :disabled="sourceLoading" @click="sourceUrl ? closeSource() : openSource()">{{ sourceLoading ? 'Loading PDF…' : sourceUrl ? 'Close original PDF' : 'Compare original PDF' }}</button></div>
                    <p v-if="sourceError" class="error" role="alert">{{ sourceError }} <button type="button" @click="openSource">Retry PDF</button></p>
                    <template v-if="canDecide">
                        <details class="note-options"><summary>Describe the correction needed <span>(optional){{ note ? ' · Note added' : '' }}</span></summary><label class="review-note">What looks wrong?<textarea v-model="note" rows="2" maxlength="1000" placeholder="For example: denominator missing, wrong sign, incomplete instructions" /></label></details>
                        <div class="decision-bar"><p class="decision-prompt">Does this match the original question?</p>
                        <div class="decisions"><button type="button" class="yes" :disabled="saving || (question.content.diagrams?.length && !diagramsReady)" @click="decide(true)">{{ saving ? 'Saving…' : 'Yes, correct' }}</button><button type="button" class="no" :disabled="saving" @click="flagForCorrection">Flag for correction</button></div>
                        </div>
                    </template>
                    <p v-else-if="question.status === 'approved'" class="verified">Verified. This exact question will not be asked again.</p>
                </article>
                <aside v-if="sourceUrl" class="source-card" aria-label="Original PDF"><h3>Original PDF · page {{ origin?.page }}</h3><a :href="sourceUrl" target="_blank" rel="noopener">Open full PDF to zoom</a><PdfDocumentViewer :url="sourceUrl" :page-number="origin?.page" :focus-rect="question.coverage?.rect" /></aside>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import api from '../../api';
import QuestionReviewSaveStatus from './QuestionReviewSaveStatus.vue';
import { useQuestionReviewSaves } from '../../composables/questionReviewSaves';
const reviewSaves = useQuestionReviewSaves();
let saveRefresh;
watch(reviewSaves.result, () => { clearTimeout(saveRefresh); saveRefresh = setTimeout(() => { if (!reviewSaves.pending.value.length) load(true, true); }, 300); });
onBeforeUnmount(() => clearTimeout(saveRefresh));
async function reviewFailedSave(job) { reviewScope.value = 'all'; grade.value = ''; subject.value = ''; topic.value = ''; lesson.value = job.lessonId; status.value = 'pending'; pdfMode.value = true; page.value = 1; load(); await nextTick(); await pdfReview.value?.reload(job.sourceId); }
import StructuredQuestion from '../common/StructuredQuestion.vue';
import ReviewQuestionContent from './ReviewQuestionContent.vue';
import StarterBankOverview from './StarterBankOverview.vue';
const QuestionCorrectionPanel = defineAsyncComponent(() => import('./QuestionCorrectionPanel.vue'));
const PdfQuestionReview = defineAsyncComponent(() => import('./PdfQuestionReview.vue'));
const pdfMode = ref(true), pdfReview = ref(null);
function refresh() { load(); pdfReview.value?.reload(); }
const PdfDocumentViewer = defineAsyncComponent(() => import('../common/PdfDocumentViewer.vue'));
const QuestionDiagrams = defineAsyncComponent(() => import('./QuestionDiagrams.vue'));
const diagramsReady = ref(false);
const items = ref([]), counts = ref({}), grades = ref([]), subjects = ref([]), lessons = ref([]);
const grade = ref(''), subject = ref(''), lesson = ref(''), status = ref('pending');
const reviewScope = ref('starter'), reviewSelection = ref(null), topic = ref('');
function selectScope(value) { reviewScope.value = value; page.value = 1; load(); }
function selectTopic(item) { topic.value = item?.topic || ''; if (item) { grade.value = item.grade_id; subject.value = item.subject_id; } lesson.value = ''; page.value = 1; load(); }
function changeLesson() { topic.value = ''; page.value = 1; load(); }
const page = ref(1), lastPage = ref(1), total = ref(0), index = ref(0), originIndex = ref(0);
const loading = ref(false), saving = ref(false), error = ref(''), message = ref(''), note = ref('');
const sourceUrl = ref(''), sourceLoading = ref(false), sourceError = ref('');
const recovery = ref(null), recoveryChecking = ref(false), recoveryError = ref('');
const recoveryNotice = ref(null), filterDialog = ref(null);
function resetFilters() { grade.value = ''; subject.value = ''; topic.value = ''; lesson.value = ''; page.value = 1; load(); }
function closeFilterBackdrop(event) { if (event.target !== filterDialog.value) return; const box = filterDialog.value.getBoundingClientRect(); if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) filterDialog.value.close(); }
const queuedLong = computed(() => recovery.value?.status === 'pending' && Date.now() - Date.parse(recovery.value.requested_at || '') > 30000);
const countsUnchanged = computed(() => recovery.value && recovery.value.question_count === recovery.value.before_count
    && recovery.value.rejected_count === recovery.value.before_rejected && recovery.value.skipped_count === recovery.value.before_skipped);
const recoveryActive = computed(() => ['pending', 'processing'].includes(recovery.value?.status)
    || (recovery.value?.status === 'failed' && recovery.value.retry_scheduled));
function selectStatus(value) { status.value = value; page.value = 1; filterDialog.value?.close(); load(); }
let loadSerial = 0, sourceSerial = 0, poll;
const tabs = [{ key: 'pending', label: 'To review' }, { key: 'corrections', label: 'Needs correction' }, { key: 'approved', label: 'Verified' }, { key: 'reprocessing', label: 'Reprocessing' }, { key: 'rejected', label: 'Rejected regions' }, { key: 'skipped', label: 'Skipped pages' }];
const isCoverage = computed(() => ['rejected', 'skipped'].includes(status.value));
const question = computed(() => items.value[index.value]);
const questionGrade = computed(() => grades.value.find(g => Number(g.id) === Number(question.value?.grade_id))?.name || 'Grade unavailable');
const questionSubject = computed(() => subjects.value.find(s => Number(s.id) === Number(question.value?.subject_id))?.name || 'Subject unavailable');
const questionLinks = computed(() => [...new Map((question.value?.origins || []).map(link => [link.lesson_id, link])).values()]);
const origin = computed(() => question.value?.origins?.[originIndex.value]);
const canDecide = computed(() => question.value?.current && ['pending', 'review_again'].includes(question.value?.status));
const visibleLessons = computed(() => lessons.value.filter(l => (!grade.value || Number(l.grade_id) === Number(grade.value)) && (!subject.value || Number(l.subject_id) === Number(subject.value))));
const visibleTopics = computed(() => (reviewSelection.value?.topics || []).filter(item => (!grade.value || item.grade_id === Number(grade.value)) && (!subject.value || item.subject_id === Number(subject.value))));
const activeTopicKey = computed(() => topic.value ? visibleTopics.value.find(item => item.topic === topic.value)?.key || '' : '');
const selectionSummary = computed(() => [grades.value.find(g => Number(g.id) === Number(grade.value))?.name, subjects.value.find(s => Number(s.id) === Number(subject.value))?.name, topic.value?.replace(/^\d+\.\s*/, '') || 'All topics', lessons.value.find(l => Number(l.id) === Number(lesson.value))?.title].filter(Boolean).join(' / '));
const statusLabel = value => ({ pending: 'Needs review', approved: 'Verified', reprocessing: 'Reprocessing', review_again: 'Review again', needs_attention: 'Needs attention', rejected: 'Not extracted', skipped: 'Page inspection needed' })[value] || value;
const reasonLabel = value => ({ unsupported_multiline_maths: 'The maths spans multiple lines the extractor cannot interpret safely.', ambiguous_superscript: 'The position or meaning of a power is unclear.', unsupported_text_or_symbol: 'Some text or symbols are not supported by the extractor.', ambiguous_fraction: 'The numerator and denominator could not be identified reliably.', empty_question: 'No usable question content was recognised.', no_exercise_heading: 'No recognised exercise heading was found.', mixed_revision_exercise: 'The page contains a mixed revision exercise excluded by the current rules.', no_question_boundaries: 'The extractor could not find reliable question boundaries.', worked_examples_or_revision: 'The page matched the worked-example or revision exclusion rule.', worked_examples_or_solutions: 'The page matched the worked-example or solution exclusion rule.', ambiguous_columns: 'The page columns could not be separated reliably.', invalid_regions: 'The detected question regions were not usable.', unsupported_list_maths: 'This question contains a list of expressions that the extractor cannot represent safely yet.', continuation_not_confirmed: 'This page could not be safely matched to the preceding exercise.', new_instructions_without_heading: 'New instructions were detected without a recognised exercise heading.' })[value] || String(value).replaceAll('_', ' ');
const auditLabel = value => ({ question_markers_found: 'Question labels were detected. Check this page for missing questions.', exercise_heading_found: 'An exercise heading was detected. Check the full page for questions.', examples_or_revision: 'Example, solution or revision text was detected. The page still needs inspection.', no_readable_text: 'No readable PDF text was found. The page may need OCR.', possible_cover: 'This may be a cover or heading page. Confirm using the original.', inspection_required: 'The page has not been classified. Inspect the original.' })[value] || 'Inspect the original page.';

async function load(silent = false, correctionChanged = false) {
    if (status.value === 'corrections' && !correctionChanged) return;
    const serial = ++loadSerial, previousId = question.value?.id;
    if (!silent) loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get('/admin/question-reviews', { params: { grade_id: grade.value || undefined, subject_id: subject.value || undefined, lesson_id: lesson.value || undefined, status: status.value === 'corrections' ? 'pending' : status.value, page: page.value, review_scope: reviewScope.value, topic: topic.value || undefined } });
        if (serial !== loadSerial) return;
        reviewSelection.value = data.selection || null; items.value = data.data; counts.value = data.counts; grades.value = data.grades; subjects.value = data.subjects; lessons.value = data.lessons;
        total.value = data.total; lastPage.value = data.last_page;
        index.value = silent ? Math.max(0, items.value.findIndex(q => q.id === previousId)) : 0;
        if (page.value > lastPage.value) { page.value = lastPage.value; return load(); }
    } catch (e) { if (serial === loadSerial) error.value = e.response?.data?.message || 'Could not load questions. Try again.'; }
    finally { if (serial === loadSerial) loading.value = false; }
}
function changeFilters() { topic.value = ''; lesson.value = ''; page.value = 1; load(); }
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
    if (approved && question.value.content.diagrams?.length && !diagramsReady.value) return;
    saving.value = true; error.value = ''; message.value = '';
    try {
        await api.put(`/admin/question-reviews/${question.value.id}`, { approved, revision: question.value.revision, note: note.value || null });
        message.value = approved ? 'Verified. This exact question is approved once.' : 'Reprocessing requested. The result will need another review; it will not be approved automatically.';
        await load(); await nextTick(); document.getElementById('question-heading')?.focus();
    } catch (e) { error.value = e.response?.data?.message || 'Could not save your review. Try again.'; }
    finally { saving.value = false; }
}
async function flagForCorrection() {
    if (saving.value || !canDecide.value) return;
    saving.value = true; error.value = ''; message.value = '';
    try {
        await api.post(`/admin/question-reviews/${question.value.id}/flag`, { revision: question.value.revision, reason: note.value || null });
        message.value = 'Flag saved. Open Needs correction to edit it.';
        await load();
    } catch (e) { error.value = e.response?.data?.message || 'Could not save the flag. Try again.'; }
    finally { saving.value = false; }
}
async function retryExtraction() {
    if (saving.value || !question.value?.coverage || ['pending', 'processing'].includes(recovery.value?.status)) return;
    saving.value = true; error.value = ''; message.value = ''; recoveryError.value = '';
    try {
        const { data } = await api.post(`/admin/question-reviews/issues/${question.value.id}/retry`, { revision: question.value.coverage.source_revision });
        recovery.value = data;
        saving.value = false;
        await nextTick();
        recoveryNotice.value?.focus({ preventScroll: true });
        recoveryNotice.value?.scrollIntoView({ block: 'start' });
        await load();
        await checkRecovery();
    } catch (e) { error.value = e.response?.data?.message || 'Could not queue extraction. Try again.'; }
    finally { saving.value = false; }
}
async function checkRecovery() {
    if (!recovery.value || recoveryChecking.value) return;
    const job = recovery.value;
    recoveryChecking.value = true; recoveryError.value = '';
    try {
        const { data } = await api.get(`/admin/lessons/${job.lesson_id}/question-bank`);
        if (recovery.value?.lesson_id !== job.lesson_id || recovery.value?.revision !== job.revision) return;
        const source = data.sources.find(s => s.slot === job.slot);
        if (!source || source.revision > job.revision) recovery.value = { ...job, status: 'superseded' };
        else if (source.revision === job.revision) {
            recovery.value = { ...job, ...source };
            if (['processed', 'processed_with_issues'].includes(source.status) && !['processed', 'processed_with_issues'].includes(job.status)) await load();
        }
    } catch { recoveryError.value = 'Progress could not be checked. Try again.'; }
    finally { recoveryChecking.value = false; }
}
function reviewRecovered() { topic.value = ''; reviewScope.value = 'all'; grade.value = ''; subject.value = ''; lesson.value = recovery.value.lesson_id; status.value = 'pending'; page.value = 1; load(); }
function previous() { if (index.value > 0) index.value--; else if (page.value > 1) { page.value--; load(); } }
function next() { if (index.value < items.value.length - 1) index.value++; else if (page.value < lastPage.value) { page.value++; load(); } }
watch(() => question.value?.id, () => { closeSource(); note.value = ''; originIndex.value = 0; diagramsReady.value = false; if (isCoverage.value && question.value) openSource(); });
watch(recovery, job => {
    try { if (job && recoveryActive.value) sessionStorage.setItem('question-extraction-recovery', JSON.stringify(job)); else sessionStorage.removeItem('question-extraction-recovery'); } catch { /* Status remains available in the current view. */ }
});
onMounted(() => {
    try {
        const job = JSON.parse(sessionStorage.getItem('question-extraction-recovery') || 'null');
        if (job && Number.isInteger(job.lesson_id) && Number.isInteger(job.revision) && ['question', 'question2'].includes(job.slot)) recovery.value = job;
    } catch { /* Ignore an invalid browser draft. */ }
    load(); if (recoveryActive.value) checkRecovery();
    poll = setInterval(() => { if (recoveryActive.value) checkRecovery(); if (counts.value.reprocessing && !saving.value && !loading.value) load(true); }, 5000);
});
onBeforeUnmount(() => { loadSerial++; clearInterval(poll); closeSource(); });
</script>

<style scoped>
.selection-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:8px 0; }.selection-toolbar>span { min-width:0; font-size:.8rem; color:var(--app-muted); overflow-wrap:anywhere; }.selection-toolbar>button { flex-shrink:0; min-height:44px; }
.filter-dialog { box-sizing:border-box; width:min(520px,calc(100% - 24px)); max-height:calc(100dvh - 32px); margin:auto; padding:20px; border:1px solid var(--app-border); border-radius:14px; background:var(--app-panel); color:var(--app-text); overflow:auto; }.filter-dialog::backdrop { background:rgba(0,0,0,.65); }.filter-dialog>header,.filter-dialog>footer { display:flex; align-items:center; justify-content:space-between; gap:12px; }.filter-dialog h3 { margin:0; font-size:1.1rem; }.filter-dialog .filter-close { flex-shrink:0; min-height:44px; padding:8px 12px; font-size:.85rem; color:var(--app-muted); background:transparent; }.filter-dialog .filter-close:hover { color:var(--app-text); background:var(--app-inset); }.filter-dialog .filters { margin:16px 0; grid-template-columns:repeat(2,minmax(0,1fr)); }.filter-dialog .full-field { grid-column:1 / -1; }.dialog-section { margin-top:16px; }.dialog-section h4 { margin:0; font-size:.8rem; color:var(--app-muted); }.filter-dialog>footer { border-top:1px solid var(--app-border); padding-top:16px; margin-top:20px; }.filter-done { background:var(--app-accent); color:#101827; min-width:90px; }

.review-panel { min-width:0; color:var(--app-text); }
.review-tabs[aria-label="Review status"] { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); max-width:600px; }
.review-tabs[aria-label="Review status"] button { min-width:0; min-height:44px; padding:6px 8px; font-size:.85rem; }
.review-tabs[aria-label="Review status"] [data-status="pending"] { --tab-tone:#71b4f8; }
.review-tabs[aria-label="Review status"] [data-status="corrections"] { --tab-tone:#eab568; }
.review-tabs[aria-label="Review status"] [data-status="approved"] { --tab-tone:#65c99b; }
.review-tabs[aria-label="Review status"] button { color:var(--app-text); border-color:color-mix(in srgb,var(--tab-tone) 30%,var(--app-border)); background:linear-gradient(135deg,color-mix(in srgb,var(--tab-tone) 12%,transparent),transparent),var(--app-panel); transition:background .15s,border-color .15s; }
.review-tabs[aria-label="Review status"] button:hover { border-color:color-mix(in srgb,var(--tab-tone) 65%,var(--app-border)); }
.review-tabs[aria-label="Review status"] button[aria-pressed="true"] { color:var(--app-text); border-color:color-mix(in srgb,var(--tab-tone) 70%,var(--app-border)); background:linear-gradient(135deg,color-mix(in srgb,var(--tab-tone) 26%,transparent),color-mix(in srgb,var(--tab-tone) 8%,transparent)),var(--app-panel); box-shadow:inset 0 -2px 0 color-mix(in srgb,var(--tab-tone) 70%,transparent); }
.review-tabs[aria-label="Review status"] span { display:inline-block; padding:1px 5px; border-radius:5px; background:color-mix(in srgb,var(--tab-tone) 14%,transparent); }
@media(forced-colors:active) { .review-tabs[aria-label="Review status"] button[aria-pressed="true"] { outline:2px solid Highlight; } }

.review-header { align-items:center; }
.filters select { box-sizing:border-box; max-width:100%; min-height:44px; }
.coverage-intro { margin:6px 0; font-size:.85rem; color:var(--app-muted); }
.question-card.coverage-card { padding:0; border:0; background:none; }
.coverage-reason { color:var(--app-muted); font-size:.9rem; margin:6px 0; }
.coverage-text { max-height:240px; overflow:auto; white-space:pre-wrap; overflow-wrap:anywhere; background:transparent; color:inherit; font:inherit; padding:0; margin:0; }
.coverage-empty { margin:0; }
.coverage-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.coverage-actions button { min-height:44px; padding:8px 12px; font-size:.85rem; }
.coverage-actions .retry-extraction:disabled { cursor:default; }
.coverage-options { font-size:.8rem; color:var(--app-muted); margin-top:8px; }
.coverage-options summary { font-size:.8rem; min-height:30px; }
.recovery-status { scroll-margin-top:110px; }
.recovery-status button { padding:8px 10px; margin:4px 6px 0 0; font-size:.85rem; min-height:44px; }
.recovery-status p { margin:6px 0 10px; }.retry-extraction { min-height:44px; border-color:var(--app-accent); }
.coverage-details { line-height:1.6; }.coverage-details p { margin:8px 0; }.coverage-limit { color:var(--app-muted); font-size:.9rem; }.raw-text pre { white-space:pre-wrap; overflow-wrap:anywhere; max-height:240px; overflow:auto; font-size:.9rem; background:var(--app-inset); color:var(--app-text); padding:10px; }
.question-placement { padding:10px 12px; margin-bottom:14px; border:1px solid var(--app-border); border-radius:10px; background:var(--app-inset); }
.course-details,.lesson-details { display:flex; flex-wrap:wrap; gap:4px 20px; margin:0; font-size:.9rem; }
.course-details > div,.lesson-details > div { display:flex; gap:6px; min-width:0; }
.question-placement dt { color:var(--app-muted); flex-shrink:0; }.question-placement dd { margin:0; overflow-wrap:anywhere; min-width:0; }
.lesson-details { margin-top:6px; }.lesson-details + .lesson-details { border-top:1px solid var(--app-border); margin-top:8px; padding-top:8px; }
.review-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:12px; }
.review-header h2 { font-size:1.4rem; margin:0 0 4px; }.review-header p { margin:0; line-height:1.45; }.review-header > div > p:last-child { font-size:.9rem; }.eyebrow { color:var(--app-accent); font-size:.7rem; text-transform:uppercase; letter-spacing:.08em; }
summary { cursor:pointer; min-height:36px; padding:6px 0; line-height:1.5; font-size:.9rem; overflow-wrap:anywhere; }summary span { color:var(--app-muted); font-size:.8rem; margin-left:6px; }summary:focus-visible { outline:3px solid var(--app-accent); outline-offset:2px; }
button,select,textarea { font:inherit; }button { cursor:pointer; border:1px solid var(--app-border); border-radius:10px; padding:10px 15px; background:var(--app-panel); color:var(--app-text); }button:disabled { cursor:wait; opacity:.55; }button:focus-visible,select:focus-visible,textarea:focus-visible { outline:3px solid var(--app-accent); outline-offset:2px; }
.filters { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }.filters label,.source-controls label,.review-note { display:flex; flex-direction:column; gap:7px; font-size:.85rem; min-width:0; }select,textarea { width:100%; min-width:0; color:var(--app-text); background:var(--app-panel); border:1px solid var(--app-border); border-radius:9px; padding:10px; }option { background:var(--app-bg,#111827); }
.review-tabs { display:flex; gap:6px; margin:8px 0; flex-wrap:wrap; }.review-tabs button { padding:8px 12px; }.review-tabs button[aria-pressed=true] { border-color:var(--app-accent); color:var(--app-accent); }.review-tabs span { margin-left:5px; font-variant-numeric:tabular-nums; }
.feedback,.notice,.error { padding:12px 15px; border-radius:10px; margin:12px 0; line-height:1.55; }.feedback { background:rgba(34,197,94,.12); }.notice { background:rgba(245,158,11,.12); }.error { background:rgba(239,68,68,.12); }
.question-navigation { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:8px 0; }.question-navigation span { font-size:.85rem; }
.review-workspace { display:grid; grid-template-columns:minmax(0,1fr); gap:18px; }.review-workspace.has-source { grid-template-columns:minmax(0,1fr) minmax(0,1fr); }
.question-card,.source-card,.empty { min-width:0; background:var(--app-glass); border:1px solid var(--app-border); border-radius:16px; padding:16px; }.card-heading { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:12px; }.card-heading h3,.source-card h3 { margin:0; font-size:1.05rem; }.status-label { font-size:.8rem; color:var(--app-accent); }.source-controls { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:12px 0; flex-wrap:wrap; }.source-controls label { flex:1 1 180px; }.source-page { font-size:.85rem; color:var(--app-muted); }.source-card { min-height:450px; max-height:720px; overflow:hidden; }.source-card h3 { margin-bottom:15px; }.source-card :deep(.pdf-document-viewer) { height:calc(100% - 40px); max-height:640px; }
.question-card :deep(.instructions),.question-card :deep(.question-text) { margin-bottom:10px; }.question-card :deep(.question-maths) { padding:10px 4px; }
.review-note { margin:8px 0 12px; }.decision-bar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:10px; }.decision-prompt { font-weight:600; font-size:.9rem; margin:0; }.decisions { display:flex; gap:8px; flex-wrap:wrap; }.decisions button { min-width:130px; min-height:44px; }.yes { background:#166534; color:white; border-color:#166534; }.no { border-color:#f59e0b; }.verified { color:#4ade80; }
@media(max-width:1000px) { .review-workspace.has-source { grid-template-columns:minmax(0,1fr); } }
@media(max-width:580px) { .review-header h2 { font-size:1.25rem; }.review-header > button { padding:8px 10px; }.filters { grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }.filters > label:last-child { grid-column:1 / -1; }.question-card,.source-card { padding:12px; }.decisions { width:100%; }.decisions button { flex:1; min-width:0; }.review-tabs { gap:5px; }.review-tabs button { padding:9px 7px; font-size:.8rem; }.source-card { min-height:330px; max-height:550px; }select,textarea { font-size:16px; } }
</style>
