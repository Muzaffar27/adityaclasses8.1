<template>
    <Layout title="Store">
        <div class="store-page">
            <p class="store-description">Choose your subjects and a term for each. Prices include the applicable discounts.</p>

            <div v-if="loading" class="store-state" role="status"><Loader /><p>Loading your packages...</p></div>
            <div v-else-if="loadError" class="store-state" role="alert">
                <h2>Packages could not be loaded</h2><p>{{ loadError }}</p>
                <button class="button is-primary" @click="fetchAllPackagesForGrade">Try again</button>
            </div>
            <div v-else-if="!availableSubjects.length" class="store-state">
                <h2>No packages available yet</h2><p>Please check back soon for this grade.</p>
            </div>
            <template v-else>
                <nav class="subject-choices" aria-label="Choose a subject">
                    <button v-for="subject in availableSubjects" :key="subject.id" type="button"
                        :aria-pressed="selectedSubjectId === subject.id" :class="{ active: selectedSubjectId === subject.id }"
                        :disabled="buttonLoad" @click="selectSubject(subject.id)">{{ subject.name }}</button>
                </nav>

                <section class="package-choices" aria-label="Choose a package" v-if="packages.length > 1">
                    <button v-for="pkg in packages" :key="pkg.id" type="button" :disabled="buttonLoad"
                        :aria-pressed="selectedPackage?.id === pkg.id" :class="{ active: selectedPackage?.id === pkg.id }"
                        @click="selectPackage(pkg)">
                        <strong>{{ pkg.name }}</strong><span>{{ pkg.items?.length || 0 }} subjects included</span>
                    </button>
                </section>

                <div v-if="selectedPackage" class="store-grid">
                    <div class="package-content">
                        <header class="package-heading">
                            <span class="grade-badge">{{ getGradeName(selectedPackage.grade_id) }}</span>
                            <h2>{{ selectedPackage.name }}</h2>
                        </header>

                        <section class="store-card" aria-labelledby="subjects-heading">
                            <div class="section-heading"><h3 id="subjects-heading">Subjects and access</h3>
                                <button type="button" class="text-button" :disabled="buttonLoad" @click="selectAllSubjects">Select all</button>
                            </div>

                            <div class="subject-items">
                                <div v-for="item in selectedPackage.items || []" :key="item.id" class="subject-item" :class="{ selected: selectedItemIds.includes(item.id) }">
                                    <label class="subject-toggle">
                                        <input type="checkbox" :value="item.id" v-model="selectedItemIds" :disabled="buttonLoad" />
                                        <span><strong>{{ item.subject?.name || 'Subject' }}</strong><small>{{ getGradeName(item.grade_id) }}</small></span>
                                    </label>
                                    <div class="item-term-options" role="group" :aria-label="'Access period for ' + (item.subject?.name || 'subject')">
                                        <button v-for="term in durationOptions" :key="term.months" type="button"
                                            :disabled="buttonLoad || !selectedItemIds.includes(item.id)"
                                            :class="{ active: durationForItem(item) === term.months }"
                                            :aria-pressed="durationForItem(item) === term.months"
                                            @click="itemDurations[item.id] = term.months">
                                            <span class="term-label">{{ term.label.split(' (')[0] }} <small>{{ term.months }}mo</small></span>
                                            <strong>{{ money(priceForItemTerm(item, term)) }}</strong>
                                            <span v-if="subjectTermPrice(selectedPackage, item, term).discount" class="discount-badge">{{ subjectTermPrice(selectedPackage, item, term).discount }}% off</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <p v-if="!selectedPackage.items?.length" class="section-help">No subjects are available in this package yet.</p>
                        </section>
                    </div>

                    <aside class="order-summary store-card" aria-labelledby="summary-heading">
                        <h3 id="summary-heading">Your selection</h3>
                        <div v-if="selectedItemIds.length" class="summary-items">
                            <div v-for="item in selectedItems" :key="item.id"><span>{{ item.subject?.name || 'Subject' }}<small>{{ durationForItem(item) }} months access</small></span><strong>{{ money(priceForDuration(item)) }}</strong></div>
                        </div>
                        <p v-else class="selection-hint">Select at least one subject to see your total.</p>
                        <div class="checkout-actions">
                        <div class="summary-total" aria-live="polite" aria-atomic="true">
                            <span>{{ selectedItemIds.length ? 'Your total' : 'No subjects selected' }}</span>
                            <strong v-if="selectedItemIds.length">{{ money(dynamicTotal) }}</strong>
                        </div>
                        <button class="button is-primary request-button" :disabled="buttonLoad || !selectedItemIds.length" @click="submitRequest">
                            {{ buttonLoad ? 'Sending request...' : 'Request access' }}
                        </button>
                        </div>
                        <p class="approval-note">Access starts after tutor approval.</p>
                    </aside>
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
import { packageTerms, subjectTermPrice, itemTermPrices } from "../utils/packagePricing.mjs"
import { useCacheStore } from "../stores/cache"

import { storeToRefs } from "pinia"
import Loader from './common/Loader.vue'
import { showAlert } from '../composables/dialog'

// store
const cacheStore = useCacheStore()
const { subjects, grades } = storeToRefs(cacheStore)

// state
const loading = ref(true)
const loadError = ref('')
const buttonLoad = ref(false)
const allPackagesForGrade = ref([])
const selectedPackage = ref(null)
const selectedSubjectId = ref(null)
const selectedItemIds = ref([])
const itemDurations = ref({})
const excludedSubjects = ['mechanics', 'statistics 1', 'statistics 2']
const durationOptions = packageTerms


// route
const route = useRoute()
const gradeId = computed(() => route.params.id)

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


const selectedItems = computed(() => (selectedPackage.value?.items || []).filter(item => selectedItemIds.value.includes(item.id)))

function money(value) {
    return Number(value) === 0 ? 'Free' : 'Rs ' + Number(value).toLocaleString('en-MU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function selectAllSubjects() { selectedItemIds.value = (selectedPackage.value?.items || []).map(item => item.id) }

const dynamicTotal = computed(() => {
    if (!selectedPackage.value || !selectedPackage.value.items) return 0;

    return selectedPackage.value.items
        .filter(item => selectedItemIds.value.includes(item.id))
        .reduce((sum, item) => sum + priceForDuration(item), 0);
});

const termPricesByDuration = computed(() => Object.fromEntries(durationOptions.map(term =>
    [term.months, itemTermPrices(selectedPackage.value || {}, term)])))
function durationForItem(item) { return itemDurations.value[item.id] || 3 }
function priceForItemTerm(item, term) { return termPricesByDuration.value[term.months]?.[item.id] || 0 }
function priceForDuration(item) { return termPricesByDuration.value[durationForItem(item)]?.[item.id] || 0 }
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
    itemDurations.value = {}
    selectedItemIds.value = (pkg.items || []).map(item => item.id)
}
function getGradeName(id) {
    if (!id) return '';
    return grades.value.find(g => g.id === id)?.name || `Grade ${id}`;
}

let loadSequence = 0
async function fetchAllPackagesForGrade() {
    if (!gradeId.value) return
    const sequence = ++loadSequence
    loading.value = true
    loadError.value = ''
    try {
        await cacheStore.fetchAllMetadata()
        if (!cacheStore.subjectsLoaded || !cacheStore.gradesLoaded) throw new Error('Metadata unavailable')
        const res = await api.get('/packages', {
            params: { grade_id: gradeId.value }
        })
        if (sequence !== loadSequence) return
        allPackagesForGrade.value = res.data || []
        if (availableSubjects.value.length) selectSubject(availableSubjects.value[0].id)
        else { selectedSubjectId.value = null; selectedPackage.value = null; selectedItemIds.value = [] }
    } catch (err) {
        if (sequence !== loadSequence) return
        loadError.value = 'We could not load the packages. Please try again.'
        allPackagesForGrade.value = []
    } finally {
        if (sequence === loadSequence) loading.value = false
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
        package_id: selectedPackage.value.id,
        package_item_id: item.id,
        subject_id: item.subject_id || item.subject?.id,
        grade_id: item.grade_id,
        duration_months: durationForItem(item),
        requested_price: priceForDuration(item),
    }))
    buttonLoad.value = true
    try {
        const req = await api.post('/lesson-access/request', payload)
        if (req.data.message) {
            await showAlert({
                title: "Request Sent",
                message: "Your access request has been sent.",
            })
        }
    } catch (err) {
        console.error(err)
        await showAlert({
            title: "Request Failed",
            message: err.response?.data?.message || "Failed to send request.",
        })
    } finally {
        buttonLoad.value = false
    }
}

// init
onMounted(fetchAllPackagesForGrade)

watch(gradeId, fetchAllPackagesForGrade)

</script>


<style scoped>
.store-page { max-width: 960px; margin: 0 auto; color: #f1f5f9; padding-bottom: env(safe-area-inset-bottom, 0px); }
.store-description { color: #a8b2c3; font-size: .8rem; margin-bottom: 16px; line-height: 1.5; }
.subject-choices { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 16px; }
.subject-choices button, .package-choices button { background: rgba(255,255,255,.035); border: 1px solid rgba(255,255,255,.12); color: #cbd5e1; border-radius: 8px; padding: 8px 12px; cursor: pointer; min-height: 44px; font: inherit; font-size: .8rem; }
.subject-choices button.active, .package-choices button.active { background: rgba(79,70,229,.2); border-color: #818cf8; color: #fff; }
.package-choices { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.package-choices button { text-align: left; display: grid; gap: 3px; }
.package-choices span { color: #a8b2c3; font-size: .7rem; }
.store-grid { display: flex; flex-direction: column; gap: 14px; }
.package-content { min-width: 0; }
.package-heading { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 10px; margin-bottom: 14px; }
.grade-badge { color: #c7d2fe; background: rgba(79,70,229,.14); border-radius: 5px; font-size: .65rem; padding: 3px 7px; }
.package-heading h2 { color: #fff; font-size: 1.05rem; font-weight: 600; overflow-wrap: anywhere; }
.store-card { background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.1); border-radius: 12px; padding: 14px 16px; }
.section-heading { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.section-heading h3, .order-summary h3 { color: #e2e8f0; font-size: .85rem; font-weight: 600; }
.text-button { background: none; border: none; color: #a5b4fc; font: inherit; font-size: .72rem; margin-left: auto; cursor: pointer; min-height: 44px; padding: 4px; }
.subject-items { display: grid; }
.subject-item { display: grid; grid-template-columns: minmax(140px,1fr) minmax(0,1.8fr); align-items: center; gap: 10px; padding: 12px 0; border-top: 1px solid rgba(255,255,255,.07); }
.subject-toggle { display: flex; align-items: center; gap: 10px; min-width: 0; cursor: pointer; min-height: 44px; }
.subject-toggle input { width: 18px; height: 18px; flex-shrink: 0; accent-color: #4f46e5; cursor: pointer; }
.subject-toggle strong { color: #f1f5f9; font-size: .8rem; overflow-wrap: anywhere; }
.subject-toggle small { display: block; color: #94a3b8; font-size: .65rem; margin-top: 3px; }
.item-term-options { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 6px; }
.item-term-options button { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; border: 1px solid rgba(255,255,255,.12); border-radius: 8px; background: transparent; color: #a8b2c3; cursor: pointer; padding: 7px 5px; min-height: 64px; font-size: .68rem; min-width: 0; }
.item-term-options button.active { border-color: #818cf8; background: rgba(79,70,229,.18); color: #e0e7ff; }
.term-label { white-space: nowrap; }
.term-label small { color: #94a3b8; margin-left: 3px; font-size: .6rem; }
.item-term-options strong { color: #e2e8f0; font-size: .78rem; overflow-wrap: anywhere; }
.discount-badge { color: #6ee7b7; font-size: .6rem; line-height: 1.2; }
.order-summary { position: static; width: 100%; }
.summary-items { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 8px; margin: 12px 0; }
.summary-items > div { display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: .75rem; padding: 10px 12px; background: rgba(255,255,255,.035); border: 1px solid rgba(255,255,255,.12); border-radius: 8px; }
.summary-items > div > span { min-width: 0; overflow-wrap: anywhere; }
.summary-items small { color: #94a3b8; display: block; font-size: .65rem; margin-top: 2px; }
.summary-items strong { color: #e2e8f0; white-space: nowrap; font-size: .75rem; }
.checkout-actions { display: flex; align-items: center; gap: 20px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,.1); }
.summary-total { display: flex; flex: 1; align-items: center; gap: 14px; font-size: .78rem; }
.summary-total > span { color: #4f46e5; }
.summary-total strong { color: #4f46e5; font-size: 1.5rem; font-weight: 700; }
.request-button { min-height: 44px; min-width: 180px; border-radius: 8px; color: #fff; font-weight: 600; font-size: .8rem; }
.approval-note { color: #94a3b8; font-size: .65rem; margin-top: 8px; }
.selection-hint, .section-help { color: #a8b2c3; font-size: .8rem; margin: 12px 0; }
.store-state { padding: 32px 12px; text-align: center; color: #a8b2c3; }
.store-state h2 { color: #fff; font-weight: 600; margin-bottom: 10px; }
.store-state button { margin-top: 16px; }
button:focus-visible, input:focus-visible { outline: 2px solid #a5b4fc; outline-offset: 3px; }
button:disabled { cursor: default; opacity: .45; }
@media (max-width: 600px) {
    .store-card { padding: 10px 12px; }
    .subject-item { grid-template-columns: minmax(0,1fr); gap: 6px; padding: 10px 0; }
    .subject-toggle { min-height: 34px; }
    .item-term-options button { min-height: 62px; }
    .checkout-actions { flex-wrap: wrap; gap: 12px; }
    .summary-total { flex-basis: 100%; justify-content: space-between; }
    .request-button { width: 100%; }
    .summary-items { grid-template-columns: minmax(0, 1fr); }
    .summary-items > div { justify-content: space-between; }
}
@media (max-width: 360px) {
    .term-label small { display: block; margin-left: 0; }
    .item-term-options strong { font-size: .7rem; }
}
</style>
