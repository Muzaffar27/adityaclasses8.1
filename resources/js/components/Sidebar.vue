<template>
    <aside class="sidebar" :class="{ 'is-open': isOpen }">

        <!-- Logo + close -->
        <div class="is-flex is-justify-content-space-between is-align-items-center mb-6">
            <div class="has-text-white is-size-5 has-text-weight-bold">Aditya Classes</div>
            <button class="button is-ghost has-text-white is-hidden-desktop" @click="$emit('close')">✕</button>
        </div>

        <!-- Logged-in user info -->
        <div class="sidebar-user mb-5">
            <div class="sidebar-avatar">{{ userInitials }}</div>
            <div>
                <p class="sidebar-user-name">{{ user?.name }}</p>
                <p class="sidebar-user-role">{{ roleLabel }}</p>
            </div>
        </div>

        <!-- ─── GENERAL (everyone) ─── -->
        <p class="menu-label">General</p>
        <ul class="menu-list mt-2">
            <li>
                <router-link :to="{ name: 'home' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                    <HomeIcon class="icon" /> <span>Home</span>
                </router-link>
            </li>

        </ul>

        <!-- ─── STUDENT PORTAL (students only) ─── -->
        <template v-if="isStudent || isAdmin">
            <p class="menu-label">My Learning</p>
            <ul class="menu-list mt-2">
                <!-- <li>
                    <router-link :to="{ name: '' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                        <PlayCircleIcon class="icon" /> <span>My Courses</span>
                    </router-link>
                </li>
                <li>
                    <router-link :to="{ name: '' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                        <ArrowRightCircleIcon class="icon" /> <span>Continue Watching</span>
                    </router-link>
                </li>
                <li>
                    <router-link :to="{ name: '' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                        <MagnifyingGlassIcon class="icon" /> <span>Browse Courses</span>
                    </router-link>
                </li>
                <li>
                    <router-link :to="{ name: '' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                        <DocumentArrowDownIcon class="icon" /> <span>Study Materials</span>
                    </router-link>
                </li> -->
            </ul>
        </template>

        <!-- ─── TUTOR WORKSPACE (tutors only) ─── -->
        <template v-if="isTutor || isAdmin">
            <p class="menu-label">Courses</p>
            <ul class="menu-list mt-2">
                <!-- <li>
                    <router-link :to="{ name: '' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                        <PlayCircleIcon class="icon" /> <span>My Courses</span>
                    </router-link>
                </li>
                <li>
                    <router-link :to="{ name: '' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                        <CloudArrowUpIcon class="icon" /> <span>New Course</span>
                    </router-link>
                </li> -->

                <li>
                    <router-link :to="{ name: 'request-access' }" class="menu-link" active-class="is-active"
                        @click="$emit('close')">
                        <Squares2X2Icon class="icon" /> <span>Request Access</span>
                    </router-link>
                </li>
            </ul>

            <p class="menu-label">Students</p>
            <ul class="menu-list mt-2">
                <!-- <li>
                    <router-link :to="{ name: '' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                        <ClockIcon class="icon" />
                        <span>Access Requests</span>
                    </router-link>
                </li> -->
                <!-- <li>
                    <router-link :to="{ name: '' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                        <UserGroupIcon class="icon" /> <span>All Students</span>
                    </router-link>
                </li> -->
            </ul>
        </template>

        <!-- ─── ADMIN PANEL (admins only) ─── -->
        <template v-if="isAdmin">
            <p class="menu-label">Admin</p>
            <ul class="menu-list mt-2">
                <!-- <li>
                    <router-link :to="{ name: '' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                        <UserGroupIcon class="icon" /> <span>Users</span>
                    </router-link>
                </li>
                <li>
                    <router-link :to="{ name: '' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                        <PlayCircleIcon class="icon" /> <span>All Courses</span>
                    </router-link>
                </li>
                <li>
                    <router-link :to="{ name: '' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                        <ClipboardDocumentListIcon class="icon" /> <span>Enrollments</span>
                    </router-link>
                </li>
                <li>
                    <router-link :to="{ name: '' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                        <ClockIcon class="icon" /> <span>Activity Log</span>
                    </router-link>
                </li> -->
            </ul>
        </template>

        <!-- ─── BOTTOM: Settings + Logout ─── -->
        <div class="sidebar-bottom">
            <router-link :to="{ name: '' }" class="menu-link" @click="$emit('close')">
                <Cog6ToothIcon class="icon" /> <span>Settings</span>
            </router-link>
            <button class="menu-link sidebar-logout" @click="logout">
                <ArrowRightOnRectangleIcon class="icon" /> <span>Log out</span>
            </button>
        </div>

    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';// adjust path to your Pinia auth store

import {
    HomeIcon,
    Squares2X2Icon,
    PlayCircleIcon,
    DocumentArrowDownIcon,
    CloudArrowUpIcon,
    UserGroupIcon,
    Cog6ToothIcon,
    ArrowRightCircleIcon,
    MagnifyingGlassIcon,
    ClockIcon,
    ClipboardDocumentListIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline';

defineProps(['isOpen']);
defineEmits(['close']);

const router = useRouter();
const auth = useAuthStore();

// ── User info ──────────────────────────────────────────────────────────────
const user = computed(() => auth.user);
const userInitials = computed(() => {
    if (!user.value?.name) return '?';
    return user.value.name
        .split(' ')
        .slice(0, 2)
        .map(n => n[0].toUpperCase())
        .join('');
});

// ── Role helpers ───────────────────────────────────────────────────────────
// Assumes auth.user.role is one of: 'student' | 'tutor' | 'admin'
const isStudent = computed(() => user.value?.role === 'student');
const isTutor = computed(() => user.value?.role === 'tutor');
const isAdmin = computed(() => user.value?.role === 'admin');

const roleLabel = computed(() => ({
    student: 'Student',
    tutor: 'Tutor',
    admin: 'Administrator',
}[user.value?.role] ?? ''));

// ── Pending access requests badge (tutor only) ─────────────────────────────
// Pull this from your store; the store should fetch from /api/tutor/requests?status=pending
// const pendingCount = computed(() => auth.pendingRequestsCount ?? 0);

// ── Logout ─────────────────────────────────────────────────────────────────
async function logout() {
    await auth.logout();          // clears token/user in store + calls API
    router.push({ name: 'login' });
}

</script>

<style scoped>
/* ── User block ───────────────────────────────────────────────────────────── */
.sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: rgba(255, 255, 255, 0.07);
    border-radius: 8px;
}

.sidebar-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    flex-shrink: 0;
}

.sidebar-user-name {
    font-size: 13px;
    font-weight: 600;
    color: #fff;
    margin: 0;
    line-height: 1.3;
}

.sidebar-user-role {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.55);
    margin: 0;
    text-transform: capitalize;
}

/* ── Pending badge ────────────────────────────────────────────────────────── */
.sidebar-badge {
    margin-left: auto;
    background: #f14668;
    /* Bulma danger */
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    line-height: 1.4;
}

/* ── Logout button (matches menu-link look) ───────────────────────────────── */
.sidebar-logout {
    width: 100%;
    background: none;
    border: none;
    cursor: pointer;
    text-align: left;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.6);
}

.sidebar-logout:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.08);
}
</style>