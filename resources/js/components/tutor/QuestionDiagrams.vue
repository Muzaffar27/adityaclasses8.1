<template>
    <section class="question-diagrams" aria-label="Question diagrams">
        <p v-if="loading" role="status">Loading diagrams…</p>
        <p v-if="error" role="alert">The diagrams could not be loaded. <button type="button" @click="load">Retry diagrams</button></p>
        <figure v-for="(url, index) in urls" :key="url">
            <a :href="url" target="_blank" rel="noopener" :aria-label="`Open diagram ${index + 1} at full size`"><img :src="url" :alt="`Original question diagram ${index + 1}; check its labels against the PDF`" /></a>
            <figcaption>Original diagram {{ index + 1 }} · Open to zoom</figcaption>
        </figure>
    </section>
</template>
<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import api from '../../api';
const props = defineProps({ questionId: { type: String, required: true }, diagrams: { type: Array, required: true } });
const emit = defineEmits(['ready']);
const urls = ref([]), loading = ref(false), error = ref(false);
let serial = 0;
function clear() { urls.value.forEach(URL.revokeObjectURL); urls.value = []; }
async function load() {
    const current = ++serial;
    clear(); loading.value = true; error.value = false; emit('ready', false);
    const downloaded = [];
    try {
        for (let index = 0; index < props.diagrams.length; index++) {
            const { data } = await api.get(`/admin/question-reviews/${props.questionId}/diagrams/${index}`, { responseType: 'blob' });
            if (serial !== current) { downloaded.forEach(URL.revokeObjectURL); return; }
            if (data.type !== 'image/png') throw new Error('Invalid diagram response');
            const url = URL.createObjectURL(data);
            downloaded.push(url);
            const image = new Image(); image.src = url; await image.decode();
            if (serial !== current) { downloaded.forEach(URL.revokeObjectURL); return; }
        }
        urls.value = downloaded;
        emit('ready', true);
    } catch { downloaded.forEach(URL.revokeObjectURL); if (serial === current) error.value = true; }
    finally { if (serial === current) loading.value = false; }
}
watch(() => [props.questionId, props.diagrams], load, { immediate: true });
onBeforeUnmount(() => { serial++; clear(); });
</script>
<style scoped>
.question-diagrams { min-width:0; margin:10px 0; }figure { margin:0 0 12px; }img { display:block; max-width:100%; width:auto; max-height:460px; object-fit:contain; background:white; border-radius:8px; }figcaption { font-size:.8rem; color:var(--app-muted); margin-top:5px; }button { font:inherit; cursor:pointer; padding:8px 12px; border:1px solid var(--app-border); border-radius:8px; color:var(--app-text); background:var(--app-panel); }
</style>
