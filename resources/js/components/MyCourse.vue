<template>
    <Layout title="Enrolled Courses" :loading="loading">

        <div v-if="loading" class="columns is-multiline is-mobile">
            <div v-for="i in 3" :key="i" class="column is-12-mobile is-4-tablet">
                <div class="skeleton-card"></div>
            </div>
        </div>

        <div v-else class="columns is-multiline is-mobile">
            <div v-for="access in enrolledCourses" :key="access.id" class="column is-12-mobile is-4-tablet">
                <div class="card glass-card">
                    <div class="card-content">
                        <div>
                            <div class="course-card-top mb-4">

                                <span class="tag is-dark-accent">
                                    {{ access.grade?.name }}
                                </span>

                                <span class="expiry-badge" :title="expiryTitle(access.expires_at)">
                                    <CalendarDaysIcon class="expiry-icon" />
                                    <span>Exp:</span>
                                    <strong>{{ formatExpiryDate(access.expires_at) }}</strong>
                                </span>

                            </div>

                            <h3 class="title is-5 has-text-white mb-5">
                                {{ access.subject?.name }}
                            </h3>
                        </div>

                        <button class="button is-primary is-fullwidth is-rounded action-btn has-text-white"
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
    CalendarDaysIcon,
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

function formatExpiryDate(value) {
    if (!value) return 'No expiry';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

function expiryTitle(value) {
    return value ? `Access expires on ${formatExpiryDate(value)}` : 'No expiry date';
}

onMounted(fetchMyCourses);
</script>

<style scoped>
.columns.is-mobile {
    padding: 0 10px;
}

.course-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.expiry-badge {
    display: inline-flex;
    align-items: baseline;
    gap: 5px;
    padding: 4px 8px;
    border: 1px solid rgba(251, 113, 133, 0.42);
    border-radius: 999px;
    background: rgba(251, 113, 133, 0.12);
    color: #fca5a5;
    font-size: 0.68rem;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
}

.expiry-badge strong {
    color: #fecaca;
    font-weight: 800;
}

.expiry-icon {
    width: 14px;
    height: 14px;
    flex: 0 0 auto;
    position: relative;
    top: 2px;
}
</style>
