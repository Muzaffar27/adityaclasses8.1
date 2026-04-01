<template>
  <section class="section px-4 py-5 main-content-wrapper mobile-container">
    <div class="container is-fluid">

      <div class="header-bar">

        <div class="left-section">
          <div v-if="showBack" class="back-btn" @click="$emit('back')">
            <ArrowLeftIcon class="back-icon" />
          </div>
        </div>

        <h1 class="title is-4 mb-0 header-title has-text-white">
          {{ title }}
        </h1>

        <div class="right-section">
          <slot name="actions" />
        </div>

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
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
}

.left-section,
.right-section {
  display: flex;
  align-items: center;
  min-width: 60px;
}

.header-title {
  flex: 1;
  text-align: center;
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