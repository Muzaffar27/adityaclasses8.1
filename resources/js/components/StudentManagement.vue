<template>
    <component :is="embedded ? 'div' : Layout" title="Student Administration" :loading="loading">

        <div v-if="embedded && loading">
            <Loader />
        </div>

        <template v-else>
            <!-- HEADER -->
            <div class="admin-header">
                <div>
                    <p class="has-text-grey">Manage students in the system</p>
                </div>
            </div>

            <div v-if="students.length > 0" class="mb-3 has-text-grey is-size-7">
                Total students: <strong>{{ students.length }}</strong>
            </div>

            <div class="student-filters mb-4">
                <div class="field mb-0 search-field">
                    <div class="control">
                        <input v-model="search" class="input" type="text" placeholder="Search student by name or email..." />
                    </div>
                </div>

                <div class="access-filter">
                    <button class="filter-btn" :class="{ active: accessFilter === 'all' }" @click="accessFilter = 'all'">
                        All
                    </button>
                    <button class="filter-btn has-expired" :class="{ active: accessFilter === 'expired' }"
                        @click="accessFilter = 'expired'">
                        Expired Access
                    </button>
                </div>
            </div>

            <!-- ===================== -->
            <!-- DESKTOP TABLE -->
            <!-- ===================== -->
            <div class="table-wrapper is-hidden-mobile">

                <table class="table is-fullwidth is-hoverable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Packages</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <template v-for="student in paginatedData" :key="student.id">
                            <tr>
                                <td>
                                    <strong>{{ student.name }}</strong>
                                </td>
                                <td>{{ student.email }}</td>
                                <td>
                                    <div v-if="packageAccess(student).length" class="package-list">
                                        <div v-for="pkg in visiblePackages(student)" :key="pkg.id" class="package-pill">
                                            <strong>{{ pkg.name }}</strong>
                                            <small :class="pkg.status === 'full' ? 'has-text-success' : 'has-text-warning'">
                                                {{ pkg.status === 'full' ? 'Full' : `${pkg.matched_items}/${pkg.total_items}` }}
                                            </small>
                                        </div>
                                        <button v-if="hiddenPackageCount(student) > 0" class="package-more" @click="toggleStudent(student.id)">
                                            +{{ hiddenPackageCount(student) }} more
                                        </button>
                                    </div>
                                    <span v-else class="has-text-grey is-size-7">No package access</span>
                                </td>
                                <td>{{ formatDate(student.created_at) }}</td>
                                <td>
                                    <div class="buttons are-small">
                                        <button class="button is-info" @click="toggleStudent(student.id)">
                                            {{ expandedStudentId === student.id ? 'Close' : 'Manage Access' }}
                                        </button>

                                        <button class="button is-warning" @click="resetPassword(student)">
                                            Reset Password
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="expandedStudentId === student.id" class="edit-row-active">
                                <td colspan="5" style="padding: 0;">
                                    <StudentAccessEditor :student="student" @updated="onAccessUpdated" />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

            </div>

            <!-- ===================== -->
            <!-- MOBILE CARDS -->
            <!-- ===================== -->
            <div class="is-hidden-tablet">

                <div v-for="student in paginatedData" :key="student.id" class="mobile-card glass-card">

                    <div class="card-content">
                        <strong>{{ student.name }}</strong>

                        <p class="is-size-7 has-text-grey mt-1">
                            {{ student.email }}
                        </p>

                        <span class="tag is-dark-accent mt-2">
                            {{ formatDate(student.created_at) }}
                        </span>

                    <div class="mt-3">
                        <p class="is-size-7 has-text-weight-semibold">Packages</p>
                        <div v-if="packageAccess(student).length" class="package-list mt-1">
                            <div v-for="pkg in visiblePackages(student)" :key="pkg.id" class="package-pill">
                                <strong>{{ pkg.name }}</strong>
                                <small :class="pkg.status === 'full' ? 'has-text-success' : 'has-text-warning'">
                                    {{ pkg.status === 'full' ? 'Full' : `${pkg.matched_items}/${pkg.total_items}` }}
                                </small>
                            </div>
                            <button v-if="hiddenPackageCount(student) > 0" class="package-more" @click="toggleStudent(student.id)">
                                +{{ hiddenPackageCount(student) }} more
                            </button>
                        </div>
                        <p v-else class="is-size-7 has-text-grey">No package access</p>
                    </div>

                    <div class="buttons are-small mt-3">
                        <button class="button is-info" @click="toggleStudent(student.id)">
                            {{ expandedStudentId === student.id ? 'Close' : 'Manage Access' }}
                        </button>

                        <button class="button is-warning" @click="resetPassword(student)">
                            Reset Password
                        </button>
                    </div>

                    <div v-if="expandedStudentId === student.id" class="mt-3">
                        <StudentAccessEditor :student="student" @updated="onAccessUpdated" />
                    </div>
                    </div>

                </div>

            </div>

            <div v-if="totalPages > 1" class="pagination-bar mt-5 is-flex is-align-items-center is-justify-content-center">

                <button class="pagination-btn" @click="prevPage" :disabled="currentPage === 1">
                    ‹
                </button>

                <div class="pagination-pages">
                    <template v-for="(page, index) in visiblePages" :key="index">

                        <span v-if="page === '...'" class="pagination-ellipsis">
                            ...
                        </span>

                        <button v-else class="page-pill" :class="{ active: currentPage === page }" @click="goToPage(page)">
                            {{ page }}
                        </button>

                    </template>
                </div>

                <button class="pagination-btn" @click="nextPage" :disabled="currentPage === totalPages">
                    ›
                </button>

            </div>

        <GlassModal v-model="confirmModal" type="confirm" title="⚠️ Confirm Reset"
            :message="`Are you sure you want to reset the password for '${pendingStudent?.name}' ?`"
            confirmText="Yes, Reset" cancelText="No, Keep it" :loading="isResetting" @confirm="confirmResetPassword" />

        <GlassModal v-model="passwordModal" title="🔐 Password Reset Successful">

            <div>
                <p class="mb-2 has-text-grey">
                    A new temporary password has been generated for:
                </p>

                <strong class="is-size-6">
                    {{ selectedStudent?.name }}
                </strong>

                <div class="password-box mt-3">
                    {{ newPassword }}
                </div>

                <p class="is-size-7 has-text-grey mt-2">
                    ⚠️ Please make sure the student updates their password after login.
                </p>
            </div>

            <!-- SUCCESS MESSAGE -->
            <p v-if="copiedText" class="has-text-success mt-2">
                ✔ Password copied to clipboard
            </p>

            <template #footer>
                <button class="button is-dark is-small" @click="copyPassword">
                    Copy password
                </button>

                <button class="button is-light is-small" @click="passwordModal = false">
                    Done
                </button>
            </template>

        </GlassModal>

        <!-- EMPTY -->
            <div v-if="!loading && students.length === 0" class="empty-state">
                No students found.
            </div>
        </template>

    </component>
</template>

<script setup>
import { ref, onMounted, nextTick, watch, computed } from 'vue';
import Layout from './common/Layout.vue';
import api from '../api';
import { Pagination } from '../composables/pagination';
import GlassModal from './common/GlassModal.vue';
import Loader from './common/Loader.vue';
import StudentAccessEditor from './StudentAccessEditor.vue';
import { showAlert } from '../composables/dialog';

defineProps({
    embedded: {
        type: Boolean,
        default: false
    }
});

const students = ref([]);
const search = ref("");
const accessFilter = ref("all");

const confirmModal = ref(false);
const pendingStudent = ref(null);
const passwordModal = ref(false);
const selectedStudent = ref(null);
const newPassword = ref("");

const copiedText = ref(false);

const isResetting = ref(false);
const expandedStudentId = ref(null);

const filteredStudents = computed(() => {
    let result = students.value;

    if (accessFilter.value === 'expired') {
        result = result.filter(hasExpiredAccess);
    }

    if (!search.value) return result;

    return result.filter((student) => {
        const keyword = search.value.toLowerCase();

        return (
            student.name?.toLowerCase().includes(keyword) ||
            student.email?.toLowerCase().includes(keyword)
        );
    });
});

const {
    currentPage,
    paginatedData,
    totalPages,
    visiblePages,
    goToPage,
    nextPage,
    prevPage,
} = Pagination(filteredStudents, 10, { type: 'flat' });

const loading = ref(false);

const fetchStudents = async () => {
    loading.value = true;

    try {
        const res = await api.get('/getStudents');
        students.value = res.data;

        await nextTick();

    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const resetPassword = (student) => {
    pendingStudent.value = student;
    confirmModal.value = true;
};

const toggleStudent = (studentId) => {
    expandedStudentId.value = expandedStudentId.value === studentId ? null : studentId;
};

const onAccessUpdated = async () => {
    await fetchStudents();
};

const packageAccess = (student) => {
    return student.package_access || [];
};

const visiblePackages = (student) => {
    return packageAccess(student).slice(0, 3);
};

const hiddenPackageCount = (student) => {
    return Math.max(packageAccess(student).length - visiblePackages(student).length, 0);
};

const hasExpiredAccess = (student) => {
    return (student.lesson_access || []).some(access => {
        return access.status === 'accepted'
            && access.expires_at
            && new Date(access.expires_at).getTime() < Date.now();
    });
};

const confirmResetPassword = async () => {
    if (!pendingStudent.value) return;

    isResetting.value = true;
    copiedText.value = false;

    try {
        const res = await api.post(
            `/students/${pendingStudent.value.id}/reset-password`
        );

        selectedStudent.value = pendingStudent.value;
        newPassword.value = res.data.password;

        confirmModal.value = false;
        passwordModal.value = true;

        isResetting.value = false;

    } catch (e) {
        console.error(e);
        await showAlert({
            title: "Reset Failed",
            message: "Failed to reset password. Please try again.",
        });
        isResetting.value = false;
    }
};

const copyPassword = () => {
    navigator.clipboard.writeText(newPassword.value);
    copiedText.value = true;

};

watch([search, accessFilter], () => {
    currentPage.value = 1;
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString();
};

onMounted(fetchStudents);
</script>

<style scoped>
/* reuse your lesson styles */
.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.table-wrapper {
    background: hsl(221, 14%, 9%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    overflow: hidden;
}

.student-filters {
    align-items: center;
    display: flex;
    gap: 0.75rem;
}

.search-field {
    flex: 1;
    min-width: 0;
}

.access-filter {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    display: inline-flex;
    flex: 0 0 auto;
    gap: 0.25rem;
    padding: 0.25rem;
}

.filter-btn {
    background: transparent;
    border: 0;
    border-radius: 6px;
    color: rgba(255, 255, 255, 0.68);
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 800;
    height: 30px;
    padding: 0 0.65rem;
}

.filter-btn.active {
    background: #4f46e5;
    color: #fff;
}

.filter-btn.has-expired.active {
    background: rgba(251, 113, 133, 0.18);
    color: #fecaca;
}

.table {
    background: transparent;
    color: rgba(255, 255, 255, 0.86);
}

.table thead th {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.08);
    color: #fff;
}

.table tbody td {
    border-color: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.82);
}

.table tbody tr:hover {
    background: rgba(99, 102, 241, 0.08);
}

.table strong,
.mobile-card strong,
.mobile-card .has-text-weight-semibold {
    color: #fff;
}

.tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.package-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.package-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.1rem;
    column-gap: 0.45rem;
    padding: 0.45rem 0.55rem;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.08);
    font-size: 0.75rem;
    max-width: 100%;
}

.package-pill strong {
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.package-more {
    background: rgba(79, 70, 229, 0.16);
    border: 1px solid rgba(99, 102, 241, 0.35);
    border-radius: 8px;
    color: #fff;
    cursor: pointer;
    font-size: 0.75rem;
    padding: 0.45rem 0.55rem;
}

.package-more:hover {
    background: rgba(79, 70, 229, 0.24);
}

.package-pill span,
.package-pill small {
    color: rgba(255, 255, 255, 0.68);
}

.mobile-card {
    background: hsl(221, 14%, 9%) !important;
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 14px;
    margin-bottom: 12px;
}

.empty-state {
    text-align: center;
    padding: 30px;
    color: #888;
}

@media (max-width: 768px) {
    .student-filters {
        align-items: stretch;
        flex-direction: column;
    }

    .access-filter {
        width: 100%;
    }

    .filter-btn {
        flex: 1;
    }
}
</style>
