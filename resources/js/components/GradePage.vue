<template>
    <div class="section px-4 py-5 main-content-wrapper mobile-container">

        <div class="container is-fluid">

            <div class="header-bar">
                <div class="back-btn" @click="goBack">
                    <ArrowLeftIcon class="back-icon" />
                </div>

                <h1 class="title is-4 mb-0">School Grades</h1>
            </div>

            <div v-if="loading">
                <Loader />
            </div>

            <div v-else class="columns is-mobile is-multiline">
                <div class="column is-6-mobile is-4-tablet is-3-desktop" v-for="grade in grades" :key="grade.id">
                    <div class="card grade-card" @click="goToLesson(grade.id)">
                        <div class="card-content p-4 has-text-centered">
                            <div class="icon-circle mb-3" :style="{ background: getColor(grade.id) }">
                                🎓
                            </div>
                            <p class="grade-name">{{ grade.name }}</p>
                            <p class="subtitle is-7 has-text-grey mb-0">
                                {{ grade.lessons_count }} lessons
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!loading && grades.length === 0" class="has-text-centered mt-6">
                <p class="has-text-grey">No grades available.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { useRoute, useRouter } from "vue-router";
import Loader from "./common/Loader.vue";
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();

const subjectId = route.params.id;
const grades = ref([]);
const loading = ref(false);

onMounted(() => {
    fetchGrades();
});

async function fetchGrades() {

    loading.value = true;

    try {
        const { data } = await axios.get("/api/grades", {
            params: {
                subject_id: subjectId
            }
        });

        grades.value = data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function goToLesson(gradeId) {
    router.push({
        name: "lesson",
        params: {
            gradeId: gradeId,
            subjectId: subjectId
        }
    });
}

function goBack() {
    if (window.history.length > 1) {
        router.back();
    } else {
        router.push('/subjects');
    }
}

function getColor(id) {
    const colors = ["#e3f2fd", "#fce4ec", "#e8f5e9", "#fff3e0"];
    return colors[id % colors.length];
}


</script>

<style scoped>
.main-content-wrapper {
    min-height: 100vh;
}

@media (min-width: 1024px) {
    .main-content-wrapper {
        padding-left: 2rem;
        padding-right: 2rem;
    }
}

.mobile-container {
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.grade-card {
    cursor: pointer;
    border-radius: 16px;
    transition: all 0.25s ease;
    padding: 10px;
    background-color: #1e2a38;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    height: 100%;
    display: flex;
    flex-direction: column;
}

.grade-card:hover {
    transform: translateY(-6px) scale(1.02);
    box-shadow: 0 2px 8px rgba(79, 70, 220, 0.8);
}

.grade-card:active {
    transform: scale(0.95);
    background-color: #4f46e5;
}

.grade-name {
    font-weight: 700;
    font-size: 1.1rem;
    line-height: 1.2;
    margin-bottom: 4px;
    color: #f9f9f9;
}

.icon-circle {
    width: 50px;
    height: 50px;
    margin: 0 auto;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.header-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.back-btn {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 12px;
    background: rgba(79, 70, 229, 0.1);

    cursor: pointer;
    transition: all 0.2s ease;

    backdrop-filter: blur(6px);
}

/* Icon itself */
.back-icon {
    width: 20px;
    height: 20px;
    color: #4f46e5;
}

/* Hover (desktop) */
.back-btn:hover {
    transform: translateX(-4px);
    background: rgba(79, 70, 229, 0.2);
}

/* Mobile tap feel */
.back-btn:active {
    transform: scale(0.92);
}
</style>