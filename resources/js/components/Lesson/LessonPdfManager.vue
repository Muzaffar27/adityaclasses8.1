<template>
    <section class="pdf-manager">
        <div class="pdf-manager-heading">
            <div>
                <p class="has-text-white has-text-weight-semibold mb-1">Lesson PDFs</p>
                <p class="is-size-7 has-text-grey-light">Upload questions and answers separately. PDF only, up to 20 MB.</p>
            </div>
        </div>

        <div class="pdf-grid mt-3">
            <article v-for="type in types" :key="type.key" class="pdf-card">
                <div class="pdf-card-copy">
                    <DocumentTextIcon class="pdf-icon" />
                    <div>
                        <p class="pdf-title">{{ type.label }}</p>
                        <p class="pdf-status" :class="{ uploaded: available[type.key] }">
                            {{ available[type.key] ? 'Uploaded' : 'No PDF' }}
                        </p>
                    </div>
                </div>

                <div class="pdf-actions">
                    <label class="button is-small is-primary has-text-white"
                        :class="{ 'is-loading': uploadingType === type.key }">
                        <input type="file" accept="application/pdf,.pdf" hidden
                            :disabled="Boolean(uploadingType)" @change="uploadPdf(type.key, $event)">
                        {{ available[type.key] ? 'Replace' : 'Upload' }}
                    </label>
                    <button v-if="available[type.key]" type="button" class="button is-small is-info"
                        :class="{ 'is-loading': viewingType === type.key }" @click="viewPdf(type.key)">
                        View
                    </button>
                    <button v-if="available[type.key]" type="button" class="button is-small is-danger"
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
                <iframe :src="`${viewerUrl}#toolbar=0&navpanes=0`" :title="viewerLabel"></iframe>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onBeforeUnmount, reactive, ref, watch } from 'vue';
import { DocumentTextIcon } from '@heroicons/vue/24/outline';
import api from '../../api';
import { showAlert, showConfirm } from '../../composables/dialog';

const props = defineProps({ lesson: { type: Object, required: true } });
const emit = defineEmits(['changed']);

const types = [
    { key: 'question', label: 'Question PDF' },
    { key: 'answer', label: 'Answer PDF' },
];
const available = reactive({ question: false, answer: false });
const uploadingType = ref('');
const removingType = ref('');
const viewingType = ref('');
const viewerUrl = ref('');
const viewerLabel = ref('');

watch(() => props.lesson, syncAvailability, { immediate: true, deep: true });

function syncAvailability(lesson) {
    available.question = Boolean(lesson?.has_question_pdf);
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

    const formData = new FormData();
    formData.append('type', type);
    formData.append('pdf', file);
    uploadingType.value = type;

    try {
        const { data } = await api.post(`/admin/lessons/${props.lesson.id}/pdf`, formData, { timeout: 60000 });
        syncAvailability(data);
        emit('changed', data);
    } catch (error) {
        console.error('Lesson PDF upload failed:', error);
        const message = error.response?.data?.errors?.pdf?.[0]
            || error.response?.data?.message
            || 'Could not upload this PDF.';
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
        viewerLabel.value = type === 'question' ? 'Question PDF' : 'Answer PDF';
        viewerUrl.value = URL.createObjectURL(data);
    } catch (error) {
        console.error('Lesson PDF view failed:', error);
        await showAlert({ title: 'View Failed', message: 'Could not open this PDF.' });
    } finally {
        viewingType.value = '';
    }
}

async function removePdf(type) {
    const confirmed = await showConfirm({
        title: `Remove ${type === 'question' ? 'Questions' : 'Answers'}`,
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

function closeViewer() {
    if (viewerUrl.value) URL.revokeObjectURL(viewerUrl.value);
    viewerUrl.value = '';
    viewerLabel.value = '';
}

onBeforeUnmount(closeViewer);
</script>

<style scoped>
.pdf-manager { border-top: 1px solid rgba(148, 163, 184, 0.2); margin-top: 1rem; padding-top: 1rem; }
.pdf-grid { display: grid; gap: 0.75rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.pdf-card { align-items: center; background: rgba(255, 255, 255, 0.035); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 10px; display: flex; gap: 0.75rem; justify-content: space-between; padding: 0.8rem; }
.pdf-card-copy, .pdf-actions { align-items: center; display: flex; gap: 0.5rem; }
.pdf-icon { color: #818cf8; height: 24px; width: 24px; }
.pdf-title { color: #fff; font-size: 0.8rem; font-weight: 700; }
.pdf-status { color: #94a3b8; font-size: 0.68rem; }
.pdf-status.uploaded { color: #34d399; }
.pdf-modal { align-items: center; background: rgba(0, 0, 0, 0.86); display: flex; inset: 0; justify-content: center; padding: 1rem; position: fixed; z-index: 1000; }
.pdf-viewer-card { background: #111827; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; display: flex; flex-direction: column; height: 92vh; overflow: hidden; width: min(1000px, 96vw); }
.pdf-viewer-header { align-items: center; color: #fff; display: flex; font-weight: 700; justify-content: space-between; padding: 0.7rem 0.9rem; }
.pdf-viewer-card iframe { background: #fff; border: 0; flex: 1; width: 100%; }
@media (max-width: 900px) { .pdf-grid { grid-template-columns: 1fr; } }
@media (max-width: 600px) { .pdf-card { align-items: stretch; flex-direction: column; } .pdf-actions { flex-wrap: wrap; } }
</style>
