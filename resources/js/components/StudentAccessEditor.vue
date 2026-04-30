<template>
    <div class="student-access-editor">
        <div class="editor-header">
            <div>
                <h3 class="title is-6 mb-1">Course Access Details</h3>
                <p class="is-size-7 has-text-grey">
                    {{ student.name }} - {{ student.email }}
                </p>
            </div>

            <span class="tag is-dark-accent">
                {{ accessRecords.length }} record{{ accessRecords.length === 1 ? '' : 's' }}
            </span>
        </div>

        <div v-if="packageAccess.length" class="package-summary mb-4">
            <p class="label is-small mb-2">Packages</p>
            <div class="package-list">
                <div v-for="pkg in packageAccess" :key="pkg.id" class="package-pill">
                    <strong>{{ pkg.name }}</strong>
                    <span>{{ pkg.grade_name || 'Grade' }} - {{ pkg.subject_name || 'Subject' }}</span>
                    <small :class="pkg.status === 'full' ? 'has-text-success' : 'has-text-warning'">
                        {{ pkg.status === 'full' ? 'Full access' : `Partial ${pkg.matched_items}/${pkg.total_items}` }}
                    </small>
                </div>
            </div>
        </div>

        <div v-if="accessRecords.length" class="access-list">
            <div v-for="access in accessRecords" :key="access.id" class="access-row">
                <div class="access-main">
                    <div class="course-heading">
                        <strong>{{ access.grade?.name || 'Grade' }} - {{ access.subject?.name || 'Subject' }}</strong>
                        <button class="button is-danger is-small" :class="{ 'is-loading': workingId === access.id }"
                            :disabled="workingId === access.id" @click="removeAccess(access)">
                            Remove Access
                        </button>
                    </div>

                    <div class="is-flex is-align-items-center is-flex-wrap-wrap access-meta">
                        <span class="tag is-small" :class="statusClass(access.status)">
                            {{ access.status }}
                        </span>
                        <span class="is-size-7 has-text-grey">
                            {{ access.lessons?.length || 0 }} lesson{{ (access.lessons?.length || 0) === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <p class="is-size-7 has-text-grey mt-2">
                        Removing this access removes all lessons for this grade and subject.
                    </p>
                </div>

                <div class="buttons are-small access-actions">
                    <button v-if="access.status !== 'accepted'" class="button is-success" :class="{ 'is-loading': workingId === access.id }"
                        :disabled="workingId === access.id" @click="acceptAccess(access)">
                        Accept Request
                    </button>

                </div>
            </div>
        </div>

        <p v-else class="has-text-grey is-size-7">
            This student does not have any lesson access records yet.
        </p>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import api from '../api';

const props = defineProps({
    student: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['updated']);
const workingId = ref(null);

const accessRecords = computed(() => {
    return [...(props.student.lesson_access || [])].sort((a, b) => {
        return statusOrder(a.status) - statusOrder(b.status)
            || String(a.grade?.name || '').localeCompare(String(b.grade?.name || ''))
            || String(a.subject?.name || '').localeCompare(String(b.subject?.name || ''));
    });
});

const packageAccess = computed(() => props.student.package_access || []);

async function acceptAccess(access) {
    workingId.value = access.id;

    try {
        await api.post('/lesson-access/accept', { id: access.id });
        emit('updated');
    } catch (error) {
        console.error('Failed to update access:', error);
        alert('Could not update this access record. Please try again.');
    } finally {
        workingId.value = null;
    }
}

async function removeAccess(access) {
    const confirmed = window.confirm(`Remove access for ${access.grade?.name || 'this grade'} - ${access.subject?.name || 'this subject'}?`);

    if (!confirmed) return;

    workingId.value = access.id;

    try {
        await api.delete(`/lesson-access/${access.id}`);
        emit('updated');
    } catch (error) {
        console.error('Failed to remove access:', error);
        alert('Could not remove this access record. Please try again.');
    } finally {
        workingId.value = null;
    }
}

function statusOrder(status) {
    return {
        pending: 1,
        accepted: 2,
        refused: 3,
    }[status] || 4;
}

function statusClass(status) {
    return {
        pending: 'is-warning is-light',
        accepted: 'is-success is-light',
        refused: 'is-danger is-light',
    }[status] || 'is-light';
}
</script>

<style scoped>
.student-access-editor {
    background: hsl(221, 14%, 9%);
    border-left: 4px solid #4f46e5;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding: 1rem;
    color: #fff;
}

.editor-header,
.access-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.editor-header {
    margin-bottom: 1rem;
}

.editor-header .title,
.label {
    color: #fff;
}

.access-list,
.package-list {
    display: grid;
    gap: 0.6rem;
}

.access-row,
.package-pill {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 0.75rem;
}

.access-main,
.package-pill {
    display: grid;
    gap: 0.15rem;
}

.access-main {
    flex: 1;
    min-width: 0;
}

.course-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.access-meta {
    gap: 0.4rem;
}

.access-actions {
    justify-content: flex-end;
}

.package-pill span,
.package-pill small {
    color: rgba(255, 255, 255, 0.68);
    font-size: 0.75rem;
}

@media (max-width: 768px) {
    .editor-header,
    .access-row,
    .course-heading {
        align-items: stretch;
        flex-direction: column;
    }

    .access-actions {
        justify-content: flex-start;
    }
}
</style>
