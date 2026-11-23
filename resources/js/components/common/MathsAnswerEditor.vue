<template>
    <section ref="editor" class="answer-editor" :aria-label="`Answer for question ${number}`">
        <div class="response-sheet">
            <div class="response-parts" role="tablist" aria-label="Response section">
                <button :id="`working-tab-${number}`" type="button" role="tab" :aria-selected="field === 'working'" :aria-controls="`working-panel-${number}`" :tabindex="field === 'working' ? 0 : -1" @click="selectPart('working')" @keydown.left.prevent="switchPart($event)" @keydown.right.prevent="switchPart($event)">Working</button>
                <button :id="`answer-tab-${number}`" type="button" role="tab" :aria-selected="field === 'answer'" :aria-controls="`answer-panel-${number}`" :tabindex="field === 'answer' ? 0 : -1" @click="selectPart('answer')" @keydown.left.prevent="switchPart($event)" @keydown.right.prevent="switchPart($event)">Final answer</button>
            </div>
            <div v-if="!readOnly && field === 'working'" class="entry-method" role="group" aria-label="Entry method">
                <button type="button" :aria-pressed="mode === 'keypad'" @click="selectMode('keypad')">Keypad</button>
                <button v-if="handwritingAvailable" type="button" :aria-pressed="mode === 'handwrite'" @click="selectMode('handwrite')">Handwrite</button>
            </div>
            <div v-for="part in parts" v-show="field === part.name" :id="`${part.name}-panel-${number}`" :key="part.name" class="response-body" role="tabpanel" :aria-labelledby="`${part.name}-tab-${number}`">
                <MathsEntryField v-if="!readOnly || modelValue[part.name] || !modelValue[`${part.name}_ink`]?.strokes?.length"
                    v-show="mode === 'keypad' || readOnly || modelValue[part.name]"
                    :ref="element => inputs[part.name] = element" :model-value="modelValue[part.name]"
                    :label="`Question ${number} ${part.label}`" :maximum="part.maximum" :read-only="readOnly || mode === 'handwrite'"
                    @update:model-value="typed(part.name, $event)" @activate="activate(part.name); keepFieldVisible()" @limit="limitReached = $event" />
                <p v-if="!readOnly && field === part.name && mode === 'keypad'" class="entry-hint">Tap where you want to write, or use ← and →.</p>
                <p v-if="part.name === 'answer' && modelValue.answer_ink?.strokes?.length" class="entry-hint">{{ readOnly ? 'Earlier handwritten response' : 'Earlier handwriting. Enter your final answer using the maths keypad.' }}</p>
                <HandwritingPad :model-value="modelValue[`${part.name}_ink`]" :label="`Question ${number} ${part.label}`"
                    :available="handwritingAvailable && part.name === 'working'" :active="mode === 'handwrite' && field === part.name && part.name === 'working'" :read-only="readOnly || part.name === 'answer'"
                    @update:model-value="typed(`${part.name}_ink`, $event)" @writing="closeKeyboard" />
            </div>
        </div>
        <Teleport to="body" :disabled="!mobile">
            <div v-if="opened && !readOnly" class="keypad-clearance" aria-hidden="true"></div>
            <div v-if="opened && !readOnly" ref="panel" class="answer-keyboard" role="region" aria-label="Maths keypad" @keydown.esc.prevent="closeKeyboard">
                <div class="keyboard-header"><strong>Question {{ number }} · {{ field === 'answer' ? 'Final answer' : 'Working' }}</strong><button v-if="field === 'working'" type="button" aria-label="New working line" @pointerdown.prevent @click="insert('\n')">↵ Line</button><button type="button" @click="closeKeyboard">Done</button></div>
                <div class="keyboard-tabs" role="group" aria-label="Choose keypad keys">
                    <button v-for="tab in tabs" :key="tab" type="button" :aria-pressed="page === tab" @pointerdown.prevent @click="page = tab">{{ tab }}</button>
                </div>
                <div class="maths-keypad">
                    <button v-for="key in visibleKeys" :key="key.name" type="button" :aria-label="key.name" @pointerdown.prevent @click="insert(key.value)">
                        <span v-if="key.label === 'Power'" class="power-icon" aria-hidden="true">x<sup>□</sup></span><span v-else-if="key.label === 'Fraction'" class="fraction-icon" aria-hidden="true"><span>□</span><span>□</span></span><span v-else>{{ key.symbol }}</span><small v-if="key.label">{{ key.label }}</small>
                    </button>
                </div>
                <div class="keyboard-footer">
                    <button type="button" class="space-key" aria-label="Space" @pointerdown.prevent @click="insert(' ')">Space</button>
                    <button type="button" aria-label="Left" @pointerdown.prevent @click="navigate('moveToPreviousChar')">← Left</button>
                    <button type="button" aria-label="Right" @pointerdown.prevent @click="navigate('moveToNextChar')">Right →</button>
                </div>
            </div>
        </Teleport>
        <p v-if="limitReached && !readOnly" class="error" role="alert">This field has reached its length limit.</p>
    </section>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { mathsKeys, numberKeys } from '../../utils/mathsKeypad.mjs';

import MathsEntryField from './MathsEntryField.vue';
import HandwritingPad from './HandwritingPad.vue';

const props = defineProps({ modelValue: { type: Object, required: true }, number: { type: Number, required: true }, readOnly: Boolean, handwritingAvailable: Boolean });
const emit = defineEmits(['update:modelValue']);
const field = ref('answer');
const workingMode = ref(props.modelValue.working_ink?.strokes?.length ? 'handwrite' : 'keypad');
const mode = computed(() => field.value === 'answer' ? 'keypad' : workingMode.value);
const parts = [
    { name: 'answer', label: 'final answer', maximum: 1000 },
    { name: 'working', label: 'working', maximum: 8000 },
];
const inputs = { answer: null, working: null };
const limitReached = ref(false);
const opened = ref(false);
const page = ref('123');
const tabs = ['123', 'Maths', 'Vars'];
const editor = ref(null), panel = ref(null);
const media = window.matchMedia('(max-width: 640px)');
const mobile = ref(media.matches);
const digits = numberKeys.map(value => ({ name: value === 'backspace' ? 'Delete previous character' : value === '.' ? 'Decimal point' : value, value, symbol: value === 'backspace' ? '⌫' : value, label: value === 'backspace' ? 'Delete' : value === '.' ? 'Decimal' : '' }));
const symbols = mathsKeys.filter(key => !['Space', 'Pi', 'Variable'].includes(key.label)).map(key => ({ ...key, name: key.label === 'Power' ? 'Power: x superscript' : `${key.label}: ${key.symbol}` }));
const variableKeys = ['π', 'x', 'y', 'a', 'b', 'c', 'm', 'n', 'p', 'q', 'r', 't'].map(value => ({
    name: value === 'π' ? 'Pi: π' : `Variable: ${value}`,
    symbol: value,
    value,
    label: value === 'π' ? 'Pi' : '',
}));
const visibleKeys = computed(() => page.value === '123' ? digits : page.value === 'Maths' ? symbols : variableKeys);

function typed(name, value) {
    if (!props.readOnly && name !== 'answer_ink') emit('update:modelValue', { ...props.modelValue, [name]: value });
}
async function keepFieldVisible() {
    await nextTick();
    await new Promise(requestAnimationFrame);
    if (!mobile.value || !panel.value) return;
    const input = inputs[field.value];
    const bottom = input?.getBoundingClientRect().bottom;
    const top = panel.value.getBoundingClientRect().top;
    if (bottom > top - 16) window.scrollBy({ top: bottom - top + 16, behavior: 'smooth' });
}
function activate(name) {
    field.value = name;
    if (props.readOnly || mode.value !== 'keypad') return;
    opened.value = true;
}
function closeKeyboard() { opened.value = false; }
function selectPart(name) {
    field.value = name;
    limitReached.value = false;
    if (opened.value) keepFieldVisible();
}
async function switchPart(event) {
    const tabs = event.currentTarget.parentElement;
    selectPart(field.value === 'answer' ? 'working' : 'answer');
    await nextTick();
    tabs.querySelector('[aria-selected="true"]')?.focus();
}
function selectMode(value) {
    if (field.value !== 'working') return;
    workingMode.value = value;
    limitReached.value = false;
    closeKeyboard();
}
async function insert(key) {
    if (props.readOnly) return;
    const input = inputs[field.value];
    input?.insert(key);
    if (key === '/' || key === '^') page.value = '123';
    await keepFieldVisible();
}
async function navigate(command) {
    const input = inputs[field.value];
    input?.navigate(command);
    await keepFieldVisible();
}
function outsidePress(event) {
    const path = event.composedPath();
    if (!path.includes(editor.value) && !path.includes(panel.value)) closeKeyboard();
}
function resized(event) { mobile.value = event.matches; }
watch(() => props.readOnly, value => { if (value) closeKeyboard(); });
onMounted(() => {
    document.addEventListener('click', outsidePress);
    media.addEventListener('change', resized);
});
onUnmounted(() => {
    document.removeEventListener('click', outsidePress);
    media.removeEventListener('change', resized);
});
</script>

<style scoped>
.answer-editor{display:grid;gap:10px;margin-top:20px;min-width:0;color:#172a44}
.response-sheet{overflow:hidden;border:1px solid #c7d4df;border-radius:10px;background:#fffefa;box-shadow:0 2px 5px #183b5908}
.response-parts{display:flex;border-bottom:1px solid #dce4eb;padding:0 12px;gap:16px;background:#f5f8fa}
.answer-editor .response-parts button{border:0;border-radius:0;padding:12px 4px;background:transparent;font-size:14px;color:#617083;border-bottom:2px solid transparent}
.answer-editor .response-parts button[aria-selected=true]{color:#183b59;border-bottom-color:#244f73;font-weight:700}
.entry-method{display:flex;gap:4px;padding:10px 14px;border-bottom:1px solid #e1e9ef}
.answer-editor .entry-method button{min-height:44px;padding:7px 14px;font-size:13px;border-color:transparent;background:transparent;color:#617083}
.answer-editor .entry-method button[aria-pressed=true]{background:#e7eef4;color:#183b59;border-color:#d2dfe9;font-weight:650}
.response-body{padding:14px;min-width:0}
.response-body :deep(.maths-entry),.response-body :deep(.maths-entry:focus-within){border:0;box-shadow:none;border-radius:0;background:transparent}
.response-body :deep(math-field){min-height:110px;border-radius:0;background:transparent;padding:12px 4px}
.answer-editor button,.answer-keyboard button{font:inherit;min-height:44px;padding:8px;border:1px solid #bac4d2;border-radius:8px;background:white;color:#172b45;cursor:pointer}
.answer-editor button:focus-visible,.answer-keyboard button:focus-visible{outline:3px solid #2563eb;outline-offset:2px}

.keypad-clearance{display:none}
.answer-keyboard{box-sizing:border-box;padding:10px;background:#edf2f9;color:#172a44;border:1px solid #c1cede;border-radius:12px;font-family:system-ui;min-width:0}
.keyboard-header{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px}
.keyboard-header strong{font-size:13px;color:#172a44}
.keyboard-header button{font-size:14px;font-weight:700}
.keyboard-tabs{display:flex;gap:6px;margin-bottom:6px}
.keyboard-tabs button{flex:1;font-size:14px}
.keyboard-tabs button[aria-pressed=true]{background:#254f86;color:white;border-color:#254f86}
.maths-keypad{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));grid-auto-rows:52px;gap:5px;min-height:109px}
.maths-keypad button{min-width:0;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2px}
.maths-keypad span{font-size:23px;font-weight:650;line-height:1.15}
.maths-keypad .power-icon{display:inline-flex;align-items:center;gap:2px;font-family:Georgia,serif;font-style:italic}.maths-keypad .power-icon sup{font:14px/1 system-ui;align-self:flex-start;color:#356ba2;background:#e6f0fb;border-radius:3px;padding:1px 2px}
.maths-keypad .fraction-icon{display:inline-flex;flex-direction:column;line-height:1;width:22px}
.maths-keypad .fraction-icon span{font:12px/13px system-ui;color:#356ba2;background:#e6f0fb;border-radius:3px;margin:1px 2px}
.maths-keypad .fraction-icon{position:relative;gap:3px}.maths-keypad .fraction-icon::after{content:"";position:absolute;left:0;right:0;top:50%;border-top:1.5px solid #356ba2}
.maths-keypad small{font-size:10px;line-height:1.3}
.keyboard-footer{display:grid;grid-template-columns:minmax(0,1.4fr) repeat(2,minmax(0,1fr));gap:6px;margin-top:6px}
.keyboard-footer button{min-width:0;font-size:14px;min-height:46px;padding:4px}
.keyboard-footer .space-key{font-size:14px;font-weight:700}
.entry-hint{font-size:12px;line-height:1.5;color:#526479;margin:8px 0}
.error{color:#8c2430;font-size:13px;margin:6px 0}
@media(max-width:640px){
    .answer-keyboard{position:fixed;z-index:1100;bottom:0;left:0;right:0;border-radius:14px 14px 0 0;padding:8px max(8px,env(safe-area-inset-right)) max(8px,env(safe-area-inset-bottom)) max(8px,env(safe-area-inset-left));box-shadow:0 -5px 24px #172a4426}
    .keypad-clearance{display:block;height:290px}
}
</style>
