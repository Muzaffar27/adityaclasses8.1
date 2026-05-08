<template>
    <Layout title="Lessons" :loading="loading">
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
                                <div v-if="isSelectedLesson(lesson)" class="card glass-card inline-video-card"
                                    :data-lesson-player="lesson.id">
                                    <div class="video-header p-4 is-flex is-align-items-center">
                                        <div class="header-content">
                                            <h3 class="has-text-white is-size-6-mobile is-size-5-tablet line-clamp-1">
                                                {{ lesson.title }}
                                            </h3>
                                            <p v-if="getSubTopic(lesson)" class="modal-sub-topic line-clamp-1">
                                                {{ getSubTopic(lesson) }}
                                            </p>
                                        </div>
                                        <button class="close-btn ml-auto" @click.stop="closeLesson">
                                            <XMarkIcon class="hero-icon-sm" />
                                        </button>
                                    </div>

                                    <div class="video-container">
                                        <iframe :src="getVideoUrl(lesson)" frameborder="0" allow="autoplay; fullscreen"
                                            allowfullscreen class="video-frame" @load="onVideoLoaded" />

                                        <div v-if="isVideoLoading" class="video-loading">
                                            <div class="loader"></div>
                                            <p class="mt-2">Buffering...</p>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="card glass-card clickable-card fixed-card lesson-card"
                                    @click="hasAccess && openLesson(lesson)">

                                    <div v-if="!hasAccess" class="locked-overlay">
                                        <LockClosedIcon class="hero-icon-sm mr-2" />
                                        <span>Locked</span>
                                    </div>

                                    <div class="card-content lesson-card-content">
                                        <div class="lesson-top-row">
                                            <div class="icon-circle">
                                                <PlayIcon class="hero-icon-sm has-text-primary" />
                                            </div>
                                            <span v-if="getSubTopic(lesson)" class="lesson-sub-topic">
                                                {{ getSubTopic(lesson) }}
                                            </span>
                                        </div>

                                        <div class="lesson-copy">
                                            <p class="lesson-title">{{ lesson.title }}</p>
                                            <p v-if="lesson.description" class="lesson-description">
                                                {{ lesson.description }}
                                            </p>
                                        </div>

                                        <div class="lesson-meta">
                                            <span>Part {{ lesson.part_number || '1' }}</span>
                                            <span class="meta-dot"></span>
                                            <span>{{ formatDuration(lesson.duration) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



            </div>
        </div>
    </Layout>
</template>

<script setup>
import { computed, ref, onMounted, nextTick } from "vue";
import api from "../api";
import { useRoute } from "vue-router";
import Layout from "./common/Layout.vue";
import { PlayIcon, LockClosedIcon, ChevronRightIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
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

const paginatedTopics = computed(() => {
    return groupLessons(lessons.value);
});

onMounted(fetchLessons);

async function fetchLessons() {
    loading.value = true;
    try {
        const { data } = await api.get("/lessons", {
            params: { subject_id: subjectId, grade_id: gradeId },
        });

        let rawLessons = data.lessons || [];

        // ✅ SORT HERE
        rawLessons.sort((a, b) => {
            const getNum = (topic) => {
                const match = topic?.match(/^(\d+)/);
                return match ? parseInt(match[1]) : null;
            };

            const numA = getNum(a.topic);
            const numB = getNum(b.topic);

            // 1. no-number topics come first
            if (numA === null && numB !== null) return -1;
            if (numA !== null && numB === null) return 1;

            // 2. both no-number → keep original order
            if (numA === null && numB === null) return 0;

            // 3. both numbered → sort ascending
            return numA - numB;
        });

        lessons.value = rawLessons;

        hasAccess.value = data.access?.has_access || false;
        requestStatus.value = data.access?.status || null;

    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function toggleTopic(topic) {
    openTopics.value[topic] = !openTopics.value[topic];
}

// watch(lessons, () => {
//     currentPage.value = 1;
// });

const isTopicOpen = (topic) => openTopics.value[topic] === true;

function onVideoLoaded() { isVideoLoading.value = false; }

async function openLesson(lesson) {
    isVideoLoading.value = true;
    selectedLesson.value = lesson;
    isPlaying.value = false;

    const img = new Image();
    img.src = lesson.thumbnail || '';

    await nextTick();
    document.querySelector(`[data-lesson-player="${lesson.id}"]`)?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });
}

function isSelectedLesson(lesson) {
    return selectedLesson.value?.id === lesson.id;
}

function getVideoUrl(lesson) {
    if (!lesson?.vimeo_url) return '';

    const separator = lesson.vimeo_url.includes('?') ? '&' : '?';
    return `${lesson.vimeo_url}${separator}autoplay=1&muted=0&quality=360p`;
}

function getSubTopic(lesson) {
    return lesson?.sub_topic || lesson?.subTopic || '';
}

function groupLessons(list) {
    const map = {};

    list.forEach((lesson) => {
        if (!map[lesson.topic]) {
            map[lesson.topic] = [];
        }
        map[lesson.topic].push(lesson);
    });

    return Object.keys(map).map(topic => ({
        topic,
        lessons: map[topic]
    }));
}

function closeLesson() {
    selectedLesson.value = null;
    isPlaying.value = false;
}

function formatDuration(value) {
    if (!value) return '5 min';

    const text = String(value).trim();
    let hours = 0;
    let minutes = 0;
    let seconds = 0;

    if (text.includes(':')) {
        const parts = text.split(':').map(part => Number(part) || 0);

        if (parts.length === 3) {
            [hours, minutes, seconds] = parts;
        } else if (parts.length === 2) {
            [minutes, seconds] = parts;
        }
    } else {
        hours = Number(text.match(/(\d+)\s*h/i)?.[1] || 0);
        minutes = Number(text.match(/(\d+)\s*m/i)?.[1] || 0);
        seconds = Number(text.match(/(\d+)\s*s/i)?.[1] || 0);

        if (!hours && !minutes && !seconds) {
            minutes = Number(text) || 0;
        }
    }

    const totalSeconds = Math.max(hours, 0) * 3600 + Math.max(minutes, 0) * 60 + Math.max(seconds, 0);

    if (!totalSeconds) return '5 min';

    hours = Math.floor(totalSeconds / 3600);
    minutes = Math.floor((totalSeconds % 3600) / 60);
    seconds = totalSeconds % 60;

    return [
        hours ? `${hours} hr${hours > 1 ? 's' : ''}` : '',
        minutes ? `${minutes} min` : '',
        seconds ? `${seconds} sec` : '',
    ].filter(Boolean).join(' ');
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
.inline-video-card {
    background: #0f172a !important;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 18px 38px rgba(0, 0, 0, 0.35);
    min-height: 184px;
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

.header-content {
    min-width: 0;
}

.modal-sub-topic {
    color: #99f6e4;
    font-size: 0.78rem;
    font-weight: 700;
    margin-top: 0.2rem;
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
    .video-container {
        width: 100%;
        aspect-ratio: 16 / 9;
        flex-shrink: 0;
    }
}

/* ── ANIMATIONS ── */
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
    min-height: 184px;
    height: 100%;
}

.lesson-card {
    display: flex;
    border-radius: 16px;
    background:
        linear-gradient(145deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.025)),
        rgba(15, 23, 42, 0.28) !important;
    border-color: rgba(255, 255, 255, 0.12);
    transition: transform 0.24s ease, border-color 0.24s ease, box-shadow 0.24s ease, background 0.24s ease;
}

.lesson-card:hover {
    transform: translateY(-5px);
    border-color: rgba(45, 212, 191, 0.42);
    box-shadow: 0 16px 34px rgba(0, 0, 0, 0.32);
}

.lesson-card .card-content {
    width: 100%;
}

.lesson-card-content {
    align-items: stretch;
    gap: 14px;
    justify-content: space-between;
    padding: 1.15rem;
    text-align: left;
}

.lesson-top-row {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.lesson-sub-topic {
    min-width: 0;
    max-width: 100%;
    color: #ccfbf1;
    background: rgba(20, 184, 166, 0.13);
    border: 1px solid rgba(45, 212, 191, 0.28);
    border-radius: 999px;
    padding: 0.28rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.2;
    overflow-wrap: anywhere;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lesson-copy {
    min-height: 58px;
}

.lesson-title {
    color: #fff;
    display: -webkit-box;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.3;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    overflow-wrap: anywhere;
}

.lesson-description {
    color: rgba(226, 232, 240, 0.72);
    display: -webkit-box;
    font-size: 0.8rem;
    line-height: 1.35;
    margin-top: 0.35rem;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    overflow-wrap: anywhere;
}

.lesson-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    color: rgba(203, 213, 225, 0.78);
    font-size: 0.78rem;
    font-weight: 600;
}

.meta-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: rgba(148, 163, 184, 0.72);
}

.icon-circle {
    width: 40px;
    height: 40px;
    min-width: 40px;
    background: rgba(79, 70, 229, 0.14);
    border: 1px solid rgba(129, 140, 248, 0.25);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
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
    border-radius: inherit;
}

@media (max-width: 768px) {
    .fixed-card {
        min-height: 172px;
    }

    .lesson-card-content {
        gap: 12px;
        padding: 1rem;
    }

    .lesson-sub-topic {
        font-size: 0.68rem;
        padding: 0.25rem 0.55rem;
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
