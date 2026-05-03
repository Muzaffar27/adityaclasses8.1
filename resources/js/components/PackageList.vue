<template>
    <div class="p-3">

        <div class="package-list-header mb-3">
            <div>
                <h1 class="title is-5 has-text-white mb-1">List of Packages</h1>
                <p class="has-text-grey-light is-size-7">{{ sortedPackages.length }} package{{ sortedPackages.length ===
                    1 ? '' : 's' }}</p>
            </div>

            <div class="field mb-0">
                <label class="label is-small has-text-grey-light">Sort by</label>
                <div class="select is-small">
                    <select v-model="sortBy">
                        <option value="newest">Newest first</option>
                        <option value="name">Name A-Z</option>
                        <option value="grade">Grade</option>
                        <option value="subject">Subject</option>
                        <option value="priceHigh">Price high-low</option>
                        <option value="priceLow">Price low-high</option>
                    </select>
                </div>
            </div>
        </div>

        <div v-if="loading">
            <Loader />
        </div>

        <div v-else>

            <p v-if="!sortedPackages.length" class="has-text-grey-light is-size-7">
                No packages found.
            </p>

            <template v-for="pkg in sortedPackages" :key="pkg.id">
                <div class="glass-card package-row p-3 mb-2">

                    <div class="package-main">
                        <p class="has-text-white has-text-weight-semibold mb-1">
                            {{ pkg.name }}
                        </p>

                        <div class="package-meta">
                            <span>{{ pkg.grade?.name || 'No grade' }}</span>
                            <span>{{ pkg.subject?.name || 'No subject' }}</span>
                            <span>{{ pkg.items?.length || 0 }} item{{ (pkg.items?.length || 0) === 1 ? '' : 's'
                                }}</span>
                        </div>
                    </div>

                    <div class="package-price">
                        Rs {{ formatMoney(pkg.total_price) }}
                    </div>

                    <div class="buttons are-small">
                        <button class="button is-primary has-text-white" @click="toggleEdit(pkg.id)">
                            {{ editingId === pkg.id ? 'Close' : 'Edit' }}
                        </button>

                        <button class="button is-danger" :class="{ 'is-loading': deletingId === pkg.id }"
                            :disabled="deletingId === pkg.id" @click="deletePackage(pkg)">
                            Delete
                        </button>
                    </div>

                </div>

                <div v-if="editingId === pkg.id" class="edit-panel mb-3">
                    <PackageBuilder :editPackage="pkg" @saved="onPackageSaved" @cancel="editingId = null" />
                </div>
            </template>

        </div>

    </div>
</template>

<script setup>
import { computed, ref, onMounted, nextTick } from "vue";
import api from "../api";
import Loader from "./common/Loader.vue";
import PackageBuilder from "./PackageBuilder.vue";
import { showAlert, showConfirm } from "../composables/dialog";

const packages = ref([]);
const loading = ref(false);
const deletingId = ref(null);
const editingId = ref(null);
const sortBy = ref("newest");

const sortedPackages = computed(() => {
    const sorted = [...packages.value];

    return sorted.sort((a, b) => {
        if (sortBy.value === "name") {
            return compareText(a.name, b.name);
        }

        if (sortBy.value === "grade") {
            return compareText(a.grade?.name, b.grade?.name) || compareText(a.subject?.name, b.subject?.name) || compareText(a.name, b.name);
        }

        if (sortBy.value === "subject") {
            return compareText(a.subject?.name, b.subject?.name) || compareText(a.grade?.name, b.grade?.name) || compareText(a.name, b.name);
        }

        if (sortBy.value === "priceHigh") {
            return Number(b.total_price || 0) - Number(a.total_price || 0);
        }

        if (sortBy.value === "priceLow") {
            return Number(a.total_price || 0) - Number(b.total_price || 0);
        }

        return Number(b.id || 0) - Number(a.id || 0);
    });
});

async function fetchPackages() {
    loading.value = true;
    try {
        const res = await api.get("/packages/admin");
        packages.value = res.data;
    } finally {
        loading.value = false;
    }
}

async function toggleEdit(id) {
    if (editingId.value === id) {
        editingId.value = null;
        return;
    }

    editingId.value = id;

    await nextTick();

    const el = document.querySelector(".edit-panel");
    if (el) el.scrollIntoView({ behavior: "smooth", block: "center" });
}

async function onPackageSaved() {
    editingId.value = null;
    await fetchPackages();
}

async function deletePackage(pkg) {
    const confirmed = await showConfirm({
        title: "Delete Package",
        message: `Delete "${pkg.name}"?`,
        confirmText: "Delete",
        cancelText: "Keep it",
    });

    if (!confirmed) return;

    deletingId.value = pkg.id;

    try {
        await api.delete(`/packages/delete/${pkg.id}`);
        packages.value = packages.value.filter(item => item.id !== pkg.id);
        if (editingId.value === pkg.id) {
            editingId.value = null;
        }
    } catch (error) {
        console.error("Failed to delete package:", error);
        await showAlert({
            title: "Delete Failed",
            message: "Could not delete this package. Please try again.",
        });
    } finally {
        deletingId.value = null;
    }
}

function compareText(a, b) {
    return String(a || "").localeCompare(String(b || ""), undefined, { sensitivity: "base" });
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

onMounted(fetchPackages);
</script>

<style scoped>
.package-list-header,
.package-row {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.package-list-header {
    justify-content: space-between;
}

.package-main {
    flex: 1;
    min-width: 0;
}

.package-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    color: rgba(255, 255, 255, 0.62);
    font-size: 0.75rem;
}

.package-meta span {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    padding: 0.15rem 0.45rem;
}

.package-price {
    color: #fff;
    font-weight: 700;
    min-width: 95px;
    text-align: right;
}

.edit-panel {
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

@media (max-width: 768px) {

    .package-list-header,
    .package-row {
        align-items: stretch;
        flex-direction: column;
    }

    .package-price {
        text-align: left;
    }

    .buttons {
        margin-bottom: 0;
    }
}
</style>
