<template>
  <div>
    <Sidebar />
    <div class="main-content">
      <slot />
    </div>
  </div>
</template>

<script setup>
import Sidebar from './Sidebar.vue';

</script>

<style scoped>
.main-content {
  margin-left: 250px;
  /* same as sidebar width */
  padding: 20px;
}
</style>