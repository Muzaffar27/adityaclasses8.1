<template>
    <Layout title="Lesson Management" :loading="loading">

        <div class="box filter-card">

            <!-- HEADER -->
            <div class="mb-5">
                <p class="has-text-grey is-size-7">
                    Select a grade and subject to manage lessons
                </p>
            </div>

            <!-- GRID -->
            <div class="columns is-variable is-3">

                <!-- GRADE -->
                <div class="column">
                    <label class="label is-small">Grade</label>

                    <div class="field has-addons">
                        <div class="control is-expanded" :class="{ 'is-loading': loading }">
                            <div class="select is-fullwidth is-medium">
                                <select v-model="selectedGrade" :disabled="loading">
                                    <option disabled value="">Select Grade</option>
                                    <option v-for="grade in grades" :key="grade.id" :value="grade.id">
                                        {{ grade.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="control">
                            <button class="button is-primary is-medium has-text-white" @click="showCreateGrade = true">
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
                            <div class="select is-fullwidth is-medium">
                                <select v-model="selectedSubject" :disabled="loading">
                                    <option disabled value="">Select Subject</option>
                                    <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                                        {{ subject.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="control">
                            <button class="button is-primary is-medium has-text-white"
                                @click="showCreateSubject = true">
                                +
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ACTION -->
            <div class="mt-5 has-text-right">
                <button class="button is-primary is-medium px-5 has-text-white"
                    :disabled="!selectedGrade || !selectedSubject" @click="goToLessons">
                    Load Lessons →
                </button>
            </div>

        </div>


        <createModal v-model="showCreateGrade" title="Create Grade">
            <input class="input" v-model="newGradeName" placeholder="Enter grade name" @keyup.enter="createGrade" />

            <template #actions>
                <button class="button mr-2" @click="showCreateGrade = false">
                    Cancel
                </button>

                <button class="button is-primary has-text-white" :disabled="!newGradeName" @click="createGrade">
                    Save
                </button>
            </template>
        </createModal>

        <createModal v-model="showCreateSubject" title="Create Subject">
            <input class="input" v-model="newSubjectName" placeholder="Enter subject name"
                @keyup.enter="createSubject" />

            <template #actions>
                <button class="button mr-2" @click="showCreateSubject = false">
                    Cancel
                </button>

                <button class="button is-primary has-text-white" :disabled="!newSubjectName" @click="createSubject">
                    Save
                </button>
            </template>
        </createModal>
    </Layout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Layout from '../common/Layout.vue';
import { useCacheStore } from '../../stores/cache';
import { storeToRefs } from 'pinia';
import createModal from '../common/createModal.vue';
import axios from 'axios';

const router = useRouter();

const cacheStore = useCacheStore();
const { subjects, grades, loading } = storeToRefs(cacheStore);

const selectedGrade = ref("");
const selectedSubject = ref("");

const showCreateGrade = ref(false);
const showCreateSubject = ref(false);

const newGradeName = ref('');
const newSubjectName = ref('');

const creatingGrade = ref(false);
const creatingSubject = ref(false);

onMounted(async () => {    // One call to rule them all
    await cacheStore.fetchAllMetadata();
});

const createGrade = async () => {
    if (!newGradeName.value || creatingGrade.value) return;

    creatingGrade.value = true;

    try {
        const res = await axios.post('/api/addGrade', {
            name: newGradeName.value
        });

        const newGrade = res.data;

        // ✅ update store directly (no refetch)
        cacheStore.grades.push(newGrade);

        // ✅ auto select
        selectedGrade.value = newGrade.id;

        // UX flow
        showCreateGrade.value = false;
        newGradeName.value = '';

        // optional: guide next step
        showCreateSubject.value = true;

    } catch (error) {
        console.error(error);
        alert("Failed to create grade");
    } finally {
        creatingGrade.value = false;
    }
};

const createSubject = async () => {
    if (!newSubjectName.value || creatingSubject.value) return;

    creatingSubject.value = true;

    try {
        const res = await axios.post('/api/addSubject', {
            name: newSubjectName.value
        });

        const newSubject = res.data;

        // ✅ update store directly
        cacheStore.subjects.push(newSubject);

        selectedSubject.value = newSubject.id;

        showCreateSubject.value = false;
        newSubjectName.value = '';

    } catch (error) {
        console.error(error);
        alert("Failed to create subject");
    } finally {
        creatingSubject.value = false;
    }
};

function goToLessons() {
    // Ensure the values exist before pushing to avoid the error
    if (!selectedGrade.value || !selectedSubject.value) {
        alert("Please select both a Grade and a Subject");
        return;
    }

    router.push({
        name: 'lessonList',
        params: {
            grade_id: selectedGrade.value,
            subject_id: selectedSubject.value
        }
    });
}

</script>

<style>
.filter-card {
    border-radius: 16px;
    padding: 1.8rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    transition: all 0.2s ease;
}
</style>