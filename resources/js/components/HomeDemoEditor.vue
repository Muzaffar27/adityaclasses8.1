<template>
    <div class="home-demo-editor">
        <div class="editor-header mb-4">
            <div>
                <h1 class="title is-5 has-text-white mb-1">Demo Videos</h1>
                <p class="has-text-grey-light is-size-7">
                    Manage the lesson demo videos shown on the homepage.
                </p>
            </div>

            <button class="button is-primary has-text-white" :class="{ 'is-loading': saving }" :disabled="saving"
                @click="save">
                Save
            </button>
        </div>

        <div v-if="loading">
            <Loader />
        </div>

        <div v-else class="editor-grid">
            <div class="glass-card p-4">
                <div class="field">
                    <label class="custom-label">Demo heading</label>
                    <input v-model="content.demo.title" class="input custom-input" />
                </div>

                <div class="demo-editor-header mt-4">
                    <p class="form-section-title mb-0">Videos</p>
                    <button class="button is-small is-primary has-text-white" @click="addVideo">
                        Add Video
                    </button>
                </div>

                <div v-if="!content.demo.videos.length" class="empty-editor-state">
                    No demo videos added.
                </div>

                <div v-for="(video, index) in content.demo.videos" :key="video.id || index" class="demo-video-editor">
                    <div class="demo-video-editor-header">
                        <strong>Video {{ index + 1 }}</strong>
                        <button class="button is-small is-danger" @click="removeVideo(index)">
                            Remove
                        </button>
                    </div>

                    <div class="columns is-variable is-2">
                        <div class="column">
                            <label class="custom-label">Title</label>
                            <input v-model="video.title" class="input custom-input" />
                        </div>

                        <div class="column">
                            <label class="custom-label">Grade tag</label>
                            <input v-model="video.subject" class="input custom-input" />
                        </div>
                    </div>

                    <div class="field">
                        <label class="custom-label">Topic / subtitle</label>
                        <input v-model="video.meta" class="input custom-input" />
                    </div>

                    <div class="columns is-variable is-2">
                        <div class="column is-4">
                            <label class="custom-label">Icon</label>
                            <select v-model="video.emoji" class="input custom-input">
                                <option v-for="icon in subjectIcons" :key="icon.value" :value="icon.value">
                                    {{ icon.label }} - {{ icon.value }}
                                </option>
                            </select>
                        </div>

                        <div class="column">
                            <label class="custom-label">Row background</label>
                            <select v-model="video.backgroundClass" class="input custom-input">
                                <option value="">Default</option>
                                <option value="bg-indigo">Indigo</option>
                                <option value="bg-green">Green</option>
                                <option value="bg-pink">Pink</option>
                                <option value="bg-blue">Blue</option>
                                <option value="bg-purple">Purple</option>
                                <option value="bg-red">Red</option>
                                <option value="bg-cyan">Cyan</option>
                                <option value="bg-slate">Slate</option>
                            </select>
                        </div>
                    </div>

                    <div class="columns is-variable is-2">
                        <div class="column">
                            <label class="custom-label">Icon color</label>
                            <select v-model="video.colorClass" class="input custom-input">
                                <option value="cd-math">Indigo</option>
                                <option value="cd-sci">Green</option>
                                <option value="cd-pink">Pink</option>
                                <option value="cd-blue">Blue</option>
                                <option value="cd-purple">Purple</option>
                                <option value="cd-red">Red</option>
                                <option value="cd-cyan">Cyan</option>
                                <option value="cd-slate">Slate</option>
                            </select>
                        </div>

                        <div class="column">
                            <label class="custom-label">Tag color</label>
                            <select v-model="video.tagClass" class="input custom-input">
                                <option value="">Indigo</option>
                                <option value="green">Green</option>
                                <option value="pink">Pink</option>
                                <option value="blue">Blue</option>
                                <option value="purple">Purple</option>
                                <option value="red">Red</option>
                                <option value="cyan">Cyan</option>
                                <option value="slate">Slate</option>
                            </select>
                        </div>
                    </div>

                    <div class="field mb-0">
                        <label class="custom-label">Video URL</label>
                        <input v-model="video.video" class="input custom-input"
                            placeholder="https://player.vimeo.com/video/..." />
                    </div>
                </div>
            </div>

            <div class="preview-sticky">
                <div class="glass-card p-4 preview-card">
                <p class="form-section-title">Preview</p>
                <p class="section-label mb-0">{{ content.demo.title }}</p>

                <div class="demo-preview-list mt-3">
                    <div v-for="(video, index) in content.demo.videos" :key="video.id || index"
                        :class="['demo-preview-row', video.backgroundClass]">
                        <div :class="['demo-preview-dot', video.colorClass]">{{ video.emoji || '▶' }}</div>
                        <div>
                            <p>{{ video.title || 'Untitled video' }}</p>
                            <span>{{ video.meta || 'No topic set' }}</span>
                        </div>
                        <strong :class="video.tagClass">{{ video.subject || 'Grade' }}</strong>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import api from "../api";
import Loader from "./common/Loader.vue";
import { showAlert } from "../composables/dialog";

const defaults = {
    demo: {
        title: "Watch how lessons are explained before enrolling",
        videos: [],
    },
    footer: {},
};

const subjectIcons = [
    { label: "Play", value: "▶" },
    { label: "Lesson", value: "▣" },
    { label: "Formula", value: "∑" },
    { label: "Equation", value: "ƒ" },
    { label: "Geometry", value: "📐" },
    { label: "Calculator", value: "🧮" },
    { label: "Compass", value: "🧭" },
    { label: "Mechanism", value: "⚙" },
    { label: "Document", value: "🧾" },
    { label: "Notebook", value: "📓" },
    { label: "Growth", value: "📈" },
    { label: "Briefcase", value: "💼" },
    { label: "Target", value: "◎" },
    { label: "Star", value: "★" },
];

const content = ref(clone(defaults));
const loading = ref(false);
const saving = ref(false);

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

function mergeContent(data = {}) {
    return {
        ...defaults,
        ...data,
        demo: {
            ...defaults.demo,
            ...(data.demo || {}),
            videos: Array.isArray(data.demo?.videos) ? data.demo.videos : [],
        },
        footer: data.footer || {},
    };
}

function addVideo() {
    content.value.demo.videos.push({
        id: Date.now(),
        title: "",
        subject: "",
        meta: "",
        emoji: "▶",
        backgroundClass: "",
        colorClass: "cd-math",
        tagClass: "",
        video: "",
    });
}

function removeVideo(index) {
    content.value.demo.videos.splice(index, 1);
}

async function fetchContent() {
    loading.value = true;

    try {
        const { data } = await api.get("/homepage-content");
        content.value = mergeContent(data);
    } catch (error) {
        console.error("Failed to load demo content:", error);
        await showAlert({ title: "Content Failed", message: "Could not load demo content." });
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;

    try {
        await api.post("/homepage-content/save", content.value);
        await showAlert({ title: "Saved", message: "Demo videos saved successfully." });
    } catch (error) {
        console.error("Failed to save demo content:", error);
        await showAlert({ title: "Save Failed", message: "Could not save demo videos." });
    } finally {
        saving.value = false;
    }
}

onMounted(fetchContent);
</script>

<style scoped>
.home-demo-editor {
    color: #fff;
}

.editor-header,
.demo-editor-header,
.demo-video-editor-header {
    align-items: center;
    display: flex;
    gap: 10px;
    justify-content: space-between;
}

.editor-grid {
    align-items: start;
    display: grid;
    gap: 16px;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 0.8fr);
}

.form-section-title {
    color: #c7d2fe;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    margin: 0 0 12px;
    text-transform: uppercase;
}

.demo-video-editor {
    background: rgba(255, 255, 255, 0.035);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 12px;
    margin-top: 10px;
    padding: 12px;
}

.demo-video-editor-header {
    margin-bottom: 10px;
}

.demo-video-editor-header strong {
    color: #fff;
    font-size: 0.78rem;
}

.empty-editor-state {
    border: 1px dashed rgba(255, 255, 255, 0.14);
    border-radius: 12px;
    color: #94a3b8;
    font-size: 0.75rem;
    margin-top: 10px;
    padding: 14px;
    text-align: center;
}

.custom-label {
    color: #94a3b8;
    font-size: 0.7rem;
    margin-bottom: 4px;
}

.custom-input {
    background: rgba(15, 23, 42, 0.82);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #fff;
    font-size: 0.8rem;
}

.preview-sticky {
    align-self: start;
    max-height: calc(100vh - 120px);
    overflow-y: auto;
    position: sticky;
    top: 12px;
}

.preview-card {
    height: auto !important;
    min-height: 0;
}

.preview-card:hover {
    transform: none;
}

.section-label {
    align-items: center;
    color: #6366f1;
    display: flex;
    font-size: 0.68rem;
    font-weight: 700;
    gap: 8px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.section-label::after {
    background: rgba(99, 102, 241, 0.2);
    content: '';
    flex: 1;
    height: 1px;
}

.demo-preview-list {
    display: grid;
    gap: 8px;
}

.demo-preview-row {
    align-items: center;
    background: rgba(255, 255, 255, 0.035);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 12px;
    display: flex;
    gap: 10px;
    padding: 10px;
}

.demo-preview-row.bg-indigo {
    background: rgba(99, 102, 241, 0.07);
}

.demo-preview-row.bg-green {
    background: rgba(16, 185, 129, 0.06);
}

.demo-preview-row.bg-pink {
    background: rgba(236, 72, 153, 0.06);
}

.demo-preview-row.bg-blue {
    background: rgba(59, 130, 246, 0.07);
}

.demo-preview-row.bg-purple {
    background: rgba(139, 92, 246, 0.07);
}

.demo-preview-row.bg-red {
    background: rgba(239, 68, 68, 0.06);
}

.demo-preview-row.bg-cyan {
    background: rgba(6, 182, 212, 0.06);
}

.demo-preview-row.bg-slate {
    background: rgba(148, 163, 184, 0.06);
}

.demo-preview-dot {
    align-items: center;
    border-radius: 10px;
    display: flex;
    flex: 0 0 auto;
    height: 34px;
    justify-content: center;
    width: 34px;
}

.demo-preview-dot.cd-math {
    background: rgba(99, 102, 241, 0.15);
}

.demo-preview-dot.cd-sci {
    background: rgba(16, 185, 129, 0.12);
}

.demo-preview-dot.cd-pink {
    background: rgba(236, 72, 153, 0.12);
}

.demo-preview-dot.cd-blue {
    background: rgba(59, 130, 246, 0.13);
}

.demo-preview-dot.cd-purple {
    background: rgba(139, 92, 246, 0.14);
}

.demo-preview-dot.cd-red {
    background: rgba(239, 68, 68, 0.13);
}

.demo-preview-dot.cd-cyan {
    background: rgba(6, 182, 212, 0.13);
}

.demo-preview-dot.cd-slate {
    background: rgba(148, 163, 184, 0.13);
}

.demo-preview-row div:nth-child(2) {
    flex: 1;
    min-width: 0;
}

.demo-preview-row p {
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    margin: 0;
}

.demo-preview-row span {
    color: #94a3b8;
    display: block;
    font-size: 0.7rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.demo-preview-row strong {
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: 999px;
    color: #a5b4fc;
    flex: 0 0 auto;
    font-size: 0.62rem;
    padding: 3px 8px;
    text-transform: uppercase;
}

.demo-preview-row strong.green {
    background: rgba(16, 185, 129, 0.12);
    border-color: rgba(16, 185, 129, 0.25);
    color: #6ee7b7;
}

.demo-preview-row strong.pink {
    background: rgba(236, 72, 153, 0.12);
    border-color: rgba(236, 72, 153, 0.25);
    color: #f9a8d4;
}

.demo-preview-row strong.blue {
    background: rgba(59, 130, 246, 0.13);
    border-color: rgba(59, 130, 246, 0.26);
    color: #93c5fd;
}

.demo-preview-row strong.purple {
    background: rgba(139, 92, 246, 0.14);
    border-color: rgba(139, 92, 246, 0.28);
    color: #c4b5fd;
}

.demo-preview-row strong.red {
    background: rgba(239, 68, 68, 0.13);
    border-color: rgba(239, 68, 68, 0.26);
    color: #fca5a5;
}

.demo-preview-row strong.cyan {
    background: rgba(6, 182, 212, 0.13);
    border-color: rgba(6, 182, 212, 0.26);
    color: #67e8f9;
}

.demo-preview-row strong.slate {
    background: rgba(148, 163, 184, 0.12);
    border-color: rgba(148, 163, 184, 0.24);
    color: #cbd5e1;
}

@media (max-width: 900px) {
    .editor-grid {
        grid-template-columns: 1fr;
    }

    .preview-sticky {
        position: static;
    }
}

@media (max-width: 768px) {
    .editor-header {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>
