<template>
  <Layout title="Home" :showBack="false">

    <!-- Announcement -->
    <div v-if="announcement?.enabled" class="announcement-bar mb-5">
      <span class="ann-icon">📢</span>
      <div>
        <p class="ann-title">{{ announcement.title }}</p>
        <p class="ann-body">{{ announcement.body }}</p>
      </div>
    </div>

    <!-- Student Showcase Slider -->
    <div v-if="sliderImages.length" class="hero-students mb-5">
      <div class="hero-track">
        <div v-for="(image, index) in sliderImages" :key="`${image.src}-${index}`" class="hero-slide">
          <img :src="image.src" :alt="image.alt" loading="eager" decoding="async" @error="hideBrokenImage" />
        </div>
      </div>

      <div class="hero-overlay">
        <p class="hero-title">Master Your Subjects</p>
        <p class="hero-sub">Structured learning built for real understanding</p>
      </div>
    </div>

    <div class="learning-wrapper  learning-panel" style="margin-bottom: 2.5rem;">

      <!-- LEFT: My Learning -->
      <div class="learning-left">
        <p class="section-label section-primary mb-3">My Learning</p>

        <!-- ✅ LOGGED IN -->
        <div v-if="auth.isLoggedIn"
          class="glass-card clickable-card star-card p-4 is-flex is-align-items-center is-justify-content-center has-text-centered"
          @click="$router.push({ name: 'myCourses' })">
          <div>
            <div class="mb-2">
              <span class="tag is-primary is-light is-rounded is-medium">▶</span>
            </div>

            <p class="has-text-weight-semibold is-size-7 mb-1">
              My Courses
            </p>
            <p class="is-size-7 has-text-grey-light">
              Continue learning
            </p>
          </div>
        </div>

        <!-- 🚀 NOT LOGGED IN -->
        <div v-else
          class="glass-card clickable-card p-4 is-flex is-align-items-center is-justify-content-center has-text-centered"
          @click="$router.push({ name: 'register' })">
          <div>
            <div class="mb-2">
              <span class="tag is-warning is-light is-rounded is-medium">🔒</span>
            </div>

            <p class="has-text-weight-semibold is-size-7 mb-1">
              Access Your Courses
            </p>
            <p class="is-size-7 has-text-grey-light">
              Login to continue learning
            </p>
          </div>
        </div>

      </div>

      <!-- RIGHT: Grades -->
      <div v-if="loading">
        <Loader />
      </div>

      <div v-else class="learning-right">
        <p class="section-label section-secondary mb-3">Browse by Grade</p>
        <div class="grade-grid">
          <div class="grade-pill is-clickable" v-for="grade in grades" :key="grade" @click="selectGrade(grade)">
            {{ grade.name }}
          </div>
        </div>
      </div>
    </div>

    <!-- Enrolled Courses -->
    <p class="section-label mb-3">{{ homepageContent.demo.title }}</p>
    <div class="mb-5">
      <div v-if="courses.length">
        <div v-for="course in courses" :key="course.id" :class="['course-row', course.backgroundClass, 'mb-2']"
          @click="openDemo(course)">
          <div :class="['course-dot', course.colorClass]">{{ course.emoji }}</div>
          <div style="flex:1; min-width:0;">
            <p class="has-text-weight-semibold is-size-7 mb-0">{{ course.title }}</p>
            <p class="is-size-7 has-text-grey-light">{{ course.meta }}</p>
          </div>
          <span :class="['course-tag', course.tagClass]">{{ course.subject }}</span>
        </div>
        <!-- Request more row -->
        <div class="course-row course-row--dashed">
          <div class="course-dot" style="background:rgba(245,158,11,0.1);">➕</div>
          <div style="flex:1;">
            <p class="is-size-7 has-text-weight-semibold mb-0">Looking for more lessons?</p>
            <p class="is-size-7 has-text-grey-light">Pick your grade above and continue learning</p>
          </div>
          <span class="course-tag course-tag--amber">Continue</span>
        </div>
      </div>
      <div v-else class="empty-container has-text-centered">
        <p class="has-text-grey-light">No courses yet. Request access to get started 🚀</p>
      </div>
    </div>


    <div v-if="!auth.isLoggedIn" class="glass-card p-5 has-text-centered mt-5">
      <h3 class="title is-6 has-text-white mb-2">
        Ready to Start Learning?
      </h3>
      <p class="is-size-7 has-text-grey-light mb-3">
        Join now and get access to structured lessons and tutor support.
      </p>

      <button class="button is-primary is-rounded has-text-white" @click="$router.push({ name: 'register' })">
        Create Account
      </button>
    </div>

    <!-- Footer -->
    <footer class="site-footer mt-5">
      <div class="footer-main">
        <div class="footer-brand">
          <div class="footer-logo">{{ footerInitial }}</div>
          <div>
            <p class="footer-title">{{ homepageContent.footer.brandName }}</p>
            <p class="footer-copy">{{ homepageContent.footer.description }}</p>
            <p class="footer-meta">{{ homepageContent.footer.subjects }}</p>
          </div>
        </div>

        <div class="footer-details">
          <div class="footer-detail">
            <span>Phone</span>
            <a :href="`tel:${homepageContent.footer.phone}`">{{ homepageContent.footer.phone }}</a>
          </div>
          <div class="footer-detail">
            <span>Email</span>
            <a :href="`mailto:${homepageContent.footer.email}`">{{ homepageContent.footer.email }}</a>
          </div>
          <div class="footer-detail">
            <span>Location</span>
            <p>{{ homepageContent.footer.location }}</p>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <span>{{ homepageContent.footer.tutorName }}</span>
        <span>{{ homepageContent.footer.supportText }}</span>
      </div>
    </footer>

    <Transition name="fade">
      <div v-if="selectedDemoVideo" class="video-modal" @click.self="closeDemo">

        <div class="video-box glass-card">

          <div class="video-header p-4 is-flex is-align-items-center">
            <h3 class="video-title has-text-white is-size-6">
              {{ selectedDemoVideo.title }}
            </h3>

            <button class="close-btn ml-auto" type="button" aria-label="Close video" title="Close video"
              @click.stop="closeDemo">
              <XMarkIcon class="close-icon" aria-hidden="true" />
            </button>
          </div>

          <div class="video-container">

            <!-- thumbnail -->
            <div v-if="!isDemoPlaying" class="video-placeholder" @click="startDemoVideo">
              <div class="play-overlay">▶</div>
            </div>

            <!-- iframe -->
            <iframe v-else :src="demoVideoUrl" class="video-frame" frameborder="0"
              allow="autoplay; fullscreen; picture-in-picture" allowfullscreen />

          </div>

        </div>

      </div>
    </Transition>
  </Layout>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import Layout from './common/Layout.vue';
import { useRouter } from "vue-router";
import { useCacheStore } from "../stores/cache";
import { useAuthStore } from "../stores/auth";
import api from "../api";
import { XMarkIcon } from '@heroicons/vue/24/outline';

import { storeToRefs } from "pinia";
import Loader from "./common/Loader.vue";

const router = useRouter();

const cache = useCacheStore();
const auth = useAuthStore();

const { grades } = storeToRefs(cache);
const loading = ref(false);

const selectedDemoVideo = ref(null);
const isDemoPlaying = ref(false);

const announcement = ref(null);
const homepageImages = ref([]);

const defaultHomepageContent = {
  demo: {
    title: "Watch how lessons are explained before enrolling",
    videos: [
      {
        id: 1,
        title: "Mathematics",
        subject: "Grade 12",
        meta: "Simultaneous Equations",
        emoji: "📐",
        backgroundClass: "",
        colorClass: "cd-math",
        tagClass: "",
        video: "https://player.vimeo.com/video/1075679310?title=0&byline=0&portrait=0&badge=0&autopause=0&player_id=0&app_id=58479",
      },
      {
        id: 2,
        title: "Accounts",
        subject: "Grade 10",
        meta: "Double Entry",
        emoji: "🔬",
        backgroundClass: "",
        colorClass: "cd-sci",
        tagClass: "green",
        video: "https://player.vimeo.com/video/820746971?h=f2f8ed7cf3",
      },
    ],
  },
  footer: {
    brandName: "Aditya Classes",
    description: "Structured private tuition for Grade 7 to HSC students in Mauritius.",
    subjects: "Maths · Add Maths · Accounting",
    phone: "+230 5947 3797",
    email: "adityaera22@mail.com",
    location: "Quartier-Militaire · Online",
    tutorName: "Mr. Aditya",
    supportText: "WhatsApp support within 24 hours",
  },
};

const homepageContent = ref(JSON.parse(JSON.stringify(defaultHomepageContent)));

const sliderImages = computed(() => {
  return [...homepageImages.value, ...homepageImages.value];
});

const footerInitial = computed(() => {
  return homepageContent.value.footer.brandName?.trim()?.charAt(0)?.toUpperCase() || "A";
});

const courses = computed(() => {
  return (homepageContent.value.demo.videos || []).filter(video => video.video);
});

function mergeHomepageContent(content = {}) {
  const demo = {
    ...defaultHomepageContent.demo,
    ...(content.demo || {}),
  };

  return {
    demo: {
      ...demo,
      videos: Array.isArray(demo.videos) ? demo.videos : defaultHomepageContent.demo.videos,
    },
    footer: {
      ...defaultHomepageContent.footer,
      ...(content.footer || {}),
    },
  };
}

const announcementClass = computed(() => {
  switch (announcement.value?.type) {
    case "success":
      return "ann-success";
    case "danger":
      return "ann-danger";
    case "warning":
      return "ann-warning";
    default:
      return "ann-default";
  }
});


function openDemo(course) {
  selectedDemoVideo.value = null;
  isDemoPlaying.value = false;

  // force re-render cycle
  setTimeout(() => {
    selectedDemoVideo.value = course;
  }, 0);
}

function closeDemo() {
  selectedDemoVideo.value = null;
  isDemoPlaying.value = false;
}

function startDemoVideo() {
  isDemoPlaying.value = true;
}

const demoVideoUrl = computed(() => {
  if (!selectedDemoVideo.value?.video) return '';

  const separator = selectedDemoVideo.value.video.includes("?") ? "&" : "?";
  return `${selectedDemoVideo.value.video}${separator}autoplay=1&muted=0`;
});

function selectGrade(grade) {
  let $gradeId = grade.id;

  router.push({
    name: "package",
    params: { id: $gradeId }
  });
}

function hideBrokenImage(event) {
  event.target.style.display = "none";
}

onMounted(async () => {
  loading.value = true;

  try {
    await cache.fetchAllMetadata();

    const announcementRes = await api.get("/announcement");
    announcement.value = announcementRes.data;

    try {
      const contentRes = await api.get("/homepage-content");
      homepageContent.value = mergeHomepageContent(contentRes.data);
    } catch (error) {
      console.error("Failed to load homepage content:", error);
      homepageContent.value = mergeHomepageContent();
    }

    try {
      const imagesRes = await api.get("/homepage-images");
      homepageImages.value = (imagesRes.data || [])
        .filter(image => image.active)
        .map(image => ({
          src: image.url,
          alt: image.name,
        }));
    } catch (error) {
      console.error("Failed to load homepage images:", error);
      homepageImages.value = [];
    }

  } finally {
    loading.value = false;
  }
});

</script>

<style scoped>
.badge-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #818cf8;
  animation: blink 2s infinite;
}

@keyframes blink {

  0%,
  100% {
    opacity: 1
  }

  50% {
    opacity: 0.3
  }
}

/* ── Announcement ── */
.announcement-bar {
  border-radius: 14px;
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.2);
  padding: 12px 14px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.ann-icon {
  font-size: 18px;
  flex-shrink: 0;
  margin-top: 2px;
}

.ann-title {
  font-size: 0.8rem;
  font-weight: 600;
  color: #fcd34d;
  margin: 0 0 2px;
}

.ann-body {
  font-size: 0.73rem;
  color: #d97706;
  margin: 0;
}

.ann-paragraph {
  font-size: 0.73rem;
  color: red;
  margin: 0;
}

/* ── Section label ── */
.section-label {
  font-size: 0.68rem;
  font-weight: 700;
  color: #6366f1;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-label::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(99, 102, 241, 0.2);
}

/* ── Star card (My Courses) ── */
.star-card {
  border: 1.5px solid rgba(99, 102, 241, 0.55) !important;
  background: linear-gradient(135deg, rgba(79, 70, 229, 0.2), rgba(99, 102, 241, 0.08)) !important;
  animation: starPulse 2.5s ease-in-out infinite;
  position: relative;

  overflow: visible !important;
}

.star-card::before {
  content: 'STUDY';
  position: absolute;
  top: -9px;
  right: 14px;
  background: linear-gradient(90deg, #6366f1, #8b5cf6);
  color: #fff;
  font-size: 0.58rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  letter-spacing: 0.1em;
  z-index: 2;
}

@keyframes starPulse {

  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4);
  }

  50% {
    box-shadow: 0 0 0 8px rgba(99, 102, 241, 0);
  }
}

/* ── Course rows ── */
.course-row {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 14px;
  padding: 12px 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.course-row:hover {
  background: rgba(255, 255, 255, 0.06);
  border-color: rgba(99, 102, 241, 0.35);
  transform: translateX(3px);
}

.course-row.bg-indigo {
  background: rgba(99, 102, 241, 0.07);
}

.course-row.bg-green {
  background: rgba(16, 185, 129, 0.06);
}

.course-row.bg-pink {
  background: rgba(236, 72, 153, 0.06);
}

.course-row.bg-blue {
  background: rgba(59, 130, 246, 0.07);
}

.course-row.bg-purple {
  background: rgba(139, 92, 246, 0.07);
}

.course-row.bg-red {
  background: rgba(239, 68, 68, 0.06);
}

.course-row.bg-cyan {
  background: rgba(6, 182, 212, 0.06);
}

.course-row.bg-slate {
  background: rgba(148, 163, 184, 0.06);
}

.course-row--dashed {
  border-style: dashed;
  opacity: 0.6;
  cursor: default;
}

.course-row--dashed:hover {
  transform: none;
}

.course-dot {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}

.cd-math {
  background: rgba(99, 102, 241, 0.15);
}

.cd-sci {
  background: rgba(16, 185, 129, 0.12);
}

.cd-pink {
  background: rgba(236, 72, 153, 0.12);
}

.cd-blue {
  background: rgba(59, 130, 246, 0.13);
}

.cd-purple {
  background: rgba(139, 92, 246, 0.14);
}

.cd-red {
  background: rgba(239, 68, 68, 0.13);
}

.cd-cyan {
  background: rgba(6, 182, 212, 0.13);
}

.cd-slate {
  background: rgba(148, 163, 184, 0.13);
}

.course-tag {
  flex-shrink: 0;
  background: rgba(99, 102, 241, 0.15);
  border: 1px solid rgba(99, 102, 241, 0.25);
  color: #a5b4fc;
  font-size: 0.62rem;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.course-tag.green {
  background: rgba(16, 185, 129, 0.12);
  border-color: rgba(16, 185, 129, 0.25);
  color: #6ee7b7;
}

.course-tag.pink {
  background: rgba(236, 72, 153, 0.12);
  border-color: rgba(236, 72, 153, 0.25);
  color: #f9a8d4;
}

.course-tag.blue {
  background: rgba(59, 130, 246, 0.13);
  border-color: rgba(59, 130, 246, 0.26);
  color: #93c5fd;
}

.course-tag.purple {
  background: rgba(139, 92, 246, 0.14);
  border-color: rgba(139, 92, 246, 0.28);
  color: #c4b5fd;
}

.course-tag.red {
  background: rgba(239, 68, 68, 0.13);
  border-color: rgba(239, 68, 68, 0.26);
  color: #fca5a5;
}

.course-tag.cyan {
  background: rgba(6, 182, 212, 0.13);
  border-color: rgba(6, 182, 212, 0.26);
  color: #67e8f9;
}

.course-tag.slate {
  background: rgba(148, 163, 184, 0.12);
  border-color: rgba(148, 163, 184, 0.24);
  color: #cbd5e1;
}

.course-tag--amber {
  background: rgba(245, 158, 11, 0.1);
  border-color: rgba(245, 158, 11, 0.2);
  color: #fcd34d;
}

/* Footer */
.site-footer {
  overflow: hidden;
  border-radius: 18px 18px 0 0;
  background: rgba(255, 255, 255, 0.035);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-bottom: 0;
  box-shadow: 0 -10px 32px rgba(0, 0, 0, 0.16);
  backdrop-filter: blur(16px);
}

.footer-main {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 28px;
  align-items: center;
  padding: 20px 22px;
}

.footer-brand {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.footer-logo {
  flex: 0 0 auto;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-weight: 800;
  background: rgba(99, 102, 241, 0.16);
  border: 1px solid rgba(99, 102, 241, 0.35);
  color: #c7d2fe;
  box-shadow: 0 10px 24px rgba(99, 102, 241, 0.12);
}

.footer-title {
  margin: 0 0 6px;
  color: #fff;
  font-size: 0.95rem;
  font-weight: 800;
}

.footer-copy {
  max-width: 330px;
  margin: 0;
  color: rgba(203, 213, 225, 0.78);
  font-size: 0.75rem;
  line-height: 1.55;
}

.footer-meta {
  margin: 8px 0 0;
  color: #fcd34d;
  font-size: 0.7rem;
  font-weight: 700;
}

.footer-details {
  display: grid;
  grid-template-columns: repeat(3, auto);
  gap: 16px;
  align-items: center;
}

.footer-detail {
  min-width: 118px;
  padding-left: 16px;
  border-left: 1px solid rgba(99, 102, 241, 0.16);
}

.footer-detail span {
  display: block;
  margin-bottom: 4px;
  color: #818cf8;
  font-size: 0.62rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.footer-detail a,
.footer-detail p {
  display: block;
  margin: 0;
  color: rgba(203, 213, 225, 0.82);
  font-size: 0.73rem;
  line-height: 1.35;
  white-space: nowrap;
}

.footer-detail a {
  color: rgba(203, 213, 225, 0.9);
}

.footer-detail a:hover {
  color: #fcd34d;
}

.footer-bottom {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 22px;
  background: rgba(99, 102, 241, 0.06);
  border-top: 1px solid rgba(255, 255, 255, 0.07);
  color: rgba(203, 213, 225, 0.72);
  font-size: 0.68rem;
}

.learning-wrapper {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  align-items: stretch;
}

/* shared panel feel */
.learning-left,
.learning-right {
  display: flex;
  flex-direction: column;
}

.learning-card {
  flex: 1;
  min-height: 120px;

  display: flex;
  align-items: center;

  padding: 16px;
  border-radius: 18px;

  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);

  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);

  transition: all 0.25s ease;
}

.learning-card:hover {
  transform: translateY(-2px);
  border-color: rgba(99, 102, 241, 0.35);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.learning-card-inner {
  display: flex;
  align-items: center;
  gap: 14px;
}

.learning-text .title {
  font-size: 0.8rem;
  font-weight: 700;
  color: #fff;
  margin: 0;
}

.learning-text .subtitle {
  font-size: 0.7rem;
  color: rgba(148, 163, 184, 0.9);
  margin: 0;
}

.learning-right {
  display: flex;
  flex-direction: column;
}

.grade-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;

  flex: 1;
  align-content: start;
}

/* pills more “luxury soft” */
.grade-pill {
  min-height: 42px;
  border-radius: 16px;

  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);

  backdrop-filter: blur(14px);

  font-size: 0.72rem;
  font-weight: 600;
  color: #c7d2fe;

  display: flex;
  align-items: center;
  justify-content: center;

  transition: all 0.25s ease;
}


.grade-pill:hover {
  transform: translateY(-2px);
  background: rgba(99, 102, 241, 0.12);
  border-color: rgba(99, 102, 241, 0.3);
}

/* KEY: equal visual height alignment */
.learning-card,
.grade-grid {
  height: 100%;
}

.section-primary {
  color: #6366f1;
}

.section-secondary {
  color: #f59e0b;
}

.section-label.section-primary::after {
  background: rgba(99, 102, 241, 0.25);
}

.section-label.section-secondary::after {
  background: rgba(245, 158, 11, 0.25);
}

.video-placeholder {
  position: absolute;
  inset: 0;
  background: #000;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
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
  display: flex;
  align-items: center;
  justify-content: center;
}

.video-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  z-index: 9999;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 20px;
}

.video-box {
  width: 100%;
  max-width: 850px;
  height: auto !important;
  border-radius: 14px;
  overflow: hidden;
  background: #0f172a !important;

  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
}

.video-box:hover {
  transform: none;
}

.video-header {
  gap: 12px;
}

.video-title {
  flex: 1;
  min-width: 0;
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.close-btn {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.08);
  color: #e5e7eb;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
}

.close-btn:hover {
  background: rgba(239, 68, 68, 0.16);
  border-color: rgba(248, 113, 113, 0.45);
  color: #ffffff;
  transform: scale(1.04);
}

.close-btn:focus-visible {
  outline: 2px solid #93c5fd;
  outline-offset: 3px;
}

.close-icon {
  width: 20px;
  height: 20px;
  stroke-width: 2.25;
}

.video-container {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  background: #000;
  overflow: hidden;
  line-height: 0;
}

@media (max-width: 768px) {
  .video-box {
    width: 95%;
    border-radius: 12px;
  }
}

.video-frame {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border: 0;
  display: block;
}


/* PHOTOS */
.hero-students {
  position: relative;
  height: 190px;
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1),
    0 4px 15px rgba(0, 0, 0, 0.5);
  transform: translateZ(0);
}

.hero-students::before,
.hero-students::after {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  width: 80px;
  z-index: 2;
  pointer-events: none;
}

.hero-students::before {
  left: 0;
  background: linear-gradient(to right, rgba(15, 23, 42, 0.85), transparent);
}

.hero-students::after {
  right: 0;
  background: linear-gradient(to left, rgba(15, 23, 42, 0.75), transparent);
}

.hero-track {
  display: flex;
  width: max-content;
  height: 100%;
  gap: 12px;
  padding: 0 12px;
  animation: slideRight 48s linear infinite;
  will-change: transform;
}

.hero-students:hover .hero-track {
  animation-play-state: paused;
}

.hero-slide {
  flex: 0 0 clamp(240px, 34vw, 420px);
  height: 100%;
  overflow: hidden;
  border-radius: 16px;
}

.hero-slide img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  filter: brightness(0.86) saturate(1.04);
}

.hero-overlay {
  position: absolute;
  left: 0;
  bottom: 0;
  z-index: 3;
  width: 100%;
  padding: 18px;
  background: linear-gradient(to top,
      rgba(0, 0, 0, 0.72) 0%,
      rgba(0, 0, 0, 0.34) 58%,
      transparent 100%);
  pointer-events: none;
}

.hero-title {
  color: #fff;
  font-weight: 700;
  font-size: 1rem;
}

.hero-sub {
  font-size: 0.75rem;
  color: #cbd5f5;
}

@keyframes slideRight {
  from {
    transform: translateX(-50%);
  }

  to {
    transform: translateX(0);
  }
}

@media (max-width: 768px) {
  .hero-students {
    height: 160px;
  }

  .footer-main {
    grid-template-columns: 1fr;
  }

  .footer-main {
    gap: 16px;
    padding: 18px;
  }

  .footer-details {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .footer-detail {
    padding-left: 0;
    border-left: 0;
  }

  .footer-detail a,
  .footer-detail p {
    white-space: normal;
  }

  .footer-bottom {
    flex-direction: column;
    padding: 10px 18px;
  }

  .hero-students::before,
  .hero-students::after {
    width: 44px;
  }

  .hero-track {
    gap: 8px;
    padding: 0 8px;
    animation-duration: 40s;
  }
}

@media (prefers-reduced-motion: reduce) {
  .hero-track {
    animation: none;
    transform: translateX(0);
  }
}
</style>
