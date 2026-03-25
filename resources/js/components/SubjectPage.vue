<template>
  <div class="section px-4 py-5 main-content-wrapper mobile-container">
    <div class="container is-fluid">
      <h1 class="title is-4 mb-6 ml-2">Subjects</h1>

      <div v-if="loading">
        <Loader />
      </div>

      <div v-else class="columns is-mobile is-multiline">
        <div class="column is-6-mobile is-4-tablet is-3-desktop" v-for="subject in subjects" :key="subject.id">
          <div class="card subject-card" @click="goToLessons(subject.id)">
            <div class="card-content p-4 has-text-centered">
              <div class="icon-circle mb-3" :style="{ background: getColor(subject.id) }">
                {{ getSubjectIcon(subject.name) }}
              </div>
              <p class="subject-name">{{ subject.name }}</p>
            </div>
          </div>
        </div>
      </div>

      <div v-if="!loading && subjects.length === 0" class="has-text-centered mt-6">
        <p class="has-text-grey">No subjects available for this grade.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import Loader from "./common/Loader.vue";

const subjects = ref([]);
const router = useRouter();
const route = useRoute();
const loading = ref(false);

// The grade ID from the URL params
const gradeId = route.params.id;

async function fetchSubjects() {
  loading.value = true;
  try {
    // const { data } = await axios.get(`/api/grades/${gradeId}/subjects`);

    subjects.value = [
      { id: 1, name: "math" },
      { id: 2, name: "science" },
      { id: 3, name: "english" },
      { id: 4, name: "hist" },
      { id: 5, name: "Biology" },
    ];

    subjects.value = data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function goToLessons(subjectId) {
  router.push({ name: "lessons", params: { id: subjectId } });
}

function getColor(id) {
  const colors = ["#e3f2fd", "#fce4ec", "#e8f5e9", "#fff3e0", "#f3e5f5"];
  return colors[id % colors.length];
}

// Map icons to subject names for a premium feel
function getSubjectIcon(name) {
  const subjectName = name.toLowerCase();
  if (subjectName.includes('math')) return '📐';
  if (subjectName.includes('science')) return '🧪';
  if (subjectName.includes('english') || subjectName.includes('lang')) return '📚';
  if (subjectName.includes('hist')) return '🏛️';
  return '📖'; // Default
}

onMounted(() => {
  fetchSubjects();
});
</script>

<style scoped>
.main-content-wrapper {
  min-height: 100vh;
}

/* Sidebar padding for Desktop */
@media (min-width: 1024px) {
  .main-content-wrapper {
    padding-left: 2rem;
    padding-right: 2rem;
  }
}

.mobile-container {
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}

.subject-card {
  cursor: pointer;
  border-radius: 16px;
  transition: all 0.25s ease;
  padding: 10px;
  background-color: #1e2a38;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  height: 100%;
  display: flex;
  flex-direction: column;
}

/* Hover effect matches the Grades page */
.subject-card:hover {
  transform: translateY(-6px) scale(1.02);
  box-shadow: 0 2px 8px rgba(79, 70, 220, 0.8);
}

/* Tactile feedback for mobile */
.subject-card:active {
  transform: scale(0.95);
  background-color: #4f46e5;
}

.subject-name {
  font-weight: 700;
  font-size: 1.1rem;
  line-height: 1.2;
  margin-bottom: 4px;
  color: #f9f9f9;
}

.icon-circle {
  width: 55px;
  height: 55px;
  margin: 0 auto;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
}
</style>