<template>
  <div class="p-5">
    <h1 class="title mb-5">Choose Your Grade</h1>

    <div class="columns is-multiline">
      <div class="column is-3" v-for="grade in grades" :key="grade.id">
        <div class="card cursor-pointer" @click="goToGrade(grade.id)">
          <div class="card-content has-text-centered">
            <p class="title is-5">{{ grade.name }}</p>
            <p class="subtitle is-6">
              {{ grade.lessons_count }} lessons
            </p>
          </div>
        </div>
      </div>
    </div>

    <p v-if="grades.length === 0">No grades available.</p>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";

const grades = ref([]);
const router = useRouter();

async function fetchGrades() {
  try {
    const { data } = await axios.get("/api/grades");
    grades.value = data;
  } catch (e) {
    console.error(e);
  }
}

function goToGrade(id) {
  router.push({ name: "grade", params: { id } });
}

onMounted(fetchGrades);
</script>