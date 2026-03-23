<template>
  <div class="p-5">
    <h1 class="title">Available Courses</h1>

    <div class="columns is-multiline">
      <div class="column is-4" v-for="course in courses" :key="course.id">
        <div class="card">
          <div class="card-content">
            <p class="title is-5">{{ course.title }}</p>
            <p class="content">
              {{ course.description }}
            </p>
          </div>

          <footer class="card-footer">
            <button class="card-footer-item button is-primary is-light" @click="handleEnroll">
              Enroll / Sign in
            </button>
          </footer>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/api";
import { useRouter } from "vue-router";

const courses = ref([]);
const router = useRouter();

async function fetchCourses() {
  //const { data } = await api.get("/courses");
  const mockData = [
    {
      id: 1,
      title: "Introduction to Vue 3",
      description: "Master the Composition API and build reactive apps with ease."
    },
    {
      id: 2,
      title: "Advanced CSS with Bulma",
      description: "Learn how to leverage modern Flexbox-based frameworks for rapid UI development."
    },
    {
      id: 3,
      title: "State Management with Pinia",
      description: "A deep dive into the intuitive, modular store for Vue applications."
    }
  ];

  courses.value = mockData;
}

function handleEnroll() {
  router.push({ name: "login" }); // redirect guest to login
}

onMounted(fetchCourses);
</script>