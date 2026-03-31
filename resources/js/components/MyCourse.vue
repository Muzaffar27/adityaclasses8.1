<template>
    <Layout title="Enrolled Courses" :loading="loading" :showBack="false">

        <div v-if="loading" class="columns is-multiline is-mobile">
            <div v-for="i in 3" :key="i" class="column is-12-mobile is-4-tablet">
                <div class="skeleton-card"></div>
            </div>
        </div>

        <div v-else class="columns is-multiline is-mobile">
            <div v-for="access in enrolledCourses" :key="access.id" class="column is-12-mobile is-4-tablet">
                <div class="card course-card">
                    <div class="card-content">
                        <div>
                            <div class="is-flex is-justify-content-between is-align-items-start mb-4">

                                <span class="tag is-dark-accent">
                                    {{ access.grade?.name }}
                                </span>

                                <AcademicCapIcon class=" ml-2 hero-icon-lg has-text-primary move-up" />

                            </div>

                            <h3 class="title is-5 has-text-white mb-5">
                                {{ access.subject?.name }}
                            </h3>
                        </div>

                        <button class="button is-primary is-fullwidth is-rounded action-btn"
                            @click="viewLessons(access.grade_id, access.subject_id)">
                            <span>View videos</span>
                            <span class="icon is-small ml-2">
                                <ArrowRightIcon class="hero-icon-sm" />
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="enrolledCourses.length === 0 && !loading" class="column is-12 has-text-centered py-6">
                <div class="empty-container">
                    <div class="is-flex is-justify-content-center mb-3">
                        <FolderOpenIcon class="hero-icon-xl has-text-grey-dark" />
                    </div>
                    <h3 class="is-size-4 has-text-white mt-3">No courses yet</h3>
                    <p class="has-text-grey mb-5">Start your learning journey by browsing our subjects.</p>
                    <router-link to="/subjects" class="button is-primary is-outlined is-rounded">
                        Browse Catalog
                    </router-link>
                </div>
            </div>
        </div>
    </Layout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import { useRouter } from 'vue-router';
import Layout from './common/Layout.vue';

// IMPORT HEROICONS (Outline version is usually best for dark mode)
import {
    AcademicCapIcon,
    ArrowRightIcon,
    FolderOpenIcon
} from '@heroicons/vue/24/outline';

const router = useRouter();
const enrolledCourses = ref([]);
const loading = ref(false);

const fetchMyCourses = async () => {
    loading.value = true;
    try {
        const res = await api.get('/myCourses');
        enrolledCourses.value = res.data;
    } catch (err) {
        console.error("Could not fetch courses", err);
    } finally {
        loading.value = false;
    }
};

const viewLessons = (gradeId, subjectId) => {
    router.push({
        name: "lesson",
        params: { gradeId, subjectId }
    });
};

onMounted(fetchMyCourses);
</script>
<style scoped>
/* Grid fix for mobile */
.columns.is-mobile {
    padding: 0 10px;
}

.course-card {
    border-radius: 20px;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    /* Glassmorphism effect */
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    height: 100%;
    position: relative;
    overflow: hidden;
}

.course-card:hover {
    transform: translateY(-8px);
    background: rgba(255, 255, 255, 0.06);
    border-color: #4f46e5;
    /* Your primary color */
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
}

.is-dark-accent {
    background-color: rgba(79, 70, 229, 0.15);
    color: #a5b4fc;
    border: 1px solid rgba(79, 70, 229, 0.3);
    font-weight: 600;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.action-btn {
    font-weight: 600;
    transition: all 0.3s ease;
}

.action-btn:active {
    transform: scale(0.95);
    /* Tactile feedback for mobile */
}

/* Empty State Styling */
.empty-container {
    padding: 3rem;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 24px;
    border: 1px dashed rgba(255, 255, 255, 0.1);
}

.empty-icon {
    font-size: 3rem;
    filter: grayscale(1);
    opacity: 0.5;
}

/* Skeleton Loading Animation */
.skeleton-card {
    height: 200px;
    background: linear-gradient(90deg, #121212 25%, #1a1a1a 50%, #121212 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 20px;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}

/* Mobile optimizations */
@media (max-width: 768px) {
    .column.is-12-mobile {
        padding-bottom: 1.5rem;
    }

    .title.is-5 {
        font-size: 1.1rem;
    }
}

/* Heroicon Styling */
.hero-icon-lg {
    width: 28px;
    height: 28px;
}

.hero-icon-sm {
    width: 18px;
    height: 18px;
}

.hero-icon-xl {
    width: 64px;
    height: 64px;
    opacity: 0.4;
}

/* Ensure the button content stays centered with the icon */
.action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.move-up {
    margin-top: -3px;
    /* Adjust this number to go higher or lower */
    /* Alternative: transform: translateY(-4px); */
}
</style>