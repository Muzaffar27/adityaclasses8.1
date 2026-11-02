<template>
    <div v-if="pending.length" class="save-status" role="status">Saving {{ pending.length }} {{ pending.length === 1 ? 'change' : 'changes' }}…</div>
    <div v-for="job in failures" :key="job.id" class="save-error" role="alert">
        <strong>{{ job.kind === 'flag' ? 'Flag not saved' : 'Approval not saved' }} · {{ job.label }}</strong>
        <p>{{ job.error }}</p>
        <button type="button" @click="queue.retry(job.id)">Retry save</button>
        <button type="button" @click="review(job)">Review again</button>
    </div>
</template>
<script setup>
import { useQuestionReviewSaves } from '../../composables/questionReviewSaves';
const queue = useQuestionReviewSaves();
const { pending, failures } = queue;
const emit = defineEmits(['review']);
function review(job) { queue.reviewAgain(job.id); emit('review', job); }
</script>
<style scoped>
.save-status { margin:8px 0; color:var(--app-muted); font-size:.8rem; }
.save-error { margin:8px 0; padding:12px; border:1px solid #b96b62; border-radius:10px; background:rgba(180,70,55,.12); font-size:.85rem; overflow-wrap:anywhere; }.save-error p { margin:6px 0; }.save-error button { min-height:44px; margin:4px 8px 0 0; padding:8px 12px; border:1px solid var(--app-border); border-radius:8px; background:var(--app-panel); color:var(--app-text); cursor:pointer; font:inherit; }
</style>
