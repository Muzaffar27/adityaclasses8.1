<template>

    <Layout title="Access Requests" :loading="loading" @back="goBack">

        <div v-if="groupedRequests.length === 0" class="has-text-centered mt-6">
            <p class="has-text-grey">No access requests.</p>
        </div>


        <div v-else>
            <div v-for="student in groupedRequests" :key="student.student_id" class="card mb-4 p-4">

                <!-- Student header -->
                <nav class="student-header" @click="toggleLessons(student.student_id)">
                    <div class="left">
                        <span class="icon">
                            <ChevronRightIcon v-if="!expandedStudents.has(student.student_id)" />
                            <ChevronDownIcon v-else />
                        </span>

                        <p class="student-name">{{ student.student_name }}</p>

                        <span class="tag is-warning is-light is-rounded">
                            {{ student.requests.length }}
                        </span>
                    </div>

                    <div class="right">

                        <button class="button is-success is-small"
                            :class="{ 'is-loading': loadingStudentsAccept.has(student.student_id) }"
                            :disabled="loadingStudentsAccept.has(student.student_id)"
                            @click.stop="acceptStudent(student)">
                            Accept All
                        </button>

                        <button class="button is-danger is-small"
                            :class="{ 'is-loading': loadingStudentsRefuse.has(student.student_id) }"
                            :disabled="loadingStudentsRefuse.has(student.student_id)"
                            @click.stop="refuseStudent(student)">
                            Refuse All
                        </button>

                    </div>
                </nav>

                <div v-if="expandedStudents.has(student.student_id)" class="mt-3">
                    <div v-for="req in student.requests" :key="req.id"
                        class="is-flex is-justify-content-space-between is-align-items-center  py-1 border-bottom">

                        <p class="is-size-7 ml-5 has-text-white">{{ req.lesson_title }}</p>

                        <div class="buttons">

                            <button class="button is-success is-small is-rounded"
                                :class="{ 'is-loading': loadingAccept.has(req.id) }"
                                :disabled="loadingAccept.has(req.id)" @click="acceptRequest(req.id)">
                                Accept
                            </button>

                            <button class="button is-danger is-small is-rounded"
                                :class="{ 'is-loading': loadingRefuse.has(req.id) }"
                                :disabled="loadingRefuse.has(req.id)" @click="refuseRequest(req.id)">
                                Refuse
                            </button>

                        </div>
                    </div>
                </div>
            </div>
        </div>

    </Layout>
</template>

<script setup>

import { ref, onMounted } from "vue";
import api from "../api";
import { computed } from "vue";
import { ChevronDownIcon, ChevronRightIcon } from "@heroicons/vue/24/outline";
import Layout from "./common/Layout.vue";

import { useRouter } from "vue-router";

const router = useRouter();
const requests = ref([]);

const loading = ref(false);

const loadingAccept = ref(new Set());   //per lesson
const loadingRefuse = ref(new Set());   //per lesson

const loadingStudentsAccept = ref(new Set());  //per student
const loadingStudentsRefuse = ref(new Set());  //per student

onMounted(fetchRequests);

const expandedStudents = ref(new Set());

async function withLoader(setRef, key, callback) {
    const newSet = new Set(setRef.value);
    newSet.add(key);
    setRef.value = newSet;

    try {
        await callback();
    } catch (e) {
        console.error(e);
    } finally {
        const newSet = new Set(setRef.value);
        newSet.delete(key);
        setRef.value = newSet;
    }
}

const toggleLessons = (studentId) => {
    const newSet = new Set(expandedStudents.value);

    if (newSet.has(studentId)) {
        newSet.delete(studentId);
    } else {
        newSet.add(studentId);
    }

    expandedStudents.value = newSet;
};

const groupedRequests = computed(() => {
    const groups = {};

    requests.value.forEach(req => {
        if (!groups[req.student_id]) {
            groups[req.student_id] = {
                student_id: req.student_id,
                student_name: req.student_name,
                requests: []
            };
        }

        groups[req.student_id].requests.push(req);
    });

    return Object.values(groups);
});

//fetch requests lists
async function fetchRequests() {
    loading.value = true;

    try {
        const { data } = await api.get("/lesson-access/list_request");
        requests.value = data;
    }
    catch (e) {
        console.error("Error Access request page : ", e)
    } finally {
        loading.value = false;
    }
}

function acceptRequest(id) {
    withLoader(loadingAccept, id, async () => {
        await api.post("/lesson-access/accept", { id });
        requests.value = requests.value.filter(r => r.id !== id);
    });
}

function refuseRequest(id) {
    withLoader(loadingRefuse, id, async () => {
        await api.post("/lesson-access/refuse", { id });
        requests.value = requests.value.filter(r => r.id !== id);
    });
}

function acceptStudent(student) {
    const ids = student.requests.map(r => r.id);

    withLoader(loadingStudentsAccept, student.student_id, async () => {
        await api.post("/lesson-access/accept-multiple", { ids });

        requests.value = requests.value.filter(r => !ids.includes(r.id));
    });
}

function refuseStudent(student) {
    const ids = student.requests.map(r => r.id);

    withLoader(loadingStudentsRefuse, student.student_id, async () => {
        await api.post("/lesson-access/refuse-multiple", { ids });

        requests.value = requests.value.filter(r => !ids.includes(r.id));
    });
}

function goBack() {
    if (window.history.length > 1) {
        router.back();
    } else {
        router.push('/');
    }
}
</script>

<style>
.student-name {
    font-weight: 700;
    font-size: 1.1rem;
}

.lesson-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.student-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    padding: 10px 12px;
    border-radius: 12px;

    cursor: pointer;
    transition: all 0.2s ease;

    background: rgba(255, 255, 255, 0.02);
}

/* hover (desktop) */
.student-header:hover {
    background: rgba(79, 70, 229, 0.15);
    transform: translateY(-1px);
}

/* tap (mobile) */
.student-header:active {
    transform: scale(0.98);
}

/* left side */
.student-header .left {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* right buttons */
.student-header .right {
    display: flex;
    gap: 6px;
}

/* name */
.student-name {
    font-weight: 600;
    color: white;
}

.expand-enter-active,
.expand-leave-active {
    transition: all 0.25s ease;
}

.expand-enter-from,
.expand-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}
</style>