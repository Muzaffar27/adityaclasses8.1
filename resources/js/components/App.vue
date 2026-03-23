<template>
    <div class="app-layout">

        <nav class="navbar is-hidden-desktop has-background-dark is-fixed-top p-3 is-flex is-align-items-center">
            <button class="button is-primary mr-3" @click="mobileMenuOpen = true">
                <span class="icon">☰</span>
            </button>

            <div class="has-text-white has-text-weight-bold is-size-5">
                Aditya Classes
            </div>
        </nav>

        <Sidebar :isOpen="mobileMenuOpen" @close="mobileMenuOpen = false" />

        <main class="main-content">

            <Loader v-if="loading" key="loader" />

            <router-view v-else v-slot="{ Component }">
                <transition name="fade" mode="out-in">
                    <component :is="Component" />
                </transition>
            </router-view>
        </main>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { storeToRefs } from "pinia";
import { useLoaderStore } from "@/stores/loader";

import Sidebar from './Sidebar.vue';
import Loader from '../components/common/Loader.vue'

const loaderStore = useLoaderStore();
const { loading } = storeToRefs(loaderStore);

const mobileMenuOpen = ref(false);
</script>