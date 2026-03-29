<template>
  <section class="section px-4 py-5 main-content-wrapper mobile-container">
    <div class="container is-fluid">

      <div class="header-bar">
        <div v-if="showBack" class="back-btn" @click="$emit('back')">
          <ArrowLeftIcon class="back-icon" />
        </div>

        <h1 class="title is-4 mb-0">{{ title }}</h1>
      </div>

      <div v-if="loading">
        <Loader />
      </div>

      <div v-else>
        <slot />
      </div>

    </div>
  </section>
</template>

<script setup>
import { ArrowLeftIcon } from "@heroicons/vue/24/outline";
import Loader from "./Loader.vue";
defineProps({
  title: String,
  loading: Boolean,
  showBack: {
    type: Boolean,
    default: true
  }
});

defineEmits(['back']);
</script>

<style>
.header-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.back-btn {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 12px;
  background: rgba(79, 70, 229, 0.1);
  cursor: pointer;
  transition: all 0.2s ease;
  backdrop-filter: blur(6px);
}

.back-btn:hover {
  transform: translateX(-4px);
  background: rgba(79, 70, 229, 0.2);
}

.back-icon {
  width: 20px;
  height: 20px;
  color: #4f46e5;
}
</style>