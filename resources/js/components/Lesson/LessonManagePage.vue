<template>
    <Layout title="Lesson Management" :loading="loading" :showBack="false">

        <div class="is-flex is-justify-content-flex-end mb-4">
            <button class="button is-primary" @click="createLesson">
                <span class="icon">
                    <PlusIcon />
                </span>
                <span>Add Lesson</span>
            </button>
        </div>

        <div class="box">

            <h2 class="title is-5 mb-4">Lesson Filters</h2>


            <!-- GRADE -->
            <div class="field">
                <label class="label">Grade</label>
                <div class="control" :class="{ 'is-loading': loading }">
                    <div class="select is-fullwidth">
                        <select v-model="selectedGrade" :disabled="loading">
                            <option disabled value="">Select Grade</option>
                            <option v-for="grade in grades" :key="grade.id" :value="grade.id">
                                {{ grade.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SUBJECT -->
            <div class="field">
                <label class="label">Subject</label>
                <div class="control" :class="{ 'is-loading': loading }">
                    <div class="select is-fullwidth">
                        <select v-model="selectedSubject" :disabled="loading">
                            <option disabled value="">Select Subject</option>
                            <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                                {{ subject.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ACTION -->
            <div class="has-text-centered mt-4">
                <button class="button is-primary" :disabled="!selectedGrade || !selectedSubject" @click="goToLessons">
                    Load Lessons
                </button>
            </div>

        </div>

    </Layout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Layout from '../common/Layout.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';
import { useCacheStore } from '../../stores/cache';
import { storeToRefs } from 'pinia';

const router = useRouter();

const cacheStore = useCacheStore();
const { subjects, grades, loading } = storeToRefs(cacheStore);

const selectedGrade = ref("");
const selectedSubject = ref("");

onMounted(async () => {    // One call to rule them all
    await cacheStore.fetchAllMetadata();
});

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

function createLesson() {
    router.push({ name: 'lessonCreate' });
}

</script>