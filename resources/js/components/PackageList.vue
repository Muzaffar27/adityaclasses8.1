<template>
    <div class="p-3">

        <div class="is-flex is-justify-content-space-between mb-3">
            <h1 class="title is-5 has-text-white">List of Packages</h1>
        </div>

        <div v-if="loading">
            <Loader />
        </div>

        <div v-else>

            <div v-for="pkg in packages" :key="pkg.id" class="glass-card p-3 mb-2 is-flex is-align-items-center">

                <div style="flex:1">
                    <p class="has-text-white has-text-weight-semibold">
                        {{ pkg.name }}
                    </p>

                    <p class="has-text-grey-light is-size-7">
                        Rs {{ pkg.total_price }}
                    </p>
                </div>

                <button class="button is-small is-primary has-text-white" @click="edit(pkg)">
                    Edit
                </button>

            </div>

        </div>

    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "../api";
import Loader from "./common/Loader.vue";

const emit = defineEmits(["navigate"]);

const packages = ref([]);
const loading = ref(false);

async function fetchPackages() {
    loading.value = true;
    try {
        const res = await api.get("/packages/admin");
        packages.value = res.data;
    } finally {
        loading.value = false;
    }
}

function edit(pkg) {
    emit("navigate", {
        name: "package",
        props: {
            editPackage: pkg
        }
    });
}

onMounted(fetchPackages);
</script>