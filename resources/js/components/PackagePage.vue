<template>
    <Layout title="Store">

        <div v-if="loading">
            <Loader />
        </div>

        <div v-else class="store-page container is-fluid px-5">
            <h1 class="title is-5 has-text-white mb-3">
                Select your subject
            </h1>
            <!-- SUBJECT TABS -->
            <div class="tabs is-toggle">
                <ul>
                    <li class="mt-1 ml-3" v-for="sub in availableSubjects" :key="sub.id"
                        :class="{ 'is-active': selectedSubjectId === sub.id }" @click="selectSubject(sub.id)">
                        <a>{{ sub.name }}</a>
                    </li>
                </ul>
            </div>

            <!-- HEADER -->
            <div class="admin-header is-flex is-justify-content-space-between is-align-items-center mb-3 mt-5">
                <div>
                    <h1 class="title is-5 has-text-white mb-1 mt-2 is-flex is-align-items-center">
                        Package
                    </h1>

                    <p class="has-text-grey is-size-7">
                        Select a subject, then a package
                    </p>
                </div>
            </div>

            <!-- ❌ NO SUBJECT SELECTED -->
            <div v-if="!selectedSubjectId" class="empty-state">
                👆 Choose a subject above to begin
            </div>

            <!-- ✅ SUBJECT SELECTED -->
            <template v-else>

                <!-- DESKTOP PACKAGE TABS -->
                <div class="tabs is-toggle is-centered is-hidden-mobile mb-3">
                    <ul>
                        <li v-for="pkg in packages" :key="pkg.id"
                            :class="{ 'is-active': selectedPackage?.id === pkg.id }" @click="selectPackage(pkg)">
                            <a>{{ pkg.name }}</a>
                        </li>
                    </ul>
                </div>

                <!-- MOBILE PACKAGE SELECT -->
                <div class="field is-hidden-tablet mb-3">
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select v-model="selectedPackageId" @change="syncMobileSelect">
                                <option disabled value="">Select package</option>
                                <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">
                                    {{ pkg.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ❌ NO PACKAGES -->
                <div v-if="!loading && !packages.length" class="empty-state">
                    No packages available for this subject.
                </div>

                <!-- ❌ PACKAGE NOT SELECTED -->
                <div v-else-if="!loading && !selectedPackage" class="empty-state">
                    Select a package to preview its content.
                </div>

                <!-- ✅ TABLE -->
                <!-- ✅ TABLE -->
                <div v-else class="table-wrapper">

                    <!-- ✅ TABLE -->
                    <table class="table is-fullwidth is-hoverable">
                        <thead>
                            <tr>
                                <th width="40px"></th>
                                <th>Included Item</th>
                                <th class="has-text-right">Price</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="item in selectedPackage.items || []" :key="item.id">
                                <td>
                                    <label class="custom-checkbox">
                                        <input type="checkbox" :value="item.id" v-model="selectedItemIds">
                                        <span class="checkmark"></span>
                                    </label>
                                </td>

                                <td class="has-text-white">
                                    <div class="is-flex is-flex-direction-column">
                                        <span>{{ item.subject?.name || 'Unknown Subject' }}</span>
                                        <span class="has-text-grey is-size-7" style="font-size: 0.65rem; opacity: 0.8;">
                                            {{ getGradeName(item.grade_id) }}
                                        </span>
                                    </div>
                                </td>

                                <td class="has-text-right has-text-grey-light">
                                    Rs {{ item.price }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- CHECKOUT -->
                <div v-if="selectedPackage" class="checkout-bar glass-card">
                    <div class="is-flex is-align-items-center is-justify-content-space-between is-flex-wrap-wrap">

                        <div>
                            <p class="is-size-7 has-text-grey">
                                Selected ({{ selectedItemIds.length }} items)
                            </p>
                            <p class="is-size-5 has-text-primary has-text-weight-bold">
                                Rs {{ dynamicTotal.toFixed(2) }}
                            </p>
                        </div>

                        <button class="button is-primary action-btn has-text-white" :disabled="buttonLoad"
                            @click="submitRequest">
                            <span v-if="buttonLoad">Loading...</span>
                            <span v-else>Request Access</span>
                        </button>

                    </div>
                </div>

            </template>
        </div>

    </Layout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import Layout from './common/Layout.vue'
import api from "../api"
import { useCacheStore } from "../stores/cache"

import { storeToRefs } from "pinia"
import Loader from './common/Loader.vue'
import { showAlert } from '../composables/dialog'

// store
const cacheStore = useCacheStore()
const { subjects, grades } = storeToRefs(cacheStore)

// state
const loading = ref(false)
const buttonLoad = ref(false)
const allPackagesForGrade = ref([])
const selectedPackage = ref(null)
const selectedPackageId = ref(null)
const selectedSubjectId = ref(null)
const selectedItemIds = ref([])
const excludedSubjects = ['mechanics', 'statistics 1', 'statistics 2']


// route
const route = useRoute()
const gradeId = route.params.id

const availableSubjects = computed(() => {
    if (!subjects.value) return []

    const subjectIdsWithPackages = new Set(allPackagesForGrade.value.map(p => p.subject_id))

    // 1. Filter subjects (have packages, not excluded)
    const filtered = subjects.value.filter(sub => {
        const isExcluded = excludedSubjects.includes(sub.name.toLowerCase().trim())
        return !isExcluded && subjectIdsWithPackages.has(sub.id)
    })

    // 2. Define custom priority order (exact names, case-insensitive)
    const priorityOrder = [
        'mathematics',
        'add maths',
        'pure mathematics',
        'accounts',
        'economics'
    ]

    // 3. Separate priority subjects from others
    const prioritySubjects = []
    const others = []

    filtered.forEach(sub => {
        const subLower = sub.name.toLowerCase().trim()
        const index = priorityOrder.findIndex(priority => priority === subLower)
        if (index !== -1) {
            prioritySubjects.push({ subject: sub, order: index })
        } else {
            others.push(sub)
        }
    })

    // 4. Sort priority subjects by their defined order
    prioritySubjects.sort((a, b) => a.order - b.order)
    const sortedPriority = prioritySubjects.map(item => item.subject)

    // 5. Sort others alphabetically
    others.sort((a, b) => a.name.localeCompare(b.name))

    // 6. Combine: priority first, then others
    return [...sortedPriority, ...others]
})

const packages = computed(() => {
    if (!selectedSubjectId.value) return []
    return allPackagesForGrade.value.filter(pkg => pkg.subject_id === selectedSubjectId.value)
})


const dynamicTotal = computed(() => {
    if (!selectedPackage.value || !selectedPackage.value.items) return 0;

    return selectedPackage.value.items
        .filter(item => selectedItemIds.value.includes(item.id))
        .reduce((sum, item) => sum + Number(item.price), 0);
});

// select subject
function selectSubject(subjectId) {
    selectedSubjectId.value = subjectId
    const pkgs = packages.value
    if (pkgs.length) {
        selectPackage(pkgs[0])
    } else {
        selectedPackage.value = null
    }
}

// select package
function selectPackage(pkg) {
    selectedPackage.value = pkg
    selectedPackageId.value = pkg.id
}
function getGradeName(id) {
    if (!id) return '';
    return grades.value.find(g => g.id === id)?.name || `Grade ${id}`;
}

// mobile sync
function syncMobileSelect() {
    const found = packages.value.find(
        p => p.id === Number(selectedPackageId.value)
    )
    if (found) selectedPackage.value = found
}

async function fetchAllPackagesForGrade() {
    if (!gradeId) return
    loading.value = true
    try {
        const res = await api.get('/packages', {
            params: { grade_id: gradeId }
        })
        allPackagesForGrade.value = res.data || []
    } catch (err) {
        console.error('Failed to fetch packages for grade:', err)
        allPackagesForGrade.value = []
    } finally {
        loading.value = false
    }
}


async function submitRequest() {
    if (!selectedPackage.value) return
    const selectedItems = selectedPackage.value.items.filter(item => selectedItemIds.value.includes(item.id))

    if (!selectedItems.length) {
        await showAlert({
            title: "Select a Subject",
            message: "Please select at least one subject before requesting access.",
        })
        return
    }

    const payload = selectedItems.map(item => ({
        subject_id: item.subject_id || item.subject?.id,
        grade_id: item.grade_id
    }))
    buttonLoad.value = true
    try {
        const req = await api.post('/lesson-access/request', payload)
        if (req.data.message) {
            selectedItemIds.value = []
            await showAlert({
                title: "Request Sent",
                message: "Your access request has been sent.",
            })
        }
    } catch (err) {
        console.error(err)
        await showAlert({
            title: "Request Failed",
            message: "Failed to send request.",
        })
    } finally {
        buttonLoad.value = false
    }
}

// init
onMounted(async () => {
    await cacheStore.fetchAllMetadata()
    await fetchAllPackagesForGrade()
})

watch(selectedSubjectId, () => {
    if (packages.value.length) {
        selectPackage(packages.value[0])
    } else {
        selectedPackage.value = null
    }
    selectedItemIds.value = []
})

watch(selectedPackage, () => {
    selectedItemIds.value = []
})

</script>


<style scoped>
.store-page {
    max-width: 1100px;
    margin: 0 auto;
}

/* HEADER tighter */
.admin-header {
    margin-bottom: 0.75rem !important;
}

.admin-header .title {
    font-size: 1.1rem;
}

/* TABS more compact */
.tabs {
    margin-bottom: 0.75rem !important;
}

.tabs ul {
    border-bottom: none;
    overflow-x: auto;
    scrollbar-width: none;
}

.tabs ul::-webkit-scrollbar {
    display: none;
}

.tabs li a {
    padding: 6px 10px;
    font-size: 0.75rem;
    border-radius: 8px;
    margin-right: 4px;

    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #fff;
    white-space: nowrap;
}

.tabs li.is-active a {
    background: #4f46e5;
    border-color: #4f46e5;
}

/* MOBILE SELECT tighter */
.select select {
    height: 34px;
    font-size: 0.8rem;
}

/* TABLE ultra compact */
.table {
    font-size: 0.75rem;
}

.table thead th {
    font-size: 0.7rem;
    padding: 6px 8px;
}

.table td {
    padding: 6px 8px;
}

/* reduce row height */
.table tr {
    height: 34px;
}

/* prevent overflow */
.table-wrapper {
    max-height: 45vh;
    overflow-y: auto;
}

/* EMPTY */
.empty-state {
    text-align: center;
    padding: 20px;
    font-size: 0.8rem;
    color: #888;
}

/* CHECKOUT compact sticky */
.checkout-bar {
    position: sticky;
    bottom: 0;

    margin-top: 8px;
    padding: 10px 12px;
    border-radius: 12px;

    backdrop-filter: blur(10px);
}

.checkout-bar p {
    margin: 0;
}

.checkout-bar .is-size-4 {
    font-size: 1.2rem !important;
}

/* BUTTON tighter */
.action-btn {
    height: 34px;
    font-size: 0.8rem;
    padding: 0 12px;
}

/* MOBILE OPTIMIZATION */
@media (max-width: 768px) {

    .store-page {
        padding: 0.75rem !important;
    }

    .admin-header {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 6px;
    }

    .table-wrapper {
        max-height: 40vh;
    }

    .checkout-bar {
        padding: 8px 10px;
    }

    .checkout-bar .is-size-4 {
        font-size: 1rem !important;
    }

    .action-btn {
        width: 100%;
        margin-top: 6px;
    }
}


/* Style the checkbox to fit the dark theme */
/* Hide the browser's default checkbox */
.custom-checkbox {
    display: block;
    position: relative;
    width: 18px;
    height: 18px;
    cursor: pointer;
    user-select: none;
    margin: 0 auto;
}

.custom-checkbox input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

/* Create a custom checkbox (the background) */
.checkmark {
    position: absolute;
    top: 0;
    left: 0;
    height: 18px;
    width: 18px;
    background-color: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    transition: all 0.2s ease;
}

/* On mouse-over, add a slightly brighter background */
.custom-checkbox:hover input~.checkmark {
    background-color: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.3);
}

/* When the checkbox is checked, add the Indigo background */
.custom-checkbox input:checked~.checkmark {
    background-color: #4f46e5;
    border-color: #4f46e5;
}

/* Create the checkmark (the white tick) - hidden when not checked */
.checkmark:after {
    content: "";
    position: absolute;
    display: none;
}

/* Show the checkmark when checked */
.custom-checkbox input:checked~.checkmark:after {
    display: block;
}

/* Style the checkmark (the tick icon) */
.custom-checkbox .checkmark:after {
    left: 6px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

/* Ensure the table cell for checkbox is centered */
.table td:first-child {
    vertical-align: middle;
    text-align: center;
}

/* Tighter vertical layout for Subject/Grade combo */
.table td .is-flex {
    line-height: 1.2;
}

.tabs.is-toggle {
    justify-content: center;
}

.tabs.is-toggle ul {
    flex-wrap: wrap;
    justify-content: center;
}

.tabs.is-toggle li {
    flex: 0 0 auto;
    /* prevents stretching */
}
</style>
