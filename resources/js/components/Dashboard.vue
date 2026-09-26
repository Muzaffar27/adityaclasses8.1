<template>
    <Layout title="" :loading="loading" :show-back="false">
        <main class="dashboard-surface">
            <header class="dashboard-header">
                <div>
                    <p class="overline">Student dashboard</p>
                    <h1>{{ greeting }}, {{ firstName }}</h1>
                    <p v-if="dashboard.student.is_preview">Preview mode · student activity will appear here automatically.</p>
                    <p v-else>{{ dashboard.student.grade || 'Your learning space' }} · pick up exactly where you stopped.</p>
                </div>
                <button class="profile-button" type="button" @click="router.push({ name: 'profile' })">
                    <UserCircleIcon /><span>Profile</span>
                </button>
            </header>

            <div v-if="error" class="dashboard-alert" role="alert">
                <ExclamationTriangleIcon />
                <div><strong>We could not load your dashboard</strong><p>{{ error }}</p></div>
                <button type="button" @click="fetchDashboard">Try again</button>
            </div>

            <template v-else>
                <section class="stats-strip" aria-label="Learning statistics">
                    <article v-for="stat in statCards" :key="stat.label" class="stat-item">
                        <span class="stat-icon" :class="stat.tone"><component :is="stat.icon" /></span>
                        <div class="stat-copy"><span>{{ stat.label }}</span><strong>{{ stat.value }}</strong><small>{{ stat.note }}</small></div>
                    </article>
                </section>

                <section class="section-block">
                    <div class="section-heading">
                        <div><p class="overline">Next up</p><h2>Quick access</h2></div>
                        <span>Only what needs your attention</span>
                    </div>

                    <div class="action-grid">
                        <article v-if="dashboard.continue_learning" class="resume-card">
                            <div class="resume-top">
                                <span class="resume-icon"><PlayIcon /></span>
                                <span class="resume-label">Continue watching</span>
                                <span class="resume-time">{{ formatTime(dashboard.continue_learning.position_seconds) }}</span>
                            </div>
                            <div class="resume-content">
                                <p>{{ dashboard.continue_learning.subject }} · {{ dashboard.continue_learning.topic }}</p>
                                <h3>{{ dashboard.continue_learning.title }}</h3>
                                <span v-if="dashboard.continue_learning.video_type === 'answer'" class="answer-tag">Answer video</span>
                            </div>
                            <div class="progress-track" role="progressbar" :aria-valuenow="dashboard.continue_learning.progress_percent" aria-valuemin="0" aria-valuemax="100">
                                <span :style="{ width: `${dashboard.continue_learning.progress_percent}%` }"></span>
                            </div>
                            <div class="resume-footer">
                                <small>{{ dashboard.continue_learning.progress_percent }}% watched</small>
                                <button type="button" @click="resumeLesson"><PlayIcon /> Resume</button>
                            </div>
                        </article>

                        <article v-else class="start-card">
                            <span class="start-icon"><PlayIcon /></span>
                            <div>
                                <p class="card-kicker">Continue watching</p>
                                <h3>No video in progress</h3>
                                <p>Open a lesson and your latest position will appear here.</p>
                            </div>
                            <button v-if="primaryCourse" type="button" @click="openCourse(primaryCourse)">Start a lesson <ArrowRightIcon /></button>
                        </article>

                        <article class="test-card">
                            <div class="test-card-top"><span class="test-icon"><ClipboardDocumentCheckIcon /></span><span class="soon-badge">Coming next</span></div>
                            <div><p class="card-kicker">Tests to complete</p><h3>No tests due</h3><p>Assigned tests and deadlines will appear here—not mixed with your course library.</p></div>
                        </article>
                    </div>
                </section>

                <section class="section-block courses-section">
                    <div class="section-heading">
                        <div><p class="overline">Learning library</p><h2>Your courses</h2></div>
                        <router-link v-if="dashboard.courses.length" :to="{ name: 'myCourses' }">See all courses <ArrowRightIcon /></router-link>
                    </div>

                    <div v-if="featuredCourses.length" class="course-grid">
                        <button v-for="(course, index) in featuredCourses" :key="course.id" class="course-card" type="button" @click="openCourse(course)">
                            <span class="course-symbol" :class="`course-color-${index % 4}`">{{ courseInitial(course.subject) }}</span>
                            <span class="course-copy"><small>{{ course.grade }}</small><strong>{{ course.subject }}</strong><span>{{ lessonLabel(course.lesson_count) }}</span></span>
                            <span class="course-open"><ChevronRightIcon /></span>
                        </button>
                    </div>
                    <div v-else class="empty-courses"><BookOpenIcon /><div><h3>{{ dashboard.courses.length ? 'No current-grade courses yet' : 'Your courses will appear here' }}</h3><p>{{ dashboard.courses.length ? 'You can still find your other available courses under See all courses.' : 'Once access is granted, every course will be one tap away.' }}</p></div></div>
                </section>
            </template>
        </main>
    </Layout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRightIcon, BookOpenIcon, ChevronRightIcon, ClipboardDocumentCheckIcon, ExclamationTriangleIcon, PlayIcon, PresentationChartLineIcon, Squares2X2Icon, UserCircleIcon } from '@heroicons/vue/24/outline'
import api from '../api'
import Layout from './common/Layout.vue'

const router = useRouter()
const loading = ref(true)
const error = ref('')
const dashboard = reactive({
    student: { name: '', grade_id: null, grade: null, is_preview: false },
    stats: { active_courses: 0, available_lessons: 0, tests_attempted: 0, average_score: null },
    courses: [], continue_learning: null, recent_results: [],
})

const firstName = computed(() => dashboard.student.name?.trim().split(/\s+/)[0] || 'Student')
const greeting = computed(() => new Date().getHours() < 12 ? 'Good morning' : new Date().getHours() < 18 ? 'Good afternoon' : 'Good evening')
const featuredCourses = computed(() => {
    if (!dashboard.student.grade_id) return dashboard.courses.slice(0, 3)

    return dashboard.courses
        .filter(course => Number(course.grade_id) === Number(dashboard.student.grade_id))
        .slice(0, 3)
})
const primaryCourse = computed(() => featuredCourses.value[0] || dashboard.courses[0] || null)
const statCards = computed(() => [
    { label: 'Courses', value: dashboard.stats.active_courses, note: 'Active now', icon: Squares2X2Icon, tone: 'blue' },
    { label: 'Lessons', value: dashboard.stats.available_lessons, note: 'Ready to learn', icon: BookOpenIcon, tone: 'aqua' },
    { label: 'Tests done', value: dashboard.stats.tests_attempted, note: 'No attempts yet', icon: ClipboardDocumentCheckIcon, tone: 'green' },
    { label: 'Average', value: dashboard.stats.average_score === null ? '—' : `${dashboard.stats.average_score}%`, note: 'After your first test', icon: PresentationChartLineIcon, tone: 'orange' },
])

async function fetchDashboard() {
    loading.value = true
    error.value = ''
    try {
        const { data } = await api.get('/student/dashboard')
        Object.assign(dashboard.student, data.student)
        Object.assign(dashboard.stats, data.stats)
        dashboard.courses = data.courses || []
        dashboard.continue_learning = data.continue_learning || null
        dashboard.recent_results = data.recent_results || []
    } catch (requestError) {
        console.error('Could not fetch student dashboard', requestError)
        error.value = 'Please check your connection and try again.'
    } finally { loading.value = false }
}

function openCourse(course) { router.push({ name: 'lesson', params: { subjectId: course.subject_id, gradeId: course.grade_id } }) }
function resumeLesson() {
    const item = dashboard.continue_learning
    if (!item) return
    router.push({ name: 'lesson', params: { subjectId: item.subject_id, gradeId: item.grade_id }, query: { resume: item.lesson_id, video: item.video_type } })
}
function courseInitial(subject) { return subject?.trim().charAt(0).toUpperCase() || 'C' }
function lessonLabel(count) { return `${count} ${count === 1 ? 'lesson' : 'lessons'}` }
function formatTime(seconds) {
    const total = Math.max(0, Number(seconds) || 0)
    const hours = Math.floor(total / 3600)
    const minutes = Math.floor((total % 3600) / 60)
    const remaining = Math.floor(total % 60)
    return hours ? `${hours}:${String(minutes).padStart(2, '0')}:${String(remaining).padStart(2, '0')}` : `${minutes}:${String(remaining).padStart(2, '0')}`
}

onMounted(fetchDashboard)
</script>

<style scoped>
.dashboard-surface {
    --glass: rgba(255, 255, 255, 0.04);
    --glass-hover: rgba(255, 255, 255, 0.065);
    --glass-border: rgba(255, 255, 255, 0.1);
    --muted: rgba(203, 213, 225, 0.72);
    background: transparent;
    border: 0;
    border-radius: 0;
    box-shadow: none;
    color: #fff;
    margin: 0 auto 2rem;
    max-width: 1480px;
    min-height: calc(100vh - 130px);
    overflow: visible;
    padding: clamp(1rem, 2.2vw, 2rem);
}

.dashboard-header,
.stats-strip,
.start-card,
.test-card,
.course-card,
.empty-courses {
    background: var(--glass);
    border: 1px solid var(--glass-border);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}

.dashboard-header {
    align-items: center;
    background:
        linear-gradient(118deg, rgba(79, 70, 229, 0.16), rgba(255, 255, 255, 0.035) 48%, rgba(99, 102, 241, 0.07)),
        var(--glass);
    border-radius: 20px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
    display: flex;
    justify-content: space-between;
    min-height: 150px;
    overflow: hidden;
    padding: clamp(1.25rem, 3vw, 2.25rem);
    position: relative;
}

.dashboard-header::after {
    display: none;
}

.overline,
.card-kicker {
    color: #a5b4fc;
    font-size: 0.69rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    margin: 0 0 0.38rem;
    text-transform: uppercase;
}

.dashboard-header h1,
.section-heading h2,
.start-card h3,
.test-card h3,
.empty-courses h3 {
    color: #fff;
}

.dashboard-header h1 {
    font-size: clamp(1.65rem, 4vw, 2.4rem);
    font-weight: 800;
    letter-spacing: -0.035em;
    margin: 0;
}

.dashboard-header p:not(.overline) {
    color: var(--muted);
    font-size: 0.82rem;
    margin: 0.4rem 0 0;
}

.profile-button {
    align-items: center;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 999px;
    color: #e0e7ff;
    cursor: pointer;
    display: flex;
    font-size: 0.75rem;
    font-weight: 700;
    gap: 0.45rem;
    min-height: 42px;
    padding: 0.55rem 0.85rem;
    position: relative;
    transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    z-index: 1;
}

.profile-button:hover,
.profile-button:focus-visible {
    background: rgba(99, 102, 241, 0.16);
    border-color: rgba(129, 140, 248, 0.42);
    outline: none;
    transform: translateY(-1px);
}

.profile-button svg { height: 20px; width: 20px; }

.stats-strip {
    border-radius: 18px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin-top: 1rem;
    overflow: hidden;
}

.stat-item {
    align-items: center;
    display: flex;
    gap: 0.75rem;
    min-width: 0;
    padding: 1rem 1.15rem;
}

.stat-item + .stat-item { border-left: 1px solid rgba(255, 255, 255, 0.08); }
.stat-icon { align-items: center; border-radius: 12px; display: flex; flex: 0 0 auto; height: 42px; justify-content: center; width: 42px; }
.stat-icon svg { height: 21px; width: 21px; }
.stat-icon.blue { background: rgba(99, 102, 241, 0.18); color: #a5b4fc; }
.stat-icon.aqua { background: rgba(129, 140, 248, 0.16); color: #c7d2fe; }
.stat-icon.green { background: rgba(52, 211, 153, 0.13); color: #6ee7b7; }
.stat-icon.orange { background: rgba(245, 158, 11, 0.13); color: #fcd34d; }
.stat-copy { min-width: 0; }
.stat-copy span,
.stat-copy small { color: var(--muted); display: block; font-size: 0.65rem; }
.stat-copy strong { color: #fff; display: block; font-size: 1.5rem; line-height: 1.05; margin: 0.12rem 0; }

.section-block { margin-top: clamp(1.35rem, 3vw, 2.2rem); }
.section-heading { align-items: end; display: flex; justify-content: space-between; margin-bottom: 0.85rem; }
.section-heading h2 { font-size: 1.22rem; margin: 0; }
.section-heading > span { color: rgba(148, 163, 184, 0.82); font-size: 0.7rem; }
.section-heading a { align-items: center; color: #a5b4fc; display: flex; font-size: 0.72rem; font-weight: 700; gap: 0.3rem; }
.section-heading a svg { height: 15px; width: 15px; }

.action-grid { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1.7fr) minmax(280px, 0.8fr); }
.resume-card,
.start-card,
.test-card { border-radius: 20px; min-height: 225px; padding: 1.3rem; }

.resume-card {
    animation: resumePulse 2.5s ease-in-out infinite;
    background:
        linear-gradient(135deg, rgba(79, 70, 229, 0.28), rgba(99, 102, 241, 0.12)),
        rgba(15, 23, 42, 0.68);
    border: 1.5px solid rgba(99, 102, 241, 0.55);
    box-shadow: 0 18px 42px rgba(30, 27, 75, 0.28);
    color: #fff;
    position: relative;
}

@keyframes resumePulse {
    0%, 100% { box-shadow: 0 18px 42px rgba(30, 27, 75, 0.28), 0 0 0 0 rgba(99, 102, 241, 0.4); }
    50% { box-shadow: 0 18px 42px rgba(30, 27, 75, 0.28), 0 0 0 8px rgba(99, 102, 241, 0); }
}

.resume-top { align-items: center; display: flex; gap: 0.5rem; }
.resume-icon { align-items: center; background: rgba(255, 255, 255, 0.14); border-radius: 10px; display: flex; height: 34px; justify-content: center; width: 34px; }
.resume-icon svg { height: 17px; width: 17px; }
.resume-label { font-size: 0.72rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; }
.resume-time { background: rgba(15, 23, 42, 0.28); border-radius: 999px; font-size: 0.68rem; font-weight: 800; margin-left: auto; padding: 0.3rem 0.55rem; }
.resume-content { margin-top: 1.15rem; }
.resume-content p { color: rgba(224, 231, 255, 0.78); font-size: 0.68rem; margin: 0 0 0.3rem; }
.resume-content h3 { color: #fff; font-size: clamp(1.1rem, 2.5vw, 1.45rem); margin: 0; }
.answer-tag { background: rgba(255, 255, 255, 0.14); border-radius: 999px; display: inline-block; font-size: 0.62rem; font-weight: 800; margin-top: 0.45rem; padding: 0.26rem 0.48rem; }
.progress-track { background: rgba(255, 255, 255, 0.18); border-radius: 999px; height: 8px; margin-top: 1.3rem; overflow: hidden; }
.progress-track span { background: rgba(203, 213, 225, 0.88); border-radius: inherit; display: block; height: 100%; min-width: 4px; }
.resume-footer { align-items: center; display: flex; justify-content: space-between; margin-top: 0.7rem; }
.resume-footer small { color: rgba(238, 242, 255, 0.8); font-size: 0.64rem; }
.resume-footer button,
.start-card button { align-items: center; border: 0; border-radius: 999px; cursor: pointer; display: flex; font-size: 0.72rem; font-weight: 800; gap: 0.35rem; min-height: 38px; padding: 0.5rem 0.75rem; }
.resume-footer button {
    background: rgba(15, 23, 42, 0.78);
    border: 1px solid rgba(199, 210, 254, 0.2);
    color: #eef2ff;
    transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}
.resume-footer button:hover,
.resume-footer button:focus-visible {
    background: rgba(30, 41, 59, 0.92);
    border-color: rgba(199, 210, 254, 0.42);
    outline: none;
    transform: translateY(-1px);
}
.resume-footer button svg,
.start-card button svg { height: 15px; width: 15px; }

.start-card { align-items: flex-start; display: grid; gap: 0.75rem; grid-template-columns: auto minmax(0, 1fr); }
.start-icon,
.test-icon { align-items: center; background: rgba(99, 102, 241, 0.16); border-radius: 12px; color: #a5b4fc; display: flex; height: 42px; justify-content: center; width: 42px; }
.start-icon svg,
.test-icon svg { height: 21px; width: 21px; }
.start-card h3,
.test-card h3,
.empty-courses h3 { font-size: 1rem; margin: 0; }
.start-card p:not(.card-kicker),
.test-card p:not(.card-kicker),
.empty-courses p { color: var(--muted); font-size: 0.71rem; line-height: 1.5; margin: 0.25rem 0 0; }
.start-card button { background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff; grid-column: 1 / -1; justify-self: start; margin-top: auto; }
.test-card { display: flex; flex-direction: column; justify-content: space-between; }
.test-card-top { align-items: center; display: flex; justify-content: space-between; }
.soon-badge { background: rgba(99, 102, 241, 0.14); border: 1px solid rgba(129, 140, 248, 0.2); border-radius: 999px; color: #c7d2fe; font-size: 0.6rem; font-weight: 800; padding: 0.32rem 0.52rem; }

.course-grid { display: grid; gap: 0.75rem; grid-template-columns: repeat(3, minmax(0, 1fr)); }
.course-card { align-items: center; border-radius: 18px; color: inherit; cursor: pointer; display: grid; gap: 0.75rem; grid-template-columns: auto minmax(0, 1fr) auto; min-height: 92px; padding: 0.85rem; text-align: left; transition: background 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease, transform 0.22s ease; }
.course-card:hover,
.course-card:focus-visible { background: var(--glass-hover); border-color: rgba(99, 102, 241, 0.48); box-shadow: 0 14px 30px rgba(0, 0, 0, 0.24); outline: none; transform: translateY(-3px); }
.course-symbol { align-items: center; border-radius: 13px; display: flex; font-size: 1rem; font-weight: 800; height: 48px; justify-content: center; width: 48px; }
.course-color-0 { background: rgba(79, 70, 229, 0.18); color: #a5b4fc; }
.course-color-1 { background: rgba(99, 102, 241, 0.16); color: #c7d2fe; }
.course-color-2 { background: rgba(129, 140, 248, 0.14); color: #e0e7ff; }
.course-color-3 { background: rgba(139, 92, 246, 0.15); color: #ddd6fe; }
.course-copy small,
.course-copy strong,
.course-copy span { display: block; }
.course-copy small,
.course-copy span { color: var(--muted); font-size: 0.64rem; }
.course-copy strong { color: #fff; font-size: 0.83rem; margin: 0.08rem 0; }
.course-open { color: #a5b4fc; }
.course-open svg { height: 18px; width: 18px; }
.empty-courses { align-items: center; border-style: dashed; border-radius: 18px; display: flex; gap: 0.8rem; padding: 1.2rem; }
.empty-courses > svg { color: #a5b4fc; height: 34px; width: 34px; }

.dashboard-alert { align-items: center; background: rgba(190, 18, 60, 0.14); border: 1px solid rgba(251, 113, 133, 0.28); border-radius: 14px; color: #fecdd3; display: flex; gap: 0.7rem; margin-top: 1rem; padding: 0.8rem; }
.dashboard-alert > svg { height: 23px; width: 23px; }
.dashboard-alert div { flex: 1; }
.dashboard-alert p { font-size: 0.68rem; margin: 0.1rem 0 0; }
.dashboard-alert button { background: #be123c; border: 0; border-radius: 8px; color: #fff; padding: 0.45rem 0.65rem; }

@media (max-width: 1000px) {
    .course-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .action-grid { grid-template-columns: minmax(0, 1.35fr) minmax(250px, 0.85fr); }
}

@media (max-width: 760px) {
    .dashboard-surface { min-height: calc(100vh - 118px); padding: 0.8rem; }
    .dashboard-header { align-items: flex-start; flex-direction: column; gap: 1rem; min-height: 0; padding: 1.15rem; }
    .profile-button { min-height: 40px; }
    .stats-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .stat-item { align-items: flex-start; flex-direction: column; gap: 0.45rem; padding: 0.8rem; }
    .stat-item + .stat-item { border-left: 0; }
    .stat-item:nth-child(even) { border-left: 1px solid rgba(255, 255, 255, 0.08); }
    .stat-item:nth-child(n + 3) { border-top: 1px solid rgba(255, 255, 255, 0.08); }
    .stat-icon { height: 36px; width: 36px; }
    .stat-copy strong { font-size: 1.3rem; }
    .section-heading { align-items: flex-start; }
    .section-heading > span { display: none; }
    .action-grid,
    .course-grid { grid-template-columns: 1fr; }
    .resume-card,
    .start-card,
    .test-card { min-height: 205px; padding: 1rem; }
    .course-card { min-height: 84px; }
    .resume-footer button { min-height: 42px; }
}

@media (prefers-reduced-motion: reduce) {
    .resume-card,
    .course-card,
    .profile-button { transition: none; }

    .resume-card { animation: none; }
}
</style>
