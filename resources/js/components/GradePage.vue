<template>
    <Layout title="School Grades" :loading="loading" @back="goBack">

        <div class="columns is-mobile is-multiline">
            <div class="column is-12-mobile is-4-tablet" v-for="grade in grades" :key="grade.id">
                <div class="card glass-card clickable-card fixed-card" @click="goToLesson(grade.id)">
                    <div class="card-content p-4 has-text-centered">

                        <div class="icon-circle mb-3" :style="{ background: getColor(grade.id) }">
                            🎓
                        </div>

                        <p class="grade-name">{{ grade.name }}</p>

                        <p class="subtitle is-7 has-text-grey mb-0">
                            {{ grade.lessons_count }} lessons
                        </p>

                    </div>
                </div>
            </div>
        </div>

        <!-- EMPTY STATE -->
        <div v-if="!loading && grades.length === 0" class="has-text-centered mt-6">
            <p class="has-text-grey">No grades available.</p>
        </div>

    </Layout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { useRoute, useRouter } from "vue-router";
import Layout from "./common/Layout.vue";

const route = useRoute();
const router = useRouter();

const subjectId = route.params.id;
const grades = ref([]);
const loading = ref(false);

onMounted(() => {
    fetchGrades();
});

async function fetchGrades() {

    loading.value = true;

    try {
        const { data } = await axios.get("/api/grades", {
            params: {
                subject_id: subjectId
            }
        });

        grades.value = data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function goToLesson(gradeId) {
    router.push({
        name: "lesson",
        params: {
            gradeId: gradeId,
            subjectId: subjectId
        }
    });
}

function goBack() {
    if (window.history.length > 1) {
        router.back();
    } else {
        router.push('/subjects');
    }
}

function getColor(id) {
    const colors = ["#e3f2fd", "#fce4ec", "#e8f5e9", "#fff3e0"];
    return colors[id % colors.length];
}


</script>

<style scoped>
.grade-name {
    font-weight: 700;
    font-size: 1.1rem;
    line-height: 1.2;
    margin-bottom: 4px;
    color: #f9f9f9;
}

.icon-circle {
    width: 50px;
    height: 50px;
    margin: 0 auto;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
</style>