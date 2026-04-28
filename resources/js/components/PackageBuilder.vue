<template>
    <div class="package-container p-3">

        <!-- HEADER -->
        <div class="is-flex is-justify-content-space-between is-align-items-center mb-3">

            <div>
                <h1 class="title is-5 has-text-white mb-1">
                    {{ isEdit ? "Edit Package" : "Create Package" }}
                </h1>
                <p class="has-text-grey is-size-7">
                    Bundle subjects into structured packages
                </p>
            </div>

            <button class="login-btn action-btn px-5" style="max-width: 85px;" @click="submit" :disabled="loading">
                <span v-if="!loading">
                    {{ isEdit ? "Update" : "Create" }}
                </span>
                <span v-else>
                    Loading..
                </span>
            </button>


        </div>

        <!-- CARD -->
        <div class="glass-card compact-card p-4">

            <!-- =========================
                 MAIN PACKAGE INFO (KEEP ALL)
                 ========================= -->
            <div class="section-title package-title">
                📦 Package Details
            </div>

            <div class="columns is-mobile is-variable is-2 mb-2">

                <!-- NAME -->
                <div class="column is-6">
                    <label class="custom-label">Package Name</label>
                    <input class="input custom-input" v-model="form.name" />
                </div>

                <!-- MAIN GRADE (✔ RESTORED) -->
                <div class="column is-3">
                    <label class="custom-label">Package Grade</label>
                    <select v-model="form.grade_id" class="input custom-input">
                        <option :value="null">Select Grade</option>
                        <option v-for="g in grades" :key="g.id" :value="g.id">
                            {{ g.name }}
                        </option>
                    </select>
                </div>

                <!-- MAIN SUBJECT (✔ RESTORED) -->
                <div class="column is-3">
                    <label class="custom-label">Package Subject</label>
                    <select v-model="form.subject_id" class="input custom-input">
                        <option :value="null">Select Subject</option>
                        <option v-for="s in subjects" :key="s.id" :value="s.id">
                            {{ s.name }}
                        </option>
                    </select>
                </div>

                <!-- BASE PRICE -->
                <!-- <div class="column is-3">
                    <label class="custom-label">Base Price (Rs)</label>
                    <input class="input custom-input" type="number" v-model="form.base_price" />
                </div> -->

            </div>

            <hr class="mini-divider" />

            <div class="section-title subject-title">
                📚 Subject Details
            </div>

            <!-- =========================
                 ITEM CREATOR
                 ========================= -->

            <label class="custom-label">Subject</label>

            <div class="subjects-row">
                <label v-for="s in subjects" :key="s.id" class="subject-pill-glass"
                    :class="{ 'is-active': newItem.subjects.includes(s.id) }">
                    <input type="radio" name="single-subject" :value="s.id" v-model="singleSubject"
                        @change="selectSubject(s.id)" />
                    <span>{{ s.name }}</span>
                </label>
            </div>

            <div class="is-flex is-gap-2 is-align-items-end mt-2">

                <div style="flex:2;">
                    <label class="custom-label">Grade</label>
                    <select v-model="newItem.grade_id" class="input custom-input">
                        <option :value="null">Default</option>
                        <option v-for="g in grades" :key="g.id" :value="g.id">
                            {{ g.name }}
                        </option>
                    </select>
                </div>

                <div style="flex:1;">
                    <label class="custom-label">Price</label>
                    <input class="input custom-input" type="text" v-model="newItem.price" />
                </div>

                <button class="button is-primary is-small has-text-white" style="height:34px;"
                    :disabled="!newItem.subjects.length" @click="handleItemSave">
                    {{ editingIndex === null ? "Add" : "Update" }}
                </button>

            </div>

            <!-- SUBJECTS -->


            <hr class="mini-divider" />

            <div class="section-title list-title">
                📋 Package Contents
            </div>

            <div style="flex:1;">
                <label class="custom-label">Click on an item to modify the Price, Grade or Subject</label>
            </div>
            <!-- =========================
                 ITEMS LIST
                 ========================= -->
            <div class="items-mini is-clickable">

                <div v-for="(item, index) in form.items" :key="index" class="item-mini"
                    :class="{ editing: editingIndex === index }" @click="loadItem(index)">

                    <!-- PRICE -->
                    <div class="is-flex is-flex-direction-column">
                        <strong class="price">Rs {{ item.price }}</strong>

                        <span v-if="item.grade_id" class="has-text-grey is-size-7" style="font-size:0.6rem;">
                            {{ getGradeName(item.grade_id) }}
                        </span>
                    </div>

                    <!-- SUBJECTS -->
                    <div class="subjects">
                        <span v-for="s in item.subjects" :key="s" class="tag is-dark-accent">
                            {{ getSubjectName(s) }}
                        </span>
                    </div>

                    <!-- DELETE -->
                    <button class="delete-btn" @click.stop="removeItem(index)">
                        ✕
                    </button>

                </div>

            </div>

            <!-- TOTAL -->
            <div class="total-bar mt-2">
                <span>Total</span>
                <strong>Rs {{ total }}</strong>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import api from "../api";
import { useCacheStore } from "../stores/cache";
import { storeToRefs } from "pinia";

const cacheStore = useCacheStore();
const { subjects, grades } = storeToRefs(cacheStore);

const props = defineProps({
    editPackage: Object
});

const isEdit = computed(() => !!props.editPackage);
const editingIndex = ref(null);
const singleSubject = ref(null);
const loading = ref(false);

/* =========================
   FULL PACKAGE MODEL (RESTORED)
   ========================= */
const form = ref({
    name: "",
    grade_id: null,     // ✔ MAIN PACKAGE GRADE
    subject_id: null,   // ✔ MAIN PACKAGE SUBJECT
    base_price: 0,
    items: []
});

const newItem = ref({
    price: 0,
    subjects: [],
    grade_id: null
});

/* =========================
   TOTAL
   ========================= */
const total = computed(() => {
    let sum = Number(form.value.base_price || 0);

    form.value.items.forEach(i => {
        sum += Number(i.price || 0);
    });

    return sum;
});

/* =========================
   ITEM SAVE (ADD / EDIT)
   ========================= */
function handleItemSave() {

    if (!form.value.grade_id) {
        return alert("Select package grade");
    }

    if (!form.value.subject_id) {
        return alert("Select package subject");
    }

    if (!newItem.value.subjects.length) {
        return alert("Select item subjects");
    }

    const payload = {
        price: newItem.value.price,
        grade_id: newItem.value.grade_id,
        subjects: [...newItem.value.subjects],
        type: "subject"
    };

    if (editingIndex.value !== null) {
        form.value.items[editingIndex.value] = payload;
        editingIndex.value = null;
    } else {
        form.value.items.push(payload);
    }

    resetItem();
}

function selectSubject(id) {
    singleSubject.value = id;
    newItem.value.subjects = [id]; // FORCE SINGLE SUBJECT ONLY
}

/* =========================
   EDIT ITEM
   ========================= */
function loadItem(index) {
    const item = form.value.items[index];

    newItem.value = {
        price: item.price,
        grade_id: item.grade_id,
        subjects: [...item.subjects]
    };

    singleSubject.value = item.subjects[0] || null;

    editingIndex.value = index;
}
/* =========================
   DELETE
   ========================= */
function removeItem(index) {
    form.value.items.splice(index, 1);

    if (editingIndex.value === index) {
        resetItem();
    }
}

/* =========================
   RESET
   ========================= */
function resetItem() {
    newItem.value = {
        price: 0,
        subjects: [],
        grade_id: null
    };
    editingIndex.value = null;
}

/* =========================
   SUBMIT
   ========================= */
async function submit() {
    loading.value = true;

    try {
        if (isEdit.value) {
            await api.put(`/packages/update/${props.editPackage.id}`, form.value);
        } else {
            await api.post("/packages/store", form.value);
        }
        alert("Saved!");
    } catch (e) {
        console.error(e);
    }
    finally {
        loading.value = false;
    }
}

/* =========================
   HELPERS
   ========================= */
function getSubjectName(id) {
    return subjects.value.find(s => s.id === id)?.name || id;
}

function getGradeName(id) {
    return grades.value.find(g => g.id === id)?.name || id;
}

/* =========================
   INIT EDIT MODE
   ========================= */
onMounted(async () => {
    await cacheStore.fetchAllMetadata();

    if (props.editPackage) {

        form.value = {
            name: props.editPackage.name,
            grade_id: props.editPackage.grade_id,     // ✔ RESTORED
            subject_id: props.editPackage.subject_id, // ✔ RESTORED
            base_price: props.editPackage.base_price,
            items: []
        };

        props.editPackage.items.forEach(i => {
            form.value.items.push({
                price: i.price,
                grade_id: i.grade_id,
                subjects: [i.subject_id],
                type: "subject"
            });
        });
    }
});
</script>

<style scoped>
.package-container {
    max-width: 1050px;
    margin: 0 auto;
}

/* =========================
   CARD
   ========================= */
.compact-card {
    display: flex;
    flex-direction: column;
    gap: 5px;
    border-radius: 14px;
}

/* =========================
   LABELS + INPUTS
   ========================= */
.custom-label {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-bottom: 4px;
}

.custom-input {
    height: 34px !important;
    font-size: 0.8rem;
    padding: 6px 10px;
}

/* =========================
   DIVIDER
   ========================= */
.mini-divider {
    margin: 4px 0;
    /* Reduced from 0.5rem */
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    opacity: 0.15;
}

.columns.mb-2 {
    margin-bottom: 0.01rem !important;
}

/* =========================
   SUBJECT SELECT PILLS
   ========================= */
.subjects-row {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    max-height: 120px;
    overflow-y: auto;
}

.subject-pill-glass {
    display: inline-flex;
    align-items: center;
    gap: 4px;

    padding: 3px 8px;
    border-radius: 999px;

    font-size: 0.65rem;
    color: #cbd5e1;

    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);

    cursor: pointer;
    user-select: none;

    transition: all 0.15s ease;

    flex: 0 0 auto;
}

.subject-pill-glass input {
    display: none;
}

.subject-pill-glass:hover {
    background: rgba(15, 15, 20, 0.55);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45);
    border-color: rgba(255, 255, 255, 0.08);
}

.subject-pill-glass.is-active {
    background: rgba(79, 70, 229, 0.25);
    border-color: #4f46e5;
    color: #fff;
}

/* =========================
   SUMMARY LIST
   ========================= */
.items-mini {
    display: flex;
    flex-direction: row;
    /* Change from column to row */
    flex-wrap: wrap;
    /* Allow items to move to next line if space runs out */
    gap: 8px;
    /* Spacing between items */
}

/* =========================
   🔥 FIX: SINGLE ROW ITEM
   ========================= */
.item-mini {
    display: inline-flex;
    /* Use inline-flex for better fit */
    align-items: center;

    /* Remove width: 100% - this was forcing it to take the full line */
    width: auto;
    min-width: 200px;
    /* Optional: ensures a nice minimum width for each pill */

    padding: 6px 10px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    gap: 10px;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.item-mini:hover {
    transform: translateY(-4px);
    background: rgba(15, 15, 20, 0.55);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45);
    border-color: rgba(255, 255, 255, 0.08);
}

.item-mini .is-size-7 {
    line-height: 1;
    margin-top: 2px;
    color: #6366f1 !important;
    /* Indigo color to make it pop slightly */
}

/* PRICE LEFT */
.price {
    font-size: 0.75rem;
    font-weight: 600;
    color: #fff;

    flex: 0 0 auto;
    white-space: nowrap;
}

/* SUBJECTS MIDDLE (FORCE ONE ROW) */
.subjects {
    display: flex;
    align-items: center;
    gap: 4px;

    flex: 1;
    min-width: 0;

    flex-wrap: nowrap;
    /* 🔥 IMPORTANT */
    overflow-x: auto;
    overflow-y: hidden;

    white-space: nowrap;
}

/* hide scrollbar */
.subjects::-webkit-scrollbar {
    display: none;
}

/* tag */
.subjects .tag {
    font-size: 0.6rem;
    padding: 2px 6px;

    white-space: nowrap;
    flex: 0 0 auto;
}

/* DELETE BUTTON RIGHT */
.delete-btn {
    flex: 0 0 auto;

    width: 20px;
    height: 20px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 10px;

    border-radius: 6px;

    background: rgba(244, 63, 94, 0.12);
    color: #f43f5e;
    border: none;
}

.delete-btn:hover {
    background: #f43f5e;
    color: white;
}

/* =========================
   TOTAL BAR
   ========================= */
.total-bar {
    display: flex;
    /* Change justify-content from space-between to flex-end */
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    /* Adds a nice space between the word 'Total' and the price */
    font-size: 0.9rem;
    margin-top: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    /* Optional: adds a separation line */
    padding-top: 8px;
}

.total-bar span {
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.75rem;
}

.total-bar strong {
    color: #fff;
    font-size: 1.1rem;
}

.section-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid;
    display: inline-block;
}

.package-title {
    color: #3b82f6;
    /* blue */
    border-bottom-color: #3b82f6;
}

.subject-title {
    color: #10b981;
    /* green */
    border-bottom-color: #10b981;
    margin-top: 0.5rem;
}

.list-title {
    color: #f59e0b;
    /* amber / warm orange */
    border-bottom-color: #f59e0b;
    margin-top: 0.5rem;
}
</style>