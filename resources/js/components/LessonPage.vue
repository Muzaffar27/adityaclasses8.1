<template>
    <Layout title="Lessons" :loading="loading">

        <template #actions>
            <div v-if="!loading">
                <button v-if="hasAccess" class="button is-dark is-light is-rounded">
                    <span class="icon is-small mr-1">✅</span> Access
                </button>
                <button v-else-if="requestStatus === 'pending'" class="button is-warning is-light is-rounded" disabled>
                    <span class="icon is-small mr-1">⏳</span> Pending
                </button>
                <button v-else class="button is-primary is-rounded" @click="requestAccess" :disabled="requestLoading">
                    <span class="icon is-small mr-1">🔒</span> Request
                </button>
            </div>
        </template>

        <Transition name="fade">
            <div v-if="selectedLesson" class="video-modal" @click.self="closeLesson">
                <div class="video-box glass-card">
                    <div class="video-header p-4 is-flex is-align-items-center">
                        <div class="header-content">
                            <h3 class="has-text-white is-size-6-mobile is-size-5-tablet line-clamp-1">
                                {{ selectedLesson.title }}
                            </h3>
                        </div>
                        <button class="close-btn ml-auto" @click="closeLesson">
                            <XMarkIcon class="hero-icon-sm" />
                        </button>
                    </div>

                    <div class="video-container">

                        <!-- THUMBNAIL FIRST -->
                        <div v-if="!isPlaying" class="video-thumbnail" @click="startVideo">
                            <img :src="selectedLesson?.thumbnail || getVimeoThumbnail(selectedLesson?.vimeo_url)" />
                            <div class="play-overlay">▶</div>
                        </div>

                        <!-- LOAD IFRAME ONLY AFTER CLICK -->
                        <iframe v-else :src="videoUrl" frameborder="0" allow="autoplay; fullscreen" allowfullscreen
                            class="video-frame" @load="onVideoLoaded" />

                        <div v-if="isVideoLoading" class="video-loading">
                            <div class="loader"></div>
                            <p class="mt-2">Buffering...</p>
                        </div>
                    </div>

                </div>
            </div>
        </Transition>

        <div v-if="!loading && lessons.length > 0">
            <div v-for="group in paginatedTopics" :key="group.topic">

                <div class="glass-card topic-header p-4 mb-2 clickable-card" @click.stop="toggleTopic(group.topic)">
                    <div class="is-flex is-align-items-center">
                        <h2 class="title is-6 has-text-white mb-0">{{ group.topic }}</h2>
                        <span class="tag is-dark-accent ml-3">
                            {{ group.lessons.length }} lessons
                        </span>

                        <div class="ml-auto">
                            <ChevronRightIcon class="hero-icon-sm arrow-icon"
                                :class="{ 'is-open': isTopicOpen(group.topic) }" />
                        </div>
                    </div>
                </div>

                <div class="accordion-wrapper" :class="{ open: isTopicOpen(group.topic) }">
                    <div class="accordion-inner">
                        <div class="columns is-mobile is-multiline px-2 py-3">
                            <div class="column is-12-mobile is-6-tablet is-4-desktop" v-for="lesson in group.lessons"
                                :key="lesson.id">
                                <div class="card glass-card clickable-card fixed-card lesson-card"
                                    @click="hasAccess && openLesson(lesson)">

                                    <div v-if="!hasAccess" class="locked-overlay">
                                        <LockClosedIcon class="hero-icon-sm mr-2" />
                                        <span>Locked</span>
                                    </div>

                                    <div class="card-content has-text-centered">
                                        <div class="icon-circle mb-3">
                                            <PlayIcon class="hero-icon-sm has-text-primary" />
                                        </div>
                                        <p class="has-text-white is-size-6 mb-1">{{ lesson.title }}</p>
                                        <p class="is-size-7 has-text-grey">
                                            Part {{ lesson.part_number || '1' }} • {{ lesson.duration || '5m' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



            </div>
        </div>

        <div v-if="totalPages > 1" class="pagination-controls pagination-wrapper mt-5">

            <button class="button is-small" @click="prevPage" :disabled="currentPage === 1">
                Prev
            </button>

            <div class="mx-3 is-flex is-align-items-center">

                <div class="mx-3 is-hidden-mobile is-flex is-align-items-center">
                    <template v-for="(page, index) in visiblePages" :key="index">
                        <span v-if="page === '...'" class="mx-2">...</span>

                        <button v-else class="button is-small mx-1" :class="{ 'is-primary': currentPage === page }"
                            @click="goToPage(page)">
                            {{ page }}
                        </button>
                    </template>
                </div>

                <div class="is-hidden-tablet has-text-white">
                    Page {{ currentPage }} / {{ totalPages }}
                </div>
            </div>

            <button class="button is-small" @click="nextPage" :disabled="currentPage === totalPages">
                Next
            </button>
        </div>




    </Layout>
</template>

<script setup>
import { computed, ref, onMounted, watch } from "vue";
import api from "../api";
import { useRoute, useRouter } from "vue-router";
import Layout from "./common/Layout.vue";
import { PlayIcon, LockClosedIcon, ChevronRightIcon, XMarkIcon, FolderOpenIcon } from '@heroicons/vue/24/outline';
import { Pagination } from "../composables/Pagination";

const route = useRoute();
const router = useRouter();
const subjectId = route.params.subjectId;
const gradeId = route.params.gradeId;

const lessons = ref([]);
const loading = ref(false);
const isVideoLoading = ref(false);
const requestLoading = ref(false);
const hasAccess = ref(false);
const requestStatus = ref(null);
const selectedLesson = ref(null);
const openTopics = ref({});
const isPlaying = ref(false);

const {
    paginatedTopics,
    currentPage,
    totalPages,
    visiblePages,
    goToPage,
    nextPage,
    prevPage
} = Pagination(lessons, 5, { type: 'grouped' });

onMounted(fetchLessons);

async function fetchLessons() {
    loading.value = true;
    try {
        const { data } = await api.get("/lessons", {
            params: { subject_id: subjectId, grade_id: gradeId },
        });
        lessons.value = data.lessons || [];
        hasAccess.value = data.access?.has_access || false;
        requestStatus.value = data.access?.status || null;
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
}

const videoUrl = computed(() => {
    if (!selectedLesson.value?.vimeo_url) return '';
    return selectedLesson.value.vimeo_url + '?autoplay=1&muted=0&quality=360p';
});

function toggleTopic(topic) {
    openTopics.value[topic] = !openTopics.value[topic];
}

watch(lessons, () => {
    currentPage.value = 1;
});

const isTopicOpen = (topic) => openTopics.value[topic] === true;

function onVideoLoaded() { isVideoLoading.value = false; }

function openLesson(lesson) {
    selectedLesson.value = lesson;
    isPlaying.value = false;

    const img = new Image();
    img.src = lesson.thumbnail || '';
}

function closeLesson() {
    selectedLesson.value = null;
    isPlaying.value = false;
}

function startVideo() {
    isPlaying.value = true;
    isVideoLoading.value = true;
}
async function requestAccess() {
    requestLoading.value = true;
    try {
        await api.post("/lesson-access/request", { subject_id: subjectId, grade_id: gradeId });
        requestStatus.value = "pending";
        await fetchLessons();
    } catch (e) { console.error(e); }
    finally { requestLoading.value = false; }
}

function getVimeoThumbnail(url) {
    if (!url) return "https://via.placeholder.com/800x450?text=Video";

    const match = url.match(/video\/(\d+)|vimeo\.com\/(\d+)/);
    const id = match ? (match[1] || match[2]) : null;

    if (!id) return "https://via.placeholder.com/800x450?text=Video";

    return `https://vumbnail.com/${id}.jpg`;
}

</script>

<style scoped>
/* ── MOBILE OPTIMIZED MODAL ── */
.video-modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.9);
    z-index: 2000;
    display: flex;
    align-items: center;
    /* Centers vertically */
    justify-content: center;
    /* Centers horizontally */
    padding: 20px;
}

.video-box {
    width: 100%;
    max-width: 900px;
    max-height: 90vh;

    /* Slightly wider for big screens */
    height: auto;
    /* IMPORTANT: Shrinks to content */
    background: #0f172a !important;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.video-container {
    position: relative;
    width: 100%;
    /* This maintains the 16:9 shape strictly */
    aspect-ratio: 16 / 9;
    background: #000;
}

.video-frame {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.close-btn {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #fff;
    padding: 8px;
    border-radius: 50%;
    display: flex;
    cursor: pointer;
}

.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;

    line-clamp: 1;
    /* ✅ ADD THIS */

    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* ── MOBILE BREAKPOINTS ── */
@media (max-width: 768px) {
    .video-modal {
        padding: 0;
        align-items: flex-start;
        /* Push to top on mobile */
    }

    .video-box {
        width: 100%;
        max-height: 100vh;
        height: auto;
        border-radius: 0;
        display: flex;
        flex-direction: column;
    }

    .video-container {
        width: 100%;
        aspect-ratio: 16 / 9;
        flex-shrink: 0;
    }

    .video-header {
        position: sticky;
        top: 0;
        z-index: 20;
        background: #0f172a;
    }
}

/* ── ANIMATIONS ── */
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}

/* ── ACCORDION ── */
.accordion-wrapper {
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    transition: max-height 0.4s ease, opacity 0.3s ease;
}

.accordion-wrapper.open {
    max-height: 3000px;
    opacity: 1;
}

.topic-header {
    background: rgba(255, 255, 255, 0.04) !important;
    border-radius: 12px;
}

.arrow-icon {
    transition: transform 0.3s ease;
}

.arrow-icon.is-open {
    transform: rotate(90deg);
}

.fixed-card {
    height: 140px;
}

.icon-circle {
    width: 40px;
    height: 40px;
    background: rgba(79, 70, 229, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.locked-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(2px);
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}

@media (max-width: 768px) {
    .fixed-card {
        height: 130px;
    }
}

.video-loading {
    /* 1. Take up the full space of the container */
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;

    /* 2. Create the dark background */
    background: #0f172a;
    z-index: 10;
    /* Stay above the iframe while loading */

    /* 3. Center the content (The Magic) */
    display: flex;
    flex-direction: column;
    /* Stack loader and text vertically */
    align-items: center;
    /* Center horizontally */
    justify-content: center;
    /* Center vertically */

    color: #fff;
}

/* Optional: Make the Bulma loader look better */
.video-loading .loader {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(255, 255, 255, 0.1);
    border-top-color: #4f46e5;
    /* Use your primary purple/blue */
    border-radius: 50%;
    margin-bottom: 1rem;
    /* Space between loader and text */
}

.video-thumbnail {
    position: absolute;
    /* 🔥 important */
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    transition: opacity 0.3s ease;

}

.video-thumbnail img {

    width: 100%;
    height: 100%;
    object-fit: cover;
    /* 🔥 prevents 3/4 weird sizing */
}

/* Center the play button */
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

    display: flex;
    align-items: center;
    justify-content: center;
}

.video-frame {
    transition: opacity 0.3s ease;
}
</style>