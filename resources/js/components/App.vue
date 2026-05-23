<template>
    <div class="app-layout">

        <!-- <nav class="navbar is-hidden-desktop has-background-dark is-fixed-top p-3 is-flex is-align-items-center">
            <button class="button is-primary mr-3" @click="mobileMenuOpen = true">
                <span class="icon">☰</span>
            </button>
        </nav> -->

        <!-- <Sidebar :isOpen="mobileMenuOpen" @close="mobileMenuOpen = false" /> -->

        <main>
            <router-view v-slot="{ Component }">
                <transition name="fade" mode="out-in">
                    <component :is="Component" />
                </transition>
            </router-view>

        </main>

        <PwaInstallPrompt />
        <GlobalDialog />
    </div>
</template>

<script setup>

import { ref } from 'vue';
import GlobalDialog from './common/GlobalDialog.vue';
import PwaInstallPrompt from './common/PwaInstallPrompt.vue';
// import Sidebar from './Sidebar.vue';

const mobileMenuOpen = ref(false);
</script>
