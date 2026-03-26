<template>

    <div v-for="req in requests" :key="req.id" class="card mb-3 p-3">

        <p><strong>{{ req.student_name }}</strong></p>
        <p>{{ req.lesson_title }}</p>

        <div class="buttons mt-2">
            <button class="button is-success" @click="acceptRequest(req.id)">
                Accept
            </button>

            <button class="button is-danger" @click="refuseRequest(req.id)">
                Refuse
            </button>
        </div>

    </div>

</template>

<script setup>

import { ref, onMounted } from "vue";
import api from "../api";

const requests = ref([]);

onMounted(fetchRequests);

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

</script>