<template>
    <div class="math-editor">
        <div class="math-toolbar" aria-label="Maths tools">
            <button type="button" :disabled="selectedNode?.type === 'operator'" @click="wrap('fraction')">Fraction</button>
            <button type="button" :disabled="selectedNode?.type === 'operator'" @click="wrap('power')">Power</button>
            <button type="button" :disabled="selectedNode?.type === 'operator'" @click="wrap('root')">Root</button>
            <button type="button" @click="update(addMathTerm(modelValue, selectedPath))">+ Term</button>
            <button type="button" :disabled="!canRemoveMathNode(modelValue, selectedPath)" @click="remove">Remove</button>
            <button type="button" :disabled="!history.length" @click="undo">Undo</button>
        </div>
        <p class="math-hint">Click a number or sign to edit.</p>
        <div class="editable-formula" role="group" aria-label="Editable formula">
            <MathValueEditor :model-value="modelValue" :active-path="selectedPath" @select="selectedPath = $event" @update:model-value="update" />
        </div>
    </div>
</template>
<script setup>
import { computed, ref, toRaw, watch } from 'vue';
import MathValueEditor from './MathValueEditor.vue';
import { addMathTerm, canRemoveMathNode, mathNodeAt, removeMathNode, wrapMathNode } from '../../composables/mathEditor.mjs';
const props = defineProps({ modelValue: { type: Object, required: true } });
const emit = defineEmits(['update:modelValue']);
const selectedPath = ref([]), history = ref([]);
let lastEmitted;
const selectedNode = computed(() => mathNodeAt(props.modelValue, selectedPath.value));
function update(value) {
    if (value === props.modelValue) return;
    history.value = [...history.value.slice(-29), JSON.parse(JSON.stringify(props.modelValue))];
    lastEmitted = toRaw(value); emit('update:modelValue', value);
}
function wrap(type) { update(wrapMathNode(props.modelValue, selectedPath.value, type)); }
function remove() { update(removeMathNode(props.modelValue, selectedPath.value)); selectedPath.value = []; }
function undo() { const value = history.value.pop(); if (value) { selectedPath.value = []; lastEmitted = toRaw(value); emit('update:modelValue', value); } }
watch(() => props.modelValue, value => { if (toRaw(value) !== lastEmitted) { history.value = []; selectedPath.value = []; } });
</script>
<style scoped>
.math-editor { min-width:0; }.math-toolbar { display:flex; flex-wrap:wrap; gap:6px; }.math-toolbar button { min-height:44px; padding:7px 10px; border:1px solid var(--app-border); border-radius:7px; font:inherit; font-size:.8rem; background:var(--app-panel); color:var(--app-text); cursor:pointer; }.math-toolbar button:disabled { opacity:.45; cursor:default; }.math-toolbar button:focus-visible,summary:focus-visible { outline:3px solid var(--app-accent); outline-offset:2px; }
.math-hint { color:var(--app-muted); font-size:.78rem; margin:8px 0; }.editable-formula { min-width:0; overflow-x:auto; padding:16px 10px; border:1px solid var(--app-border); border-radius:10px; background:var(--app-inset); font-size:1.1rem; }
</style>
