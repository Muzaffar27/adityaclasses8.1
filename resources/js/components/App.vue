<template>
    <div class="app-layout">

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

import GlobalDialog from './common/GlobalDialog.vue';
import PwaInstallPrompt from './common/PwaInstallPrompt.vue';
</script>
