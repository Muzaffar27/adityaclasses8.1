<template>
    <div v-if="isOpen" class="sidebar-overlay is-hidden-desktop" @click="$emit('close')"></div>

    <aside class="sidebar" :class="{ 'is-open': isOpen }">
        <div class="is-flex is-justify-content-space-between is-align-items-center mb-5">
            <p class="menu-label has-text-white mb-0">General</p>
            <button class="button is-ghost has-text-white is-hidden-desktop" @click="$emit('close')">
                ✕
            </button>
        </div>

        <ul class="menu-list ml-3">
            <li>
                <router-link :to="{ name: 'home' }" class="menu-link" active-class="is-active" @click="$emit('close')">
                    <HomeIcon class="icon" />
                    <span>Home Page</span>
                </router-link>
            </li>
            <li>
                <router-link :to="{ name: 'dashboard' }" class="menu-link" active-class="is-active"
                    @click="$emit('close')">
                    <Squares2X2Icon class="icon" />
                    <span>Dashboard</span>
                </router-link>
            </li>
        </ul>
    </aside>
</template>

<script setup>
import { HomeIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';
defineProps(['isOpen']);
defineEmits(['close']);
</script>