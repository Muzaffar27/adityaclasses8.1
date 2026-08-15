<template>

    <!-- HEADER -->
    <div class="admin-header">
        <div>
            <p class="has-text-grey">Create, edit, and delete lessons</p>
        </div>

        <div class="is-flex is-justify-content-flex-end mb-4">
            <button class="button is-primary has-text-white" @click="createLesson">
                <span class="icon">
                    <PlusIcon />
                </span>
                <span>{{ creating ? 'Close Form' : 'Add Lesson' }}</span>
            </button>
        </div>
    </div>

    <!-- INFO -->
    <div v-if="lessons.length > 0" class="mb-3 has-text-grey is-size-7">
        Showing lessons for:
        <strong>{{ gradeName }}</strong> •
        <strong>{{ subjectName }}</strong>
    </div>

    <div v-else-if="!loading" class="mb-3 has-text-grey is-size-7">
        No lessons found for this selection.
    </div>

    <!-- TABLE -->
    <div class="table-wrapper is-hidden-mobile">

        <table class="table is-fullwidth is-hoverable">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Part</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

                <!-- CREATE ROW -->
                <tr v-if="creating" class="edit-row-active">
                    <td colspan="4" style="padding: 0;">
                        <LessonEditForm :grade_id="gradeId" :subject_id="subjectId" inline @saved="onCreated"
                            @cancel="creating = false" />
                    </td>
                </tr>

                <!-- GROUPED TOPICS -->
                <template v-for="group in paginatedTopics" :key="group.topic">

                    <!-- TOPIC -->
                    <tr class="topic-row">
                        <td colspan="4">
                            <div class="glass-card topic-card is-flex is-align-items-center is-justify-content-space-between"
                                @click="toggleTopic(group.topic)">
                                <div>
                                    <strong class="has-text-white">{{ group.topic }}</strong>
                                    <p class="is-size-7 has-text-grey">
                                        {{ group.lessons.length }} lessons
                                    </p>
                                </div>

                                <span class="tag is-dark-accent">
                                    {{ isTopicOpen(group.topic) ? 'Hide' : 'Show' }}
                                </span>
                            </div>
                        </td>
                    </tr>

                    <!-- LESSONS -->
                    <template v-if="isTopicOpen(group.topic)">
                        <template v-for="lesson in group.lessons" :key="lesson.id">

                            <tr>
                                <td>
                                    <strong class="ml-6">
                                        {{ lesson.title }}
                                    </strong>
                                    <p v-if="lesson.sub_topic" class="ml-6 is-size-7 has-text-grey">
                                        {{ lesson.sub_topic }}
                                    </p>
                                </td>

                                <td :class="{ 'has-text-grey-light is-italic': !lesson.part_number }">
                                    {{ lesson.part_number ?? 'N/A' }}
                                </td>

                                <td>{{ lesson.is_active ? 'Yes' : 'No' }}</td>

                                <td>
                                    <button class="button is-small" @click.stop="toggleEdit(lesson.id)">
                                        {{ editingId === lesson.id ? 'Close' : 'Edit' }}
                                    </button>
                                </td>
                            </tr>

                            <!-- EDIT FORM -->
                            <tr v-if="editingId === lesson.id" class="edit-row-active">
                                <td colspan="4" style="padding: 0;">
                                    <LessonEditForm inline :lesson="lesson" @saved="onLessonSaved" @resource-changed="fetchLessons"
                                        @cancel="editingId = null" />
                                </td>
                            </tr>

                        </template>
                    </template>

                </template>

            </tbody>
        </table>

    </div>

    <!-- MOBILE -->
    <div class="is-hidden-tablet">

        <div v-for="group in paginatedTopics" :key="group.topic">

            <div class="topic-header" @click="toggleTopic(group.topic)">
                <strong>{{ group.topic }}</strong>
                <span class="ml-2 has-text-grey">
                    ({{ group.lessons.length }})
                </span>
            </div>

            <div v-if="isTopicOpen(group.topic)">
                <div v-for="lesson in group.lessons" :key="lesson.id" class="mobile-card">

                    <div class="card-content">

                        <strong>{{ lesson.title }}</strong>
                        <p v-if="lesson.sub_topic" class="is-size-7 has-text-grey mt-1">
                            {{ lesson.sub_topic }}
                        </p>

                        <div class="mt-2">
                            <span class="tag is-warning is-light">
                                Part: {{ lesson.part_number ?? 'N/A' }}
                            </span>

                            <span class="tag ml-1" :class="lesson.is_active ? 'is-success' : 'is-danger'">
                                {{ lesson.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <button class="button is-small is-info mt-2" @click="toggleEdit(lesson.id)">
                            {{ editingId === lesson.id ? 'Close' : 'Edit' }}
                        </button>

                        <div v-if="editingId === lesson.id" class="edit-row-active mt-3">
                            <LessonEditForm inline :lesson="lesson" @saved="onLessonSaved" @resource-changed="fetchLessons"
                                @cancel="editingId = null" />
                        </div>

                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- PAGINATION -->
    <div v-if="totalPages > 1" class="pagination-bar mt-5 is-flex is-justify-content-center">

        <button class="pagination-btn" @click="prevPage" :disabled="currentPage === 1">‹</button>

        <div class="pagination-pages">
            <template v-for="(page, index) in visiblePages" :key="index">
                <span v-if="page === '...'" class="pagination-ellipsis">...</span>

                <button v-else class="page-pill" :class="{ active: currentPage === page }"
                    @click="goToPage(Number(page))">
                    {{ page }}
                </button>
            </template>
        </div>

        <button class="pagination-btn" @click="nextPage" :disabled="currentPage === totalPages">›</button>
    </div>

</template>

<script setup>
import { ref, computed, nextTick, watch, toRefs } from 'vue';
import api from '../../api';
import LessonEditForm from './LessonEditForm.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';
import { Pagination } from '../../composables/pagination';

/* PROPS (from TutorSpace) */
const props = defineProps({
    grade_id: [String, Number],
    subject_id: [String, Number]
});

/* ✅ reactive props */
const { grade_id: gradeId, subject_id: subjectId } = toRefs(props);

/* STATE */
const lessons = ref([]);
const loading = ref(false);
const openTopics = ref({});
const editingId = ref(null);
const creating = ref(false);

/* PAGINATION */
const {
    currentPage,
    paginatedTopics,
    totalPages,
    visiblePages,
    goToPage,
    nextPage,
    prevPage,
} = Pagination(lessons, 5, { type: 'grouped' });

/* WATCH props (CRITICAL FIX) */
watch(
    [gradeId, subjectId],
    ([g, s]) => {
        if (!g || !s) return;
        fetchLessons();
    },
    { immediate: true }
);

/* METHODS */
function toggleTopic(topic) {
    openTopics.value[topic] = !openTopics.value[topic];
}

const isTopicOpen = (topic) => openTopics.value[topic] === true;

const toggleEdit = async (id) => {
    if (editingId.value === id) {
        editingId.value = null;
        return;
    }

    editingId.value = id;

    await nextTick();

    const el = document.querySelector('.edit-row-active');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

const onLessonSaved = () => {
    editingId.value = null;
    fetchLessons();
};

const onCreated = () => {
    creating.value = false;
    fetchLessons();
};
function extractTopicNumber(topic) {
    const match = topic.match(/^(\d+)/);
    return match ? parseInt(match[1], 10) : null;
}

async function fetchLessons() {
    loading.value = true;

    try {
        const res = await api.get("/admin/lessons", {
            params: {
                grade_id: gradeId.value,
                subject_id: subjectId.value
            }
        });

        console.log("Lessons = ", res.data);

        const sorted = [...res.data].sort((a, b) => {
            const aNum = extractTopicNumber(a.topic);
            const bNum = extractTopicNumber(b.topic);

            // 1. No-number topics first
            if (aNum === null && bNum !== null) return -1;
            if (aNum !== null && bNum === null) return 1;

            // 2. both no numbers → alphabetical
            if (aNum === null && bNum === null) {
                return a.topic.localeCompare(b.topic);
            }

            // 3. both numbered → numeric sort
            return aNum - bNum;
        });

        lessons.value = sorted;

        currentPage.value = 1;

        await nextTick();

    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function createLesson() {
    editingId.value = null;
    creating.value = !creating.value;

    nextTick(() => {
        const el = document.querySelector('.edit-row-active');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

/* COMPUTED */
const gradeName = computed(() => lessons.value[0]?.grade?.name || '');
const subjectName = computed(() => lessons.value[0]?.subject?.name || '');
</script>

<style scoped>
.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.table-wrapper {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.mobile-card {
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
}

.topic-card {
    padding: 12px 16px;
    border-radius: 12px;
    cursor: pointer;
}
</style>
