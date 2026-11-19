<template>
    <Layout title="Store">
        <div class="store-page">
            <div class="store-intro">
                <p class="eyebrow">LEARN AT YOUR PACE</p>
                <h1>Find your learning package</h1>
                <p>Choose your subjects and access period. Your price updates as you choose.</p>
            </div>

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
                            <p>All subjects are selected to start. Keep the ones you need.</p>
                        </header>

                        <details class="store-card shared-periods">
                            <summary>Use the same period for all subjects <span>Optional</span></summary>
                            <p class="section-help">Apply a period to all subjects, or choose a different period for each subject below.</p>
                            <div class="term-options">
                                <button v-for="term in durationOptions" :key="term.months" type="button" :disabled="buttonLoad"
                                    class="term-option" :class="{ active: allItemsUseDuration(term.months) }"
                                    :aria-pressed="allItemsUseDuration(term.months)" @click="applyDurationToAll(term.months)">
                                    <span class="term-name">{{ term.label.split(' (')[0] }}</span>
                                    <span class="term-months">{{ term.months }} months access</span>
                                    <span v-if="termPrice(selectedPackage, term).discount" class="discount-badge">{{ termPrice(selectedPackage, term).discount }}% off</span>
                                    <span class="term-price">{{ money(termPrice(selectedPackage, term).final) }}</span>
                                    <del v-if="termPrice(selectedPackage, term).discount">{{ money(termPrice(selectedPackage, term).original) }}</del>
                                    <span class="term-note">Full package price</span>
                                </button>
                            </div>
                        </details>

                        <section class="store-card" aria-labelledby="subjects-heading">
                            <div class="section-heading"><span class="step-number">1</span><h3 id="subjects-heading">Subjects and access</h3>
                                <button type="button" class="text-button" :disabled="buttonLoad" @click="selectAllSubjects">Select all</button>
                            </div>
                            <p class="section-help">Choose each subject and its access period. You can mix different terms.</p>
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
                                            <span>{{ term.label.split(' (')[0] }}</span>
                                            <small>{{ term.months }} months</small>
                                            <strong>{{ money(priceForItemTerm(item, term)) }}</strong>
                                            <span v-if="termPrice(selectedPackage, term).discount" class="discount-badge">{{ termPrice(selectedPackage, term).discount }}% off</span>
                                        </button>
                                    </div>
                                    <div class="subject-price"><small>Subject total</small><strong>{{ money(priceForDuration(item)) }}</strong><small>{{ durationForItem(item) }} months access</small></div>
                                </div>
                            </div>
                            <p v-if="!selectedPackage.items?.length" class="section-help">No subjects are available in this package yet.</p>
                        </section>
                    </div>

                    <aside class="order-summary store-card" aria-labelledby="summary-heading">
                        <p class="eyebrow">YOUR SELECTION</p><h3 id="summary-heading">At a glance</h3>
                        <p class="summary-package">{{ selectedPackage.name }}</p>
                        <div v-if="selectedItemIds.length" class="summary-items">
                            <div v-for="item in selectedItems" :key="item.id"><span>{{ item.subject?.name || 'Subject' }}<small>{{ durationForItem(item) }} months access</small></span><strong>{{ money(priceForDuration(item)) }}</strong></div>
                        </div>
                        <p v-else class="selection-hint">Select at least one subject to see your total.</p>
                        <div class="summary-total" aria-live="polite" aria-atomic="true">
                            <span>{{ selectedItemIds.length ? 'Your total' : 'No subjects selected' }}</span>
                            <strong v-if="selectedItemIds.length">{{ money(dynamicTotal) }}</strong>
                        </div>
                        <p v-if="hasSelectedDiscount" class="discount-note">Applicable term discounts are included above.</p>
                        <button class="button is-primary request-button" :disabled="buttonLoad || !selectedItemIds.length" @click="submitRequest">
                            {{ buttonLoad ? 'Sending request...' : 'Request access' }}
                        </button>
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
import { packageTerms, termPrice, itemTermPrices } from "../utils/packagePricing.mjs"
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
const selectedDurationMonths = ref(3)
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
const hasSelectedDiscount = computed(() => selectedItems.value.some(item => {
    const term = durationOptions.find(term => term.months === durationForItem(item))
    return termPrice(selectedPackage.value, term).discount > 0
}))
function durationForItem(item) { return itemDurations.value[item.id] || selectedDurationMonths.value }
function priceForItemTerm(item, term) { return termPricesByDuration.value[term.months]?.[item.id] || 0 }
function priceForDuration(item) { return termPricesByDuration.value[durationForItem(item)]?.[item.id] || 0 }
function allItemsUseDuration(months) {
    return (selectedPackage.value?.items || []).length > 0
        && selectedPackage.value.items.every(item => durationForItem(item) === months)
}
function applyDurationToAll(months) {
    selectedDurationMonths.value = months
    itemDurations.value = Object.fromEntries((selectedPackage.value?.items || []).map(item => [item.id, months]))
}

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
    applyDurationToAll(3)
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
.store-page { max-width: 1160px; margin: 0 auto; color: #f1f5f9; padding-bottom: env(safe-area-inset-bottom, 0px); }
.store-intro { margin-bottom: 24px; }
.eyebrow { color: #a5b4fc; font-size: .68rem; font-weight: 700; letter-spacing: .12em; margin-bottom: 8px; }
.store-intro h1 { color: #fff; font-size: clamp(1.4rem, 3vw, 2rem); font-weight: 700; line-height: 1.2; margin-bottom: 10px; }
.store-intro > p:last-child, .package-heading p, .section-help { color: #a8b2c3; font-size: .85rem; line-height: 1.6; }
.subject-choices { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
.subject-choices button, .package-choices button { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.13); color: #cbd5e1; border-radius: 10px; padding: 10px 16px; cursor: pointer; min-height: 44px; font: inherit; font-size: .85rem; }
.subject-choices button.active, .package-choices button.active { background: rgba(79,70,229,.23); border-color: #818cf8; color: #fff; }
.package-choices { display: grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap: 10px; margin-bottom: 24px; }
.package-choices button { text-align: left; display: grid; gap: 6px; }
.package-choices span { color: #a8b2c3; font-size: .75rem; }
.store-grid { display: grid; grid-template-columns: minmax(0,1fr) 310px; gap: 22px; align-items: start; }
.package-content { min-width: 0; }
.package-heading { margin-bottom: 20px; }
.grade-badge { display: inline-block; color: #c7d2fe; background: rgba(79,70,229,.16); border-radius: 6px; font-size: .7rem; padding: 4px 8px; margin-bottom: 10px; }
.package-heading h2 { color: #fff; font-size: 1.35rem; font-weight: 700; margin-bottom: 8px; overflow-wrap: anywhere; }
.store-card { background: rgba(255,255,255,.035); border: 1px solid rgba(255,255,255,.11); border-radius: 16px; padding: 22px; margin-bottom: 20px; }
.section-heading { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.section-heading h3, .order-summary h3 { color: #fff; font-size: 1rem; font-weight: 600; }
.step-number { display: grid; place-items: center; width: 26px; height: 26px; flex-shrink: 0; color: #c7d2fe; background: rgba(79,70,229,.25); border-radius: 8px; font-size: .75rem; }
.shared-periods summary { cursor: pointer; color: #e2e8f0; font-size: .85rem; font-weight: 600; min-height: 24px; }
.shared-periods summary span { color: #a8b2c3; font-size: .7rem; font-weight: 400; margin-left: 6px; }
.shared-periods[open] summary { margin-bottom: 16px; }
.term-options { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 10px; }
.term-option { display: flex; flex-direction: column; align-items: flex-start; gap: 7px; min-width: 0; text-align: left; padding: 16px 12px; border-radius: 12px; color: #cbd5e1; background: rgba(255,255,255,.02); border: 1px solid rgba(255,255,255,.13); cursor: pointer; }
.term-option.active { background: rgba(79,70,229,.16); border-color: #818cf8; box-shadow: inset 0 0 0 1px #818cf8; }
.term-name { color: #fff; font-size: .9rem; font-weight: 700; }
.term-months, .term-note, .term-option del { color: #a8b2c3; font-size: .7rem; }
.term-price { color: #fff; font-size: 1.05rem; font-weight: 700; overflow-wrap: anywhere; }
.discount-badge { display: inline-block; align-self: flex-start; color: #6ee7b7; background: rgba(16,185,129,.1); padding: 3px 6px; border-radius: 5px; font-size: .65rem; font-weight: 700; }
.text-button { background: none; border: none; color: #a5b4fc; font: inherit; font-size: .75rem; margin-left: auto; cursor: pointer; min-height: 44px; padding: 4px; }
.section-help { margin-bottom: 14px; }
.subject-items { display: grid; gap: 10px; }
.subject-item { display: grid; grid-template-columns: minmax(0,1fr) auto; align-items: center; gap: 12px; border: 1px solid rgba(255,255,255,.1); border-radius: 10px; padding: 14px; }
.subject-item.selected { background: rgba(79,70,229,.07); border-color: rgba(129,140,248,.45); }
.subject-toggle { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; cursor: pointer; min-height: 44px; }
.subject-toggle input { width: 20px; height: 20px; flex-shrink: 0; accent-color: #4f46e5; cursor: pointer; }
.subject-toggle strong { color: #f1f5f9; font-size: .85rem; overflow-wrap: anywhere; }
.subject-toggle small, .subject-price small { display: block; color: #a8b2c3; font-size: .7rem; margin-top: 4px; }
.subject-price { text-align: right; font-size: .85rem; }
.item-term-options { grid-column: 1 / -1; grid-row: 2; display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 8px; }
.item-term-options button { display: flex; flex-direction: column; align-items: center; gap: 5px; border: 1px solid rgba(255,255,255,.15); border-radius: 8px; background: rgba(255,255,255,.02); color: #cbd5e1; cursor: pointer; padding: 10px 6px; min-height: 84px; font-size: .72rem; min-width: 0; }
.item-term-options button.active { border-color: #818cf8; background: rgba(79,70,229,.22); color: #fff; }
.item-term-options small { color: #a8b2c3; font-size: .65rem; }
.item-term-options strong { font-size: .8rem; overflow-wrap: anywhere; }
.item-term-options .discount-badge { align-self: center; font-size: .6rem; }
.order-summary { position: sticky; top: 100px; margin: 0; }
.summary-package { color: #a8b2c3; font-size: .8rem; margin-top: 8px; overflow-wrap: anywhere; }
.summary-items { display: grid; gap: 16px; margin: 24px 0; }
.summary-items > div { display: flex; justify-content: space-between; gap: 12px; font-size: .8rem; }
.summary-items small { color: #a8b2c3; display: block; font-size: .7rem; margin-top: 4px; }
.summary-items strong { color: #f1f5f9; white-space: nowrap; }
.summary-total { display: flex; align-items: center; justify-content: space-between; gap: 8px; border-top: 1px solid rgba(255,255,255,.12); padding-top: 18px; font-size: .85rem; }
.summary-total strong { color: #a5b4fc; font-size: 1.3rem; font-weight: 700; }
.discount-note { color: #6ee7b7; font-size: .72rem; margin-top: 8px; }
.request-button { min-height: 46px; margin-top: 20px; width: 100%; border-radius: 10px; color: #fff; font-weight: 600; font-size: .85rem; }
.approval-note { color: #a8b2c3; text-align: center; font-size: .7rem; margin-top: 10px; }
.selection-hint { color: #a8b2c3; font-size: .85rem; margin: 20px 0; }
.store-state { padding: 40px 16px; text-align: center; color: #a8b2c3; }
.store-state h2 { color: #fff; font-weight: 600; margin-bottom: 10px; }
.store-state button { margin-top: 18px; }
button:focus-visible, input:focus-visible { outline: 2px solid #a5b4fc; outline-offset: 4px; }
button:disabled { cursor: default; opacity: .5; }
@media (max-width: 900px) {
    .store-grid { grid-template-columns: minmax(0,1fr); gap: 0; }
    .order-summary { position: static; }
}
@media (max-width: 480px) {
    .store-card { padding: 16px; border-radius: 12px; }
    .term-options { grid-template-columns: 1fr; }
    .term-option { display: grid; grid-template-columns: 1fr auto; gap: 6px 10px; padding: 14px; }
    .term-name, .term-months, .term-note { grid-column: 1; }
    .term-price { grid-column: 2; grid-row: 1 / span 2; align-self: center; }
    .discount-badge, .term-option del { grid-column: 2; }
    .subject-item { padding: 10px; gap: 12px 8px; }
    .item-term-options { gap: 5px; }
    .item-term-options strong { font-size: .7rem; }
    .subject-toggle { gap: 9px; }
    .summary-total strong { font-size: 1.2rem; }
}
</style>
