<template>

    <div v-for="student in groupedRequests" :key="student.student_id" class="card mb-4 p-4">

        <!-- Student header -->
        <nav class="student-header" @click="toggleLessons(student.student_id)">
            <div class="left">
                <span class="icon">
                    <ChevronDownIcon v-if="!expandedStudents.includes(student.student_id)" />
                    <ChevronUpIcon v-else />
                </span>

                <p class="student-name">{{ student.student_name }}</p>

                <span class="tag is-warning is-light is-rounded">
                    {{ student.requests.length }}
                </span>
            </div>

            <div class="right">
                <button class="button is-success is-small" @click.stop="acceptStudent(student)">
                    Accept All
                </button>

                <button class="button is-danger is-small" @click.stop="refuseStudent(student)">
                    Refuse All
                </button>
            </div>
        </nav>

        <transition name="expand">
            <div v-if="expandedStudents.includes(student.student_id)" class="mt-3">
                <div v-for="req in student.requests" :key="req.id"
                    class="is-flex is-justify-content-space-between is-align-items-center  py-1 border-bottom">

                    <p class="is-size-7 ml-5 has-text-white">{{ req.lesson_title }}</p>

                    <div class="buttons">
                        <button class="button is-success is-small is-rounded" @click="acceptRequest(req.id)">
                            <span>Accept</span>
                        </button>
                        <button class="button is-danger is-small is-rounded" @click="refuseRequest(req.id)">
                            <span>Refuse</span>
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>

</template>

<script setup>

import { ref, onMounted } from "vue";
import api from "../api";
import { computed } from "vue";

const requests = ref([]);

onMounted(fetchRequests);

const expandedStudents = ref([]);

const toggleLessons = (studentId) => {
    if (expandedStudents.value.includes(studentId)) {
        expandedStudents.value = expandedStudents.value.filter(id => id !== studentId);
    } else {
        expandedStudents.value.push(studentId);
    }
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
    const { data } = await api.get("/lesson-access/list_request");
    requests.value = data;
}

async function acceptRequest(id) {
    await api.post("/lesson-access/accept", { id });
    requests.value = requests.value.filter(r => r.id !== id);
}

async function refuseRequest(id) {
    await api.post("/lesson-access/refuse", { id });
    requests.value = requests.value.filter(r => r.id !== id);
}

async function acceptStudent(student) {
    const ids = student.requests.map(r => r.id);

    try {
        await api.post("/lesson-access/accept-multiple", {
            ids: ids
        });

        // Remove accepted requests from UI
        requests.value = requests.value.filter(r => !ids.includes(r.id));

    } catch (e) {
        console.error(e);
    }
}

async function refuseStudent(student) {
    const ids = student.requests.map(r => r.id);

    try {
        await api.post("/lesson-access/refuse-multiple", {
            ids: ids
        });

        requests.value = requests.value.filter(r => !ids.includes(r.id));

    } catch (e) {
        console.error(e);
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