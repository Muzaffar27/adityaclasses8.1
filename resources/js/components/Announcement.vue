<template>
    <div v-if="loading">
        <Loader />
    </div>
    <div v-else class="glass-card p-4">

        <h2 class="title is-6 has-text-white mb-3">
            Announcement Editor
        </h2>

        <!-- ENABLED -->
        <label class="custom-checkbox mb-3">
            <input type="checkbox" v-model="form.enabled" />
            <span class="checkmark"></span>
            <span class="label-text">Show Announcement</span>
        </label>

        <!-- TYPE -->
        <!-- <div class="mb-3">
            <label class="custom-label">Type</label>
            <select v-model="form.type" class="input custom-input">
                <option value="default">Default</option>
                <option value="warning">Warning</option>
                <option value="success">Success</option>
                <option value="danger">Danger</option>
            </select>
        </div> -->

        <!-- TITLE -->
        <div class="mb-3">
            <label class="custom-label">Title</label>
            <input v-model="form.title" class="input custom-input" />
        </div>

        <!-- BODY -->
        <div class="mb-3">
            <label class="custom-label">Message</label>
            <textarea v-model="form.body" class="textarea custom-input"></textarea>
        </div>

        <!-- PREVIEW -->
        <div class="announcement-bar mb-3" :class="previewClass">
            <span class="ann-icon">📢</span>
            <div>
                <p class="ann-title">{{ form.title }}</p>
                <p class="ann-body">{{ form.body }}</p>
            </div>
        </div>

        <!-- SAVE -->
        <button class="button is-primary has-text-white" @click="save">
            <span v-if="!loading">
                Save
            </span>
            <span v-else>
                Loading..
            </span>
        </button>

    </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import api from "../api";
import Loader from "./common/Loader.vue";

const loading = ref(false);

const form = ref({
    enabled: true,
    type: "warning",
    title: "",
    body: ""
});

/* load existing */
onMounted(async () => {
    loading.value = true;

    try {
        const res = await api.get("/announcement");
        form.value = res.data;
    } catch (e) {
        console.log("Error ", e)
    } finally {
        loading.value = false;
    }

});

/* save */
async function save() {
    try {
        loading.value = true;
        await api.post("/announcement/save", form.value);
    } catch (e) {
        console.log("Error announcement: ", e);
    }
    finally {
        loading.value = false;
    }
    alert("Saved!");
}

/* preview style */
const previewClass = computed(() => {
    switch (form.value.type) {
        case "success": return "ann-success";
        case "danger": return "ann-danger";
        case "warning": return "ann-warning";
        default: return "ann-default";
    }
});
</script>