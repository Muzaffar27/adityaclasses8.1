<template>
    <span v-if="loading" class="review-count muted" aria-label="Loading question approval count">…</span>
    <span v-else-if="error" class="review-count muted" aria-label="Question approval count unavailable">!</span>
    <template v-else-if="count">
        <span v-if="count.important > 0" class="review-count important" :aria-label="`${count.important} questions need correction or attention`" :title="`${count.important} questions need correction or attention`">{{ count.important }}</span>
        <span v-else-if="count.optional > 0" class="review-count optional" :aria-label="`${count.optional} optional questions awaiting approval`" :title="`${count.optional} optional questions awaiting approval`">{{ count.optional }}</span>
    </template>
</template>
<script setup>
defineProps({ count: Object, loading: Boolean, error: Boolean });
</script>
<style scoped>
.review-count { display:inline-flex; justify-content:center; align-items:center; flex-shrink:0; min-width:23px; min-height:23px; padding:2px 6px; border-radius:999px; font-size:.7rem; font-weight:750; line-height:1; box-sizing:border-box; }
.important { color:#fff; background:#b91c1c; border:1px solid #f87171; }
.optional { color:#422006; background:#facc15; border:1px solid #fde047; }
.muted { color:#cbd5e1; background:rgba(148,163,184,.15); }
</style>
