<template>
    <Layout :title="isEditMode ? 'Edit Lesson' : 'Create New Lesson'" :loading="loading" :showBack="false">
        <div class="edit-form-container" :class="{ 'is-create-page': !isEditMode }">
            <div class="box is-shadowless has-background-transparent">
                <div v-if="isEditMode" class="mb-4">
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

                    <div class="column is-6">
                        <label class="label is-small">Lesson Title</label>
                        <input class="input is-small" v-model="localLesson.title" placeholder="e.g. Intro to Algebra">
                    </div>
                    <div class="column is-6">
                        <label class="label is-small">Topic</label>
                        <input class="input is-small" v-model="localLesson.topic" placeholder="e.g. Mathematics">
                    </div>
                    <div class="column is-3">
                        <label class="label is-small">Part #</label>
                        <input class="input is-small" type="number" v-model="localLesson.part_number">
                    </div>
                    <div class="column is-6">
                        <label class="label is-small">Vimeo URL</label>
                        <input class="input is-small" v-model="localLesson.vimeo_url"
                            placeholder="https://player.vimeo.com/...">
                    </div>
                    <div class="column is-3">
                        <label class="label is-small">Show/Hidden</label>
                        <div class="field mt-2">
                            <label class="switch">
                                <input type="checkbox" v-model="localLesson.is_active" :true-value="1" :false-value="0">
                                <span class="slider round"></span>
                                <span class="switch-label">{{ localLesson.is_active ? 'Active' : 'Inactive' }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="column is-12">
                        <label class="label is-small">Description</label>
                        <textarea class="textarea is-small" v-model="localLesson.description" rows="3"></textarea>
                    </div>
                </div>

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
    </Layout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '../../api';
import { useRouter } from 'vue-router';
import { useCacheStore } from '../../stores/cache';
import { storeToRefs } from 'pinia';
import Layout from '../common/Layout.vue';

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
    }
});

const emit = defineEmits(['saved', 'cancel']);
const loading = ref(false);

// 1. Initialize with either the prop data OR a blank template
const getInitialData = () => {
    if (props.lesson) {
        return { ...props.lesson };
    }
    return {
        title: '',
        topic: '',
        part_number: 1,
        vimeo_url: '',
        is_active: 1,
        description: '',
        grade_id: props.grade_id || '',
        subject_id: props.subject_id || ''
    };
};

// Create a local copy so we don't mutate the parent's data directly
const localLesson = ref(getInitialData());

// 2. Determine if we are creating or editing
const isEditMode = computed(() => !!props.lesson?.id);

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
        { key: 'vimeo_url', label: 'Vimeo URL' },
        { key: 'grade_id', label: 'Grade' },
        { key: 'subject_id', label: 'Subject' }
    ];

    // 2. Check each field
    for (const field of requiredFields) {
        if (!localLesson.value[field.key]) {
            alert(`Please fill in the ${field.label}.`);
            return; // Stop execution
        }
    }

    // 3. If validation passes, proceed as normal
    loading.value = true;
    try {
        if (isEditMode.value) {
            await api.put(`/admin/lessons/${localLesson.value.id}`, localLesson.value);
        } else {
            await api.post(`/admin/lessons`, localLesson.value);
        }
        emit('saved');
    } catch (error) {
        console.error("Update failed", error);
        alert("Failed to update lesson. Please check your internet connection.");
    } finally {
        loading.value = false;
    }
};

const handleCancel = () => {
    localLesson.value = getInitialData();

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
    background-color: hsl(221, 14%, 9%, 1) !important;
    transition: all 0.3s ease;
}

/* Specific styles for "Create Mode" standalone page */
.edit-form-container.is-create-page {
    border: 1px solid #dbdbdb;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
    margin-top: 1rem;
    background-color: hsl(221, 14%, 9%, 1) !important;
    border-left: none;
    /* Remove the aggressive indigo bar in full-page mode */
}

/* Specific styles for "Edit Mode" (likely inside a list/table) */
.edit-form-container:not(.is-create-page) {
    border-left: 4px solid #4f46e5;
    border-bottom: 1px solid #dbdbdb;
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
</style>
