<template>
    <Layout title="Lessons" :loading="loading" @back="goBack">

        <template #actions>

            <div class="mr-2">
                <button v-if="hasAccess" class="button is-dark is-light">
                    ✅ Access
                </button>

                <button v-else-if="requestStatus === 'pending'" class="button is-warning is-light" disabled>
                    ⏳ Pending
                </button>

                <button v-else class="button is-primary" @click="requestAccess" :disabled="requestLoading">
                    🔒 Request
                </button>
            </div>
        </template>

        <!-- VIDEO MODAL -->
        <div v-if="selectedLesson" class="video-modal">
            <div class="video-box">

                <div class="video-header">
                    <h3>{{ selectedLesson.title }}</h3>

                    <button class="close-btn" @click="closeLesson">✕</button>
                </div>

                <div v-if="!isPlaying" class="video-thumbnail" @click="startVideo">
                    <img :src="selectedLesson.thumbnail || getVimeoThumbnail(selectedLesson.vimeo_url)" />
                    <div class="play-overlay">▶</div>
                </div>

                <iframe v-if="isPlaying" :src="videoUrl" loading="lazy" frameborder="0" allow="autoplay; fullscreen"
                    allowfullscreen class="video-frame" @load="onVideoLoaded" />

                <div v-if="isVideoLoading" class="video-loading">
                    Loading video...
                </div>

            </div>
        </div>


        <!-- GRID -->
        <div v-else class="columns is-mobile is-multiline">

            <div class="column is-12" v-for="lesson in filteredLessons" :key="lesson.id">

                <div class="card lesson-card" @click="hasAccess && openLesson(lesson)">

                    <div v-if="!hasAccess && !selectedLesson" class="locked-overlay">
                        🔒 Locked
                    </div>

                    <div class="card-content p-4">

                        <div class="lesson-top">
                            <p class="lesson-title">{{ lesson.title }}</p>
                            <span class="lesson-part">Part {{ lesson.part_number || '-' }}</span>
                        </div>

                        <p class="lesson-topic">{{ lesson.topic }}</p>


                        <div class="lesson-bottom">
                            <span class="duration">{{ lesson.duration || '—' }}</span>

                            <button class="watch-btn">
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

    </Layout>
</template>

<script setup>
import { computed, ref, onMounted } from "vue";
import api from "../api";
import { useRoute, useRouter } from "vue-router";
import Layout from "./common/Layout.vue";

const route = useRoute();
const router = useRouter();

const subjectId = route.params.subjectId;
const gradeId = route.params.gradeId;

const lessons = ref([]);
const loading = ref(false);
const isPlaying = ref(false);
const isVideoLoading = ref(false);
const requestLoading = ref(false);

const hasAccess = ref(false);
const requestStatus = ref(null);

const search = ref("");
const selectedLesson = ref(null);

onMounted(() => {
    fetchLessons();
});

async function fetchLessons() {
    loading.value = true;

    try {
        const { data } = await api.get("/lessons", {
            params: {
                subject_id: subjectId,
                grade_id: gradeId,
            },
        });

        lessons.value = data.lessons || [];
        hasAccess.value = data.access?.has_access || false;
        requestStatus.value = data.access?.status || null;

    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

const videoUrl = computed(() => {
    if (!selectedLesson.value?.vimeo_url) return '';

    // Add performance params
    return selectedLesson.value.vimeo_url +
        '?autoplay=1&muted=1&quality=360p';
});

const filteredLessons = computed(() => {
    if (!search.value) return lessons.value;

    return lessons.value.filter((lesson) =>
        lesson.title.toLowerCase().includes(search.value.toLowerCase()) ||
        lesson.topic?.toLowerCase().includes(search.value.toLowerCase())
    );
});

function getVimeoThumbnail(url) {
    if (!url) return "https://via.placeholder.com/800x450?text=Video";

    // Works for both vimeo.com and player.vimeo.com
    const match = url.match(/video\/(\d+)|vimeo\.com\/(\d+)/);
    const id = match ? (match[1] || match[2]) : null;

    if (!id) return "https://via.placeholder.com/800x450?text=Video";

    return `https://vumbnail.com/${id}.jpg`;
}

function startVideo() {
    isPlaying.value = true;
    isVideoLoading.value = true;

}

function onVideoLoaded() {
    isVideoLoading.value = false;
}

function openLesson(lesson) {
    if (!hasAccess.value) return;

    if (!lesson?.vimeo_url) return;

    selectedLesson.value = lesson;
    isPlaying.value = false;
}

function closeLesson() {
    selectedLesson.value = null;
    isPlaying.value = false;
}

async function requestAccess() {
    try {
        requestLoading.value = true;

        await api.post("/lesson-access/request", {
            subject_id: subjectId,
            grade_id: gradeId,
        });

        requestStatus.value = "pending";
        hasAccess.value = false;
        await fetchLessons();

    } catch (e) {
        console.error(e);
    } finally {
        requestLoading.value = false;
    }
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

/* LESSON CARD (consistent style with grade-card) */
.lesson-card {
    position: relative;
    cursor: pointer;
    border-radius: 16px;
    transition: all 0.25s ease;
    background-color: #1e2a38;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: all 0.25s ease;
}

.lesson-card:hover {
    cursor: pointer;
    transform: translateY(-4px);
    box-shadow: 0 2px 8px rgba(79, 70, 220, 0.8);
}

.lesson-card:has(.locked-overlay) {
    cursor: not-allowed;
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
    position: relative;

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
    animation: fadeIn 0.3s ease;
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

.video-thumbnail {
    position: relative;
    cursor: pointer;
    min-height: 200px;
    background: #000;
}

.video-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.video-frame,
.video-thumbnail {
    width: 100%;
    aspect-ratio: 16 / 9;
    background: #000;
}

.play-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 50px;
    color: white;
    background: rgba(0, 0, 0, 0.6);
    border-radius: 50%;
    padding: 10px 20px;
}

/* mobile */
@media (max-width: 768px) {
    .video-frame {
        height: 250px;
    }
}

.video-loading {
    position: absolute;
    inset: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    background: rgba(0, 0, 0, 0.85);

    color: white;
    font-size: 14px;

    aspect-ratio: 16 / 9;
}

.is-disabled {
    opacity: 0.5;
    pointer-events: none;
}

.locked-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    border-radius: 16px;
}
</style>