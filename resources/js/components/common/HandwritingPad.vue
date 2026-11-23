<template>
    <section v-if="available || hasInk" class="handwriting-pad" :aria-label="`${label} handwriting area`">
        <template v-if="writing || hasInk">

            <canvas ref="canvas" :class="{ writing }" :aria-label="`${label} handwriting`" role="img"
                @pointerdown="startStroke" @pointermove="continueStroke" @pointerup="finishStroke" @pointercancel="finishStroke" @lostpointercapture="finishStroke"></canvas>
            <div v-if="writing" class="ink-tools">
                <button type="button" :disabled="!history.length" @click="undo">Undo stroke</button>
                <button type="button" :disabled="!hasInk" @click="clear">Clear</button>
            </div>
            <p v-if="message" class="ink-message" role="status">{{ message }}</p>
        </template>
    </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({ modelValue: { type: Object, default: null }, label: { type: String, required: true }, available: Boolean, active: Boolean, readOnly: Boolean });
const emit = defineEmits(['update:modelValue', 'writing']);
const canvas = ref(null);
const writing = computed(() => props.active && props.available && !props.readOnly);
const history = ref([]);
const message = ref('');
const strokes = computed(() => props.modelValue?.strokes ?? []);
const hasInk = computed(() => strokes.value.length > 0);
let observer;
let pointer = null;
let baseStrokes = [];
let activeStroke = [];
let frame = null;

function redraw() {
    if (!canvas.value) return;
    const rect = canvas.value.getBoundingClientRect();
    const scale = Math.min(window.devicePixelRatio || 1, 3);
    const width = Math.round(rect.width * scale);
    const height = Math.round(rect.height * scale);
    if (canvas.value.width !== width) canvas.value.width = width;
    if (canvas.value.height !== height) canvas.value.height = height;
    const context = canvas.value.getContext('2d');
    context.clearRect(0, 0, width, height);
    context.lineWidth = Math.max(2, rect.width * 0.008) * scale;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.strokeStyle = '#183b59';
    context.fillStyle = '#183b59';
    const visible = pointer === null ? strokes.value : [...baseStrokes, activeStroke];
    for (const stroke of visible) {
        if (!stroke.length) continue;
        context.beginPath();
        context.moveTo(stroke[0][0] * width, stroke[0][1] * height);
        for (const [x, y] of stroke.slice(1)) context.lineTo(x * width, y * height);
        context.stroke();
        if (stroke.length === 1) {
            context.beginPath();
            context.arc(stroke[0][0] * width, stroke[0][1] * height, context.lineWidth / 2, 0, 2 * Math.PI);
            context.fill();
        }
    }
}
function remember() {
    history.value = [...history.value.slice(-19), props.modelValue];
}
function update(value) { emit('update:modelValue', value); }
function publishStroke() {
    frame = null;
    if (pointer === null) return;
    update({ v: 1, strokes: [...baseStrokes, [...activeStroke]] });
    redraw();
}
function position(event) {
    const rect = canvas.value.getBoundingClientRect();
    return [event.clientX - rect.left, event.clientY - rect.top].map((value, index) =>
        Math.round(Math.max(0, Math.min(1, value / (index === 0 ? rect.width : rect.height))) * 10000) / 10000);
}
function startStroke(event) {
    if (!writing.value || props.readOnly || !props.available || pointer !== null || !event.isPrimary
        || (event.pointerType === 'mouse' && event.button !== 0)) return;
    const count = strokes.value.reduce((sum, stroke) => sum + stroke.length, 0);
    if (strokes.value.length >= 200 || count >= 5000) {
        message.value = 'This area is full. Undo a stroke or clear it to keep writing.';
        return;
    }
    event.preventDefault();
    emit('writing');
    remember();
    pointer = event.pointerId;
    baseStrokes = strokes.value;
    activeStroke = [position(event)];
    message.value = '';
    canvas.value.setPointerCapture(pointer);
    publishStroke();
}
function continueStroke(event) {
    if (event.pointerId !== pointer) return;
    event.preventDefault();
    const point = position(event);
    const previous = activeStroke.at(-1);
    const rect = canvas.value.getBoundingClientRect();
    if (Math.hypot((point[0] - previous[0]) * rect.width, (point[1] - previous[1]) * rect.height) < 1.5) return;
    const count = baseStrokes.reduce((sum, stroke) => sum + stroke.length, 0) + activeStroke.length;
    if (activeStroke.length >= 500 || count >= 5000) {
        message.value = 'Lift your finger or stylus to finish this stroke.';
        finishStroke(event);
        return;
    }
    activeStroke.push(point);
    if (frame === null) frame = requestAnimationFrame(publishStroke);
}
function finishStroke(event) {
    if (pointer === null || (event && event.pointerId !== pointer)) return;
    if (frame !== null) cancelAnimationFrame(frame);
    publishStroke();
    const previousPointer = pointer;
    pointer = null;
    if (canvas.value?.hasPointerCapture(previousPointer)) canvas.value.releasePointerCapture(previousPointer);
}
function undo() {
    finishStroke();
    update(history.value.at(-1) ?? null);
    history.value = history.value.slice(0, -1);
    message.value = '';
}
function clear() {
    finishStroke();
    remember();
    update(null);
    message.value = '';
}
watch(canvas, element => {
    observer?.disconnect();
    if (!element) return;
    observer = new ResizeObserver(redraw);
    observer.observe(element);
    redraw();
});
watch(() => props.modelValue, () => nextTick(redraw));
watch(writing, async value => {
    if (!value) finishStroke();
    message.value = '';
    if (value) {
        emit('writing');
        await nextTick();
        redraw();
        canvas.value?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
});
onBeforeUnmount(() => {
    finishStroke();
    observer?.disconnect();
});
</script>

<style scoped>
.handwriting-pad{display:grid;gap:8px;min-width:0;color:#183b59}
.ink-tools{display:flex;justify-content:flex-end;gap:6px;border-top:1px solid #e1e9ef;padding-top:8px}
.ink-tools button{font:inherit;font-size:13px;min-height:44px;border:1px solid #bac4d2;border-radius:8px;background:#fff;color:#183b59;padding:8px 12px;cursor:pointer}
.ink-tools button:disabled{opacity:.45;cursor:default}
.ink-tools button:focus-visible{outline:3px solid #3975bc;outline-offset:2px}
canvas{display:block;box-sizing:border-box;width:100%;aspect-ratio:4/3;border:0;border-radius:0;background:repeating-linear-gradient(to bottom,#fff 0,#fff 31px,#e7eff7 31px,#e7eff7 32px);touch-action:pan-y}
canvas.writing{touch-action:none;cursor:crosshair}
.ink-message{font-size:12px;line-height:1.5;margin:0;color:#526479}
</style>
