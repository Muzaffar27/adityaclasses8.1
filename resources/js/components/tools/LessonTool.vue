<template>
    <div class="tool-panel">

        <!-- HEADER -->
        <div class="tool-header mb-4">
            <p class="title is-6 has-text-white mb-1">Lesson Manager</p>
            <p class="is-size-7 has-text-grey-light">
                Select grade & subject
            </p>
        </div>

        <!-- FILTER CARD -->
        <div class="glass-card p-4">

            <div class="filter-grid">
                <!-- GRADE -->
                <div class="column">
                    <label class="label is-small">Grade</label>

                    <div class="field has-addons">
                        <div class="control is-expanded" :class="{ 'is-loading': loading }">
                            <div class="select is-fullwidth is-small">
                                <select v-model="selectedGrade">
                                    <option disabled value="">Grade</option>
                                    <option v-for="grade in grades" :key="grade.id" :value="grade.id">
                                        {{ grade.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="control">
                            <button class="button is-primary is-small has-text-white" @click="showCreateGrade = true">
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SUBJECT -->
                <div class="column">
                    <label class="label is-small">Subject</label>

                    <div class="field has-addons">
                        <div class="control is-expanded" :class="{ 'is-loading': loading }">
                            <div class="select is-fullwidth is-small">
                                <select v-model="selectedSubject">
                                    <option disabled value="">Subject</option>
                                    <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                                        {{ subject.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="control">
                            <button class="button is-primary is-small has-text-white" @click="showCreateSubject = true">
                                +
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ACTION -->
            <div class="mt-4 is-flex is-justify-content-flex-end">
                <button class="button is-primary is-small px-5 has-text-white"
                    :disabled="!selectedGrade || !selectedSubject" @click="openLessons">
                    Open Lessons →
                </button>
            </div>

        </div>

        <!-- MODALS -->
        <createModal v-model="showCreateGrade" title="Create Grade">
            <input class="input" v-model="newGradeName" placeholder="Grade name" />

            <template #actions>
                <button class="button mr-2" @click="showCreateGrade = false">Cancel</button>
                <button class="button is-primary" @click="createGrade">Save</button>
            </template>
        </createModal>

        <createModal v-model="showCreateSubject" title="Create Subject">
            <input class="input" v-model="newSubjectName" placeholder="Subject name" />

            <template #actions>
                <button class="button mr-2" @click="showCreateSubject = false">Cancel</button>
                <button class="button is-primary" @click="createSubject">Save</button>
            </template>
        </createModal>

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCacheStore } from '../../stores/cache';
import { storeToRefs } from 'pinia';
import createModal from '../common/CreateModal.vue';
import axios from 'axios';
import { showAlert } from '../../composables/dialog';

const emit = defineEmits(['navigate']);

const cacheStore = useCacheStore();
const { subjects, grades, loading } = storeToRefs(cacheStore);

const selectedGrade = ref("");
const selectedSubject = ref("");

const showCreateGrade = ref(false);
const showCreateSubject = ref(false);

const newGradeName = ref('');
const newSubjectName = ref('');

onMounted(async () => {
    await cacheStore.fetchAllMetadata();
});

/* 🔥 THIS replaces router */
async function openLessons() {
    if (!selectedGrade.value || !selectedSubject.value) {
        await showAlert("Select both");
        return;
    }

    emit('navigate', {
        name: 'lessonList',
        props: {
            grade_id: selectedGrade.value,
            subject_id: selectedSubject.value
        }
    });
}

const createGrade = async () => {
    if (!newGradeName.value) return;

    const res = await axios.post('/api/addGrade', {
        name: newGradeName.value
    });

    cacheStore.grades.push(res.data);
    selectedGrade.value = res.data.id;

    showCreateGrade.value = false;
    newGradeName.value = '';
};

const createSubject = async () => {
    if (!newSubjectName.value) return;

    const res = await axios.post('/api/addSubject', {
        name: newSubjectName.value
    });

    cacheStore.subjects.push(res.data);
    selectedSubject.value = res.data.id;

    showCreateSubject.value = false;
    newSubjectName.value = '';
};
</script>

<style scoped>
.tool-panel {
    width: 100%;
}

.tool-header {
    padding-left: 4px;
}

/* tighten spacing */
.label.is-small {
    margin-bottom: 4px;
    color: #94a3b8;
    font-size: 0.65rem;
}

/* selects smaller */
.select.is-small select {
    font-size: 0.75rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
</style>
