<template>
  <div class="section px-4 py-5 main-content-wrapper mobile-container">

    <div class="container is-fluid">
      <h1 class="title is-4 mb-6 ml-2">School Grades</h1>

      <div class="columns is-mobile is-multiline">
        <div class="column is-6-mobile is-4-tablet is-3-desktop" v-for="grade in grades" :key="grade.id">
          <div class="card grade-card" @click="goToGrade(grade.id)">
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

      <div v-if="grades.length === 0" class="has-text-centered mt-6">
        <p class="has-text-grey">No grades available.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUpdated, onUnmounted } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";
import { useLoaderStore } from "../stores/loader";

const grades = ref([]);
const router = useRouter();
const loader = useLoaderStore();

onMounted(() => {
  fetchGrades();
});

async function fetchGrades() {

  loader.start();

  try {
    const { data } = await axios.get("/api/grades");
    grades.value = data;
  } catch (e) {
    console.error(e);
  } finally {
    loader.stop();
  }
}

function goToGrade(id) {
  router.push({ name: "grade", params: { id } });
}

function getColor(id) {
  const colors = ["#e3f2fd", "#fce4ec", "#e8f5e9", "#fff3e0"];
  return colors[id % colors.length];
}


</script>

<style scoped>
.main-content-wrapper {
  min-height: 100vh;
}

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

.grade-card {
  cursor: pointer;
  border-radius: 16px;
  transition: all 0.25s ease;
  padding: 10px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  height: 100%;
  display: flex;
  flex-direction: column;
}

.grade-card:hover {
  transform: translateY(-6px) scale(1.02);
  box-shadow: 0 2px 8px rgba(255, 255, 255, 0.6);
}

.grade-card:active {
  transform: scale(0.95);
  background-color: #f9f9f9;
}

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