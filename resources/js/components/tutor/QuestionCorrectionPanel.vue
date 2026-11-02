<template>
    <section class="corrections" :class="{ 'has-source': sourceItem }" aria-label="Needs correction">
        <p v-if="message" ref="notice" tabindex="-1" role="status" class="feedback">{{ message }}</p>
        <p v-if="error" role="alert" class="error">{{ error }} <button @click="load">Retry</button></p>
        <div class="toolbar queue-toolbar"><label>Show<select v-model="status" :disabled="saving" @change="page = 1; load()"><option value="open">Open corrections</option><option value="resolved">Resolved</option><option value="all">All</option></select></label><span class="queue-count">{{ total }} corrections</span><button :disabled="loading || saving" @click="load">Refresh corrections</button></div>
        <p v-if="loading" role="status">Loading corrections…</p>
        <p v-else-if="!items.length">No corrections match this view.</p>
        <template v-else>
            <div v-if="lastPage > 1" class="toolbar pagination"><button :disabled="saving || page === 1" @click="page--; load()">Previous</button><span>Page {{ page }} of {{ lastPage }}</span><button :disabled="saving || page >= lastPage" @click="page++; load()">Next</button></div>
            <article v-for="item in items" :key="item.id" class="correction" :data-correction-id="item.id">
                <header class="correction-heading"><h3 :id="`correction-${item.id}`" tabindex="-1">{{ item.origins[0]?.lesson_title }} · page {{ item.origins[0]?.page }} · {{ item.origins[0]?.label }}</h3><span class="status-badge">{{ labels[item.status] || item.status }}</span></header>
                <p v-if="item.outcome" class="outcome">{{ outcomes[item.outcome] }}</p>
                <p v-if="item.reason"><strong>Reported problem:</strong> {{ item.reason }}</p>
                <ReviewQuestionContent v-if="editing !== item.id"><StructuredQuestion :content="item.content" /><QuestionDiagrams v-if="item.content.diagrams?.length" :question-id="item.replacement_id || item.id" :diagrams="item.content.diagrams" @ready="diagramsReady[item.id] = $event" /></ReviewQuestionContent>
                <div class="correction-actions">
                    <button :disabled="saving" :aria-expanded="sourceItem === item.id" aria-controls="correction-original-panel" @click="sourceItem === item.id ? closeSource(true) : openOriginal(item, $event)">{{ sourceItem === item.id ? 'Close original PDF' : 'Compare original PDF' }}</button>
                    <button v-if="editing !== item.id && ['queued', 'needs_manual', 'review_needed'].includes(item.status)" :disabled="saving" @click="startEdit(item)">Edit question</button>
                    <button v-if="item.approval && editing !== item.id" class="approve-correction" :disabled="saving || (item.content.diagrams?.length && !diagramsReady[item.id])" @click="approve(item)">{{ saving ? 'Saving...' : 'Approve correction' }}</button>
                </div>
                <p v-if="item.status === 'stale'">The source changed. Review its latest extraction instead.</p>
                <form v-if="editing === item.id" class="editor" @submit.prevent="save(item, true)">
                    <div class="editor-workspace">
                        <div class="editor-fields">
                            <label>Instructions<textarea v-model="draft.instructions" maxlength="5000" rows="2" /></label>
                            <nav v-if="draft.math" class="writing-modes" aria-label="Editing mode"><button type="button" :aria-pressed="format === 'text'" @click="setFormat('text')">Text &amp; maths</button><button type="button" :aria-pressed="format === 'math'" @click="setFormat('math')">Formula layout</button></nav>
                            <div v-if="format === 'text'" class="writing-editor">
                                <div class="writing-symbols" aria-label="Insert maths symbol"><button v-for="symbol in writingSymbols" :key="symbol.value" type="button" :aria-label="symbol.label" @click="insertSymbol(symbol.value)">{{ symbol.value }}</button></div>
                                <label>Question<textarea :ref="setWritingInput" v-model="draft.text" maxlength="15000" rows="5" required @input="writingEdited = true" /></label>
                            </div>
                            <MathExpressionEditor v-else v-model="draft.math" />
                        </div>
                        <div class="editor-preview"><ReviewQuestionContent label="Correction preview"><StructuredQuestion :content="preview" /><QuestionDiagrams v-if="item.content.diagrams?.length" :question-id="item.replacement_id || item.id" :diagrams="item.content.diagrams" @ready="diagramsReady[item.id] = $event" /></ReviewQuestionContent></div>
                    </div>
                    <p v-if="item.content.diagrams?.length">Original diagram attachments are retained. If the diagram itself is wrong, replace the source PDF and re-extract it.</p>
                    <div class="editor-footer"><div class="correction-actions"><button type="button" :disabled="saving" @click="editing = ''">Cancel</button><button type="button" :disabled="saving" @click="save(item, false)">Save only</button><button class="save-correction" :disabled="saving || (item.content.diagrams?.length && !diagramsReady[item.id])" type="submit">{{ saving ? 'Saving…' : 'Save & approve' }}</button></div></div>
                </form>
            </article>
        </template>
        <aside v-if="sourceItem" id="correction-original-panel" class="source-panel" aria-label="Original PDF" :aria-busy="sourceLoading">
            <header><h3 ref="sourceHeading" tabindex="-1">Original PDF</h3><button type="button" @click="closeSource(true)">Close</button></header>
            <p class="source-caption">{{ sourceOrigin?.lesson_title }} · page {{ sourceOrigin?.page }}</p>
            <p v-if="sourceLoading" role="status">Loading PDF…</p>
            <p v-if="sourceError" role="alert">{{ sourceError }} <button type="button" @click="loadOriginal">Retry PDF</button></p>
            <div v-if="sourceUrl" class="source-document"><a :href="sourceUrl" target="_blank" rel="noopener">Open full PDF</a><PdfDocumentViewer :url="sourceUrl" :page-number="sourceOrigin?.page" /></div>
        </aside>
    </section>
</template>
<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import api from '../../api';
import StructuredQuestion from '../common/StructuredQuestion.vue';
import ReviewQuestionContent from './ReviewQuestionContent.vue';
import MathExpressionEditor from './MathExpressionEditor.vue';
import { mathToWriting, needsFormulaLayout } from '../../composables/questionWriting.mjs';
const PdfDocumentViewer = defineAsyncComponent(() => import('../common/PdfDocumentViewer.vue'));
const emit = defineEmits(['changed', 'busy']);
const QuestionDiagrams = defineAsyncComponent(() => import('./QuestionDiagrams.vue'));
const diagramsReady = ref({});
const items = ref([]), status = ref('open'), page = ref(1), lastPage = ref(1), total = ref(0);
const loading = ref(false), saving = ref(false), error = ref(''), message = ref(''), notice = ref(null), editing = ref('');
const draft = ref({}), format = ref('text');
const writingInput = ref(null), writingEdited = ref(false);
const writingSymbols = [{ value: '+', label: 'Insert plus' }, { value: '−', label: 'Insert minus' }, { value: '×', label: 'Insert multiplication sign' }, { value: '÷', label: 'Insert division sign' }, { value: '²', label: 'Insert squared' }, { value: '√', label: 'Insert square root' }];
const sourceUrl = ref(''), sourceItem = ref(''), sourceError = ref(''), sourceLoading = ref(false), sourceOrigin = ref(null), sourceHeading = ref(null);
let serial = 0, sourceSerial = 0, sourceReturnFocus = null;
const labels = { queued: 'Manual correction needed', processing: 'Background processing', needs_manual: 'Manual correction needed', review_needed: 'Ready to approve', resolved: 'Resolved and approved', stale: 'Source changed' };
const outcomes = { unchanged: 'Extraction returned the same content. Correct it manually.', not_recovered: 'Automatic extraction could not recover this question.', changed: 'A different extracted version is available. Check it before approval.', manual_correction: 'A tutor saved a correction. Approval is still required.', source_changed: 'The flagged PDF version is no longer current.' };
const preview = computed(() => ({ instructions: draft.value.instructions, ...(format.value === 'text' ? { text: draft.value.text } : { math: draft.value.math }) }));
function closeSource(restoreFocus = false) {
    sourceSerial++; if (sourceUrl.value) URL.revokeObjectURL(sourceUrl.value);
    sourceUrl.value = ''; sourceItem.value = ''; sourceError.value = ''; sourceLoading.value = false; sourceOrigin.value = null;
    if (restoreFocus) nextTick(() => { if (sourceReturnFocus?.isConnected) sourceReturnFocus.focus(); });
}
function closeSourceOnEscape(event) {
    if (event.key === 'Escape' && sourceItem.value && !document.querySelector('dialog[open]')) { event.preventDefault(); event.stopPropagation(); closeSource(true); }
}
async function load() {
    const request = ++serial; loading.value = true; error.value = ''; editing.value = ''; diagramsReady.value = {}; closeSource();
    try { const { data } = await api.get('/admin/question-corrections', { params: { page: page.value, status: status.value } }); if (request !== serial) return;
        items.value = data.data; total.value = data.total; lastPage.value = data.last_page;
        if (page.value > lastPage.value) { page.value = lastPage.value; await load(); }
    } catch (e) { if (request === serial) error.value = e.response?.data?.message || 'Could not load corrections.'; }
    finally { if (request === serial) loading.value = false; }
}
function startEdit(item) {
    editing.value = item.id; writingEdited.value = false;
    const math = item.content.math ? JSON.parse(JSON.stringify(item.content.math)) : null;
    let text = item.content.text || '';
    try { if (math) text = mathToWriting(math); } catch { /* Keep unsupported formula nodes intact in Formula layout. */ }
    draft.value = { instructions: item.content.instructions || '', text, math };
    format.value = math && needsFormulaLayout(math) ? 'math' : 'text';
}
function setFormat(value) {
    error.value = '';
    if (value === 'text' && draft.value.math && !writingEdited.value) {
        try { draft.value.text = mathToWriting(draft.value.math); } catch { error.value = 'Enter the question text to replace this unsupported formula.'; }
    }
    format.value = value;
}
function setWritingInput(element) { writingInput.value = element; }
async function insertSymbol(symbol) {
    const input = writingInput.value, start = input?.selectionStart ?? draft.value.text.length, end = input?.selectionEnd ?? start;
    const text = draft.value.text.slice(0, start) + symbol + draft.value.text.slice(end);
    if (text.length > 15000) return;
    draft.value.text = text;
    writingEdited.value = true; await nextTick(); input?.focus(); input?.setSelectionRange(start + symbol.length, start + symbol.length);
}
async function save(item, approveNow = false) {
    if (saving.value || (approveNow && item.content.diagrams?.length && !diagramsReady.value[item.id])) return;
    const position = items.value.findIndex(row => row.id === item.id);
    saving.value = true; emit('busy', true); error.value = '';
    try { await api.put(`/admin/question-corrections/${item.id}`, { revision: item.revision, content: preview.value, approve: approveNow });
        message.value = approveNow ? 'Correction saved and verified.' : 'Correction saved. You can approve it here.';
        emit('changed'); await load(); await focusCorrection(approveNow ? items.value[Math.min(position, items.value.length - 1)]?.id : item.id);
    } catch (e) { error.value = e.response?.data?.message || 'Could not save the correction.'; }
    finally { saving.value = false; emit('busy', false); }
}
async function focusCorrection(id) {
    await nextTick();
    const target = document.getElementById(`correction-${id}`) || notice.value;
    target?.focus({ preventScroll: true }); target?.scrollIntoView({ block: 'start' });
}
async function approve(item) {
    if (saving.value || !item.approval || (item.content.diagrams?.length && !diagramsReady.value[item.id])) return;
    const position = items.value.findIndex(row => row.id === item.id);
    saving.value = true; emit('busy', true); error.value = '';
    try {
        await api.put(`/admin/question-reviews/${item.approval.id}`, { approved: true, revision: item.approval.revision });
        message.value = 'Correction approved and verified.';
        emit('changed'); await load();
        await focusCorrection(items.value[Math.min(position, items.value.length - 1)]?.id);
    } catch (e) { error.value = e.response?.data?.message || 'Could not approve the correction. Refresh and try again.'; }
    finally { saving.value = false; emit('busy', false); }
}
async function openOriginal(item, event) {
    closeSource(); sourceReturnFocus = event?.currentTarget || document.activeElement;
    sourceItem.value = item.id; sourceOrigin.value = item.origins[0];
    const version = sourceSerial;
    await nextTick(); if (version !== sourceSerial) return;
    sourceHeading.value?.focus({ preventScroll: true });
    if (window.matchMedia('(max-width:900px)').matches) {
        const card = sourceReturnFocus?.closest('.correction');
        (card?.querySelector('.editor') || card?.querySelector('h3'))?.scrollIntoView({ block: 'start' });
    }
    await loadOriginal();
}
async function loadOriginal() {
    if (!sourceOrigin.value) return;
    const request = ++sourceSerial, origin = sourceOrigin.value;
    sourceLoading.value = true; sourceError.value = '';
    try { const { data } = await api.get(`/lessons/${origin.lesson_id}/pdf/${origin.slot}`, { responseType: 'blob', timeout: 60000 }); if (request === sourceSerial) sourceUrl.value = URL.createObjectURL(data); }
    catch { if (request === sourceSerial) sourceError.value = 'Could not load the original PDF.'; }
    finally { if (request === sourceSerial) sourceLoading.value = false; }
}
onMounted(() => { load(); window.addEventListener('keydown', closeSourceOnEscape); });
onBeforeUnmount(() => { serial++; closeSource(); window.removeEventListener('keydown', closeSourceOnEscape); });
</script>
<style scoped>
.corrections { --source-width:min(43vw,600px); box-sizing:border-box; }.corrections.has-source { padding-right:calc(var(--source-width) + 24px); }.has-source .editor-workspace { grid-template-columns:minmax(0,1fr); }.has-source .editor-preview { position:static; }
.has-source .editor { scroll-margin-top:110px; }
.source-panel { box-sizing:border-box; position:fixed; top:110px; right:16px; bottom:16px; width:var(--source-width); min-width:0; display:flex; flex-direction:column; gap:8px; padding:12px; border:1px solid var(--app-border); border-radius:12px; background:var(--app-panel); box-shadow:0 10px 35px rgba(0,0,0,.3); z-index:40; }.source-panel>header { display:flex; justify-content:space-between; align-items:center; gap:12px; }.source-caption { margin:0; font-size:.8rem; color:var(--app-muted); overflow-wrap:anywhere; }.source-document { min-height:0; min-width:0; flex:1; display:flex; flex-direction:column; gap:8px; }.source-document>a { font-size:.8rem; flex-shrink:0; }.source-document :deep(.pdf-document-viewer) { flex:1; min-height:0; height:100%; }
@media(max-width:900px) { .corrections.has-source { padding-right:0; padding-bottom:calc(min(50dvh,420px) + 24px); }.source-panel { top:auto; right:12px; bottom:12px; width:calc(100% - 24px); height:min(50dvh,420px); } }
.writing-modes,.writing-symbols { display:flex; flex-wrap:wrap; gap:6px; }.writing-modes button[aria-pressed=true] { border-color:var(--app-accent); background:rgba(96,165,250,.12); }.writing-symbols { margin-bottom:8px; }.writing-symbols button { min-width:44px; font-size:1.15rem; padding:6px 10px; }
.corrections,.correction,.editor,.editor-fields,.editor-preview { min-width:0; }
.correction { border-top:1px solid var(--app-border); padding:18px 0; margin:8px 0; overflow-wrap:anywhere; }.correction-heading h3 { scroll-margin-top:110px; }.correction-heading { display:flex; justify-content:space-between; align-items:center; gap:8px 16px; flex-wrap:wrap; margin-bottom:10px; }h3 { font-size:1rem; margin:0; }.status-badge { font-size:.78rem; padding:5px 9px; border:1px solid var(--app-border); border-radius:6px; color:var(--app-muted); }.outcome { color:var(--app-muted); font-size:.9rem; line-height:1.5; }
.toolbar { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin:10px 0; }.queue-toolbar { align-items:flex-end; }.queue-toolbar>label { flex:0 1 260px; }.queue-count { font-size:.8rem; color:var(--app-muted); align-self:center; }.queue-toolbar>button { margin-left:auto; }.pagination { justify-content:space-between; }
label { display:grid; gap:6px; min-width:0; font-size:.85rem; }button,select,textarea { box-sizing:border-box; font:inherit; background:var(--app-panel); color:var(--app-text); border:1px solid var(--app-border); border-radius:8px; padding:10px; }button { min-height:44px; cursor:pointer; margin:0; font-size:.85rem; }button:disabled { opacity:.55; cursor:default; }textarea,select { display:block; width:100%; max-width:100%; min-width:0; min-height:44px; }textarea { resize:vertical; }button:focus-visible,textarea:focus-visible,select:focus-visible,summary:focus-visible { outline:3px solid var(--app-accent); outline-offset:2px; }
.correction-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }.editor { padding-top:16px; margin-top:16px; border-top:1px solid var(--app-border); }.editor-workspace { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); align-items:start; gap:24px; }.editor-fields { display:grid; gap:14px; }.format-field { max-width:320px; }.editor-preview { position:sticky; top:110px; }.editor-preview :deep(.review-question-content) { margin:0; }.editor-footer { display:flex; align-items:center; justify-content:space-between; gap:10px 20px; flex-wrap:wrap; margin-top:20px; border-top:1px solid var(--app-border); padding-top:14px; }.editor-footer p { font-size:.8rem; color:var(--app-muted); margin:0; }.save-correction,.approve-correction { background:#166534; color:white; border-color:#166534; }
.feedback,.error { padding:12px; scroll-margin-top:110px; line-height:1.6; }.feedback { background:rgba(34,197,94,.12); }.error { background:rgba(239,68,68,.12); }
@media(max-width:900px) { .editor-workspace { grid-template-columns:minmax(0,1fr); gap:18px; }.editor-preview { position:static; } }
@media(max-width:500px) { .queue-toolbar>label { flex:1 1 100%; }.queue-toolbar>button { margin-left:0; }.queue-count { flex:1; }.format-field { max-width:none; }.editor-footer,.editor-footer .correction-actions { width:100%; }.editor-footer .correction-actions>button { flex:1 1 100px; }textarea,select { font-size:16px; } }
</style>
