<template>
    <Layout title="Lesson Management" :loading="loading" :showBack="false">

        <div class="box">

            <h2 class="title is-5 mb-4">Lesson Filters</h2>

            <!-- GRADE -->
            <div class="field">
                <label class="label">Grade</label>
                <div class="control">
                    <div class="select is-fullwidth">
                        <select v-model="selectedGrade">
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
                <div class="control">
                    <div class="select is-fullwidth">
                        <select v-model="selectedSubject">
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
import api from '../../api';
import Layout from '../common/Layout.vue';

const router = useRouter();

const grades = ref([]);
const subjects = ref([]);

const selectedGrade = ref("");
const selectedSubject = ref("");

const loading = ref(false);

onMounted(async () => {
    loading.value = true;

    try {
        const [gradesRes, subjectsRes] = await Promise.all([
            api.get('/grades'),
            api.get('/subjects')
        ]);

        grades.value = gradesRes.data;
        subjects.value = subjectsRes.data;

    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
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
</script>