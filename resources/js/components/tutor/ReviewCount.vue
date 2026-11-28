<template>
    <span v-if="loading" class="review-count muted" aria-label="Loading question review counts">…</span>
    <span v-else-if="error" class="review-count muted" aria-label="Question review counts unavailable">!</span>
    <span v-else-if="count?.important > 0 || count?.optional > 0" class="review-counts">
        <span v-if="count.important > 0" class="review-count important" :aria-label="`${count.important} required Starter bank reviews`" :title="`${count.important} required Starter bank reviews`">{{ count.important }}</span>
        <span v-else-if="count.optional > 0" class="review-count optional" :aria-label="`${count.optional} optional reviews for more variety`" :title="`${count.optional} optional reviews for more variety`">{{ count.optional }}</span>
    </span>
</template>
<script setup>
defineProps({ count: Object, loading: Boolean, error: Boolean });
</script>
<style scoped>
.review-counts { display:inline-flex; align-items:center; gap:4px; flex-shrink:0; }
.review-count { display:inline-flex; justify-content:center; align-items:center; flex-shrink:0; min-width:23px; min-height:23px; padding:2px 6px; border-radius:999px; font-size:.7rem; font-weight:750; line-height:1; box-sizing:border-box; }
.important { color:#fff; background:#b91c1c; border:1px solid #f87171; }
.optional { color:#422006; background:#facc15; border:1px solid #fde047; }
.muted { color:#cbd5e1; background:rgba(148,163,184,.15); }
</style>
