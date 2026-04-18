<template>
  <section class="main-layout-wrapper">
    <header class="smooth-header">
      <div class="header-inner">

        <div class="header-side-left">
          <transition name="fade">
            <div v-if="showBack" class="header-action-btn" @click="goBack">
              <ArrowLeftIcon class="h-icon" />
            </div>
          </transition>
        </div>

        <div class="header-center">
          <div class="logo-wrapper" @click="$router.push({ name: 'home' })">
            <img src="../../../../public/menu_logo.png" class="main-logo mt-5" alt="Aditya Classes" />
            <div class="logo-glow"></div>
          </div>
        </div>

        <div class="header-side-right">
          <div class="user-identity-chip">
            <span class="initials">{{ userInitials }}</span>
            <div class="online-indicator"></div>
          </div>
          <slot name="actions" />
        </div>

      </div>
    </header>

    <div class="content-body px-4 py-5">
      <div v-if="loading" class="loader-overlay">
        <Loader />
      </div>

      <div v-else class="fade-in">
        <h1 v-if="title && title !== 'Home'" class="page-main-title">{{ title }}</h1>
        <slot />
      </div>
    </div>
  </section>
</template>

<script setup>
import { ArrowLeftIcon } from "@heroicons/vue/24/outline";
import Loader from "./Loader.vue";
import { useRouter } from "vue-router";
import { computed } from "vue";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();
const router = useRouter();

defineProps({
  title: String,
  loading: Boolean,
  showBack: { type: Boolean, default: true }
});

const userInitials = computed(() => {
  if (!auth.user?.name) return '?';
  return auth.user.name
    .split(' ')
    .slice(0, 2)
    .map(n => n[0].toUpperCase())
    .join('');
});

function goBack() {
  const canGoBack = window.history.state?.back;
  canGoBack ? router.back() : router.replace({ name: 'home' });
}
</script>

<style scoped>
.main-layout-wrapper {
  min-height: 100vh;
  background: transparent;
  /* Let the global dark background show through */
}

/* ── The Floating Header ── */
.smooth-header {
  overflow: visible !important;
  position: sticky;
  top: 0;
  z-index: 100;
  padding: 15px 10px;

  /* The Secret Sauce: A gradient mask for smooth scrolling */
  background: linear-gradient(to bottom,
      rgba(15, 23, 42, 0.9) 0%,
      rgba(15, 23, 42, 0.7) 60%,
      transparent 100%);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}

.header-inner {
  max-width: 100%;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  /* Keep the header row compact and clean */
  height: 60px;
  position: relative;
  overflow: visible !important;
  padding: 0 5px;
}

/* ── The Floating Logo Spotlight ── */
.header-center {
  display: flex;
  justify-content: center;
  align-items: center;
  position: relative;
  /* We give it a small width so the browser renders the absolute child */
  width: 60px;
  height: 60px;
  overflow: visible !important;
}

.logo-wrapper {
  position: absolute;
  /* This centers the wrapper exactly in the middle of the header */
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);

  display: flex;
  justify-content: center;
  align-items: center;

  cursor: pointer;
  /* z-index is key to making sure it stays above the header background */
  z-index: 50;
  pointer-events: all;
}

.main-logo {
  /* This is the "Bigger" size you requested */
  height: 120px;
  width: auto;

  transform: scale(2);
  min-width: 120px;
  /* Forces visibility */

  object-fit: contain;
  filter: drop-shadow(0 10px 25px rgba(99, 102, 241, 0.6));

  /* Reset any opacity/visibility issues */
  opacity: 1 !important;
  visibility: visible !important;
}


.logo-glow {
  position: absolute;
  width: 200px;
  height: 120px;

  background: radial-gradient(circle at center,
      rgba(255, 255, 255, 0.08) 0%,
      rgba(79, 70, 229, 0.12) 40%,
      transparent 75%);

  filter: blur(35px);
  opacity: 0.7;

  z-index: -1;
  pointer-events: none;
}

/* ── Side Actions ── */
.header-action-btn {
  width: 42px;
  height: 42px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.header-action-btn:hover {
  background: rgba(79, 70, 229, 0.2);
  border-color: rgba(79, 70, 229, 0.4);
}

.h-icon {
  width: 20px;
  height: 20px;
  color: #fff;
}

.header-side-right {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  padding-right: 5px;
  gap: 12px;
}

.header-side-left {
  display: flex;
  justify-content: flex-start;
  /* Aligns content to the start */
  align-items: center;
  padding-left: 5px;
  /* Adjust this to 0 if you want it touching the edge */
}

.user-identity-chip {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  /* Circle looks more modern for user avatars */
  background: linear-gradient(135deg, #4f46e5, #818cf8);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  border: 2px solid rgba(255, 255, 255, 0.1);
}

.initials {
  font-size: 13px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.5px;
}

.online-indicator {
  position: absolute;
  bottom: 1px;
  right: 0px;
  width: 10px;
  height: 10px;
  background: #10b981;
  border: 2px solid #0f172a;
  border-radius: 50%;
}

/* ── Content Area ── */
.page-main-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: #fff;
  margin-bottom: 1.5rem;
  text-align: left;
}

/* Loader centering */
.loader-overlay {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 200px;
}

/* ── Mobile Adjustment ── */
@media (max-width: 768px) {
  .header-inner {
    padding: 0 2px;
    /* Even tighter for mobile screens */
  }

  .header-side-left {
    padding-left: 2px;
  }
}
</style>