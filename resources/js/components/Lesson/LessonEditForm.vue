<template>
    <div class="edit-form-container">
        <div class="box is-shadowless has-background-transparent">
            <div class="mb-4">
                <h3 class="is-size-6 has-text-weight-bold">
                    Editing: <span class="has-text-primary">{{ lesson.title }}</span>
                </h3>
            </div>

            <div class="columns is-multiline">
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
                    <input class="input is-small" v-model="localLesson.vimeo_url" placeholder="https://vimeo.com/...">
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
                <button class="button is-small is-light" @click="$emit('cancel')">
                    Cancel
                </button>
                <button class="button is-small is-primary" :class="{ 'is-loading': loading }" @click="handleSave">
                    Update Lesson
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import api from '../../api';

const props = defineProps({
    lesson: Object
});

const emit = defineEmits(['saved', 'cancel']);
const loading = ref(false);

// Create a local copy so we don't mutate the parent's data directly
const localLesson = ref({ ...props.lesson });

const handleSave = async () => {
    loading.value = true;
    try {
        await api.put(`/admin/lessons/${localLesson.value.id}`, localLesson.value);
        emit('saved'); // Tell parent to refresh the list
    } catch (error) {
        console.error("Update failed", error);
        alert("Failed to update lesson.");
    } finally {
        loading.value = false;
    }
};
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
</style>
