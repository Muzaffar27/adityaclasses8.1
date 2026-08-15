<template>
    <section v-if="lesson.has_question_pdf || lesson.has_answer_pdf" class="lesson-resources">
        <div class="resources-heading">
            <p class="resources-label">Lesson practice</p>
            <p class="resources-help">Try the questions first, then reveal the answers when you're ready.</p>
        </div>
        <div class="resource-list">
            <button v-if="lesson.has_question_pdf" type="button" class="resource-button question-button"
                :disabled="Boolean(loadingType)" @click.stop="openPdf('question')">
                <span class="resource-icon"><DocumentTextIcon /></span>
                <span class="resource-copy">
                    <strong>{{ loadingType === 'question' ? 'Loading questions...' : 'Questions' }}</strong>
                    <small>Open the lesson question sheet</small>
                </span>
                <ChevronRightIcon class="resource-arrow" />
            </button>
            <button v-if="lesson.has_answer_pdf" type="button" class="resource-button answer-button"
                :disabled="Boolean(loadingType)" @click.stop="toggleAnswer">
                <span class="resource-icon"><EyeIcon /></span>
                <span class="resource-copy">
                    <strong>{{ loadingType === 'answer' ? 'Loading answer...' : 'Reveal answer' }}</strong>
                    <small>Hidden until you choose to view it</small>
                </span>
                <ChevronRightIcon class="resource-arrow" />
            </button>
        </div>

        <Teleport to="body">
            <div v-if="viewerUrl" class="pdf-screen" role="dialog" aria-modal="true"
                :aria-label="viewerType === 'answer' ? 'Answer PDF' : 'Question PDF'">
                <header class="pdf-screen-header">
                    <button ref="backButton" type="button" class="pdf-back-button" @click.stop="closeViewer">
                        <ArrowLeftIcon />
                        <span>Back to lesson video</span>
                    </button>
                    <div class="pdf-screen-title">
                        <strong>{{ viewerType === 'answer' ? 'Answer PDF' : 'Question PDF' }}</strong>
                        <small>{{ lesson.title }}</small>
                    </div>
                    <div class="pdf-actions">
                        <span class="pdf-kind" :class="viewerType">
                            {{ viewerType === 'answer' ? 'Answer revealed' : 'Questions' }}
                        </span>
                        <button v-if="otherPdfAvailable" type="button" class="pdf-switch-button"
                            :disabled="Boolean(loadingType)" @click.stop="openPdf(otherPdfType)">
                            <ArrowsRightLeftIcon />
                            {{ loadingType ? 'Loading...' : `Go to ${otherPdfType === 'answer' ? 'answer' : 'questions'}` }}
                        </button>
                    </div>
                </header>
                <main class="pdf-screen-body">
                    <iframe :src="`${viewerUrl}#toolbar=0&navpanes=0&view=FitH`"
                        :title="viewerType === 'answer' ? 'Answer PDF' : 'Question PDF'"></iframe>
                </main>
                <footer class="pdf-screen-footer">
                    <button type="button" class="pdf-return-button" @click.stop="closeViewer">
                        <ArrowLeftIcon /> Return to video
                    </button>
                </footer>
            </div>
        </Teleport>
    </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { ArrowLeftIcon, ArrowsRightLeftIcon, ChevronRightIcon, DocumentTextIcon, EyeIcon } from '@heroicons/vue/24/outline';
import api from '../api';
import { showAlert } from '../composables/dialog';

const props = defineProps({ lesson: { type: Object, required: true } });
const loadingType = ref('');
const viewerUrl = ref('');
const viewerType = ref('');
const backButton = ref(null);
const otherPdfType = computed(() => viewerType.value === 'answer' ? 'question' : 'answer');
const otherPdfAvailable = computed(() => props.lesson[`has_${otherPdfType.value}_pdf`]);
let previousBodyOverflow = '';
let returnFocusElement = null;
let requestSerial = 0;

function toggleAnswer() {
    if (viewerType.value === 'answer') {
        closeViewer();
        return;
    }
    openPdf('answer');
}

async function openPdf(type) {
    const request = ++requestSerial;
    const isSwitching = Boolean(viewerUrl.value);
    loadingType.value = type;
    if (!isSwitching) returnFocusElement = document.activeElement;

    try {
        const { data } = await api.get(`/lessons/${props.lesson.id}/pdf/${type}`, {
            responseType: 'blob',
            timeout: 60000,
        });
        if (request !== requestSerial) return;
        const oldUrl = viewerUrl.value;
        viewerType.value = type;
        viewerUrl.value = URL.createObjectURL(data);
        if (!isSwitching) {
            previousBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
        }
        await nextTick();
        if (oldUrl) URL.revokeObjectURL(oldUrl);
        else backButton.value?.focus();
    } catch (error) {
        if (request !== requestSerial) return;
        console.error('Lesson PDF view failed:', error);
        await showAlert({ title: 'PDF Failed', message: 'Could not open this PDF. Please try again.' });
    } finally {
        if (request === requestSerial) loadingType.value = '';
    }
}

async function closeViewer(restoreFocus = true) {
    requestSerial++;
    loadingType.value = '';
    const url = viewerUrl.value;
    viewerUrl.value = '';
    viewerType.value = '';
    document.body.style.overflow = previousBodyOverflow;
    await nextTick();
    if (url) URL.revokeObjectURL(url);
    if (restoreFocus) returnFocusElement?.focus?.();
}

function handleKeydown(event) {
    if (event.key === 'Escape' && viewerUrl.value) {
        event.preventDefault();
        closeViewer();
    }
}

onMounted(() => window.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
    closeViewer(false);
});
</script>

<style scoped>
.lesson-resources { background: rgba(15, 23, 42, 0.96); border-top: 1px solid rgba(148, 163, 184, 0.2); padding: 0.8rem; }
.resources-heading { margin-bottom: 0.6rem; }
.resources-label { color: #e2e8f0; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.resources-help { color: #94a3b8; font-size: 0.67rem; margin-top: 0.12rem; }
.resource-list { display: grid; gap: 0.5rem; }
.resource-button { align-items: center; border: 1px solid; border-radius: 10px; cursor: pointer; display: grid; gap: 0.6rem; grid-template-columns: auto minmax(0, 1fr) auto; padding: 0.65rem 0.7rem; text-align: left; transition: 0.2s ease; width: 100%; }
.resource-button:disabled { cursor: wait; opacity: 0.65; }
.resource-icon { align-items: center; border-radius: 8px; display: flex; height: 34px; justify-content: center; width: 34px; }
.resource-icon svg { height: 19px; width: 19px; }
.resource-copy { min-width: 0; }
.resource-copy strong, .resource-copy small { display: block; }
.resource-copy strong { font-size: 0.78rem; }
.resource-copy small { color: #94a3b8; font-size: 0.65rem; font-weight: 500; margin-top: 0.08rem; }
.resource-arrow { height: 17px; opacity: 0.7; width: 17px; }
.question-button { background: rgba(79, 70, 229, 0.18); border-color: rgba(129, 140, 248, 0.42); color: #e0e7ff; }
.answer-button { background: rgba(20, 184, 166, 0.14); border-color: rgba(45, 212, 191, 0.36); color: #ccfbf1; }
.question-button .resource-icon { background: rgba(99, 102, 241, 0.2); }
.answer-button .resource-icon { background: rgba(20, 184, 166, 0.18); }
.resource-button:hover:not(:disabled) { filter: brightness(1.15); transform: translateY(-1px); }
.pdf-screen { background: #0b1120; display: grid; grid-template-rows: auto minmax(0, 1fr) auto; inset: 0; position: fixed; z-index: 2147483000; }
.pdf-screen-header { align-items: center; background: #111827; border-bottom: 1px solid rgba(255, 255, 255, 0.1); display: grid; gap: 0.75rem; grid-template-columns: auto minmax(0, 1fr) auto; min-height: 64px; padding: max(0.65rem, env(safe-area-inset-top)) 0.8rem 0.65rem; }
.pdf-back-button, .pdf-return-button { align-items: center; background: #4f46e5; border: 0; border-radius: 9px; color: #fff; cursor: pointer; display: inline-flex; font-size: 0.75rem; font-weight: 800; gap: 0.4rem; padding: 0.6rem 0.75rem; }
.pdf-back-button svg, .pdf-return-button svg { height: 18px; width: 18px; }
.pdf-back-button:focus-visible, .pdf-return-button:focus-visible { box-shadow: 0 0 0 3px rgba(165, 180, 252, 0.55); outline: none; }
.pdf-screen-title { color: #fff; min-width: 0; text-align: center; }
.pdf-screen-title strong, .pdf-screen-title small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pdf-screen-title strong { font-size: 0.86rem; }
.pdf-screen-title small { color: #94a3b8; font-size: 0.68rem; margin-top: 0.08rem; }
.pdf-kind { border: 1px solid rgba(129, 140, 248, 0.35); border-radius: 999px; color: #c7d2fe; font-size: 0.67rem; font-weight: 800; padding: 0.38rem 0.58rem; white-space: nowrap; }
.pdf-kind.answer { border-color: rgba(45, 212, 191, 0.35); color: #99f6e4; }
.pdf-actions { align-items: center; display: flex; gap: 0.5rem; }
.pdf-switch-button { align-items: center; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.18); border-radius: 9px; color: #fff; cursor: pointer; display: inline-flex; font-size: 0.7rem; font-weight: 800; gap: 0.35rem; padding: 0.5rem 0.65rem; white-space: nowrap; }
.pdf-switch-button:hover:not(:disabled) { background: rgba(255, 255, 255, 0.15); }
.pdf-switch-button:disabled { cursor: wait; opacity: 0.6; }
.pdf-switch-button svg { height: 16px; width: 16px; }
.pdf-switch-button:focus-visible { box-shadow: 0 0 0 3px rgba(165, 180, 252, 0.55); outline: none; }
.pdf-screen-body { background: #374151; min-height: 0; overflow: hidden; }
.pdf-screen-body iframe { background: #fff; border: 0; display: block; height: 100%; width: 100%; }
.pdf-screen-footer { background: #111827; border-top: 1px solid rgba(255, 255, 255, 0.1); display: none; padding: 0.55rem 0.75rem max(0.55rem, env(safe-area-inset-bottom)); }
@media (max-width: 600px) {
    .pdf-screen-header { grid-template-columns: minmax(0, 1fr) auto; }
    .pdf-back-button span { display: none; }
    .pdf-back-button { padding: 0.58rem; }
    .pdf-screen-title { grid-column: 1 / -1; grid-row: 2; text-align: left; }
    .pdf-actions { grid-column: 2; grid-row: 1; }
    .pdf-kind { display: none; }
    .pdf-screen-footer { display: flex; }
    .pdf-return-button { justify-content: center; width: 100%; }
}
</style>
