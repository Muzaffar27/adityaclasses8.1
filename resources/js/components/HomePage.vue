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

    <!-- Student Showcase Row -->
    <div class="hero-students mb-5">

      <!-- LEFT BIG IMAGE -->
      <div class="hero-main">
        <img src="/public/images/home/1.jpg" />

        <div class="hero-overlay">
          <p class="hero-title">Master Your Subjects</p>
          <p class="hero-sub">Structured learning built for real understanding</p>
        </div>
      </div>

      <!-- RIGHT STACK -->
      <div class="hero-side">
        <div class="hero-small">
          <img src="/public/images/home/2.jpg" />
        </div>

        <div class="hero-small">
          <img src="/public/images/home/3.jpg" />
        </div>
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
          @click="$router.push({ name: 'login' })">
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
    <p class="section-label mb-3">Watch how lessons are explained before enrolling</p>
    <div class="mb-5">
      <div v-if="courses.length">
        <div v-for="course in courses" :key="course.id" class="course-row mb-2" @click="openDemo(course)">
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

      <button class="button is-primary is-rounded has-text-white" @click="$router.push({ name: 'login' })">
        Create Account
      </button>
    </div>

    <!-- Info Cards -->
    <p class="section-label mb-3 mt-5">About Aditya Classes</p>
    <div class="columns is-multiline">

      <div class="column is-12-mobile is-6-tablet is-4-desktop">
        <div class="glass-card p-4">
          <div class="info-icon ic-indigo mb-3">🏫</div>
          <h3 class="title is-6 has-text-white mb-2">About Us</h3>
          <p class="is-size-7 has-text-grey-light">Structured private tuition for students across Mauritius — Grade 7 to
            HSC. Online & in-person options available.</p>
        </div>
      </div>

      <div class="column is-12-mobile is-6-tablet is-4-desktop">
        <div class="glass-card p-4">
          <div class="info-icon ic-teal mb-3">👨‍🏫</div>
          <h3 class="title is-6 has-text-white mb-2">Your Tutor</h3>
          <p class="is-size-7 mb-1"><span class="contact-label">Name</span> Mr. Aditya</p>
          <p class="is-size-7 mb-1"><span class="contact-label">Phone</span> +230 5947 3797</p>
          <p class="is-size-7"><span class="contact-label">Email</span> adityaera22@mail.com</p>
        </div>
      </div>

      <div class="column is-12-mobile is-12-tablet is-4-desktop">
        <div class="glass-card p-4">
          <div class="info-icon ic-amber mb-3">📍</div>
          <h3 class="title is-6 has-text-white mb-2">Location</h3>
          <p class="is-size-7 has-text-grey-light">Quartier-Militaire & online · Recordings available</p>
        </div>
      </div>

      <div class="column is-12-mobile is-6-tablet is-4-desktop">
        <div class="glass-card p-4">
          <div class="info-icon ic-indigo mb-3">📚</div>
          <h3 class="title is-6 has-text-white mb-2">Subjects</h3>
          <p class="is-size-7 has-text-grey-light">Maths · Add Maths · Accounting</p>
        </div>
      </div>

      <div class="column is-12-mobile is-6-tablet is-4-desktop">
        <div class="glass-card p-4">
          <div class="info-icon ic-pink mb-3">💬</div>
          <h3 class="title is-6 has-text-white mb-2">Need Help?</h3>
          <p class="is-size-7 has-text-grey-light">WhatsApp Mr Aditya. All inquiries
            answered within 24 hrs.</p>
        </div>
      </div>

      <div class="column is-12-mobile is-12-tablet is-4-desktop">
        <div class="glass-card p-4">
          <div class="info-icon ic-teal mb-3">✅</div>
          <h3 class="title is-6 has-text-white mb-2">How It Works</h3>
          <p class="is-size-7 has-text-grey-light">1. Request course access · 2. Tutor approves · 3. Unlock lessons · 4.
            Track progress & ace your exams.</p>
        </div>
      </div>

    </div>

    <Transition name="fade">
      <div v-if="selectedDemoVideo" class="video-modal" @click.self="closeDemo">

        <div class="video-box glass-card">

          <div class="video-header p-4 is-flex is-align-items-center">
            <h3 class="has-text-white is-size-6">
              {{ selectedDemoVideo.title }}
            </h3>

            <button class="close-btn ml-auto" @click="closeDemo">
              ✕
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
  isVideoLoading.value = true;
}

const demoVideoUrl = computed(() => {
  if (!selectedDemoVideo.value?.video) return '';

  return `${selectedDemoVideo.value.video}?autoplay=1&muted=0`;
});

function selectGrade(grade) {
  let $gradeId = grade.id;

  router.push({
    name: "package",
    params: { id: $gradeId }
  });
}
const courses = ref([
  {
    id: 1,
    title: "Mathematics",
    subject: "Grade 12",
    meta: "Simultaneous Equations",
    emoji: "📐",
    colorClass: "cd-math",
    tagClass: "",
    video: "https://player.vimeo.com/video/1075679310?title=0&amp;byline=0&amp;portrait=0&amp;badge=0&amp;autopause=0&amp;player_id=0&amp;app_id=58479"
  },
  {
    id: 2,
    title: "Accounts",
    subject: "Grade 10",
    meta: "Double Entry",
    emoji: "🔬",
    colorClass: "cd-sci",
    tagClass: "green",
    video: "https://player.vimeo.com/video/820746971?h=f2f8ed7cf3"
  }
]);

onMounted(async () => {
  loading.value = true;

  try {
    await cache.fetchAllMetadata();

    const res = await api.get("/announcement");
    announcement.value = res.data;

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

/* ── Quick action icons ── */
.q-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.ic-indigo {
  background: rgba(99, 102, 241, 0.15);
  border: 1px solid rgba(99, 102, 241, 0.3);
}

.ic-violet {
  background: rgba(139, 92, 246, 0.15);
  border: 1px solid rgba(139, 92, 246, 0.3);
}

.ic-pink {
  background: rgba(236, 72, 153, 0.12);
  border: 1px solid rgba(236, 72, 153, 0.25);
}

.ic-teal {
  background: rgba(20, 184, 166, 0.12);
  border: 1px solid rgba(20, 184, 166, 0.25);
}

.ic-amber {
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.2);
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

.course-tag--amber {
  background: rgba(245, 158, 11, 0.1);
  border-color: rgba(245, 158, 11, 0.2);
  color: #fcd34d;
}

/* ── Info card extras ── */
.info-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 17px;
}

.contact-label {
  font-size: 0.65rem;
  color: #475569;
  min-width: 42px;
  display: inline-block;
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
  border-radius: 14px;
  overflow: hidden;
  background: #0f172a !important;

  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
}

.video-container {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  background: #000;
  overflow: hidden;
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
}


/* PHOTOS */
.hero-students {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 12px;
}

/* BIG IMAGE */
.hero-main {
  position: relative;
  border-radius: 18px;
  overflow: hidden;
  height: 180px;

}

.hero-main img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* OVERLAY TEXT */
.hero-overlay {
  position: absolute;
  bottom: 0;
  padding: 16px;
  width: 100%;
  background: linear-gradient(to top,
      rgba(0, 0, 0, 0.8) 0%,
      rgba(0, 0, 0, 0.4) 50%,
      transparent 100%);
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

/* RIGHT SIDE */
.hero-side {
  display: grid;
  grid-template-rows: 1fr 1fr;
  gap: 12px;
  height: 180px;

}

.hero-small {
  border-radius: 18px;
  overflow: hidden;
}

.hero-small img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  filter: brightness(0.85);
}

.hero-main:hover img,
.hero-small:hover img {
  transform: scale(1.03);
}

.hero-main img,
.hero-small img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  will-change: transform;
  /* Use 'ease-out' for a snappier, more professional feel */
  transition: transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

.hero-main,
.hero-small {

  /* This prevents the "jagged corner" look on dark themes */
  border: 1px solid rgba(255, 255, 255, 0.08);
  /* This forces the browser to use hardware acceleration for smoother scaling */
  transform: translateZ(0);

  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1),
    0 4px 15px rgba(0, 0, 0, 0.5);
}
</style>