<template>
    <Layout title="Student Administration" :loading="loading">

        <!-- HEADER -->
        <div class="admin-header">
            <div>
                <p class="has-text-grey">Manage students in the system</p>
            </div>
        </div>

        <div v-if="students.length > 0" class="mb-3 has-text-grey is-size-7">
            Total students: <strong>{{ students.length }}</strong>
        </div>

        <div class="field mb-4">
            <div class="control">
                <input v-model="search" class="input" type="text" placeholder="Search student by name or email..." />
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
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="student in paginatedData" :key="student.id">
                        <td>
                            <strong>{{ student.name }}</strong>
                        </td>
                        <td>{{ student.email }}</td>
                        <td>{{ formatDate(student.created_at) }}</td>
                        <td>
                            <button class="button is-small is-warning" @click="resetPassword(student)">
                                Reset Password
                            </button>
                        </td>
                    </tr>
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

    </Layout>
</template>

<script setup>
import { ref, onMounted, nextTick, watch, computed } from 'vue';
import Layout from './common/Layout.vue';
import axios from 'axios';
import { Pagination } from '../composables/Pagination';
import GlassModal from './common/GlassModal.vue';

const students = ref([]);
const search = ref("");

const confirmModal = ref(false);
const pendingStudent = ref(null);
const passwordModal = ref(false);
const selectedStudent = ref(null);
const newPassword = ref("");

const copiedText = ref(false);

const isResetting = ref(false);

const filteredStudents = computed(() => {
    if (!search.value) return students.value;

    return students.value.filter((student) => {
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
        const res = await axios.get('/api/getStudents');
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

const confirmResetPassword = async () => {
    if (!pendingStudent.value) return;

    isResetting.value = true;
    copiedText.value = false;

    try {
        const res = await axios.post(
            `/api/students/${pendingStudent.value.id}/reset-password`
        );

        selectedStudent.value = pendingStudent.value;
        newPassword.value = res.data.password;

        confirmModal.value = false;
        passwordModal.value = true;

        isResetting.value = false;

    } catch (e) {
        console.error(e);
        alert("Failed to reset password. Please try again.");
    }
};

const copyPassword = () => {
    navigator.clipboard.writeText(newPassword.value);
    copiedText.value = true;

};

watch(search, () => {
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
    background: white;
    border-radius: 12px;
    overflow: hidden;
}

.mobile-card {
    padding: 14px;
    margin-bottom: 12px;
}

.empty-state {
    text-align: center;
    padding: 30px;
    color: #888;
}
</style>