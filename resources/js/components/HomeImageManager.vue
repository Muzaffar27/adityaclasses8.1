<template>
    <div class="home-image-manager">
        <div class="manager-header mb-4">
            <div>
                <h1 class="title is-5 has-text-white mb-1">Homepage Images</h1>
                <p class="has-text-grey-light is-size-7">
                    Add or remove images used in the homepage slider.
                </p>
            </div>

            <label class="button is-primary has-text-white" :class="{ 'is-loading': uploading }">
                <input type="file" accept="image/*" hidden :disabled="uploading" @change="uploadImage">
                Add Image
            </label>
        </div>

        <div v-if="loading">
            <Loader />
        </div>

        <div v-else>
            <p v-if="!images.length" class="empty-state">
                No homepage images found.
            </p>

            <div v-else class="image-grid">
                <div v-for="image in images" :key="image.name" class="image-card">
                    <div class="image-preview">
                        <img :src="image.url" :alt="image.name">
                    </div>

                    <div class="image-meta">
                        <div>
                            <p class="image-name">{{ image.name }}</p>
                            <p class="is-size-7 has-text-grey-light">
                                {{ formatSize(image.size) }}
                            </p>
                        </div>

                        <div class="image-actions">
                            <label class="active-toggle">
                                <input type="checkbox" :checked="image.active" :disabled="savingName === image.name"
                                    @change="toggleImage(image, $event)">
                                <span>{{ image.active ? 'Shown' : 'Hidden' }}</span>
                            </label>

                            <button class="button is-small is-danger"
                                :class="{ 'is-loading': deletingName === image.name }"
                                :disabled="deletingName === image.name" @click="deleteImage(image)">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import Loader from './common/Loader.vue';
import { showAlert, showConfirm } from '../composables/dialog';

const images = ref([]);
const loading = ref(false);
const uploading = ref(false);
const deletingName = ref(null);
const savingName = ref(null);

async function fetchImages() {
    loading.value = true;

    try {
        const { data } = await api.get('/homepage-images');
        images.value = data;
    } catch (error) {
        console.error('Failed to load homepage images:', error);
        await showAlert({
            title: 'Images Failed',
            message: 'Could not load homepage images.',
        });
    } finally {
        loading.value = false;
    }
}

async function uploadImage(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) return;

    const formData = new FormData();
    formData.append('image', file);
    uploading.value = true;

    try {
        await api.post('/homepage-images', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        await fetchImages();
    } catch (error) {
        console.error('Failed to upload homepage image:', error);
        await showAlert({
            title: 'Upload Failed',
            message: 'Could not upload this image. Use JPG, PNG, or WebP under 8 MB.',
        });
    } finally {
        uploading.value = false;
    }
}

async function deleteImage(image) {
    const confirmed = await showConfirm({
        title: 'Delete Image',
        message: `Delete "${image.name}" from the homepage slider?`,
        confirmText: 'Delete',
        cancelText: 'Keep it',
    });

    if (!confirmed) return;

    deletingName.value = image.name;

    try {
        await api.delete(`/homepage-images/${encodeURIComponent(image.name)}`);
        images.value = images.value.filter(item => item.name !== image.name);
    } catch (error) {
        console.error('Failed to delete homepage image:', error);
        await showAlert({
            title: 'Delete Failed',
            message: 'Could not delete this image.',
        });
    } finally {
        deletingName.value = null;
    }
}

async function toggleImage(image, event) {
    const active = event.target.checked;
    savingName.value = image.name;

    try {
        await api.put(`/homepage-images/${encodeURIComponent(image.name)}`, { active });
        image.active = active;
    } catch (error) {
        console.error('Failed to update homepage image:', error);
        event.target.checked = image.active;
        await showAlert({
            title: 'Update Failed',
            message: 'Could not update this image.',
        });
    } finally {
        savingName.value = null;
    }
}

function formatSize(bytes) {
    if (!bytes) return '0 KB';

    const mb = bytes / 1024 / 1024;

    if (mb >= 1) {
        return `${mb.toFixed(1)} MB`;
    }

    return `${Math.round(bytes / 1024)} KB`;
}

onMounted(fetchImages);
</script>

<style scoped>
.home-image-manager {
    color: #fff;
}

.manager-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
}

.image-card {
    background: hsl(221, 14%, 9%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    overflow: hidden;
}

.image-preview {
    aspect-ratio: 16 / 9;
    background: rgba(255, 255, 255, 0.04);
}

.image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.image-meta {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.8rem;
}

.image-name {
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    max-width: 130px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.image-actions {
    display: flex;
    align-items: flex-end;
    flex-direction: column;
    gap: 0.5rem;
}

.active-toggle {
    align-items: center;
    color: rgba(255, 255, 255, 0.72);
    cursor: pointer;
    display: inline-flex;
    font-size: 0.75rem;
    gap: 0.35rem;
    white-space: nowrap;
}


.empty-state {
    color: rgba(255, 255, 255, 0.6);
    padding: 2rem;
    text-align: center;
}

@media (max-width: 768px) {
    .manager-header {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>
