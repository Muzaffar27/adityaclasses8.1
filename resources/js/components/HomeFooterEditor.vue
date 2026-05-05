<template>
    <div class="home-footer-editor">
        <div class="editor-header mb-4">
            <div>
                <h1 class="title is-5 has-text-white mb-1">Footer Content</h1>
                <p class="has-text-grey-light is-size-7">
                    Edit the homepage footer details.
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
                    <label class="custom-label">Brand name</label>
                    <input v-model="content.footer.brandName" class="input custom-input" />
                </div>

                <div class="field">
                    <label class="custom-label">Description</label>
                    <textarea v-model="content.footer.description" class="textarea custom-input" rows="3"></textarea>
                </div>

                <div class="field">
                    <label class="custom-label">Subjects</label>
                    <input v-model="content.footer.subjects" class="input custom-input" />
                </div>

                <div class="columns is-variable is-2">
                    <div class="column">
                        <label class="custom-label">Phone</label>
                        <input v-model="content.footer.phone" class="input custom-input" />
                    </div>

                    <div class="column">
                        <label class="custom-label">Email</label>
                        <input v-model="content.footer.email" class="input custom-input" />
                    </div>
                </div>

                <div class="field">
                    <label class="custom-label">Location</label>
                    <input v-model="content.footer.location" class="input custom-input" />
                </div>

                <div class="columns is-variable is-2">
                    <div class="column">
                        <label class="custom-label">Tutor name</label>
                        <input v-model="content.footer.tutorName" class="input custom-input" />
                    </div>

                    <div class="column">
                        <label class="custom-label">Support text</label>
                        <input v-model="content.footer.supportText" class="input custom-input" />
                    </div>
                </div>
            </div>

            <div class="footer-preview">
                <div class="footer-preview-main">
                    <div class="footer-preview-brand">
                        <div class="footer-preview-logo">{{ footerInitial }}</div>
                        <div>
                            <p class="footer-preview-title">{{ content.footer.brandName }}</p>
                            <p class="footer-preview-copy">{{ content.footer.description }}</p>
                            <p class="footer-preview-meta">{{ content.footer.subjects }}</p>
                        </div>
                    </div>

                    <div class="footer-preview-details">
                        <div>
                            <span>Phone</span>
                            <p>{{ content.footer.phone }}</p>
                        </div>
                        <div>
                            <span>Email</span>
                            <p>{{ content.footer.email }}</p>
                        </div>
                        <div>
                            <span>Location</span>
                            <p>{{ content.footer.location }}</p>
                        </div>
                    </div>
                </div>

                <div class="footer-preview-bottom">
                    <span>{{ content.footer.tutorName }}</span>
                    <span>{{ content.footer.supportText }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import api from "../api";
import Loader from "./common/Loader.vue";
import { showAlert } from "../composables/dialog";

const defaults = {
    demo: {
        title: "",
        videos: [],
    },
    footer: {
        brandName: "Aditya Classes",
        description: "Structured private tuition for Grade 7 to HSC students in Mauritius.",
        subjects: "Maths · Add Maths · Accounting",
        phone: "+230 5947 3797",
        email: "adityaera22@mail.com",
        location: "Quartier-Militaire · Online",
        tutorName: "Mr. Aditya",
        supportText: "WhatsApp support within 24 hours",
    },
};

const content = ref(clone(defaults));
const loading = ref(false);
const saving = ref(false);

const footerInitial = computed(() => {
    return content.value.footer.brandName?.trim()?.charAt(0)?.toUpperCase() || "A";
});

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

function mergeContent(data = {}) {
    return {
        ...defaults,
        ...data,
        demo: data.demo || defaults.demo,
        footer: {
            ...defaults.footer,
            ...(data.footer || {}),
        },
    };
}

async function fetchContent() {
    loading.value = true;

    try {
        const { data } = await api.get("/homepage-content");
        content.value = mergeContent(data);
    } catch (error) {
        console.error("Failed to load footer content:", error);
        await showAlert({ title: "Content Failed", message: "Could not load footer content." });
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;

    try {
        await api.post("/homepage-content/save", content.value);
        await showAlert({ title: "Saved", message: "Footer content saved successfully." });
    } catch (error) {
        console.error("Failed to save footer content:", error);
        await showAlert({ title: "Save Failed", message: "Could not save footer content." });
    } finally {
        saving.value = false;
    }
}

onMounted(fetchContent);
</script>

<style scoped>
.home-footer-editor {
    color: #fff;
}

.editor-header {
    align-items: center;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
}

.editor-grid {
    align-items: start;
    display: grid;
    gap: 16px;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 0.8fr);
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

.footer-preview {
    background: rgba(255, 255, 255, 0.035);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-bottom: 0;
    border-radius: 18px 18px 0 0;
    overflow: hidden;
}

.footer-preview-main {
    display: grid;
    gap: 16px;
    padding: 18px;
}

.footer-preview-brand {
    align-items: flex-start;
    display: flex;
    gap: 12px;
}

.footer-preview-logo {
    align-items: center;
    background: rgba(99, 102, 241, 0.16);
    border: 1px solid rgba(99, 102, 241, 0.35);
    border-radius: 12px;
    color: #c7d2fe;
    display: flex;
    flex: 0 0 auto;
    font-weight: 800;
    height: 42px;
    justify-content: center;
    width: 42px;
}

.footer-preview-title {
    color: #fff;
    font-size: 0.95rem;
    font-weight: 800;
    margin: 0 0 6px;
}

.footer-preview-copy {
    color: rgba(203, 213, 225, 0.78);
    font-size: 0.75rem;
    line-height: 1.55;
    margin: 0;
}

.footer-preview-meta {
    color: #fcd34d;
    font-size: 0.7rem;
    font-weight: 700;
    margin: 8px 0 0;
}

.footer-preview-details {
    display: grid;
    gap: 10px;
}

.footer-preview-details div {
    border-left: 1px solid rgba(99, 102, 241, 0.16);
    padding-left: 12px;
}

.footer-preview-details span {
    color: #818cf8;
    display: block;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    margin-bottom: 4px;
    text-transform: uppercase;
}

.footer-preview-details p {
    color: rgba(203, 213, 225, 0.82);
    font-size: 0.73rem;
    margin: 0;
}

.footer-preview-bottom {
    background: rgba(99, 102, 241, 0.06);
    border-top: 1px solid rgba(255, 255, 255, 0.07);
    color: rgba(203, 213, 225, 0.72);
    display: flex;
    font-size: 0.68rem;
    gap: 12px;
    justify-content: space-between;
    padding: 10px 18px;
}

@media (max-width: 900px) {
    .editor-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .editor-header,
    .footer-preview-bottom {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>
