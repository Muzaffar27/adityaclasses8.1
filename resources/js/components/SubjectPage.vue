<template>
  <Layout title="School subjects" :loading="loading">
    <div class="columns is-mobile is-multiline">
      <div class="column is-12-mobile is-4-tablet" v-for="subject in subjects" :key="subject.id">
        <div class="card glass-card clickable-card fixed-card" @click="goToGrades(subject.id)">
          <div class="card-content p-4 has-text-centered">

            <div class="icon-circle mb-3" :style="{ background: getColor(subject.id) }">
              {{ getSubjectIcon(subject.name) }}
            </div>

            <p class="subject-name">{{ subject.name }}</p>

          </div>
        </div>
      </div>
    </div>

    <!-- EMPTY STATE -->
    <div v-if="!loading && subjects.length === 0" class="has-text-centered mt-6">
      <p class="has-text-grey">No subjects available for this grade.</p>
    </div>

  </Layout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import Layout from "./common/Layout.vue";

import { useCacheStore } from "../stores/cache";
import { storeToRefs } from "pinia";

const cacheStore = useCacheStore();
const { subjects } = storeToRefs(cacheStore);

const router = useRouter();
const loading = ref(false);

function goToGrades(subjectId) {
  router.push({ name: "grade", params: { id: subjectId } });
}

function getColor(id) {
  const colors = ["#e3f2fd", "#fce4ec", "#e8f5e9", "#fff3e0", "#f3e5f5"];
  return colors[id % colors.length];
}

function getSubjectIcon(name) {
  const subjectName = name.toLowerCase();
  if (subjectName.includes("math")) return "📐";
  if (subjectName.includes("science")) return "🧪";
  if (subjectName.includes("english") || subjectName.includes("lang")) return "📚";
  if (subjectName.includes("hist")) return "🏛️";
  return "📖";
}

onMounted(async () => {
  loading.value = true;
  try {
    // FIX: Use the unified fetcher
    await cacheStore.fetchAllMetadata();
  } catch (error) {
    console.error("Failed to load school subjects:", error);
  } finally {
    loading.value = false;
  }
});

</script>

<style scoped src="./common/catalog.css"></style>

<style scoped>
.icon-circle {
  width: 55px;
  height: 55px;
  font-size: 26px;
}
</style>
