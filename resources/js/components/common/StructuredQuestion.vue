<template>
    <div class="structured-question">
        <p v-if="content.instructions" class="instructions">{{ content.instructions }}</p>
        <p v-if="content.text" class="question-text">{{ content.text }}</p>
        <div v-else-if="markup" class="question-maths" v-html="markup"></div>
        <p v-else role="alert">This mathematical expression cannot be displayed. Compare the original PDF and request reprocessing.</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { mathMarkup } from '../../../../scripts/questions/structured-maths.mjs';
const props = defineProps({ content: { type: Object, required: true } });
// Only the whitelisted MathML renderer builds markup; all node values are XML-escaped.
const markup = computed(() => {
    try { return props.content.math ? `<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">${mathMarkup(props.content.math)}</math>` : ''; }
    catch { return ''; }
});
</script>

<style scoped>
.structured-question { min-width: 0; }
.instructions,.question-text { white-space: pre-line; overflow-wrap: anywhere; line-height: 1.65; margin: 0 0 20px; }
.question-text { font-size: 1.15rem; }
.question-maths { max-width: 100%; overflow-x: auto; padding: 18px 4px; }
.question-maths :deep(math) { font-size: 1.8rem; }
</style>
