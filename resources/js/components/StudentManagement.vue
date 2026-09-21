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
                        <input v-model="search" class="input" type="search"
                            placeholder="Search name, email, class or parent..." />
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
                            <th>Class</th>
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
                                    <span v-if="student.student_profile?.grade" class="student-grade">
                                        {{ student.student_profile.grade.name }}
                                    </span>
                                    <span v-else class="has-text-grey is-size-7">Not set</span>
                                </td>
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
                                        <button class="button profile-button" @click="openProfileEditor(student)">
                                            Edit Profile
                                        </button>

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
                                <td colspan="6" style="padding: 0;">
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

                        <span v-if="student.student_profile?.grade" class="student-grade ml-2">
                            {{ student.student_profile.grade.name }}
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
                        <button class="button profile-button" @click="openProfileEditor(student)">
                            Edit Profile
                        </button>

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

        <GlassModal v-model="profileModal" title="Student profile" size="wide" :closeOnOverlay="!profileSaving">
            <form class="student-profile-form" @submit.prevent="saveStudentProfile">
                <div class="profile-form-heading">
                    <div>
                        <strong>{{ editingStudent?.name }}</strong>
                        <p>Account, class and parent contact details</p>
                    </div>
                    <span>Student #{{ editingStudent?.id }}</span>
                </div>

                <div class="profile-form-grid">
                    <div class="profile-field">
                        <label for="student-name">Full name</label>
                        <input id="student-name" v-model.trim="profileForm.name" class="input" autocomplete="off" />
                        <p v-if="profileErrors.name" class="help is-danger">{{ profileErrors.name[0] }}</p>
                    </div>

                    <div class="profile-field">
                        <label for="student-email">Email address</label>
                        <input id="student-email" v-model.trim="profileForm.email" class="input" type="email"
                            inputmode="email" autocomplete="off" />
                        <p v-if="profileErrors.email" class="help is-danger">{{ profileErrors.email[0] }}</p>
                    </div>

                    <div class="profile-field">
                        <label for="student-grade">Class</label>
                        <div class="select is-fullwidth">
                            <select id="student-grade" v-model="profileForm.grade_id">
                                <option :value="null">Not set</option>
                                <option v-for="grade in grades" :key="grade.id" :value="grade.id">
                                    {{ grade.name }}
                                </option>
                            </select>
                        </div>
                        <p v-if="profileErrors.grade_id" class="help is-danger">{{ profileErrors.grade_id[0] }}</p>
                    </div>

                    <div class="profile-field">
                        <label for="academic-year">Academic year</label>
                        <input id="academic-year" v-model.trim="profileForm.academic_year" class="input"
                            inputmode="numeric" placeholder="e.g. 2026" />
                        <p v-if="profileErrors.academic_year" class="help is-danger">
                            {{ profileErrors.academic_year[0] }}
                        </p>
                    </div>

                    <div class="profile-field form-wide">
                        <label for="student-phone">Student phone <span>(optional)</span></label>
                        <input id="student-phone" v-model.trim="profileForm.student_phone" class="input" type="tel"
                            inputmode="tel" placeholder="e.g. 5 123 4567" />
                        <p v-if="profileErrors.student_phone" class="help is-danger">
                            {{ profileErrors.student_phone[0] }}
                        </p>
                    </div>

                    <div class="form-divider form-wide">
                        <span>Parent / guardian</span>
                    </div>

                    <div class="profile-field">
                        <label for="guardian-name">Full name</label>
                        <input id="guardian-name" v-model.trim="profileForm.guardian_name" class="input" />
                        <p v-if="profileErrors.guardian_name" class="help is-danger">
                            {{ profileErrors.guardian_name[0] }}
                        </p>
                    </div>

                    <div class="profile-field">
                        <label for="guardian-relationship">Relationship</label>
                        <input id="guardian-relationship" v-model.trim="profileForm.guardian_relationship" class="input"
                            placeholder="e.g. Mother, Father, Guardian" />
                        <p v-if="profileErrors.guardian_relationship" class="help is-danger">
                            {{ profileErrors.guardian_relationship[0] }}
                        </p>
                    </div>

                    <div class="profile-field form-wide">
                        <label for="guardian-phone">WhatsApp phone number</label>
                        <input id="guardian-phone" v-model.trim="profileForm.guardian_phone" class="input" type="tel"
                            inputmode="tel" placeholder="Parent's WhatsApp number" />
                        <p v-if="profileErrors.guardian_phone" class="help is-danger">
                            {{ profileErrors.guardian_phone[0] }}
                        </p>
                    </div>

                    <label class="consent-field form-wide">
                        <input v-model="profileForm.guardian_report_consent" type="checkbox" />
                        <span>
                            <strong>Parent approved WhatsApp report sharing</strong>
                            <small>Save consent now so reports can be shared safely in a later phase.</small>
                        </span>
                    </label>
                </div>
            </form>

            <template #footer>
                <button class="button is-light is-small" :disabled="profileSaving" @click="profileModal = false">
                    Cancel
                </button>
                <button class="button is-primary is-small has-text-white" :class="{ 'is-loading': profileSaving }"
                    :disabled="profileSaving" @click="saveStudentProfile">
                    Save profile
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
const grades = ref([]);
const profileModal = ref(false);
const profileSaving = ref(false);
const editingStudent = ref(null);
const profileErrors = ref({});
const profileForm = ref({
    name: '',
    email: '',
    grade_id: null,
    academic_year: '',
    student_phone: '',
    guardian_name: '',
    guardian_relationship: '',
    guardian_phone: '',
    guardian_report_consent: false,
});

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
            student.email?.toLowerCase().includes(keyword) ||
            student.student_profile?.grade?.name?.toLowerCase().includes(keyword) ||
            student.student_profile?.guardian_name?.toLowerCase().includes(keyword) ||
            student.student_profile?.guardian_phone?.toLowerCase().includes(keyword)
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

const fetchGrades = async () => {
    try {
        const { data } = await api.get('/grades');
        grades.value = data;
    } catch (error) {
        console.error('Failed to load grades', error);
    }
};

const openProfileEditor = (student) => {
    const profile = student.student_profile || {};
    editingStudent.value = student;
    profileErrors.value = {};
    profileForm.value = {
        name: student.name || '',
        email: student.email || '',
        grade_id: profile.grade_id || null,
        academic_year: profile.academic_year || '',
        student_phone: profile.student_phone || '',
        guardian_name: profile.guardian_name || '',
        guardian_relationship: profile.guardian_relationship || '',
        guardian_phone: profile.guardian_phone || '',
        guardian_report_consent: Boolean(profile.guardian_report_consent_at),
    };
    profileModal.value = true;
};

const saveStudentProfile = async () => {
    if (!editingStudent.value || profileSaving.value) return;

    profileSaving.value = true;
    profileErrors.value = {};

    try {
        const { data } = await api.put(
            `/students/${editingStudent.value.id}/profile`,
            profileForm.value
        );
        const index = students.value.findIndex(student => student.id === data.student.id);

        if (index !== -1) {
            students.value[index] = { ...students.value[index], ...data.student };
        }

        profileModal.value = false;
    } catch (error) {
        if (error.response?.status === 422) {
            profileErrors.value = error.response.data.errors;
        } else {
            await showAlert({
                title: 'Profile Update Failed',
                message: 'The student profile could not be saved. Please try again.',
            });
        }
    } finally {
        profileSaving.value = false;
    }
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

onMounted(() => Promise.all([fetchStudents(), fetchGrades()]));
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

.student-grade {
    background: rgba(56, 189, 248, 0.1);
    border: 1px solid rgba(56, 189, 248, 0.22);
    border-radius: 999px;
    color: #bae6fd;
    display: inline-flex;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.28rem 0.52rem;
    white-space: nowrap;
}

.profile-button {
    background: rgba(129, 140, 248, 0.12);
    border-color: rgba(129, 140, 248, 0.28);
    color: #c7d2fe;
}

.profile-button:hover,
.profile-button:focus {
    background: rgba(129, 140, 248, 0.2);
    border-color: rgba(129, 140, 248, 0.42);
    color: #fff;
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

.student-profile-form {
    color: #e2e8f0;
}

.profile-form-heading {
    align-items: center;
    background: rgba(99, 102, 241, 0.09);
    border: 1px solid rgba(129, 140, 248, 0.18);
    border-radius: 11px;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding: 0.75rem;
}

.profile-form-heading strong,
.profile-form-heading p {
    display: block;
}

.profile-form-heading strong {
    color: #fff;
    font-size: 0.86rem;
}

.profile-form-heading p,
.profile-form-heading > span {
    color: rgba(203, 213, 225, 0.56);
    font-size: 0.67rem;
}

.profile-form-grid {
    display: grid;
    gap: 0.85rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.profile-field label,
.form-divider span {
    color: rgba(226, 232, 240, 0.72);
    display: block;
    font-size: 0.69rem;
    font-weight: 750;
    margin-bottom: 0.38rem;
}

.profile-field label span {
    color: rgba(203, 213, 225, 0.42);
    font-weight: 600;
}

.profile-field .input,
.profile-field .select select {
    background: rgba(2, 6, 23, 0.42);
    border-color: rgba(148, 163, 184, 0.17);
    border-radius: 9px;
    color: #f8fafc;
    font-size: 16px;
    height: 42px;
}

.profile-field .input:focus,
.profile-field .select select:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14);
}

.profile-field .select:not(.is-multiple):not(.is-loading)::after {
    border-color: #a5b4fc;
}

.form-wide {
    grid-column: 1 / -1;
}

.form-divider {
    border-top: 1px solid rgba(148, 163, 184, 0.12);
    margin-top: 0.2rem;
    padding-top: 0.75rem;
}

.form-divider span {
    color: #a5b4fc;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.consent-field {
    align-items: flex-start;
    background: rgba(14, 165, 233, 0.07);
    border: 1px solid rgba(56, 189, 248, 0.16);
    border-radius: 11px;
    cursor: pointer;
    display: flex;
    gap: 0.7rem;
    padding: 0.75rem;
}

.consent-field input {
    accent-color: #6366f1;
    flex: 0 0 auto;
    height: 17px;
    margin-top: 0.12rem;
    width: 17px;
}

.consent-field strong,
.consent-field small {
    display: block;
}

.consent-field strong {
    color: #e0f2fe;
    font-size: 0.72rem;
}

.consent-field small {
    color: rgba(186, 230, 253, 0.56);
    font-size: 0.64rem;
    margin-top: 0.12rem;
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

    .profile-form-grid {
        grid-template-columns: 1fr;
    }

    .form-wide {
        grid-column: auto;
    }

    .profile-form-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 0.25rem;
    }

    .mobile-card .buttons .button {
        flex: 1 1 calc(50% - 0.4rem);
    }
}
</style>
