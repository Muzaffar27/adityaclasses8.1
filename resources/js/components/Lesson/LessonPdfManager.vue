<template>
    <section class="pdf-manager" :class="{ 'is-lesson-upload': mode === 'lesson' }">
        <div v-if="mode === 'practice'" class="pdf-manager-heading">
            <div>
                <p class="has-text-white has-text-weight-semibold mb-1">Questions and answers</p>
                <p class="is-size-7 has-text-grey-light">Add up to two question sheets and one written answer. PDF only, up to 20 MB each.</p>
            </div>
        </div>

        <div v-if="mode === 'lesson'" class="lesson-upload-card">
            <span class="lesson-upload-icon"><DocumentTextIcon /></span>
            <div class="lesson-upload-copy">
                <strong>{{ statusText('lesson') }}</strong>
                <small>PDF only, up to 20 MB</small>
            </div>
            <div class="lesson-upload-actions">
                <label class="lesson-file-button" :class="{ 'is-loading': uploadingType === 'lesson' }">
                    <input type="file" accept="application/pdf,.pdf" hidden :disabled="Boolean(uploadingType)"
                        @change="uploadPdf('lesson', $event)">
                    <ArrowUpTrayIcon />
                    {{ available.lesson || pendingFiles.lesson ? 'Replace' : 'Choose file' }}
                </label>
                <button v-if="available.lesson" type="button" class="lesson-icon-button" title="View lesson"
                    :disabled="Boolean(viewingType)" @click="viewPdf('lesson')">
                    <EyeIcon />
                </button>
                <button v-if="available.lesson || pendingFiles.lesson" type="button"
                    class="lesson-icon-button is-remove" title="Remove lesson"
                    :disabled="Boolean(removingType)" @click="removePdf('lesson')">
                    <TrashIcon />
                </button>
            </div>
        </div>

        <div v-else class="pdf-grid mt-3">
            <article v-for="type in types" :key="type.key" class="pdf-card"
                :class="{ 'lesson-file-card': type.key === 'lesson' }">
                <div class="pdf-card-copy">
                    <DocumentTextIcon class="pdf-icon" />
                    <div>
                        <p class="pdf-title">{{ type.label }}</p>
                        <p class="pdf-status" :class="{ uploaded: available[type.key] || pendingFiles[type.key] }">
                            {{ statusText(type.key) }}
                        </p>
                    </div>
                </div>

                <div class="pdf-actions">
                    <label class="button is-small is-primary has-text-white"
                        :class="{ 'is-loading': uploadingType === type.key }">
                        <input type="file" accept="application/pdf,.pdf" hidden
                            :disabled="Boolean(uploadingType)" @change="uploadPdf(type.key, $event)">
                        {{ available[type.key] || pendingFiles[type.key] ? 'Replace' : 'Upload' }}
                    </label>
                    <button v-if="available[type.key]" type="button" class="button is-small is-info"
                        :class="{ 'is-loading': viewingType === type.key }" @click="viewPdf(type.key)">
                        View
                    </button>
                    <button v-if="available[type.key] || pendingFiles[type.key]" type="button" class="button is-small is-danger"
                        :class="{ 'is-loading': removingType === type.key }" @click="removePdf(type.key)">
                        Remove
                    </button>
                </div>
            </article>
        </div>

        <div v-if="viewerUrl" class="pdf-modal" role="dialog" aria-modal="true" @click.self="closeViewer">
            <div class="pdf-viewer-card">
                <div class="pdf-viewer-header">
                    <p>{{ viewerLabel }}</p>
                    <button type="button" class="button is-small" @click="closeViewer">Close</button>
                </div>
                <PdfDocumentViewer :url="viewerUrl" />
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { ArrowUpTrayIcon, DocumentTextIcon, EyeIcon, TrashIcon } from '@heroicons/vue/24/outline';
import api from '../../api';
import { showAlert, showConfirm } from '../../composables/dialog';
import PdfDocumentViewer from '../common/PdfDocumentViewer.vue';

const props = defineProps({
    lesson: { type: Object, default: null },
    mode: { type: String, default: 'practice' },
});
const emit = defineEmits(['changed']);

const allTypes = [
    { key: 'lesson', label: 'Lesson' },
    { key: 'question', label: 'Question PDF 1' },
    { key: 'question2', label: 'Question PDF 2' },
    { key: 'answer', label: 'Answer PDF' },
];
const types = computed(() => allTypes.filter(type => (
    props.mode === 'lesson' ? type.key === 'lesson' : type.key !== 'lesson'
)));
const available = reactive({ lesson: false, question: false, question2: false, answer: false });
const pendingFiles = reactive({ lesson: null, question: null, question2: null, answer: null });
const uploadingType = ref('');
const removingType = ref('');
const viewingType = ref('');
const viewerUrl = ref('');
const viewerLabel = ref('');

watch(() => props.lesson, syncAvailability, { immediate: true, deep: true });

function syncAvailability(lesson) {
    available.lesson = Boolean(lesson?.has_lesson_pdf);
    available.question = Boolean(lesson?.has_question_pdf);
    available.question2 = Boolean(lesson?.has_question_pdf_2);
    available.answer = Boolean(lesson?.has_answer_pdf);
}

async function uploadPdf(type, event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    if (file.size > 20 * 1024 * 1024) {
        await showAlert({ title: 'File Too Large', message: 'Choose a PDF no larger than 20 MB.' });
        return;
    }

    if (!props.lesson?.id) {
        pendingFiles[type] = file;
        return;
    }

    await sendPdf(type, file, props.lesson.id);
}

async function sendPdf(type, file, lessonId, rethrow = false) {

    const formData = new FormData();
    formData.append('type', type);
    formData.append('pdf', file);
    uploadingType.value = type;

    try {
        const { data } = await api.post(`/admin/lessons/${lessonId}/pdf`, formData, { timeout: 60000 });
        syncAvailability(data);
        emit('changed', data);
    } catch (error) {
        console.error('Lesson PDF upload failed:', error);
        const message = error.response?.data?.errors?.pdf?.[0]
            || error.response?.data?.message
            || 'Could not upload this PDF.';
        if (rethrow) throw error;
        await showAlert({ title: 'Upload Failed', message });
    } finally {
        uploadingType.value = '';
    }
}

async function viewPdf(type) {
    closeViewer();
    viewingType.value = type;

    try {
        const { data } = await api.get(`/lessons/${props.lesson.id}/pdf/${type}`, {
            responseType: 'blob',
            timeout: 60000,
        });
        viewerLabel.value = allTypes.find(item => item.key === type)?.label || 'Lesson material';
        viewerUrl.value = URL.createObjectURL(data);
    } catch (error) {
        console.error('Lesson PDF view failed:', error);
        await showAlert({ title: 'View Failed', message: 'Could not open this PDF.' });
    } finally {
        viewingType.value = '';
    }
}

async function removePdf(type) {
    if (!props.lesson?.id) {
        pendingFiles[type] = null;
        return;
    }

    const confirmed = await showConfirm({
        title: `Remove ${allTypes.find(item => item.key === type)?.label || 'PDF'}`,
        message: `Remove this ${type} PDF from the lesson?`,
        confirmText: 'Remove',
        cancelText: 'Keep it',
    });
    if (!confirmed) return;

    removingType.value = type;
    try {
        const { data } = await api.delete(`/admin/lessons/${props.lesson.id}/pdf/${type}`);
        syncAvailability(data);
        emit('changed', data);
        closeViewer();
    } catch (error) {
        console.error('Lesson PDF removal failed:', error);
        await showAlert({ title: 'Remove Failed', message: 'Could not remove this PDF.' });
    } finally {
        removingType.value = '';
    }
}

function statusText(type) {
    if (available[type]) return 'Uploaded';
    if (pendingFiles[type]) return `${pendingFiles[type].name} - ready to upload`;
    if (type === 'lesson') return 'No file selected';
    return 'No PDF';
}

async function uploadPending(lessonId) {
    for (const type of types.value.map(item => item.key)) {
        if (pendingFiles[type]) await sendPdf(type, pendingFiles[type], lessonId, true);
    }
}

function hasResource(type) {
    return Boolean(available[type] || pendingFiles[type]);
}

defineExpose({ uploadPending, hasResource });

function closeViewer() {
    if (viewerUrl.value) URL.revokeObjectURL(viewerUrl.value);
    viewerUrl.value = '';
    viewerLabel.value = '';
}

onBeforeUnmount(closeViewer);
</script>

<style scoped>
.pdf-manager { border-top: 1px solid rgba(148, 163, 184, 0.2); margin-top: 1rem; padding-top: 1rem; }
.pdf-manager.is-lesson-upload { border-top: 0; margin-top: 0; padding-top: 0; }
.pdf-manager.is-lesson-upload .pdf-grid { margin-top: 0 !important; }
.lesson-upload-card { align-items: center; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(96, 165, 250, 0.3); border-radius: 10px; display: grid; gap: 0.65rem; grid-template-columns: auto minmax(0, 1fr) auto; min-height: 68px; padding: 0.65rem 0.75rem; }
.lesson-upload-icon { align-items: center; background: rgba(59, 130, 246, 0.16); border-radius: 8px; color: #93c5fd; display: flex; height: 34px; justify-content: center; width: 34px; }
.lesson-upload-icon svg { height: 19px; width: 19px; }
.lesson-upload-copy { min-width: 0; }
.lesson-upload-copy strong, .lesson-upload-copy small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lesson-upload-copy strong { color: #f8fafc; font-size: 0.75rem; }
.lesson-upload-copy small { color: #94a3b8; font-size: 0.64rem; margin-top: 0.08rem; }
.lesson-upload-actions { align-items: center; display: flex; gap: 0.4rem; }
.lesson-file-button, .lesson-icon-button { align-items: center; border-radius: 8px; cursor: pointer; display: inline-flex; font-size: 0.7rem; font-weight: 800; justify-content: center; min-height: 34px; }
.lesson-file-button { background: #4f46e5; color: #fff; gap: 0.32rem; padding: 0.45rem 0.65rem; }
.lesson-file-button:hover { background: #4338ca; }
.lesson-file-button svg, .lesson-icon-button svg { height: 16px; width: 16px; }
.lesson-icon-button { background: rgba(255, 255, 255, 0.075); border: 1px solid rgba(148, 163, 184, 0.24); color: #dbeafe; padding: 0.42rem; width: 34px; }
.lesson-icon-button.is-remove { color: #fca5a5; }
.lesson-icon-button:disabled, .lesson-file-button.is-loading { cursor: wait; opacity: 0.6; }
.pdf-grid { display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
.pdf-card { align-items: stretch; background: rgba(255, 255, 255, 0.035); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 10px; display: flex; flex-direction: column; gap: 0.75rem; justify-content: space-between; padding: 0.8rem; }
.lesson-file-card { background: rgba(59, 130, 246, 0.08); border-color: rgba(96, 165, 250, 0.3); }
.pdf-card-copy, .pdf-actions { align-items: center; display: flex; gap: 0.5rem; min-width: 0; }
.pdf-card-copy { flex: 1 1 auto; }
.pdf-card-copy > div { min-width: 0; }
.pdf-actions { display: grid; flex: 0 0 auto; grid-template-columns: repeat(auto-fit, minmax(76px, 1fr)); width: 100%; }
.pdf-actions .button { justify-content: center; margin: 0; width: 100%; }
.pdf-icon { color: #818cf8; height: 24px; width: 24px; }
.pdf-title { color: #fff; font-size: 0.8rem; font-weight: 700; }
.pdf-status { color: #94a3b8; font-size: 0.68rem; }
.pdf-status.uploaded { color: #34d399; }
.pdf-modal { align-items: center; background: rgba(0, 0, 0, 0.86); display: flex; inset: 0; justify-content: center; padding: 1rem; position: fixed; z-index: 1000; }
.pdf-viewer-card { background: #111827; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; display: flex; flex-direction: column; height: 92vh; overflow: hidden; width: min(1000px, 96vw); }
.pdf-viewer-header { align-items: center; color: #fff; display: flex; font-weight: 700; justify-content: space-between; padding: 0.7rem 0.9rem; }
.pdf-viewer-card :deep(.pdf-document-viewer) { flex: 1; min-height: 0; }
@media (max-width: 900px) { .pdf-grid { grid-template-columns: 1fr; } }
@media (max-width: 600px) {
    .lesson-upload-card { gap: 0.5rem; grid-template-columns: auto minmax(0, 1fr); }
    .lesson-upload-actions { grid-column: 1 / -1; }
    .lesson-file-button { flex: 1; }
    .pdf-actions { grid-template-columns: repeat(auto-fit, minmax(72px, 1fr)); }
}
</style>
