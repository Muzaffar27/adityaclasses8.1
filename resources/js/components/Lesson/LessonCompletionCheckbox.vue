<template>
    <div class="completion-control" @click.stop>
        <label :class="{ completed, saving }">
            <input type="checkbox" :checked="completed" :disabled="completed || saving" @change="completeLesson">
            <span>{{ saving ? 'Saving completion…' : completed ? `${videoLabel} completed` : `Mark ${videoLabel.toLowerCase()} completed` }}</span>
        </label>
        <p v-if="error" role="alert">{{ error }} <button type="button" @click="completeLesson">Retry</button></p>
    </div>
</template>
<script setup>
import { computed, ref } from 'vue';
import api from '../../api';
const props = defineProps({ lesson: { type: Object, required: true }, videoType: { type: String, default: 'lesson' } });
const emit = defineEmits(['completed']);
const videoLabel = computed(() => props.videoType === 'answer' ? 'Answer video' : 'Lesson video');
const completed = computed(() => props.lesson.progress?.[`${props.videoType}_video`]?.status === 'completed');
const saving = ref(false), error = ref('');
async function completeLesson(event) {
    // Completion is reflected only after the server confirms it.
    if (event?.target?.type === 'checkbox') event.target.checked = completed.value;
    if (saving.value || completed.value) return;
    saving.value = true; error.value = '';
    try {
        const { data } = await api.put(`/lesson-progress/${props.lesson.id}/completion`, { video_type: props.videoType, video_url: props.videoType === 'answer' ? props.lesson.answer_vimeo_url : props.lesson.vimeo_url });
        emit('completed', data.progress);
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not save completion. Please try again.';
    } finally { saving.value = false; }
}
</script>
<style scoped>
.completion-control { padding:10px 14px; border-top:1px solid var(--app-border); }
label { display:flex; gap:10px; align-items:center; min-height:30px; color:var(--app-muted,#a8b3c7); font-size:.8rem; cursor:pointer; }
input:focus-visible { outline:2px solid #a5b4fc; outline-offset:3px; }
input:disabled { cursor:default; }
.completed { color:#6ee7b7; cursor:default; }.saving { opacity:.7; }
p { margin:5px 0 0; font-size:.75rem; color:#fca5a5; overflow-wrap:anywhere; }
button { background:none; border:0; color:#c7d2fe; text-decoration:underline; cursor:pointer; font:inherit; }
</style>
