<template>
    <span class="value-node" :class="{ selected: path.join('.') === activePath.join('.') }">
        <input v-if="['number', 'variable', 'operator'].includes(modelValue.type)" :value="modelValue.value" :aria-label="label" :title="label"
            :inputmode="modelValue.type === 'number' ? 'decimal' : 'text'" :style="{ width: `${Math.max(3, Math.min(16, (modelValue.value?.length || 1) + 1))}ch` }"
            maxlength="100" required autocomplete="off" spellcheck="false" @focus="emit('select', path)" @input="emit('update:modelValue', editMathValue(modelValue, $event.target.value))" />
        <span v-else-if="modelValue.type === 'expression'" class="expression-values">
            <MathValueEditor v-for="(child, index) in modelValue.children" :key="index" :model-value="child" :path="[...path, 'children', index]" :label="`${label}, part ${index + 1}`" :active-path="activePath" @select="emit('select', $event)" @update:model-value="setChild(index, $event)" />
        </span>
        <span v-else-if="modelValue.type === 'fraction'" class="fraction-values">
            <span class="fraction-top"><MathValueEditor :model-value="modelValue.numerator" :path="[...path, 'numerator']" :label="`${label}, numerator`" :active-path="activePath" @select="emit('select', $event)" @update:model-value="set('numerator', $event)" /></span>
            <span class="fraction-bottom"><MathValueEditor :model-value="modelValue.denominator" :path="[...path, 'denominator']" :label="`${label}, denominator`" :active-path="activePath" @select="emit('select', $event)" @update:model-value="set('denominator', $event)" /></span>
        </span>
        <span v-else-if="modelValue.type === 'power'" class="power-values">
            <span class="power-base"><MathValueEditor :model-value="modelValue.base" :path="[...path, 'base']" :label="`${label}, base`" :active-path="activePath" @select="emit('select', $event)" @update:model-value="set('base', $event)" /></span>
            <span class="power-exponent"><MathValueEditor :model-value="modelValue.exponent" :path="[...path, 'exponent']" :label="`${label}, exponent`" :active-path="activePath" @select="emit('select', $event)" @update:model-value="set('exponent', $event)" /></span>
        </span>
        <span v-else-if="modelValue.type === 'root'" class="root-values">
            <span v-if="modelValue.index" class="root-index"><MathValueEditor :model-value="modelValue.index" :path="[...path, 'index']" :label="`${label}, root index`" :active-path="activePath" @select="emit('select', $event)" @update:model-value="set('index', $event)" /></span>
            <span class="root-sign" aria-hidden="true">√</span><span class="root-inside"><MathValueEditor :model-value="modelValue.radicand" :path="[...path, 'radicand']" :label="`${label}, inside root`" :active-path="activePath" @select="emit('select', $event)" @update:model-value="set('radicand', $event)" /></span>
        </span>
        <span v-else>This part needs replacing in Text &amp; maths.</span>
    </span>
</template>
<script setup>
import { editMathValue } from '../../composables/mathEditor.mjs';
const props = defineProps({ modelValue: { type: Object, required: true }, path: { type: Array, default: () => [] }, activePath: { type: Array, default: () => [] }, label: { type: String, default: 'Formula' } });
const emit = defineEmits(['update:modelValue', 'select']);
function set(field, value) { emit('update:modelValue', { ...props.modelValue, [field]: value }); }
function setChild(index, value) { const children = [...props.modelValue.children]; children[index] = value; set('children', children); }
</script>
<style scoped>
.value-node { display:inline-flex; align-items:center; max-width:100%; min-width:0; font:inherit; vertical-align:middle; }
input { box-sizing:border-box; min-width:44px; max-width:100%; min-height:44px; padding:6px; border:1px solid var(--app-border); border-radius:6px; background:var(--app-panel); color:var(--app-text); font:inherit; text-align:center; }.selected>input { border-color:var(--app-accent); }input:focus-visible { outline:3px solid var(--app-accent); outline-offset:2px; }
.expression-values { display:inline-flex; align-items:center; flex-wrap:wrap; gap:8px; max-width:100%; min-width:0; }.fraction-values { display:inline-flex; flex-direction:column; align-items:stretch; max-width:100%; padding:2px; }.fraction-top,.fraction-bottom { display:flex; justify-content:center; min-width:0; padding:5px 3px; }.fraction-top { border-bottom:2px solid var(--app-text); }
.power-values { display:inline-flex; align-items:flex-start; max-width:100%; gap:3px; }.power-base { min-width:0; padding-top:24px; }.power-exponent { min-width:0; font-size:.75em; }
.root-values { display:inline-flex; align-items:center; max-width:100%; min-width:0; }.root-sign { font-family:Georgia,serif; font-size:2rem; }.root-inside { min-width:0; border-top:2px solid var(--app-text); padding:5px 3px 0; }.root-index { min-width:0; align-self:flex-start; font-size:.7em; transform:translateY(-12px); }
@media(max-width:500px) { input { font-size:16px; } }
</style>
