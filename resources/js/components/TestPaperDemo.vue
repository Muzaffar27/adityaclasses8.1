<template>
    <Layout title=""><main class="demo"><router-link :to="{ name: 'testPapers' }">← Test papers</router-link><h1>Try answering a question</h1><p class="notice">Practice demo only. Your entries stay on this page; no student records are created and no score is calculated.</p>
        <div class="navigation"><button type="button" :disabled="index === 0" @click="index--">Previous</button><span>Question {{ index + 1 }} of {{ questions.length }}</span><button type="button" :disabled="index === questions.length - 1" @click="index++">Next</button></div>
        <article><h2>Question {{ index + 1 }}</h2><StructuredQuestion :content="questions[index]" /><MathsAnswerEditor :key="index + '-' + resetCount" :number="index + 1" v-model="answers[index]" :read-only="submitted" handwriting-available /></article>
        <p v-if="submitted" class="notice" role="status">Demo submitted. On a real paper your answers would be saved and await review.</p><button v-else type="button" :disabled="!answers.some(a => a.answer.trim() || a.working.trim() || a.working_ink?.strokes?.length)" @click="submitted = true">Submit demo</button><button v-if="submitted" type="button" @click="reset">Try again</button>
    </main></Layout>
</template>
<script setup>
import { ref } from 'vue';
import Layout from './common/Layout.vue';
import StructuredQuestion from './common/StructuredQuestion.vue';
import MathsAnswerEditor from './common/MathsAnswerEditor.vue';
const n = value => ({ type: 'number', value });
const questions = [
    { instructions: 'Simplify:', math: { type: 'expression', children: [{ type: 'fraction', numerator: n('3'), denominator: n('4') }, { type: 'operator', value: '+' }, { type: 'fraction', numerator: n('1'), denominator: n('2') }] } },
    { instructions: 'Evaluate:', math: { type: 'power', base: n('2'), exponent: n('3') } },
    { instructions: 'Solve for x:', text: 'x + 5 = 12' },
];
const index = ref(0), submitted = ref(false), resetCount = ref(0), answers = ref(questions.map(() => ({ answer: '', working: '' })));
function reset() { answers.value = questions.map(() => ({ answer: '', working: '' })); submitted.value = false; index.value = 0; resetCount.value++; }
</script>
<style scoped>
.demo{max-width:620px;padding:18px;margin:auto;min-width:0}h1{font-size:1.6rem}h2{font-size:1.1rem}.notice{padding:14px;background:#edf4fb;border-radius:10px;line-height:1.6}.navigation{display:flex;gap:8px;justify-content:space-between;align-items:center;flex-wrap:wrap;margin:20px 0}article{padding:18px;border:1px solid #ddd;border-radius:12px;background:#fffef8}button{font:inherit;min-height:44px;padding:10px 14px;background:white;color:#172b45;border:1px solid #bac4d2;border-radius:8px;cursor:pointer}button:disabled{opacity:.5;cursor:default}@media(max-width:380px){.demo{padding:8px}article{padding:10px}.navigation{font-size:13px}.navigation button{padding:8px}}
</style>
<style scoped>
article,.notice{--app-text:#172a44;--app-muted:#526479;color:#172a44}article :deep(.instructions),article :deep(.question-text),article :deep(.question-maths),article :deep(math){color:#172a44}
</style>
