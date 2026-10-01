<template>
    <div class="lesson-progress" :class="status" aria-live="polite">
        <div class="progress-caption">
            <span><CheckCircleIcon v-if="status === 'completed'" />{{ labels[status] || labels.not_started }}</span>
            <span v-if="status === 'in_progress'">{{ progress?.percent || 0 }}%</span>
        </div>
        <div v-if="status === 'in_progress'" class="progress-track" role="progressbar"
            aria-label="Lesson video progress" :aria-valuenow="progress?.percent || 0"
            aria-valuemin="0" aria-valuemax="100">
            <span :style="{ width: `${progress?.percent || 0}%` }"></span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { CheckCircleIcon } from '@heroicons/vue/24/outline';
const props = defineProps({ progress: Object });
const status = computed(() => props.progress?.status || 'not_started');
const labels = { not_started: 'Not started', in_progress: 'In progress', completed: 'Completed' };
</script>

<style scoped>
.lesson-progress { padding: 0.65rem 1rem; color: var(--app-muted); border-top: 1px solid var(--app-border); }
.progress-caption { display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.72rem; font-weight: 700; }
.progress-caption > span { display: inline-flex; align-items: center; gap: 0.3rem; }
.progress-caption svg { height: 16px; width: 16px; }
.in_progress { color: #fdba74; }
.completed { color: #6ee7b7; }
.progress-track { height: 5px; border-radius: 99px; background: var(--app-glass-hover); overflow: hidden; margin-top: 0.45rem; }
.progress-track span { display: block; height: 100%; border-radius: inherit; background: #fb923c; }
</style>
