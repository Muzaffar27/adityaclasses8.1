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

        <div v-if="packageAccess.length" class="package-card-grid mb-3">
            <div v-for="pkg in packageAccess" :key="pkg.id" class="access-card">
                <div class="access-card-header">
                    <div class="package-title">
                        <strong>{{ pkg.name }}</strong>
                        <span>{{ pkg.grade_name || 'Grade' }} - {{ pkg.subject_name || 'Subject' }}</span>
                    </div>

                    <span class="package-status" :class="pkg.status === 'full' ? 'is-full' : 'is-partial'">
                        {{ pkg.status === 'full' ? 'Full access' : `Partial ${pkg.matched_items}/${pkg.total_items}` }}
                    </span>
                </div>

                <div class="package-items">
                    <div v-for="item in pkg.items || []" :key="item.access_id" class="package-item-chip">
                        <div>
                            <strong>{{ item.subject_name || 'Subject' }}</strong>
                            <span>{{ item.grade_name || 'Grade' }} - {{ item.lesson_count || 0 }} lesson{{
                                (item.lesson_count || 0) === 1 ? '' : 's' }}</span>
                            <span class="expiry-chip" :class="{ 'is-expired': isExpired(item) }">
                                Exp: {{ formatExpiryDate(item.expires_at) }}
                            </span>
                        </div>

                        <button class="button is-danger is-small"
                            :class="{ 'is-loading': workingId === item.access_id }"
                            :disabled="workingId === item.access_id" @click="removeAccess(item)">
                            Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="standaloneAccessRecords.length" class="access-list">
            <div class="section-heading">
                <span class="label is-small mb-0">Other Access</span>
                <span class="section-count">{{ standaloneAccessRecords.length }}</span>
            </div>

            <div v-for="access in standaloneAccessRecords" :key="access.id" class="access-row">
                <div class="access-main">
                    <div class="course-heading">
                        <strong>{{ getAccessLabel(access) }}</strong>
                    </div>

                    <div class="access-meta">
                        <span class="tag is-small" :class="statusClass(access.status)">
                            {{ access.status }}
                        </span>
                        <span>
                            {{ access.lessons?.length || 0 }} lesson{{ (access.lessons?.length || 0) === 1 ? '' : 's' }}
                        </span>
                        <span class="expiry-chip" :class="{ 'is-expired': isExpired(access) }">
                            Exp: {{ formatExpiryDate(access.expires_at) }}
                        </span>
                        <!-- <span class="access-warning">
                            Removing access removes these lessons.
                        </span> -->
                    </div>
                </div>

                <div class="buttons are-small access-actions">
                    <button v-if="access.status !== 'accepted'" class="button is-success"
                        :class="{ 'is-loading': workingId === access.id }" :disabled="workingId === access.id"
                        @click="acceptAccess(access)">
                        Accept Request
                    </button>

                    <button class="button is-danger" :class="{ 'is-loading': workingId === access.id }"
                        :disabled="workingId === access.id" @click="removeAccess(access)">
                        Remove
                    </button>
                </div>
            </div>
        </div>

        <p v-if="!packageAccess.length && !standaloneAccessRecords.length" class="has-text-grey is-size-7">
            This student does not have any lesson access records yet.
        </p>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import api from '../api';
import { showAlert, showConfirm } from '../composables/dialog';

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

const packagedAccessIds = computed(() => {
    return new Set(
        packageAccess.value.flatMap(pkg => (pkg.items || []).map(item => item.access_id))
    );
});

const standaloneAccessRecords = computed(() => {
    return accessRecords.value.filter(access => !packagedAccessIds.value.has(access.id));
});

async function acceptAccess(access) {
    workingId.value = access.id;

    try {
        await api.post('/lesson-access/accept', { id: access.id });
        emit('updated');
    } catch (error) {
        console.error('Failed to update access:', error);
        await showAlert({
            title: 'Update Failed',
            message: 'Could not update this access record. Please try again.',
        });
    } finally {
        workingId.value = null;
    }
}

async function removeAccess(access) {
    const confirmed = await showConfirm({
        title: 'Remove Access',
        message: `Remove access for ${getAccessLabel(access)}?`,
        confirmText: 'Remove',
        cancelText: 'Keep access',
    });

    if (!confirmed) return;

    const accessId = access.id || access.access_id;
    workingId.value = accessId;

    try {
        await api.delete(`/lesson-access/${accessId}`);
        emit('updated');
    } catch (error) {
        console.error('Failed to remove access:', error);
        await showAlert({
            title: 'Remove Failed',
            message: 'Could not remove this access record. Please try again.',
        });
    } finally {
        workingId.value = null;
    }
}

function getAccessLabel(access) {
    const grade = access.grade?.name || access.grade_name || 'this grade';
    const subject = access.subject?.name || access.subject_name || 'this subject';

    return `${grade} - ${subject}`;
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

function formatExpiryDate(value) {
    if (!value) return 'No expiry';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

function isExpired(access) {
    return access?.status === 'accepted'
        && access?.expires_at
        && new Date(access.expires_at).getTime() < Date.now();
}
</script>

<style scoped>
.student-access-editor {
    background: hsl(221, 14%, 9%);
    border-left: 4px solid #4f46e5;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding: 0.85rem;
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
    margin-bottom: 0.75rem;
}

.editor-header .title,
.label {
    color: #fff;
}

.section-heading {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.45rem;
}

.section-count {
    align-items: center;
    background: rgba(79, 70, 229, 0.18);
    border: 1px solid rgba(99, 102, 241, 0.28);
    border-radius: 999px;
    color: #c7d2fe;
    display: inline-flex;
    font-size: 0.68rem;
    font-weight: 700;
    height: 18px;
    justify-content: center;
    min-width: 18px;
    padding: 0 0.4rem;
}

.access-list {
    display: grid;
    gap: 0.45rem;
}

.package-card-grid {
    display: grid;
    gap: 0.65rem;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}

.access-card,
.access-row {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
}

.access-card {
    padding: 0.65rem;
}

.access-card-header {
    align-items: flex-start;
    display: flex;
    gap: 0.75rem;
    justify-content: space-between;
    margin-bottom: 0.55rem;
}

.access-row {
    align-items: center;
    padding: 0.55rem 0.65rem;
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

.course-heading strong {
    color: #fff;
    font-size: 0.86rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.access-meta {
    align-items: center;
    color: rgba(255, 255, 255, 0.62);
    display: flex;
    flex-wrap: wrap;
    font-size: 0.73rem;
    gap: 0.4rem;
    margin-top: 0.18rem;
}

.access-warning {
    color: rgba(248, 113, 113, 0.82);
}

.expiry-chip {
    align-items: center;
    background: rgba(251, 113, 133, 0.12);
    border: 1px solid rgba(251, 113, 133, 0.34);
    border-radius: 999px;
    color: #fecaca;
    display: inline-flex;
    font-size: 0.66rem;
    font-weight: 800;
    line-height: 1;
    padding: 0.22rem 0.42rem;
    white-space: nowrap;
}

.expiry-chip.is-expired {
    background: rgba(248, 113, 113, 0.2);
    border-color: rgba(248, 113, 113, 0.48);
    color: #fca5a5;
}

.access-actions {
    flex-shrink: 0;
    justify-content: flex-end;
    margin-bottom: 0 !important;
}

.access-actions .button {
    height: 28px;
    padding-left: 0.6rem;
    padding-right: 0.6rem;
}

.package-title {
    min-width: 0;
}

.package-title strong {
    color: #fff;
    display: block;
    font-size: 0.86rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.package-title span {
    color: rgba(255, 255, 255, 0.68);
    display: block;
    font-size: 0.72rem;
    line-height: 1.2;
    margin-top: 0.12rem;
}

.package-status {
    border-radius: 999px;
    flex-shrink: 0;
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1.1;
    padding: 0.28rem 0.5rem;
    white-space: nowrap;
}

.package-status.is-full {
    background: rgba(72, 199, 116, 0.14);
    border: 1px solid rgba(72, 199, 116, 0.28);
    color: #86efac;
}

.package-status.is-partial {
    background: rgba(255, 221, 87, 0.14);
    border: 1px solid rgba(255, 221, 87, 0.28);
    color: #fde68a;
}

.package-items {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.package-item-chip {
    align-items: center;
    background: rgba(15, 23, 42, 0.58);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    display: inline-flex;
    gap: 0.55rem;
    max-width: 100%;
    min-width: 215px;
    padding: 0.4rem 0.45rem;
}

.package-item-chip div {
    min-width: 0;
}

.package-item-chip strong,
.package-item-chip span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.package-item-chip strong {
    color: #fff;
    font-size: 0.74rem;
}

.package-item-chip span {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.68rem;
}

.package-item-chip .button {
    flex-shrink: 0;
    height: 26px;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

@media (max-width: 768px) {

    .editor-header,
    .access-row {
        align-items: stretch;
        flex-direction: column;
    }

    .package-card-grid {
        grid-template-columns: 1fr;
    }

    .access-card-header {
        flex-direction: column;
        gap: 0.45rem;
    }

    .package-item-chip {
        width: 100%;
    }

    .course-heading strong {
        white-space: normal;
    }

    .access-actions {
        justify-content: flex-start;
    }
}
</style>
