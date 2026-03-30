<template>
    <Layout title="Lesson List" :loading="loading" @back="goBack">

        <!-- HEADER -->
        <div class="admin-header">
            <div>
                <p class="has-text-grey">Create, edit, and delete lessons</p>
            </div>

            <button class="button is-primary" @click="createLesson">
                + Add Lesson
            </button>
        </div>

        <div v-if="lessons.length > 0" class="mb-3 has-text-grey is-size-7">
            Showing lessons for:
            <strong>{{ gradeName }}</strong> •
            <strong>{{ subjectName }}</strong>
        </div>
        <div v-else-if="!loading" class="mb-3 has-text-grey is-size-7">
            No lessons found for this selection.
        </div>

        <!-- ===================== -->
        <!-- DESKTOP TABLE VIEW -->
        <!-- ===================== -->
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
                    <tr v-for="lesson in lessons" :key="lesson.id">
                        <td>
                            <strong>{{ lesson.title }}</strong>
                            <p class="is-size-7 has-text-grey">
                                {{ lesson.topic }}
                            </p>
                        </td>

                        <td :class="{ 'has-text-grey-light is-italic': !lesson.part_number }">
                            {{ lesson.part_number ?? 'N/A' }}
                        </td>

                        <td>{{ lesson.is_active ? 'Yes' : 'No' }}</td>

                        <td>
                            <button class="button is-small" @click="toggleEdit(lesson.id)">
                                {{ editingId === lesson.id ? 'Close' : 'Edit' }}
                            </button>
                        </td>

                        <!-- <button class="button is-small is-danger" @click="deleteLesson(lesson.id)">
                                Delete
                            </button> -->

                    <tr v-if="editingId === lesson.id" class="edit-row-active">
                        <td colspan="4" style="padding: 0;">
                            <LessonEditForm :lesson="lesson" @saved="onLessonSaved" @cancel="editingId = null" />
                        </td>
                    </tr>
                    </tr>
                </tbody>
            </table>

        </div>

        <!-- ===================== -->
        <!-- MOBILE CARD VIEW -->
        <!-- ===================== -->
        <div class="is-hidden-tablet">

            <div v-for="lesson in lessons" :key="lesson.id" class="mobile-card">

                <div class="card-content">

                    <div class="card-top">
                        <strong>{{ lesson.title }}</strong>
                    </div>

                    <p class="is-size-7 has-text-grey mb-2">
                        {{ lesson.topic }}
                    </p>

                    <div class="is-flex is-justify-content-space-between is-align-items-center is-flex-wrap-wrap mt-3"
                        style="gap: 10px;">

                        <div class="is-flex is-flex-wrap-wrap" style="gap: 5px;">
                            <span class="tag is-warning is-light">
                                Part: {{ lesson.part_number ?? 'N/A' }}
                            </span>

                            <span class="tag" :class="lesson.is_active ? 'is-success' : 'is-danger'">
                                {{ lesson.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <div class="is-flex" style="gap: 8px; margin-left: auto;">
                            <button class="button is-small is-info" @click="toggleEdit(lesson.id)">
                                {{ editingId === lesson.id ? 'Close' : 'Edit' }}
                            </button>

                            <div v-if="editingId === lesson.id" class="edit-row-active mt-3">
                                <LessonEditForm :lesson="lesson" @saved="onLessonSaved" @cancel="editingId = null" />
                            </div>

                            <!-- <button class="button is-small is-danger" @click="deleteLesson(lesson.id)">
                                Delete
                            </button> -->
                        </div>

                    </div>

                </div>
            </div>

        </div>

        <!-- EMPTY STATE -->
        <div v-if="!loading && lessons.length === 0" class="empty-state">
            No lessons found.
        </div>

    </Layout>
</template>


<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import api from '../../api';
import Layout from '../common/Layout.vue';
import LessonEditForm from './LessonEditForm.vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();

const lessons = ref([]);
const loading = ref(false);

const gradeId = route.params.grade_id;
const subjectId = route.params.subject_id;

const editingId = ref(null);

const toggleEdit = async (id) => {
    // If we are closing the current edit, just null it out
    if (editingId.value === id) {
        editingId.value = null;
        return;
    }

    // Set the ID to open the form
    editingId.value = id;

    // 2. Wait for Vue to render the LessonEditForm
    await nextTick();

    // 3. Find the newly opened edit row and scroll to it
    const editRow = document.querySelector('.edit-row-active');
    if (editRow) {
        editRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};

const onLessonSaved = () => {
    editingId.value = null; // Close the form
    fetchLessons(); // Refresh the list
};

const fetchLessons = async () => {
    loading.value = true;

    try {
        const res = await api.get("/admin/lessons", {
            params: {
                grade_id: gradeId,
                subject_id: subjectId
            }
        });

        lessons.value = res.data;

        console.log("Filtered lessons = ", lessons.value);

    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const gradeName = computed(() => lessons.value[0]?.grade?.name || '');
const subjectName = computed(() => lessons.value[0]?.subject?.name || '');

const deleteLesson = async (id) => {
    if (confirm("Are you sure?")) {
        await api.delete(`/admin/lessons/${id}`);
        await fetchLessons();
    }
};

function goBack() {
    router.back();
}

onMounted(() => {
    if (!gradeId || !subjectId) {
        console.warn("Missing filters");
        return;
    }

    fetchLessons();
});

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

.actions {
    display: flex;
    gap: 8px;
}

.empty-state {
    text-align: center;
    padding: 30px;
    color: #888;
}

/* MOBILE CARD STYLE */
.mobile-card {
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
}

.card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.card-bottom {
    margin-top: 8px;
}

.mobile-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

/* HEADER FIX FOR MOBILE */
@media (max-width: 768px) {
    .admin-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>
