<template>
    <div class="edit-form-container" :class="{ 'is-create-page': !isEditMode, 'is-inline-form': inline }">
        <div class="box is-shadowless has-background-transparent">
            <div v-if="isEditMode && !hideHeading" class="mb-4">
                <h3 class="is-size-6 has-text-weight-bold">
                    Editing: <span class="has-text-primary">{{ lesson.title }}</span>
                </h3>
            </div>

            <div class="columns is-multiline">
                <template v-if="!isEditMode">
                    <div class="column is-6">
                        <label class="label is-small">Grade</label>
                        <div class="control" :class="{ 'is-loading': cacheLoading }">
                            <div class="select is-small is-fullwidth">
                                <select v-model="localLesson.grade_id" :disabled="cacheLoading">
                                    <option value="" disabled>Select Grade</option>
                                    <option v-for="grade in grades" :key="grade.id" :value="grade.id">
                                        {{ grade.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="column is-6">
                        <label class="label is-small">Subject</label>
                        <div class="control" :class="{ 'is-loading': cacheLoading }">
                            <div class="select is-small is-fullwidth">
                                <select v-model="localLesson.subject_id" :disabled="cacheLoading">
                                    <option value="" disabled>Select Subject</option>
                                    <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                                        {{ subject.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="column is-3">
                    <label class="label is-small">Topic</label>
                    <div class="suggestion-field">
                        <input class="input is-small" v-model="localLesson.topic" placeholder="Choose or type a topic"
                            autocomplete="off" @focus="showSuggestions('topic')" @input="showSuggestions('topic')"
                            @blur="hideSuggestions('topic')">
                        <div v-if="activeSuggestionField === 'topic' && filteredTopicOptions.length"
                            class="suggestion-menu">
                            <button v-for="topic in filteredTopicOptions" :key="topic" type="button"
                                class="suggestion-option" @mousedown.prevent="selectTopic(topic)">
                                {{ topic }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <label class="label is-small">Sub Topic</label>
                    <div class="suggestion-field">
                        <input class="input is-small" v-model="localLesson.sub_topic"
                            placeholder="Choose or type a sub topic" autocomplete="off"
                            @focus="showSuggestions('subTopic')" @input="showSuggestions('subTopic')"
                            @blur="hideSuggestions('subTopic')">
                        <div v-if="activeSuggestionField === 'subTopic' && filteredSubTopicOptions.length"
                            class="suggestion-menu">
                            <button v-for="subTopic in filteredSubTopicOptions" :key="subTopic" type="button"
                                class="suggestion-option" @mousedown.prevent="selectSubTopic(subTopic)">
                                {{ subTopic }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="column is-3">
                    <label class="label is-small">Lesson Title</label>
                    <input class="input is-small" v-model="localLesson.title" placeholder="e.g. Intro to Algebra">
                </div>

                <div class="column is-1">
                    <label class="label is-small">Part #</label>
                    <input class="input is-small" type="text" v-model="localLesson.part_number" placeholder="Ex: 1">
                </div>

                <div class="column is-2">
                    <label class="label is-small">Show/Hidden</label>
                    <div class="field mt-2">
                        <label class="switch">
                            <input type="checkbox" v-model="localLesson.is_active" :true-value="1" :false-value="0">
                            <span class="slider round"></span>
                            <span class="switch-label">{{ localLesson.is_active ? 'Active' : 'Inactive' }}</span>
                        </label>
                    </div>
                </div>

                <div class="column is-4">
                    <label class="label is-small">Duration</label>
                    <div class="duration-grid">
                        <div>
                            <input class="input is-small" type="text" min="0" v-model.number="durationParts.hours">
                            <p class="help has-text-grey-light">Hours</p>
                        </div>
                        <div>
                            <input class="input is-small" type="text" min="0" max="59"
                                v-model.number="durationParts.minutes">
                            <p class="help has-text-grey-light">Minutes</p>
                        </div>
                        <div>
                            <input class="input is-small" type="text" min="0" max="59"
                                v-model.number="durationParts.seconds">
                            <p class="help has-text-grey-light">Seconds</p>
                        </div>
                    </div>
                </div>

                <div class="column is-8">
                    <label class="label is-small">Description</label>
                    <textarea class="textarea is-small" v-model="localLesson.description" rows="3"></textarea>
                </div>

                <div class="column is-6">
                    <label class="label is-small">Vimeo URL / Embed Code <span class="has-text-grey-light">(optional)</span></label>
                    <textarea class="textarea is-small" v-model="localLesson.vimeo_url" rows="2"
                        placeholder="Paste the Vimeo player URL or full embed code"></textarea>
                    <p class="help has-text-grey-light">Add the lesson video here, or use the Lesson upload beside it.</p>
                </div>

                <div class="column is-6 lesson-upload-column">
                    <label class="label is-small">Lesson <span class="has-text-grey-light">(optional)</span></label>
                    <LessonPdfManager ref="lessonPdfManager" mode="lesson"
                        :lesson="isEditMode ? lesson : null" @changed="$emit('resource-changed', $event)" />
                </div>
            </div>

            <section class="practice-materials-panel">
                <header class="practice-materials-header">
                    <span class="practice-materials-icon"><ClipboardDocumentCheckIcon /></span>
                    <div>
                        <h4 class="practice-materials-title">Practice &amp; Solutions</h4>
                        <p class="practice-materials-subtitle">
                            Add the question sheets and provide written or video solutions.
                        </p>
                    </div>
                </header>

                <div class="practice-materials-content">
                    <LessonPdfManager ref="practicePdfManager" mode="practice"
                        :lesson="isEditMode ? lesson : null"
                        @changed="$emit('resource-changed', $event)" />

                    <section class="answer-video-panel">
                        <header class="answer-video-header">
                            <span class="answer-video-icon"><VideoCameraIcon /></span>
                            <div>
                                <p class="answer-video-title">Answer video</p>
                                <p class="answer-video-subtitle">Add a Vimeo video solution for your students.</p>
                            </div>
                            <span class="answer-video-optional">Optional</span>
                        </header>
                        <label class="label is-small mt-3">Vimeo URL / Embed Code</label>
                        <textarea class="textarea is-small answer-video-input" v-model="localLesson.answer_vimeo_url"
                            rows="2" placeholder="Paste the answer video's Vimeo URL or full embed code"></textarea>
                        <p class="help answer-video-help">Use an answer video, an answer PDF, or both.</p>
                    </section>
                </div>
            </section>

            <hr class="my-4" style="height: 1px; background-color: #dbdbdb;">

            <div class="buttons is-right">
                <button class="button is-small is-light" @click="handleCancel">
                    Cancel
                </button>

                <button class="button is-small is-primary has-text-white" :class="{ 'is-loading': loading }"
                    @click="handleSave">
                    <span>{{ lesson?.id ? 'Update Lesson' : 'Create Lesson' }}</span>
                </button>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '../../api';
import { useRouter } from 'vue-router';
import { useCacheStore } from '../../stores/cache';
import { storeToRefs } from 'pinia';
import { showAlert } from '../../composables/dialog';
import LessonPdfManager from './LessonPdfManager.vue';
import { normalizeVimeoUrl } from '../../utils/vimeo';
import { ClipboardDocumentCheckIcon, VideoCameraIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const cacheStore = useCacheStore();

const { subjects, grades, loading: cacheLoading } = storeToRefs(cacheStore);

const props = defineProps({
    lesson: Object,
    grade_id: [String, Number],
    subject_id: [String, Number],
    inline: {
        type: Boolean,
        default: false
    },
    hideHeading: {
        type: Boolean,
        default: false
    },
    topicOptions: {
        type: Array,
        default: () => []
    },
    subTopicOptions: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['saved', 'cancel', 'resource-changed']);
const loading = ref(false);
const lessonPdfManager = ref(null);
const practicePdfManager = ref(null);
const activeSuggestionField = ref(null);

// 1. Initialize with either the prop data OR a blank template
const getInitialData = () => {
    if (props.lesson) {
        return { ...props.lesson };
    }
    return {
        title: '',
        topic: '',
        sub_topic: '',
        part_number: 1,
        vimeo_url: '',
        is_active: 1,
        description: '',
        answer_vimeo_url: '',
        duration: '',
        grade_id: props.grade_id || '',
        subject_id: props.subject_id || ''
    };
};

// Create a local copy so we don't mutate the parent's data directly
const localLesson = ref(getInitialData());
const durationParts = ref(parseDuration(localLesson.value.duration));

// 2. Determine if we are creating or editing
const isEditMode = computed(() => !!props.lesson?.id);

const normalizedTopicOptions = computed(() => uniqueSorted(props.topicOptions));

const matchingSubTopicOptions = computed(() => {
    const currentTopic = String(localLesson.value.topic || '').trim();
    const options = props.subTopicOptions
        .filter(option => {
            if (!option) return false;
            if (typeof option === 'string') return true;
            return !currentTopic || option.topic === currentTopic;
        })
        .map(option => typeof option === 'string' ? option : option.name);

    return uniqueSorted(options);
});

const filteredTopicOptions = computed(() => filterSuggestions(
    normalizedTopicOptions.value,
    localLesson.value.topic
));

const filteredSubTopicOptions = computed(() => filterSuggestions(
    matchingSubTopicOptions.value,
    localLesson.value.sub_topic
));

onMounted(async () => {
    // 4. Await the combined fetcher
    await cacheStore.fetchAllMetadata();
    console.log("Metadata synced for the sir.");
});

const handleSave = async () => {
    // 1. Define required fields
    const requiredFields = [
        { key: 'title', label: 'Lesson Title' },
        { key: 'topic', label: 'Topic' },
        { key: 'grade_id', label: 'Grade' },
        { key: 'subject_id', label: 'Subject' }
    ];

    // 2. Check each field
    for (const field of requiredFields) {
        if (!localLesson.value[field.key]) {
            await showAlert({
                title: 'Missing Information',
                message: `Please fill in the ${field.label}.`,
            });
            return; // Stop execution
        }
    }

    localLesson.value.vimeo_url = normalizeVimeoUrl(localLesson.value.vimeo_url);
    localLesson.value.answer_vimeo_url = normalizeVimeoUrl(localLesson.value.answer_vimeo_url);

    if (!localLesson.value.vimeo_url && !lessonPdfManager.value?.hasResource('lesson')) {
        await showAlert({
            title: 'Lesson Material Required',
            message: 'Add either a Vimeo video or a Lesson file before saving.',
        });
        return;
    }

    // 3. If validation passes, proceed as normal
    localLesson.value.duration = formatDuration(durationParts.value);
    const payload = Object.fromEntries([
        'grade_id', 'subject_id', 'topic', 'sub_topic', 'title', 'part_number',
        'description', 'vimeo_url', 'answer_vimeo_url', 'duration', 'is_active'
    ].map(key => [key, localLesson.value[key]]));

    loading.value = true;
    try {
        let savedLesson;
        if (isEditMode.value) {
            const { data } = await api.put(`/admin/lessons/${localLesson.value.id}`, payload);
            savedLesson = data;
        } else {
            const { data: createdLesson } = await api.post(`/admin/lessons`, payload);
            savedLesson = createdLesson;
            try {
                await lessonPdfManager.value?.uploadPending(createdLesson.id);
                await practicePdfManager.value?.uploadPending(createdLesson.id);
            } catch (error) {
                console.error('New lesson PDF upload failed:', error);
                await showAlert({
                    title: 'Lesson Created',
                    message: 'The lesson was created, but one or more PDFs could not be uploaded. You can add them by editing the lesson.',
                });
            }
        }
        emit('saved', savedLesson);
    } catch (error) {
        console.error("Update failed", error);
        await showAlert({
            title: 'Save Failed',
            message: 'Failed to update lesson. Please check your internet connection.',
        });
    } finally {
        loading.value = false;
    }
};

const handleCancel = () => {
    localLesson.value = getInitialData();
    durationParts.value = parseDuration(localLesson.value.duration);

    if (props.inline) {
        emit('cancel');
    } else {
        router.back();
    }
};


watch(
    () => [props.grade_id, props.subject_id],
    ([newGrade, newSubject]) => {
        if (!isEditMode.value) {
            if (newGrade) localLesson.value.grade_id = newGrade;
            if (newSubject) localLesson.value.subject_id = newSubject;
        }
    },
    { immediate: true }
);

watch(
    () => props.lesson,
    () => {
        localLesson.value = getInitialData();
        durationParts.value = parseDuration(localLesson.value.duration);
    }
);

function parseDuration(value) {
    if (!value) {
        return { hours: 0, minutes: 0, seconds: 0 };
    }

    const text = String(value).trim();

    if (text.includes(':')) {
        const parts = text.split(':').map(part => Number(part) || 0);

        if (parts.length === 3) {
            return normalizeDuration({
                hours: parts[0],
                minutes: parts[1],
                seconds: parts[2],
            });
        }

        if (parts.length === 2) {
            return normalizeDuration({
                hours: 0,
                minutes: parts[0],
                seconds: parts[1],
            });
        }
    }

    const hours = Number(text.match(/(\d+)\s*h/i)?.[1] || 0);
    const minutes = Number(text.match(/(\d+)\s*m/i)?.[1] || 0);
    const seconds = Number(text.match(/(\d+)\s*s/i)?.[1] || 0);

    if (hours || minutes || seconds) {
        return normalizeDuration({ hours, minutes, seconds });
    }

    return normalizeDuration({
        hours: 0,
        minutes: Number(text) || 0,
        seconds: 0,
    });
}

function normalizeDuration(parts) {
    const totalSeconds =
        Math.max(Number(parts.hours) || 0, 0) * 3600 +
        Math.max(Number(parts.minutes) || 0, 0) * 60 +
        Math.max(Number(parts.seconds) || 0, 0);

    return {
        hours: Math.floor(totalSeconds / 3600),
        minutes: Math.floor((totalSeconds % 3600) / 60),
        seconds: totalSeconds % 60,
    };
}

function formatDuration(parts) {
    const normalized = normalizeDuration(parts);
    const pad = value => String(value).padStart(2, '0');

    if (!normalized.hours && !normalized.minutes && !normalized.seconds) {
        return '';
    }

    return `${normalized.hours}:${pad(normalized.minutes)}:${pad(normalized.seconds)}`;
}

function showSuggestions(field) {
    activeSuggestionField.value = field;
}

function hideSuggestions(field) {
    window.setTimeout(() => {
        if (activeSuggestionField.value === field) {
            activeSuggestionField.value = null;
        }
    }, 120);
}

function selectTopic(topic) {
    localLesson.value.topic = topic;
    activeSuggestionField.value = null;
}

function selectSubTopic(subTopic) {
    localLesson.value.sub_topic = subTopic;
    activeSuggestionField.value = null;
}

function filterSuggestions(options, searchText) {
    const needle = String(searchText || '').trim().toLowerCase();

    if (!needle) return options;

    return options.filter(option => option.toLowerCase().includes(needle));
}

function uniqueSorted(values) {
    return [...new Set(
        values
            .map(value => String(value || '').trim())
            .filter(Boolean)
    )].sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));
}

</script>

<style scoped>
.edit-form-container {
    /* Create a "slide down" entry */
    animation: slideDown 0.2s ease-out;
    border-left: 4px solid #4f46e5;
    /* Matches primary color */
    background-color: #fafafa;
    border-bottom: 1px solid #dbdbdb;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.box {
    border-radius: 0;
    /* Keeps it flush with the table */
    padding: 1.5rem;
}

/* Subtle focus state for inputs */
.input:focus,
.textarea:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 0.125em rgba(0, 209, 178, 0.25);
}

.duration-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
}

.duration-grid .help {
    margin-top: 0.25rem;
    margin-bottom: 0;
    text-align: center;
}

.suggestion-field {
    position: relative;
    width: 100%;
}

.suggestion-menu {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 50;
    max-height: 10.75rem;
    overflow-y: auto;
    border: 1px solid rgba(148, 163, 184, 0.32);
    border-radius: 8px;
    background: #111827;
    box-shadow: 0 14px 32px rgba(0, 0, 0, 0.34);
    padding: 0.25rem;
}

.suggestion-option {
    display: block;
    width: 100%;
    min-height: 2.1rem;
    padding: 0.45rem 0.65rem;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #f8fafc;
    cursor: pointer;
    font-size: 0.8rem;
    line-height: 1.15;
    text-align: left;
}

.suggestion-option:hover,
.suggestion-option:focus {
    background: rgba(79, 70, 229, 0.34);
    color: #fff;
    outline: none;
}

/* The switch - the box around the slider */
.switch {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

/* Hide default HTML checkbox */
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

/* The slider */
.slider {
    position: relative;
    width: 40px;
    height: 20px;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

/* Colors when checked */
input:checked+.slider {
    background-color: #4f46e5;
    /* Bulma Primary Green */
}

input:focus+.slider {
    box-shadow: 0 0 1px #4f46e5;
}

/* Move the circle on check */
input:checked+.slider:before {
    transform: translateX(20px);
}

/* Label text style */
.switch-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
    user-select: none;
}

/* Base container style */
.edit-form-container {
    animation: slideDown 0.2s ease-out;
    background:
        linear-gradient(145deg, #26324a 0%, #1d273b 100%) !important;
    border: 1px solid rgba(203, 213, 225, 0.22);
    border-radius: 14px;
    box-shadow:
        0 18px 42px rgba(0, 0, 0, 0.34),
        inset 0 1px 0 rgba(255, 255, 255, 0.055);
    overflow: hidden;
    transition: all 0.3s ease;
}

.edit-form-container:hover {
    background:
        linear-gradient(145deg, #344866 0%, #273a56 100%) !important;
    border-color: rgba(147, 197, 253, 0.38);
    box-shadow:
        0 20px 46px rgba(0, 0, 0, 0.34),
        0 8px 24px rgba(59, 130, 246, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.08);
    transform: translateY(-1px);
}

.edit-form-container.is-inline-form {
    margin: 0.7rem;
}

/* Specific styles for "Create Mode" standalone page */
.edit-form-container.is-create-page {
    margin-top: 1rem;
    border-left: 1px solid rgba(203, 213, 225, 0.18);
}

.edit-form-container.is-create-page.is-inline-form {
    margin: 0.7rem;
}

/* Specific styles for "Edit Mode" (likely inside a list/table) */
.edit-form-container:not(.is-create-page) {
    border-left: 4px solid #4f46e5;
}

.box {
    border-radius: 12px;
    padding: 1.5rem;
}

/* Visibility fixes for labels */
.label.is-small {
    color: white;
    font-weight: 600;
}

.switch-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
    /* Changed from white to dark grey for visibility */
    user-select: none;
}

/* Animation */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Better focus states */
.input:focus,
.textarea:focus,
.select select:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 0.125em rgba(79, 70, 229, 0.1);
}

.answer-video-panel {
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 12px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.035);
    padding: 1rem;
}

.practice-materials-panel {
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.055), rgba(15, 23, 42, 0.52));
    border: 1px solid rgba(226, 232, 240, 0.16);
    border-left: 4px solid rgba(129, 140, 248, 0.78);
    border-radius: 14px;
    box-shadow:
        0 16px 34px rgba(0, 0, 0, 0.3),
        0 3px 8px rgba(0, 0, 0, 0.22),
        inset 0 1px 0 rgba(255, 255, 255, 0.07);
    margin-top: 0.5rem;
    padding: 1rem;
}

.practice-materials-header {
    align-items: center;
    border-bottom: 1px solid rgba(148, 163, 184, 0.18);
    display: flex;
    gap: 0.75rem;
    padding-bottom: 0.9rem;
}

.practice-materials-icon {
    align-items: center;
    background: rgba(99, 102, 241, 0.2);
    border-radius: 11px;
    color: #a5b4fc;
    display: flex;
    flex: 0 0 44px;
    height: 44px;
    justify-content: center;
}

.practice-materials-icon svg {
    height: 24px;
    width: 24px;
}

.practice-materials-title {
    color: #fff;
    font-size: 1.05rem;
    font-weight: 800;
}

.practice-materials-subtitle {
    color: #94a3b8;
    font-size: 0.74rem;
    margin-top: 0.12rem;
}

.practice-materials-content {
    display: grid;
    gap: 1rem;
    padding-top: 1rem;
}

.practice-materials-panel :deep(.pdf-manager) {
    border-top: 0;
    margin-top: 0;
    padding-top: 0;
}

.answer-video-header {
    align-items: center;
    display: flex;
    gap: 0.75rem;
}

.answer-video-icon {
    align-items: center;
    background: rgba(255, 255, 255, 0.07);
    border-radius: 10px;
    color: #cbd5e1;
    display: flex;
    flex: 0 0 42px;
    height: 42px;
    justify-content: center;
}

.answer-video-icon svg {
    height: 23px;
    width: 23px;
}

.answer-video-title {
    color: #f8fafc;
    font-size: 1rem;
    font-weight: 800;
}

.answer-video-subtitle,
.answer-video-help {
    color: #94a3b8;
    font-size: 0.72rem;
}

.answer-video-optional {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 999px;
    color: #cbd5e1;
    font-size: 0.65rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    margin-left: auto;
    padding: 0.3rem 0.55rem;
    text-transform: uppercase;
}

.answer-video-input {
    border-color: rgba(148, 163, 184, 0.28);
}

@media (max-width: 600px) {
    .answer-video-header {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .answer-video-optional {
        margin-left: 0;
    }
}
</style>
