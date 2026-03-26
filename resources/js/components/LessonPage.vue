<template>
    <div class="section px-4 py-5 main-content-wrapper mobile-container">

        <div class="container is-fluid">

            <!-- HEADER (same as Grades page) -->
            <div class="header-bar">
                <div class="back-btn" @click="goBack">
                    <ArrowLeftIcon class="back-icon" />
                </div>

                <h1 class="title is-4 mb-0">
                    Lessons
                </h1>
            </div>

            <div class="search-box mb-4">
                <input v-model="search" type="text" class="input search-input" placeholder="Search lessons..." />
            </div>

            <div v-if="selectedLesson" class="video-modal">

                <div class="video-box">

                    <div class="video-header">
                        <h3>{{ selectedLesson.title }}</h3>

                        <button class="close-btn" @click="closeLesson">
                            ✕
                        </button>
                    </div>

                    <iframe :src="selectedLesson.vimeo_url" frameborder="0" allow="autoplay; fullscreen" allowfullscreen
                        class="video-frame"></iframe>

                </div>

            </div>
            <!-- LOADER -->
            <div v-if="loading">
                <Loader />
            </div>

            <!-- LESSON GRID (same structure style as grades) -->
            <div v-else class="columns is-mobile is-multiline">

                <div class="column is-12" v-for="lesson in filteredLessons" :key="lesson.id">

                    <div class="card lesson-card">

                        <div class="card-content p-4">

                            <div class="lesson-top">
                                <p class="lesson-title">
                                    {{ lesson.title }}
                                </p>

                                <span class="lesson-part">
                                    Part {{ lesson.part_number || '-' }}
                                </span>
                            </div>

                            <p class="lesson-topic">
                                {{ lesson.topic }}
                            </p>

                            <div class="lesson-bottom">
                                <span class="duration">
                                    {{ lesson.duration || '—' }}
                                </span>

                                <button class="watch-btn" @click="openLesson(lesson)">
                                    ▶ Watch
                                </button>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EMPTY STATE -->
            <div v-if="!loading && lessons.length === 0" class="has-text-centered mt-6">
                <p class="has-text-grey">No lessons available.</p>
            </div>

        </div>

    </div>
</template>

<script setup>
import { computed, ref, onMounted } from "vue";
import axios from "axios";
import { useRoute, useRouter } from "vue-router";
import Loader from "./common/Loader.vue";
import { ArrowLeftIcon } from "@heroicons/vue/24/outline";

const route = useRoute();
const router = useRouter();

const subjectId = route.params.subjectId;
const gradeId = route.params.gradeId;

const lessons = ref([]);
const loading = ref(false);

const search = ref("");
const selectedLesson = ref(null);

onMounted(() => {
    fetchLessons();
});

async function fetchLessons() {
    loading.value = true;

    try {
        const { data } = await axios.get("/api/lessons", {
            params: {
                subject_id: subjectId,
                grade_id: gradeId,
            },
        });

        lessons.value = data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

const filteredLessons = computed(() => {
    if (!search.value) return lessons.value;

    return lessons.value.filter((lesson) =>
        lesson.title.toLowerCase().includes(search.value.toLowerCase()) ||
        lesson.topic?.toLowerCase().includes(search.value.toLowerCase())
    );
});

function openLesson(lesson) {
    selectedLesson.value = lesson;
}

function closeLesson() {
    selectedLesson.value = null;
}
function goBack() {
    router.back();
}
</script>

<style scoped>
.main-content-wrapper {
    min-height: 100vh;
}

.mobile-container {
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

/* SAME HEADER STYLE (reused) */
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

.back-btn:hover {
    transform: translateX(-4px);
    background: rgba(79, 70, 229, 0.2);
}

.back-icon {
    width: 20px;
    height: 20px;
    color: #4f46e5;
}

/* LESSON CARD (consistent style with grade-card) */
.lesson-card {
    cursor: pointer;
    border-radius: 16px;
    transition: all 0.25s ease;
    background-color: #1e2a38;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.lesson-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 2px 8px rgba(79, 70, 220, 0.8);
}

/* layout inside card */
.lesson-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.lesson-title {
    font-weight: 700;
    color: #fff;
}

.lesson-part {
    font-size: 0.75rem;
    color: #cbd5e1;
    background: rgba(79, 70, 229, 0.15);
    padding: 2px 8px;
    border-radius: 999px;
}

.lesson-topic {
    font-size: 0.85rem;
    color: #aab4c0;
    margin-bottom: 10px;
}

/* bottom row */
.lesson-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.duration {
    font-size: 0.8rem;
    color: #cbd5e1;
}

.watch-btn {
    background: #4f46e5;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
}

.search-box {
    margin-bottom: 15px;
}

.search-input {
    border-radius: 12px;
    background-color: #1e2a38;
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #fff;
}

.search-input::placeholder {
    color: #aab4c0;
}

/* focus */
.search-input:focus {
    border-color: #4f46e5;
    box-shadow: none;
}

/* MOBILE RESPONSIVE */
@media (max-width: 768px) {
    .lesson-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }

    .lesson-bottom {
        margin-top: 8px;
    }
}

.video-modal {
    position: fixed;
    top: 0;
    left: 0;

    width: 100%;
    height: 100%;

    background: rgba(0, 0, 0, 0.75);

    display: flex;
    align-items: center;
    justify-content: center;

    z-index: 999;
    animation: fadeIn 0.2s ease;
}

.video-box {
    width: 90%;
    max-width: 900px;
    background: #1e2a38;
    border-radius: 16px;
    overflow: hidden;
    animation: popIn 0.25s ease;
}

.video-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    padding: 12px 16px;
    color: white;
}

.close-btn {
    background: transparent;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
}

.video-frame {
    width: 100%;
    height: 500px;
    border: none;
}

/* animations */
@keyframes fadeIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

@keyframes popIn {
    from {
        transform: scale(0.95);
        opacity: 0;
    }

    to {
        transform: scale(1);
        opacity: 1;
    }
}

/* mobile */
@media (max-width: 768px) {
    .video-frame {
        height: 250px;
    }
}
</style>