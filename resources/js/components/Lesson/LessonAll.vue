<template>
    <!-- HEADER WITH FILTERS -->
    <div class="admin-header">
        <div>
            <p class="has-text-grey">Create, edit, and delete lessons</p>
        </div>
        <div class="is-flex is-justify-content-flex-end mb-4">

            <button class="button is-primary has-text-white mr-3" @click="showCreateSubject = true">
                <span class="icon">
                    <PlusIcon />
                </span>
                <span>New Subject</span>
            </button>

            <button class="button is-primary has-text-white" @click="createLesson">
                <span class="icon">
                    <PlusIcon />
                </span>
                <span>{{ creating ? 'Close Form' : 'New Lesson' }}</span>
            </button>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section mb-5">
        <div class="columns is-mobile is-multiline is-variable is-3">
            <div class="column is-6-mobile is-3-tablet">
                <label class="label has-text-grey-light is-size-7">Select Grade</label>
                <div class="select is-fullwidth">
                    <select v-model="selectedGradeId" @change="onGradeChange">
                        <option :value="null">All Grades</option>
                        <option v-for="grade in cacheStore.grades" :key="grade.id" :value="grade.id">
                            {{ grade.name }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="column is-6-mobile is-3-tablet">
                <label class="label has-text-grey-light is-size-7">Select Subject</label>
                <div class="select is-fullwidth">
                    <select v-model="selectedSubjectId" @change="onSubjectChange"
                        :disabled="!selectedGradeId && availableSubjects.length === 0">
                        <option :value="null">All Subjects</option>
                        <option v-for="subject in availableSubjects" :key="subject.id" :value="subject.id">
                            {{ subject.name }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="column is-12-mobile is-6-tablet">
                <label class="label has-text-grey-light is-size-7">&nbsp;</label>
                <button class="button is-primary is-fullwidth has-text-white" @click="resetFilters">
                    Reset Filters
                </button>
            </div>
        </div>
    </div>

    <!-- INFO SECTION WITH TOPIC COUNT -->
    <div v-if="!loading && filteredLessons.length > 0" class="mb-3 has-text-grey is-size-7">
        <div class="info-stats">
            <span>📚 <strong>{{ filteredLessons.length }}</strong> lessons</span>
            <span class="mx-2">•</span>
            <span>📑 <strong>{{ totalTopics }}</strong> topics</span>
            <span class="mx-2">•</span>
            <span>
                <strong v-if="selectedGradeName">{{ selectedGradeName }}</strong>
                <span v-if="selectedGradeName && selectedSubjectName"> + </span>
                <strong v-if="selectedSubjectName">{{ selectedSubjectName }}</strong>
                <span v-if="!selectedGradeName && !selectedSubjectName">All grades & subjects</span>
            </span>
        </div>
    </div>

    <div v-else-if="!loading && filteredLessons.length === 0" class="mb-3 has-text-grey is-size-7">
        No lessons found for this selection.
    </div>

    <!-- LOADING STATE -->
    <div v-if="loading" class="has-text-centered py-5">
        <Loader />
    </div>

    <!-- CONTENT - DESKTOP & MOBILE -->
    <template v-else>
        <!-- DESKTOP TABLE VIEW -->
        <div class="table-wrapper is-hidden-mobile">
            <table class="table is-fullwidth is-hoverable">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Topic</th>
                        <th>Part</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- CREATE ROW -->
                    <tr v-if="creating" class="edit-row-active">
                        <td colspan="5" style="padding: 0">
                            <LessonEditForm :grade_id="selectedGradeId" :subject_id="selectedSubjectId" inline
                                @saved="onCreated" @cancel="creating = false" />
                        </td>
                    </tr>

                    <!-- GROUPED TOPICS -->
                    <template v-for="group in groupedTopics" :key="group.topic">
                        <!-- TOPIC ROW -->
                        <tr class="topic-row">
                            <td colspan="5">
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

                        <!-- LESSONS + EDIT ROWS (SINGLE LOOP) -->
                        <template v-if="isTopicOpen(group.topic)">
                            <template v-for="lesson in group.lessons" :key="lesson.id">
                                <!-- LESSON ROW -->
                                <tr>
                                    <td>
                                        <strong class="ml-6">{{ lesson.title }}</strong>
                                        <p v-if="lesson.sub_topic" class="ml-6 is-size-7 has-text-grey">
                                            {{ lesson.sub_topic }}
                                        </p>
                                    </td>
                                    <td>{{ lesson.topic }}</td>
                                    <td :class="{ 'has-text-grey-light is-italic': !lesson.part_number }">
                                        {{ lesson.part_number ?? 'N/A' }}
                                    </td>
                                    <td>{{ lesson.is_active ? 'Yes' : 'No' }}</td>
                                    <td>
                                        <button class="button is-small is-info" @click.stop="toggleEdit(lesson.id)">
                                            {{ editingId === lesson.id ? 'Close' : 'Edit' }}
                                        </button>
                                    </td>
                                </tr>
                                <!-- EDIT ROW (appears directly below) -->
                                <tr v-if="editingId === lesson.id" class="edit-row-active">
                                    <td colspan="5" style="padding: 0">
                                        <LessonEditForm inline :lesson="lesson" @saved="onLessonSaved"
                                            @cancel="editingId = null" />
                                    </td>
                                </tr>
                            </template>
                        </template>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- MOBILE CARD VIEW (unchanged – already safe) -->
        <div class="is-hidden-tablet">
            <div v-for="group in groupedTopics" :key="group.topic">
                <div class="topic-header" @click="toggleTopic(group.topic)">
                    <strong>{{ group.topic }}</strong>
                    <span class="ml-2 has-text-grey">({{ group.lessons.length }})</span>
                </div>

                <div v-if="isTopicOpen(group.topic)">
                    <!-- CREATE FORM FOR MOBILE -->
                    <div v-if="creating" class="mobile-card mb-3">
                        <div class="card-content">
                            <LessonEditForm :grade_id="selectedGradeId" :subject_id="selectedSubjectId" inline
                                @saved="onCreated" @cancel="creating = false" />
                        </div>
                    </div>

                    <!-- LESSON CARDS -->
                    <div v-for="lesson in group.lessons" :key="lesson.id" class="mobile-card">
                        <div class="card-content">
                            <strong>{{ lesson.title }}</strong>
                            <p v-if="lesson.sub_topic" class="is-size-7 has-text-grey mt-1">
                                {{ lesson.sub_topic }}
                            </p>
                            <div class="mt-1">
                                <span class="tag is-info is-light">Topic: {{ lesson.topic }}</span>
                            </div>
                            <div class="mt-2">
                                <span class="tag is-warning is-light">Part: {{ lesson.part_number ?? 'N/A' }}</span>
                                <span class="tag ml-1" :class="lesson.is_active ? 'is-success' : 'is-danger'">
                                    {{ lesson.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <button class="button is-small is-info mt-2" @click="toggleEdit(lesson.id)">
                                {{ editingId === lesson.id ? 'Close' : 'Edit' }}
                            </button>
                            <div v-if="editingId === lesson.id" class="edit-row-active mt-3">
                                <LessonEditForm inline :lesson="lesson" @saved="onLessonSaved"
                                    @cancel="editingId = null" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <createModal v-model="showCreateSubject" title="Create Subject">
            <input class="input" v-model="newSubjectName" placeholder="Subject name" />

            <template #actions>
                <button class="button mr-2" @click="showCreateSubject = false">Cancel</button>
                <button class="button is-primary" @click="createSubject">Save</button>
            </template>
        </createModal>

    </template>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';
import api from '../../api';
import LessonEditForm from './LessonEditForm.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';
import { useCacheStore } from '@/stores/cache';
import Loader from '../common/Loader.vue';
import createModal from '../common/createModal.vue';
/* PROPS */
const props = defineProps({
    grade_id: [String, Number],
    subject_id: [String, Number]
});

/* STORES */
const cacheStore = useCacheStore();

/* STATE */
const allLessons = ref([]);
const loading = ref(false);
const openTopics = ref({});
const editingId = ref(null);
const creating = ref(false);
const showCreateSubject = ref(false);
const emit = defineEmits(['navigate']);

/* FILTER STATE */
const selectedGradeId = ref(null);
const selectedSubjectId = ref(null);

/* COMPUTED: Filtered lessons */
const filteredLessons = computed(() => {
    let result = allLessons.value;
    if (selectedGradeId.value) {
        result = result.filter(lesson => lesson.grade_id === selectedGradeId.value);
    }
    if (selectedSubjectId.value) {
        result = result.filter(lesson => lesson.subject_id === selectedSubjectId.value);
    }
    return result;
});

/* COMPUTED: Total unique topics */
const totalTopics = computed(() => {
    const uniqueTopics = new Set();
    filteredLessons.value.forEach(lesson => {
        if (lesson.topic) uniqueTopics.add(lesson.topic);
    });
    return uniqueTopics.size;
});

/* COMPUTED: Group lessons by topic (with safety filter) */
const groupedTopics = computed(() => {
    const grouped = {};
    filteredLessons.value.forEach(lesson => {
        // Skip invalid lessons
        if (!lesson.topic || !lesson.id) return;
        if (!grouped[lesson.topic]) grouped[lesson.topic] = [];
        grouped[lesson.topic].push(lesson);
    });

    function extractTopicNumber(topic) {
        const match = topic.match(/^(\d+)/);
        return match ? parseInt(match[1], 10) : null;
    }

    const sortedGroups = Object.entries(grouped).sort((a, b) => {
        const aNum = extractTopicNumber(a[0]);
        const bNum = extractTopicNumber(b[0]);
        if (aNum === null && bNum !== null) return -1;
        if (aNum !== null && bNum === null) return 1;
        if (aNum === null && bNum === null) return a[0].localeCompare(b[0]);
        return aNum - bNum;
    });

    return sortedGroups.map(([topic, lessons]) => ({
        topic,
        lessons: lessons
            .filter(l => l && l.id) // extra safety
            .sort((a, b) => (a.part_number || 0) - (b.part_number || 0))
    }));
});

/* COMPUTED: Filter names */
const selectedGradeName = computed(() => {
    if (!selectedGradeId.value) return null;
    const grade = cacheStore.grades.find(g => g.id === selectedGradeId.value);
    return grade?.name;
});

const selectedSubjectName = computed(() => {
    if (!selectedSubjectId.value) return null;
    const subject = cacheStore.subjects.find(s => s.id === selectedSubjectId.value);
    return subject?.name;
});

/* COMPUTED: Available subjects for selected grade */
const availableSubjects = computed(() => {
    if (!selectedGradeId.value) return cacheStore.subjects;
    const subjectsWithLessons = new Set(
        allLessons.value
            .filter(lesson => lesson.grade_id === selectedGradeId.value)
            .map(lesson => lesson.subject_id)
    );
    return cacheStore.subjects.filter(subject => subjectsWithLessons.has(subject.id));
});

/* METHODS */
function toggleTopic(topic) {
    openTopics.value[topic] = !openTopics.value[topic];
}

const isTopicOpen = (topic) => openTopics.value[topic] === true;

const toggleEdit = async (id) => {
    console.log('Toggle edit for lesson id:', id);
    const lesson = allLessons.value.find(l => l.id === id);
    console.log('Found lesson:', lesson);

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
    fetchAllLessons();
};

const onCreated = () => {
    creating.value = false;
    fetchAllLessons();
};

/* FETCH ALL LESSONS */
async function fetchAllLessons() {
    loading.value = true;
    try {
        const res = await api.get("/lessons/all");
        console.log("All Lessons = ", res.data);
        allLessons.value = res.data || [];
    } catch (e) {
        console.error("Error fetching lessons:", e);
        allLessons.value = [];
    } finally {
        loading.value = false;
    }
}

/* FILTER HANDLERS */
function onGradeChange() {
    selectedSubjectId.value = null;
}

function onSubjectChange() { }

function resetFilters() {
    selectedGradeId.value = null;
    selectedSubjectId.value = null;
}

function createLesson() {
    editingId.value = null;
    creating.value = !creating.value;
    nextTick(() => {
        const el = document.querySelector('.edit-row-active');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

const createSubject = async () => {
    if (!newSubjectName.value) return;

    const res = await api.post('/addSubject', {
        name: newSubjectName.value
    });

    cacheStore.subjects.push(res.data);
    selectedSubject.value = res.data.id;

    showCreateSubject.value = false;
    newSubjectName.value = '';
};

/* LIFECYCLE */
onMounted(async () => {
    await cacheStore.fetchAllMetadata();
    await fetchAllLessons();
});
</script>

<style scoped>
.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.filter-section {
    background: rgba(255, 255, 255, 0.05);
    padding: 1rem;
    border-radius: 12px;
    backdrop-filter: blur(8px);
}

.table-wrapper {
    background: white;
    border-radius: 12px;
    overflow-x: auto;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.table {
    min-width: 800px;
}

.mobile-card {
    background: white;
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
}

.topic-card {
    padding: 12px 16px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.topic-card:hover {
    background: rgba(255, 255, 255, 0.05);
    transform: translateX(5px);
}

.topic-header {
    padding: 12px;
    background: #f5f5f5;
    border-radius: 10px;
    margin-bottom: 10px;
    cursor: pointer;
    font-weight: bold;
}


.info-stats {
    background: rgba(255, 255, 255, 0.05);
    padding: 8px 12px;
    border-radius: 8px;
    display: inline-block;
    backdrop-filter: blur(4px);
}

@media (max-width: 768px) {
    .admin-header {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-section {
        margin-top: 10px;
    }

    .info-stats {
        display: block;
        text-align: center;
    }

    .info-stats .mx-2 {
        margin: 0 4px;
    }
}

.edit-row-active :deep(.lesson-edit-form) {
    padding: 1.5rem;
    background: #f9f9f9;
}

.topic-row {
    background-color: rgba(0, 0, 0, 0.02);
}

.glass-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}
</style>
