<template>
    <Layout title="Enrolled Courses" :loading="loading" :showBack="false">

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
.columns.is-mobile {
    padding: 0 10px;
}

.move-up {
    margin-top: -3px;
}
</style>