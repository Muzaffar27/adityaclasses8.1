<template>
  <Layout title="Home" :showBack="false">

    <!-- Announcement -->
    <div class="announcement-bar mb-5" v-if="announcement">
      <span class="ann-icon">📢</span>
      <div>
        <p class="ann-title">{{ announcement.title }}</p>
        <p class="ann-body">{{ announcement.body }}</p>
      </div>
    </div>

    <!-- Stats Row -->
    <div class="columns is-mobile mb-5">
      <div class="column" v-for="stat in stats" :key="stat.label">
        <div class="stat-pill has-text-centered">
          <p class="stat-num">{{ stat.value }}</p>
          <p class="stat-lbl">{{ stat.label }}</p>
        </div>
      </div>
    </div>

    <div class="learning-wrapper  learning-panel" style="margin-bottom: 2.5rem;">

      <!-- LEFT: My Learning -->
      <div class="learning-left">
        <p class="section-label section-primary mb-3">My Learning</p>

        <div
          class="glass-card clickable-card star-card p-4 is-flex is-align-items-center is-justify-content-center has-text-centered"
          @click="$router.push({ name: 'myCourses' })">

          <div>

            <div class="mb-2">
              <span class="tag is-primary is-light is-rounded is-medium">
                ▶
              </span>
            </div>

            <p class="has-text-weight-semibold is-size-7 mb-1">
              My Courses
            </p>
            <p class="is-size-7 has-text-grey-light">
              Continue learning
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
    <p class="section-label mb-3">Demo courses</p>
    <div class="mb-5">
      <div v-if="courses.length">
        <div v-for="course in courses" :key="course.id" class="course-row mb-2"
          @click="$router.push({ name: 'myCourses' })">
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
            <p class="is-size-7 has-text-weight-semibold mb-0">Want more courses?</p>
            <p class="is-size-7 has-text-grey-light">'Browse by Grade' above for more</p>
          </div>
          <span class="course-tag course-tag--amber">Request</span>
        </div>
      </div>
      <div v-else class="empty-container has-text-centered">
        <p class="has-text-grey-light">No courses yet. Request access to get started 🚀</p>
      </div>
    </div>

    <!-- Info Cards -->
    <p class="section-label mb-3">About Aditya Classes</p>
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
          <p class="is-size-7 mb-1"><span class="contact-label">Phone</span> +230 5XXX XXXX</p>
          <p class="is-size-7"><span class="contact-label">Email</span> aditya@example.com</p>
        </div>
      </div>

      <div class="column is-12-mobile is-12-tablet is-4-desktop">
        <div class="glass-card p-4">
          <div class="info-icon ic-amber mb-3">📍</div>
          <h3 class="title is-6 has-text-white mb-2">Schedule & Location</h3>
          <p class="is-size-7 has-text-grey-light">Rose Hill & online · Sat 9am–1pm · Sun 2pm–6pm. Recordings available
            within 24 hrs for missed sessions.</p>
        </div>
      </div>

      <div class="column is-12-mobile is-6-tablet is-4-desktop">
        <div class="glass-card p-4">
          <div class="info-icon ic-indigo mb-3">📚</div>
          <h3 class="title is-6 has-text-white mb-2">Subjects</h3>
          <p class="is-size-7 has-text-grey-light">Maths · Physics · Chemistry · Biology · French · English · Accounting
            · Economics</p>
        </div>
      </div>

      <div class="column is-12-mobile is-6-tablet is-4-desktop">
        <div class="glass-card p-4">
          <div class="info-icon ic-pink mb-3">💬</div>
          <h3 class="title is-6 has-text-white mb-2">Need Help?</h3>
          <p class="is-size-7 has-text-grey-light">WhatsApp Mr. Aditya or use the Request Access form. All inquiries
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

  </Layout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import Layout from './common/Layout.vue';
import { useRouter } from "vue-router";
import { useCacheStore } from "../stores/cache";

import { storeToRefs } from "pinia";
import Loader from "./common/Loader.vue";

const router = useRouter();
const cacheStore = useCacheStore();

const { grades } = storeToRefs(cacheStore);
const loading = ref(false);

const announcement = ref({
  title: "New batch starting 15 July!",
  body: "Grade 9 & 10 Science — Limited seats. Contact Mr. Aditya to reserve yours."
});

const stats = ref([
  { value: "120+", label: "Students" },
  { value: "8", label: "Subjects" },
  { value: "5★", label: "Rated" }
]);


function selectGrade(grade) {
  let $gradeId = grade.id;

  router.push({
    name: "package",
    params: { id: $gradeId }
  });
}

onMounted(async () => {
  loading.value = true;

  try {
    await cacheStore.fetchAllMetadata();
  } finally {
    loading.value = false;
  }
});

const courses = ref([
  { id: 1, title: "Mathematics — Grade 10", subject: "Math", meta: "12 lessons · 3 remaining", emoji: "📐", colorClass: "cd-math", tagClass: "" },
  { id: 2, title: "Physics Basics", subject: "Physics", meta: "8 lessons · In progress", emoji: "🔬", colorClass: "cd-sci", tagClass: "green" },
]);
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

/* ── Stats ── */
.stat-pill {
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.07);
  padding: 12px 8px;
}

.stat-num {
  font-size: 1.3rem;
  font-weight: 800;
  color: #a5b4fc;
  margin: 0 0 2px;
  line-height: 1;
}

.stat-lbl {
  font-size: 0.6rem;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.1em;
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
</style>