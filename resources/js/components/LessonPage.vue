<template>
    <Layout title="Lessons" :loading="loading">
        <div v-if="!loading && lessons.length > 0">
            <div class="lesson-finder glass-card mb-4">
                <div class="finder-main">
                    <div class="finder-summary">
                        <p>
                            Showing <strong>{{ filteredLessons.length }}</strong> of <strong>{{ lessons.length
                                }}</strong>
                            lessons
                        </p>
                        <button v-if="hasActiveFilters" class="button is-small is-dark-accent" @click="clearFilters">
                            Clear
                        </button>
                    </div>

                    <div class="search-control">
                        <MagnifyingGlassIcon class="search-icon" />
                        <input class="input finder-input" v-model="searchQuery"
                            placeholder="Search topics or subtopics">
                    </div>
                </div>
            </div>

            <div v-if="filteredLessons.length === 0" class="empty-results glass-card p-4 has-text-centered">
                <p class="has-text-white has-text-weight-semibold mb-1">No lessons found</p>
                <p class="has-text-grey-light is-size-7">Try a different topic or subtopic.</p>
            </div>

            <div v-for="group in paginatedTopics" :key="group.topic">

                <div class="glass-card topic-header p-4 mb-2 clickable-card" @click.stop="toggleTopic(group.topic)">
                    <div class="topic-header-row">
                        <h2 class="title is-6 has-text-white mb-0 header-list-title">{{ group.topic }}</h2>
                        <span class="tag is-dark-accent lesson-count-tag">
                            {{ group.lessons.length }} lessons
                        </span>

                        <div class="header-arrow">
                            <ChevronRightIcon class="hero-icon-sm arrow-icon"
                                :class="{ 'is-open': isTopicOpen(group.topic) }" />
                        </div>
                    </div>
                </div>

                <div class="accordion-wrapper" :class="{ open: isTopicOpen(group.topic) }">
                    <div class="accordion-inner">
                        <div v-if="group.hasSubTopics" class="subtopic-list px-2 py-3">
                            <div v-for="subtopic in group.subtopics" :key="subtopic.key" class="subtopic-block">
                                <div class="glass-card subtopic-header clickable-card"
                                    @click.stop="toggleSubTopic(group.topic, subtopic.name)">
                                    <div class="subtopic-header-row">
                                        <div class="subtopic-marker"></div>
                                        <h3 class="subtopic-title mb-0 header-list-title">{{ subtopic.name }}</h3>
                                        <span class="tag is-dark-accent lesson-count-tag">
                                            {{ subtopic.lessons.length }} lessons
                                        </span>

                                        <div class="header-arrow">
                                            <ChevronRightIcon class="hero-icon-sm arrow-icon"
                                                :class="{ 'is-open': isSubTopicOpen(group.topic, subtopic.name) }" />
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-wrapper"
                                    :class="{ open: isSubTopicOpen(group.topic, subtopic.name) }">
                                    <div class="accordion-inner">
                                        <div class="columns is-mobile is-multiline px-2 py-3">
                                            <div class="column is-12-mobile is-6-tablet is-4-desktop"
                                                v-for="lesson in subtopic.lessons" :key="lesson.id">
                                                <div v-if="isSelectedLesson(lesson)"
                                                    class="card glass-card inline-video-card"
                                                    :data-lesson-player="lesson.id">
                                                    <div class="video-header p-4 is-flex is-align-items-center">
                                                        <div class="header-content">
                                                            <h3
                                                                class="has-text-white is-size-6-mobile is-size-5-tablet line-clamp-1">
                                                                {{ lesson.title }}
                                                            </h3>
                                                            <span v-if="videoMode === 'answer'" class="answer-video-badge">Answer video</span>
                                                            <p v-if="getSubTopic(lesson)"
                                                                class="modal-sub-topic line-clamp-1">
                                                                {{ getSubTopic(lesson) }}
                                                            </p>
                                                        </div>
                                                        <button class="close-btn ml-auto" @click.stop="closeLesson">
                                                            <XMarkIcon class="hero-icon-sm" />
                                                        </button>
                                                    </div>

                                                    <div class="video-container">
                                                        <iframe :key="`${lesson.id}-${videoMode}`"
                                                            :src="getVideoUrl(lesson)" frameborder="0"
                                                            allow="autoplay; fullscreen; picture-in-picture"
                                                            allowfullscreen webkitallowfullscreen mozallowfullscreen
                                                            class="video-frame" @load="onVideoLoaded" />

                                                        <div v-if="isVideoLoading" class="video-loading">
                                                            <div class="loader"></div>
                                                            <p class="mt-2">Buffering...</p>
                                                        </div>
                                                    </div>
                                                    <LessonPdfResources :lesson="lesson"
                                                        :showing-answer-video="videoMode === 'answer'"
                                                        @play-answer-video="playAnswerVideo(lesson)"
                                                        @play-lesson-video="playLessonVideo" />
                                                </div>

                                                <div v-else
                                                    class="card glass-card clickable-card fixed-card lesson-card"
                                                    :class="{ 'pdf-only-card': isPdfOnlyLesson(lesson) }"
                                                    @click="hasAccess && openPrimaryLesson(lesson)">

                                                    <div v-if="!hasAccess" class="locked-overlay">
                                                        <LockClosedIcon class="hero-icon-sm mr-2" />
                                                        <span>Locked</span>
                                                    </div>

                                                    <div class="card-content lesson-card-content">
                                                        <div class="lesson-copy">
                                                            <div class="lesson-heading">
                                                                <p class="lesson-title">{{ lesson.title }}</p>
                                                                <span v-if="lesson.part_number" class="part-badge">
                                                                    Part {{ lesson.part_number }}
                                                                </span>
                                                            </div>
                                                            <p v-if="lesson.description" class="lesson-description">
                                                                {{ lesson.description }}
                                                            </p>
                                                        </div>

                                                        <div class="lesson-meta">
                                                            <template v-if="isPdfOnlyLesson(lesson)">
                                                                <DocumentTextIcon class="lesson-format-icon" />
                                                                <span class="pdf-lesson-format">PDF lesson</span>
                                                            </template>
                                                            <template v-else>
                                                                <span class="duration-label">Duration:</span>
                                                                <span
                                                                :class="{ 'duration-missing': !hasDuration(lesson.duration) }">
                                                                    {{ formatDuration(lesson.duration) }}
                                                                </span>
                                                            </template>
                                                        </div>

                                                        <div v-if="isPdfOnlyLesson(lesson)"
                                                            class="icon-circle pdf-icon-circle">
                                                            <DocumentTextIcon class="hero-icon-sm" />
                                                        </div>
                                                        <div v-else-if="lesson.vimeo_url" class="icon-circle">
                                                            <PlayIcon class="hero-icon-sm has-text-primary" />
                                                        </div>

                                                    </div>

                                                    <div v-if="hasAccess" class="lesson-card-actions" @click.stop>
                                                        <button v-if="lesson.vimeo_url" type="button" class="lesson-play-action"
                                                            @click="openLesson(lesson)">
                                                            <PlayIcon />
                                                            <span>Watch lesson</span>
                                                        </button>
                                                        <LessonPdfResources :ref="el => setResourceRef(lesson.id, el)"
                                                            :lesson="lesson" compact return-label="lessons"
                                                            @play-answer-video="openLesson(lesson, 'answer')"
                                                            @play-lesson-video="openLesson(lesson)" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="columns is-mobile is-multiline px-2 py-3">
                            <div class="column is-12-mobile is-6-tablet is-4-desktop" v-for="lesson in group.lessons"
                                :key="lesson.id">
                                <div v-if="isSelectedLesson(lesson)" class="card glass-card inline-video-card"
                                    :data-lesson-player="lesson.id">
                                    <div class="video-header p-4 is-flex is-align-items-center">
                                        <div class="header-content">
                                            <h3 class="has-text-white is-size-6-mobile is-size-5-tablet line-clamp-1">
                                                {{ lesson.title }}
                                            </h3>
                                            <span v-if="videoMode === 'answer'" class="answer-video-badge">Answer video</span>
                                        </div>
                                        <button class="close-btn ml-auto" @click.stop="closeLesson">
                                            <XMarkIcon class="hero-icon-sm" />
                                        </button>
                                    </div>

                                    <div class="video-container">
                                        <iframe :key="`${lesson.id}-${videoMode}`"
                                            :src="getVideoUrl(lesson)" frameborder="0"
                                            allow="autoplay; fullscreen; picture-in-picture" allowfullscreen
                                            webkitallowfullscreen mozallowfullscreen class="video-frame"
                                            @load="onVideoLoaded" />

                                        <div v-if="isVideoLoading" class="video-loading">
                                            <div class="loader"></div>
                                            <p class="mt-2">Buffering...</p>
                                        </div>
                                    </div>
                                    <LessonPdfResources :lesson="lesson"
                                        :showing-answer-video="videoMode === 'answer'"
                                        @play-answer-video="playAnswerVideo(lesson)"
                                        @play-lesson-video="playLessonVideo" />
                                </div>

                                <div v-else class="card glass-card clickable-card fixed-card lesson-card"
                                    :class="{ 'pdf-only-card': isPdfOnlyLesson(lesson) }"
                                    @click="hasAccess && openPrimaryLesson(lesson)">

                                    <div v-if="!hasAccess" class="locked-overlay">
                                        <LockClosedIcon class="hero-icon-sm mr-2" />
                                        <span>Locked</span>
                                    </div>

                                    <div class="card-content lesson-card-content">
                                        <div class="lesson-copy">
                                            <div class="lesson-heading">
                                                <p class="lesson-title">{{ lesson.title }}</p>
                                                <span v-if="lesson.part_number" class="part-badge">
                                                    Part {{ lesson.part_number }}
                                                </span>
                                            </div>
                                            <p v-if="lesson.description" class="lesson-description">
                                                {{ lesson.description }}
                                            </p>
                                        </div>

                                        <div class="lesson-meta">
                                            <template v-if="isPdfOnlyLesson(lesson)">
                                                <DocumentTextIcon class="lesson-format-icon" />
                                                <span class="pdf-lesson-format">PDF lesson</span>
                                            </template>
                                            <template v-else>
                                                <span class="duration-label">Duration:</span>
                                                <span :class="{ 'duration-missing': !hasDuration(lesson.duration) }">
                                                    {{ formatDuration(lesson.duration) }}
                                                </span>
                                            </template>
                                        </div>

                                        <div v-if="isPdfOnlyLesson(lesson)"
                                            class="icon-circle pdf-icon-circle">
                                            <DocumentTextIcon class="hero-icon-sm" />
                                        </div>
                                        <div v-else-if="lesson.vimeo_url" class="icon-circle">
                                            <PlayIcon class="hero-icon-sm has-text-primary" />
                                        </div>

                                    </div>

                                    <div v-if="hasAccess" class="lesson-card-actions" @click.stop>
                                        <button v-if="lesson.vimeo_url" type="button" class="lesson-play-action"
                                            @click="openLesson(lesson)">
                                            <PlayIcon />
                                            <span>Watch lesson</span>
                                        </button>
                                        <LessonPdfResources :ref="el => setResourceRef(lesson.id, el)"
                                            :lesson="lesson" compact return-label="lessons"
                                            @play-answer-video="openLesson(lesson, 'answer')"
                                            @play-lesson-video="openLesson(lesson)" />
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
import { computed, ref, onMounted, onBeforeUnmount, nextTick, watch } from "vue";
import api from "../api";
import { useRoute } from "vue-router";
import Layout from "./common/Layout.vue";
import LessonPdfResources from "./LessonPdfResources.vue";
import { getVimeoPlayerUrl } from "../utils/vimeo";
import { PlayIcon, LockClosedIcon, ChevronRightIcon, XMarkIcon, MagnifyingGlassIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

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
const resourceRefs = new Map();
const videoMode = ref('lesson');
const openTopics = ref({});
const openSubTopics = ref({});
const isPlaying = ref(false);
const videoHistoryEntryOpen = ref(false);
const searchQuery = ref('');
let activeVimeoFrame = null;
let resumeApplied = false;
let progressLoadPromise = Promise.resolve(0);
let latestPlayback = { seconds: 0, duration: 0 };
let lastSavedSecond = -30;
let progressSaveInFlight = false;

const paginatedTopics = computed(() => {
    return groupLessons(filteredLessons.value);
});

const hasActiveFilters = computed(() => {
    return Boolean(searchQuery.value.trim());
});

const filteredLessons = computed(() => {
    const query = normalizeSearchText(searchQuery.value);

    return lessons.value.filter((lesson) => {
        if (!query) return true;

        return normalizeSearchText([
            lesson.topic,
            getSubTopic(lesson),
        ].filter(Boolean).join(' ')).includes(query);
    });
});

watch(searchQuery, () => {
    if (selectedLesson.value) {
        clearSelectedLesson();
    }

    openTopics.value = {};
    openSubTopics.value = {};
});

onMounted(() => {
    fetchLessons();
    window.addEventListener('popstate', handleHistoryBack);
    window.addEventListener('message', handleVimeoMessage);
    document.addEventListener('fullscreenchange', handleVideoFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleVideoFullscreenChange);
});

onBeforeUnmount(() => {
    void saveCurrentProgress(true);
    detachVimeoPlayer();
    window.removeEventListener('popstate', handleHistoryBack);
    window.removeEventListener('message', handleVimeoMessage);
    document.removeEventListener('fullscreenchange', handleVideoFullscreenChange);
    document.removeEventListener('webkitfullscreenchange', handleVideoFullscreenChange);
    releaseVideoOrientation();
});

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

        const resumeLessonId = Number(route.query.resume);
        const resumeLesson = rawLessons.find(lesson => lesson.id === resumeLessonId);

        if (hasAccess.value && resumeLesson?.vimeo_url) {
            openTopics.value[resumeLesson.topic || 'General'] = true;
            openSubTopics.value[getSubTopicKey(
                resumeLesson.topic || 'General',
                getSubTopicLabel(resumeLesson)
            )] = true;
            await openLesson(resumeLesson, route.query.video === 'answer' ? 'answer' : 'lesson');
        }

    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function toggleTopic(topic) {
    openTopics.value[topic] = !openTopics.value[topic];
}

function getSubTopicKey(topic, subtopic) {
    return `${topic}::${subtopic}`;
}

function toggleSubTopic(topic, subtopic) {
    const key = getSubTopicKey(topic, subtopic);
    openSubTopics.value[key] = !openSubTopics.value[key];
}

// watch(lessons, () => {
//     currentPage.value = 1;
// });

const isTopicOpen = (topic) => openTopics.value[topic] === true;
const isSubTopicOpen = (topic, subtopic) => openSubTopics.value[getSubTopicKey(topic, subtopic)] === true;

function onVideoLoaded(event) {
    detachVimeoPlayer();
    isVideoLoading.value = false;

    if (!event?.target || !selectedLesson.value) return;

    activeVimeoFrame = event.target;
    subscribeToVimeoEvents();
}

function postToVimeo(message) {
    activeVimeoFrame?.contentWindow?.postMessage(message, 'https://player.vimeo.com');
}

function subscribeToVimeoEvents() {
    ['timeupdate', 'pause', 'ended'].forEach((eventName) => {
        postToVimeo({ method: 'addEventListener', value: eventName });
    });
    postToVimeo({ method: 'ping' });
}

async function handleVimeoMessage(event) {
    if (!activeVimeoFrame || event.source !== activeVimeoFrame.contentWindow) return;
    if (event.origin !== 'https://player.vimeo.com') return;

    let message = event.data;

    if (typeof message === 'string') {
        try {
            message = JSON.parse(message);
        } catch {
            return;
        }
    }

    if (!message || typeof message !== 'object') return;

    if (message.event === 'ready' || message.method === 'ping') {
        ['timeupdate', 'pause', 'ended'].forEach((eventName) => {
            postToVimeo({ method: 'addEventListener', value: eventName });
        });

        if (!resumeApplied) {
            resumeApplied = true;
            const resumeSeconds = await progressLoadPromise;

            if (resumeSeconds >= 5) {
                postToVimeo({ method: 'setCurrentTime', value: resumeSeconds });
            }
        }
    }

    if (message.event === 'timeupdate') handlePlaybackUpdate(message.data || {});
    if (message.event === 'pause') handlePlaybackPause(message.data || latestPlayback);
    if (message.event === 'ended') handlePlaybackEnded(message.data || latestPlayback);
}

function getFullscreenElement() {
    return document.fullscreenElement || document.webkitFullscreenElement || null;
}

function isLessonVideoFullscreen(element) {
    if (!element) return false;

    return element.classList?.contains('video-frame')
        || Boolean(element.closest?.('[data-lesson-player]'))
        || Boolean(element.querySelector?.('.video-frame'));
}

async function requestVideoOrientation(orientation) {
    if (typeof window.screen?.orientation?.lock !== 'function') return;

    try {
        await window.screen.orientation.lock(orientation);
    } catch {
        // Some browsers only allow orientation locking while fullscreen.
    }
}

function releaseVideoOrientation() {
    if (typeof window.screen?.orientation?.unlock !== 'function') return;

    try {
        window.screen.orientation.unlock();
    } catch {
        // Ignore browsers that expose the API but do not permit it here.
    }
}

function handleVideoFullscreenChange() {
    const fullscreenElement = getFullscreenElement();

    if (isLessonVideoFullscreen(fullscreenElement)) {
        void requestVideoOrientation('landscape');
        return;
    }

    if (selectedLesson.value) {
        void requestVideoOrientation('any');
        return;
    }

    releaseVideoOrientation();
}

function setResourceRef(lessonId, component) {
    if (component) resourceRefs.set(lessonId, component);
    else resourceRefs.delete(lessonId);
}

function isPdfOnlyLesson(lesson) {
    return Boolean(!lesson?.vimeo_url && lesson?.has_lesson_pdf);
}

function openPrimaryLesson(lesson) {
    if (lesson?.vimeo_url) {
        openLesson(lesson);
        return;
    }

    if (lesson?.has_lesson_pdf) resourceRefs.get(lesson.id)?.openPdf('lesson');
}

async function openLesson(lesson, mode = 'lesson') {
    if (selectedLesson.value) {
        void saveCurrentProgress(true);
        detachVimeoPlayer();
    }

    isVideoLoading.value = true;
    videoMode.value = mode;
    selectedLesson.value = lesson;
    isPlaying.value = false;
    latestPlayback = { seconds: 0, duration: 0 };
    lastSavedSecond = -30;
    progressLoadPromise = loadSavedProgress(lesson.id, mode);
    void markVideoViewed(lesson.id, mode);
    void requestVideoOrientation('any');

    if (!videoHistoryEntryOpen.value) {
        window.history.pushState(
            { ...(window.history.state || {}), lessonVideoOpen: true },
            '',
            window.location.href
        );
        videoHistoryEntryOpen.value = true;
    }

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

function handleHistoryBack() {
    if (!selectedLesson.value) return;

    videoHistoryEntryOpen.value = false;
    clearSelectedLesson();
}

function getVideoUrl(lesson) {
    const value = videoMode.value === 'answer' ? lesson?.answer_vimeo_url : lesson?.vimeo_url;
    return getVimeoPlayerUrl(value);
}

function playAnswerVideo(lesson) {
    if (!lesson?.answer_vimeo_url) return;
    void saveCurrentProgress(true);
    detachVimeoPlayer();
    isVideoLoading.value = true;
    videoMode.value = 'answer';
    latestPlayback = { seconds: 0, duration: 0 };
    lastSavedSecond = -30;
    progressLoadPromise = loadSavedProgress(lesson.id, 'answer');
    void markVideoViewed(lesson.id, 'answer');
}

function playLessonVideo() {
    void saveCurrentProgress(true);
    detachVimeoPlayer();
    isVideoLoading.value = true;
    videoMode.value = 'lesson';
    latestPlayback = { seconds: 0, duration: 0 };
    lastSavedSecond = -30;
    progressLoadPromise = loadSavedProgress(selectedLesson.value?.id, 'lesson');
    void markVideoViewed(selectedLesson.value?.id, 'lesson');
}

async function markVideoViewed(lessonId, mode) {
    if (!lessonId) return;

    try {
        await api.put(`/lesson-progress/${lessonId}`, {
            video_type: mode,
            viewed_only: true,
        });
    } catch (error) {
        if (![403, 503].includes(error.response?.status)) {
            console.warn('Could not mark video as viewed', error);
        }
    }
}

async function loadSavedProgress(lessonId, mode) {
    if (!lessonId) return 0;

    try {
        const { data } = await api.get(`/lesson-progress/${lessonId}`, {
            params: { video_type: mode },
        });
        return Number(data.position_seconds) || 0;
    } catch (error) {
        if (![403, 503].includes(error.response?.status)) {
            console.warn('Could not load video progress', error);
        }
        return 0;
    }
}

function handlePlaybackUpdate(data) {
    latestPlayback = {
        seconds: Number(data.seconds) || 0,
        duration: Number(data.duration) || 0,
    };

    if (Math.abs(latestPlayback.seconds - lastSavedSecond) >= 30) {
        void saveCurrentProgress();
    }
}

function handlePlaybackPause(data) {
    handlePlaybackUpdate(data);
    void saveCurrentProgress(true);
}

function handlePlaybackEnded(data) {
    latestPlayback = {
        seconds: Number(data.seconds || data.duration) || 0,
        duration: Number(data.duration) || 0,
    };
    void saveCurrentProgress(true);
}

async function saveCurrentProgress(force = false) {
    const lessonId = selectedLesson.value?.id;
    const { seconds, duration } = latestPlayback;

    if (!lessonId || seconds < 1 || progressSaveInFlight) return;
    if (!force && Math.abs(seconds - lastSavedSecond) < 30) return;

    progressSaveInFlight = true;
    lastSavedSecond = seconds;

    try {
        await api.put(`/lesson-progress/${lessonId}`, {
            video_type: videoMode.value,
            position_seconds: seconds,
            duration_seconds: duration || null,
        });
    } catch (error) {
        if (![403, 503].includes(error.response?.status)) {
            console.warn('Could not save video progress', error);
        }
    } finally {
        progressSaveInFlight = false;
    }
}

function detachVimeoPlayer() {
    activeVimeoFrame = null;
    resumeApplied = false;
}

function getSubTopic(lesson) {
    return lesson?.sub_topic || lesson?.subTopic || '';
}

function getSubTopicLabel(lesson) {
    return getSubTopic(lesson)?.trim() || 'General';
}

function groupLessons(list) {
    const map = {};

    list.forEach((lesson) => {
        const topic = lesson.topic || 'General';
        const subtopic = getSubTopicLabel(lesson);

        if (!map[topic]) {
            map[topic] = {
                lessons: [],
                subtopics: {},
            };
        }

        if (!map[topic].subtopics[subtopic]) {
            map[topic].subtopics[subtopic] = [];
        }

        map[topic].lessons.push(lesson);
        map[topic].subtopics[subtopic].push(lesson);
    });

    return Object.keys(map).map(topic => {
        const sortedLessons = sortLessonsByPart(map[topic].lessons);

        return {
            topic,
            lessons: sortedLessons,
            hasSubTopics: sortedLessons.some(lesson => Boolean(getSubTopic(lesson)?.trim())),
            subtopics: Object.keys(map[topic].subtopics).map(subtopic => ({
                key: getSubTopicKey(topic, subtopic),
                name: subtopic,
                lessons: sortLessonsByPart(map[topic].subtopics[subtopic]),
            })),
        };
    });
}

function sortLessonsByPart(list) {
    return [...list].sort((a, b) => {
        const partA = getLessonOrderNumber(a);
        const partB = getLessonOrderNumber(b);

        if (partA !== partB) return partA - partB;

        return (a.id || 0) - (b.id || 0);
    });
}

function getLessonOrderNumber(lesson) {
    const partNumber = Number(lesson.part_number);

    if (Number.isFinite(partNumber) && partNumber > 0) {
        return partNumber;
    }

    const titleNumber = String(lesson.title || '').trim().match(/^(\d+)/);

    if (titleNumber) {
        return Number(titleNumber[1]);
    }

    return Number.MAX_SAFE_INTEGER;
}

function normalizeSearchText(value) {
    return String(value || '').toLowerCase().trim();
}

function clearFilters() {
    searchQuery.value = '';
}

function closeLesson() {
    if (videoHistoryEntryOpen.value) {
        window.history.back();
        return;
    }

    clearSelectedLesson();
}

function clearSelectedLesson() {
    void saveCurrentProgress(true);
    detachVimeoPlayer();
    selectedLesson.value = null;
    videoMode.value = 'lesson';
    isVideoLoading.value = false;
    isPlaying.value = false;
    releaseVideoOrientation();
}

function formatDuration(value) {
    if (!value) return 'Not available';

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

    if (!totalSeconds) return 'Not available';

    hours = Math.floor(totalSeconds / 3600);
    minutes = Math.floor((totalSeconds % 3600) / 60);
    seconds = totalSeconds % 60;

    return [
        hours ? `${hours} hr${hours > 1 ? 's' : ''}` : '',
        minutes ? `${minutes} min` : '',
        seconds ? `${seconds} sec` : '',
    ].filter(Boolean).join(' ');
}

function hasDuration(value) {
    return formatDuration(value) !== 'Not available';
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

.answer-video-badge {
    background: rgba(20, 184, 166, 0.16);
    border: 1px solid rgba(45, 212, 191, 0.32);
    border-radius: 999px;
    color: #99f6e4;
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 800;
    margin-top: 0.3rem;
    padding: 0.2rem 0.5rem;
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

@media (max-width: 920px) and (orientation: landscape) {
    .inline-video-card {
        position: fixed;
        inset: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
        z-index: 500;
        border-radius: 0;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        min-height: 100dvh;
    }

    .inline-video-card .video-header {
        flex: 0 0 auto;
        padding: 0.55rem 0.75rem !important;
    }

    .inline-video-card .video-container {
        aspect-ratio: auto;
        flex: 1 1 auto;
        min-height: 0;
    }
}

/* ── ANIMATIONS ── */
/* ── ACCORDION ── */
.lesson-finder {
    background: rgba(255, 255, 255, 0.04) !important;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 1rem;
}

.finder-main {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 420px);
    gap: 0.75rem;
    align-items: center;
}

.finder-summary {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: rgba(226, 232, 240, 0.78);
    font-size: 0.82rem;
}

.finder-summary strong {
    color: #fff;
}

.search-control {
    position: relative;
}

.search-icon {
    position: absolute;
    left: 0.8rem;
    top: 50%;
    width: 18px;
    height: 18px;
    color: rgba(203, 213, 225, 0.72);
    transform: translateY(-50%);
    pointer-events: none;
}

.finder-input {
    padding-left: 2.25rem;
}

.finder-input {
    background: rgba(15, 23, 42, 0.72);
    border-color: rgba(148, 163, 184, 0.25);
    color: #fff;
}

.finder-input::placeholder {
    color: rgba(203, 213, 225, 0.62);
}

.empty-results {
    background: rgba(255, 255, 255, 0.035) !important;
    border-radius: 12px;
}

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

.topic-header-row,
.subtopic-header-row {
    align-items: center;
    column-gap: 0.75rem;
    display: grid;
}

.topic-header-row {
    grid-template-columns: minmax(0, 18rem) auto minmax(0, 1fr) auto;
}

.subtopic-header-row {
    grid-template-columns: 3px minmax(0, 18rem) auto minmax(0, 1fr) auto;
}

.header-list-title {
    min-width: 0;
    overflow-wrap: anywhere;
}

.lesson-count-tag {
    justify-self: start;
    margin: 0;
    white-space: nowrap;
}

.header-arrow {
    display: flex;
    justify-self: end;
}

.subtopic-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.subtopic-block {
    margin-left: 1.5rem;
}

.subtopic-header {
    background: rgba(255, 255, 255, 0.032) !important;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 10px;
    padding: 0.85rem 1rem;
}

.subtopic-title {
    color: #e2e8f0;
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.subtopic-marker {
    width: 3px;
    height: 26px;
    margin-right: 0.75rem;
    border-radius: 999px;
    background: var(--primary, #4f46e5);
    box-shadow: 0 0 16px rgba(79, 70, 229, 0.38);
}

.arrow-icon {
    transition: transform 0.3s ease;
}

.arrow-icon.is-open {
    transform: rotate(90deg);
}

.fixed-card {
    min-height: 220px;
    height: 100%;
}

.lesson-card {
    position: relative;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 16px;
    background:
        linear-gradient(145deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.025)),
        rgba(15, 23, 42, 0.28) !important;
    border-color: rgba(255, 255, 255, 0.12);
    transition: transform 0.24s ease, border-color 0.24s ease, box-shadow 0.24s ease, background 0.24s ease;
}

.lesson-card:hover {
    transform: translateY(-5px);
    border-color: rgba(129, 140, 248, 0.46);
    box-shadow: 0 16px 34px rgba(0, 0, 0, 0.32);
}

.lesson-card.pdf-only-card {
    background:
        linear-gradient(145deg, rgba(59, 130, 246, 0.12), rgba(14, 116, 144, 0.055)),
        rgba(15, 23, 42, 0.34) !important;
    border-color: rgba(96, 165, 250, 0.28);
}

.lesson-card.pdf-only-card:hover {
    border-color: rgba(125, 211, 252, 0.5);
    box-shadow: 0 16px 34px rgba(2, 132, 199, 0.12), 0 16px 34px rgba(0, 0, 0, 0.28);
}

.lesson-card .card-content {
    flex: 1 1 auto;
    width: 100%;
}

.lesson-card-content {
    display: grid;
    grid-template-columns: 1fr;
    align-items: start;
    position: relative;
    row-gap: 10px;
    padding: 1.15rem;
    text-align: left;
    min-height: 150px;
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
    min-width: 0;
}

.lesson-heading {
    display: flex;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
    min-width: 0;
}

.part-badge {
    color: #c7d2fe;
    background: rgba(79, 70, 229, 0.18);
    border: 1px solid rgba(129, 140, 248, 0.28);
    border-radius: 999px;
    flex: 0 0 auto;
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1.1;
    padding: 0.28rem 0.5rem;
    transform: translateY(-3px);
    white-space: nowrap;
}

.lesson-title {
    color: #fff;
    display: -webkit-box;
    font-size: 0.98rem;
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
    margin-top: 0.5rem;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    overflow-wrap: anywhere;
}

.lesson-meta {
    grid-column: 1;
    align-self: end;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
    color: rgba(203, 213, 225, 0.78);
    font-size: 0.78rem;
    font-weight: 600;
    margin-top: auto;
}

.duration-label {
    color: rgba(226, 232, 240, 0.92);
    font-weight: 800;
}

.duration-missing {
    color: rgba(248, 250, 252, 0.52);
    font-style: italic;
}

.lesson-format-icon {
    color: #7dd3fc;
    height: 18px;
    width: 18px;
}

.pdf-lesson-format {
    color: #bae6fd;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.035em;
    text-transform: uppercase;
}

.meta-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: rgba(148, 163, 184, 0.72);
}

.icon-circle {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 44px;
    height: 44px;
    min-width: 44px;
    background: rgba(79, 70, 229, 0.14);
    border: 1px solid rgba(129, 140, 248, 0.25);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
    transition: transform 0.22s ease, background 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease;
    z-index: 2;
}

.icon-circle:hover {
    background: rgba(79, 70, 229, 0.28);
    border-color: rgba(129, 140, 248, 0.58);
    box-shadow:
        0 0 0 8px rgba(79, 70, 229, 0.1),
        0 14px 28px rgba(79, 70, 229, 0.24),
        inset 0 1px 0 rgba(255, 255, 255, 0.12);
    transform: translate(-50%, -50%) scale(1.08);
}

.pdf-icon-circle {
    color: #bae6fd;
    background: rgba(14, 165, 233, 0.14);
    border-color: rgba(125, 211, 252, 0.3);
}

.pdf-icon-circle:hover {
    background: rgba(14, 165, 233, 0.26);
    border-color: rgba(125, 211, 252, 0.58);
    box-shadow:
        0 0 0 8px rgba(14, 165, 233, 0.09),
        0 14px 28px rgba(14, 165, 233, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.lesson-card-actions {
    align-items: stretch;
    background: rgba(2, 6, 23, 0.28);
    border-top: 1px solid rgba(148, 163, 184, 0.14);
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    padding: 0.65rem 0.75rem;
}

.lesson-play-action {
    align-items: center;
    background: rgba(79, 70, 229, 0.2);
    border: 1px solid rgba(129, 140, 248, 0.42);
    border-radius: 9px;
    color: #e0e7ff;
    cursor: pointer;
    display: inline-flex;
    flex: 1 1 112px;
    font-size: 0.7rem;
    font-weight: 800;
    gap: 0.4rem;
    justify-content: center;
    min-height: 38px;
    padding: 0.45rem 0.65rem;
    transition: 0.2s ease;
}

.lesson-play-action:hover {
    background: rgba(79, 70, 229, 0.34);
    border-color: rgba(165, 180, 252, 0.62);
    transform: translateY(-1px);
}

.lesson-play-action:focus-visible {
    box-shadow: 0 0 0 3px rgba(165, 180, 252, 0.42);
    outline: none;
}

.lesson-play-action svg {
    height: 17px;
    width: 17px;
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
    .lesson-finder {
        padding: 0.85rem;
        position: sticky;
        top: 0.5rem;
        z-index: 15;
        backdrop-filter: blur(14px);
    }

    .finder-main {
        grid-template-columns: 1fr;
        gap: 0.6rem;
    }

    .finder-summary {
        align-items: flex-start;
        flex-direction: column;
        font-size: 0.76rem;
        order: 2;
    }

    .finder-summary .button {
        width: 100%;
    }

    .subtopic-block {
        margin-left: 0;
    }

    .subtopic-header {
        padding: 0.8rem 0.9rem;
    }

    .subtopic-title {
        font-size: 0.88rem;
    }

    .topic-header-row,
    .subtopic-header-row {
        column-gap: 0.5rem;
    }

    .topic-header-row {
        grid-template-columns: minmax(0, 1fr) auto auto;
    }

    .subtopic-header-row {
        grid-template-columns: 3px minmax(0, 1fr) auto auto;
    }

    .topic-header-row .header-arrow,
    .subtopic-header-row .header-arrow {
        grid-column: -2;
    }

    .fixed-card {
        min-height: 210px;
    }

    .lesson-card-content {
        grid-template-columns: 1fr;
        padding: 1rem;
    }

    .lesson-card-actions {
        padding: 0.6rem;
    }

    .icon-circle {
        width: 40px;
        height: 40px;
        min-width: 40px;
    }

    .lesson-play-action {
        flex-basis: 100%;
    }

    .lesson-heading {
        align-items: flex-start;
        flex-direction: row;
        gap: 7px;
    }

    .part-badge {
        font-size: 0.64rem;
        padding: 0.24rem 0.45rem;
    }

    .lesson-title {
        font-size: 0.92rem;
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
flex: 1 1 auto;
min-width: 0;
