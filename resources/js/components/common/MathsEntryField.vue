<template>
    <div ref="host" class="maths-entry" @click.capture="$emit('activate')"></div>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { MathfieldElement, convertAsciiMathToLatex } from 'mathlive';
import 'mathlive/fonts.css';

// Vite bundles the fonts locally with the assessment screen.
MathfieldElement.fontsDirectory = null;
MathfieldElement.soundsDirectory = null;

const props = defineProps({
    modelValue: { type: String, required: true },
    label: { type: String, required: true },
    maximum: { type: Number, required: true },
    readOnly: Boolean,
});
const emit = defineEmits(['update:modelValue', 'activate', 'limit']);
const host = ref(null);
let mathfield;
let acceptedLatex = '';
let acceptedResponse = props.modelValue;

function decodeResponse(value) {
    // Standard LaTeX delimiters distinguish new structured entries from old text.
    if (value.startsWith('\\(') && value.endsWith('\\)')) return value.slice(2, -2);
    const lines = value.split('\n').map(line => {
        const words = line.match(/[A-Za-z]{2,}/g) ?? [];
        const prose = words.some(word => !['sqrt', 'pi', 'sin', 'cos', 'tan', 'log', 'ln'].includes(word.toLowerCase()));
        return convertAsciiMathToLatex(prose ? JSON.stringify(line) : line);
    });
    return lines.length > 1 ? `\\begin{gathered}${lines.join('\\\\')}\\end{gathered}` : lines[0];
}
function responseValue() {
    const latex = mathfield.getValue('latex');
    return latex.replace(/\\(?: |;|,)/g, '').trim() ? `\\(${latex}\\)` : '';
}
function changed() {
    if (props.readOnly) return;
    const value = responseValue();
    if (value.length > props.maximum) {
        mathfield.setValue(acceptedLatex, { silenceNotifications: true });
        emit('limit', true);
        return;
    }
    emit('limit', false);
    acceptedLatex = mathfield.getValue('latex');
    if (value === acceptedResponse) return;
    acceptedResponse = value;
    emit('update:modelValue', value);
}
function load(value) {
    acceptedResponse = value;
    mathfield.setValue(decodeResponse(value), { silenceNotifications: true });
    acceptedLatex = mathfield.getValue('latex');
    mathfield.position = mathfield.lastOffset;
}
function insert(key) {
    if (props.readOnly || !mathfield) return;
    mathfield.focus({ preventScroll: true });
    if (key === 'backspace') mathfield.executeCommand('deleteBackward');
    else if (key === '/') {
        const previous = mathfield.getValue([Math.max(0, mathfield.position - 1), mathfield.position], 'latex');
        let template = '\\frac{#@}{#?}';
        if (/^\\(?: |;|,)$/.test(previous)) template = '\\frac{#?}{#?}';
        mathfield.insert(template, { format: 'latex', selectionMode: 'placeholder' });
    }
    else if (key === '^') mathfield.insert(mathfield.lastOffset === 0 ? '\\placeholder{}^{\\placeholder{}}' : '^{#?}', { format: 'latex', selectionMode: 'placeholder' });
    else if (key === '\n') {
        if (!mathfield.getValue('latex').startsWith('\\begin{gathered}')) {
            mathfield.executeCommand('selectAll');
            mathfield.insert('\\begin{gathered}#0\\\\\\placeholder{}\\end{gathered}', { format: 'latex', selectionMode: 'placeholder' });
        } else mathfield.executeCommand('addRowAfter');
    } else {
        const symbols = { '√(': '\\sqrt{#?}', '×': '\\times', '÷': '\\div', '−': '-', 'π': '\\pi', '°': '\\degree', '%': '\\%', ' ': '\\;' };
        mathfield.insert(symbols[key] ?? key, { format: 'latex', selectionMode: key === '√(' ? 'placeholder' : 'after' });
    }
    changed();
}
function navigate(command) {
    if (props.readOnly || !mathfield) return;
    mathfield.focus({ preventScroll: true });
    mathfield.executeCommand(command);
}
watch(() => props.modelValue, value => {
    if (mathfield && value !== acceptedResponse) load(value);
});
function setReadOnly(value) {
    mathfield.readOnly = value;
    mathfield.placeholder = value ? '\\text{No answer entered}' : '\\text{Tap to answer}';
}
watch(() => props.readOnly, value => { if (mathfield) setReadOnly(value); });
onMounted(() => {
    mathfield = new MathfieldElement();
    mathfield.mathVirtualKeyboardPolicy = 'manual';
    mathfield.smartFence = false;
    mathfield.smartSuperscript = false;
    mathfield.setAttribute('aria-label', props.label);
    mathfield.setAttribute('inputmode', 'none');
    mathfield.addEventListener('input', changed);
    host.value.append(mathfield);
    setReadOnly(props.readOnly);
    mathfield.menuItems = [];
    load(props.modelValue);
});
onUnmounted(() => {
    mathfield?.removeEventListener('input', changed);
});
defineExpose({ insert, navigate, getBoundingClientRect: () => host.value.getBoundingClientRect() });
</script>

<style scoped>
.maths-entry{min-width:0;max-width:100%;border:1px solid #a9bdd3;border-radius:12px;background:#fff;box-shadow:inset 0 1px 3px #19365608;overflow:hidden}
.maths-entry:focus-within{border-color:#3975bc;box-shadow:0 0 0 3px #3975bc24}
.maths-entry :deep(math-field){display:block;box-sizing:border-box;width:100%;min-width:0;min-height:78px;margin:0;padding:16px 12px;border:0;border-radius:12px;font-size:27px;color:#183650;background:#fff;--caret-color:#2563a4;--selection-background-color:#dcecff;--placeholder-color:#407bb3;--contains-highlight-background-color:#edf5ff}
.maths-entry :deep(math-field::part(virtual-keyboard-toggle)),.maths-entry :deep(math-field::part(menu-toggle)){display:none}
</style>
