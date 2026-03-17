<template>
    <aside class="sidebar" :class="{ 'is-open': isOpen }">
        <div class="is-flex is-justify-content-space-between is-align-items-center mb-6">
            <div class="has-text-white is-size-5 has-text-weight-bold">Aditya Classes</div>
            <button class="button is-ghost has-text-white is-hidden-desktop" @click="$emit('close')">✕</button>
        </div>

        <p class="menu-label">General</p>
        <ul class="menu-list mt-2">
            <li>
                <router-link :to="{ name: 'home' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                    <HomeIcon class="icon" /> <span>Home Page</span>
                </router-link>
            </li>
            <li>
                <router-link :to="{ name: 'dashboard' }" class="menu-link" active-class="is-active"
                    @click="$emit('close')">
                    <Squares2X2Icon class="icon" /> <span>Dashboard</span>
                </router-link>
            </li>
        </ul>

        <p class="menu-label">Student Portal</p>
        <ul class="menu-list mt-2">
            <li>
                <router-link :to="{ name: 'dashboard' }" class="menu-link" active-class="is-active"
                    @click="$emit('close')">
                    <PlayCircleIcon class="icon" /> <span>My Courses</span>
                </router-link>
            </li>
            <li>
                <router-link :to="{ name: 'dashboard' }" class="menu-link" active-class="is-active"
                    @click="$emit('close')">
                    <DocumentArrowDownIcon class="icon" /> <span>Study Materials</span>
                </router-link>
            </li>
        </ul>

        <p class="menu-label">Tutor Workspace</p>
        <ul class="menu-list mt-2">
            <li>
                <router-link :to="{ name: 'dashboard' }" class="menu-link" active-class="is-active"
                    @click="$emit('close')">
                    <CloudArrowUpIcon class="icon" /> <span>Upload / Manage</span>
                </router-link>
            </li>
            <li>
                <router-link :to="{ name: 'dashboard' }" class="menu-link" active-class="is-active"
                    @click="$emit('close')">
                    <UserGroupIcon class="icon" /> <span>Students</span>
                </router-link>
            </li>
        </ul>

        <div class="mt-auto pt-5">
            <router-link :to="{ name: 'dashboard' }" class="menu-link">
                <Cog6ToothIcon class="icon" /> <span>Settings</span>
            </router-link>
        </div>
    </aside>
</template>

<script setup>
import {
    HomeIcon,
    Squares2X2Icon,
    PlayCircleIcon,
    DocumentArrowDownIcon,
    CloudArrowUpIcon,
    UserGroupIcon,
    Cog6ToothIcon
} from '@heroicons/vue/24/outline';

defineProps(['isOpen']);
defineEmits(['close']);
</script>