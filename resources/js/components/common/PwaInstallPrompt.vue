<template>
  <transition name="pwa-install-slide">
    <aside v-if="shouldShowPrompt" class="pwa-install-prompt" role="status">
      <div class="pwa-install-icon" aria-hidden="true">
        <DevicePhoneMobileIcon />
      </div>

      <div class="pwa-install-copy">
        <p class="pwa-install-title">Install Aditya Classes</p>
        <p class="pwa-install-text">
          {{ installText }}
        </p>
      </div>

      <button v-if="canInstall" class="pwa-install-btn" type="button" @click="installApp">
        Install
      </button>

      <button
        class="pwa-install-close"
        type="button"
        aria-label="Dismiss install prompt"
        @click="dismissPrompt"
      >
        <XMarkIcon />
      </button>
    </aside>
  </transition>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { DevicePhoneMobileIcon, XMarkIcon } from "@heroicons/vue/24/outline";

const DISMISS_KEY = "aditya_pwa_install_prompt_dismissed_at";
const DISMISS_DURATION_MS = 24 * 60 * 60 * 1000;

function wasRecentlyDismissed() {
  const dismissedAt = Number(localStorage.getItem(DISMISS_KEY));

  return Number.isFinite(dismissedAt) && Date.now() - dismissedAt < DISMISS_DURATION_MS;
}

const deferredPrompt = ref(null);
const isInstallable = ref(false);
const isIosSafari = ref(false);
const isStandalone = ref(false);
const isDismissed = ref(wasRecentlyDismissed());

const canInstall = computed(() => Boolean(deferredPrompt.value));

const shouldShowPrompt = computed(() => {
  if (isDismissed.value || isStandalone.value) {
    return false;
  }

  return isInstallable.value || isIosSafari.value;
});

const installText = computed(() => {
  if (isIosSafari.value && !canInstall.value) {
    return "Use the Share button, then choose Add to Home Screen.";
  }

  return "Add it to your device for a faster app-like experience.";
});

function updateStandaloneState() {
  isStandalone.value =
    window.matchMedia("(display-mode: standalone)").matches ||
    window.navigator.standalone === true;
}

function handleBeforeInstallPrompt(event) {
  event.preventDefault();
  deferredPrompt.value = event;
  isInstallable.value = true;
  isDismissed.value = wasRecentlyDismissed();
  window.adityaPwaInstallPrompt = event;
}

function handleInstallableEvent() {
  if (window.adityaPwaInstallPrompt) {
    deferredPrompt.value = window.adityaPwaInstallPrompt;
    isInstallable.value = true;
    isDismissed.value = wasRecentlyDismissed();
  }
}

function handleAppInstalled() {
  deferredPrompt.value = null;
  isInstallable.value = false;
  isStandalone.value = true;
  localStorage.removeItem(DISMISS_KEY);
}

async function installApp() {
  if (!deferredPrompt.value) {
    return;
  }

  deferredPrompt.value.prompt();
  await deferredPrompt.value.userChoice;
  deferredPrompt.value = null;
  isInstallable.value = false;
}

function dismissPrompt() {
  isDismissed.value = true;
  localStorage.setItem(DISMISS_KEY, String(Date.now()));
}

onMounted(() => {
  const userAgent = window.navigator.userAgent.toLowerCase();
  isIosSafari.value =
    /iphone|ipad|ipod/.test(userAgent) &&
    /safari/.test(userAgent) &&
    !/crios|fxios|edgios/.test(userAgent);

  updateStandaloneState();

  window.addEventListener("beforeinstallprompt", handleBeforeInstallPrompt);
  window.addEventListener("aditya-pwa-installable", handleInstallableEvent);
  window.addEventListener("appinstalled", handleAppInstalled);

  handleInstallableEvent();
});

onBeforeUnmount(() => {
  window.removeEventListener("beforeinstallprompt", handleBeforeInstallPrompt);
  window.removeEventListener("aditya-pwa-installable", handleInstallableEvent);
  window.removeEventListener("appinstalled", handleAppInstalled);
});
</script>

<style scoped>
.pwa-install-prompt {
  position: fixed;
  right: 18px;
  bottom: 18px;
  z-index: 300;
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr) auto 34px;
  align-items: center;
  gap: 12px;
  width: min(520px, calc(100vw - 32px));
  padding: 12px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 16px;
  background: rgba(15, 23, 42, 0.94);
  box-shadow: 0 18px 48px rgba(0, 0, 0, 0.35);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}

.pwa-install-icon,
.pwa-install-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.pwa-install-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  color: #fbbf24;
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.28);
}

.pwa-install-icon svg {
  width: 24px;
  height: 24px;
}

.pwa-install-copy {
  min-width: 0;
}

.pwa-install-title,
.pwa-install-text {
  margin: 0;
}

.pwa-install-title {
  color: #ffffff;
  font-size: 0.92rem;
  font-weight: 800;
}

.pwa-install-text {
  margin-top: 2px;
  color: #cbd5e1;
  font-size: 0.78rem;
  line-height: 1.35;
}

.pwa-install-btn {
  border: 1px solid #f59e0b;
  border-radius: 999px;
  padding: 0.62rem 0.95rem;
  color: #451a03;
  background: #f59e0b;
  font-size: 0.78rem;
  font-weight: 800;
  cursor: pointer;
  transition: transform 0.16s ease, background 0.16s ease;
}

.pwa-install-btn:hover,
.pwa-install-btn:focus {
  background: #fbbf24;
}

.pwa-install-btn:active {
  transform: scale(0.96);
}

.pwa-install-close {
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 50%;
  color: #94a3b8;
  background: rgba(255, 255, 255, 0.06);
  cursor: pointer;
}

.pwa-install-close:hover,
.pwa-install-close:focus {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.12);
}

.pwa-install-close svg {
  width: 18px;
  height: 18px;
}

.pwa-install-slide-enter-active,
.pwa-install-slide-leave-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}

.pwa-install-slide-enter-from,
.pwa-install-slide-leave-to {
  opacity: 0;
  transform: translateY(14px);
}

@media (max-width: 560px) {
  .pwa-install-prompt {
    right: 12px;
    bottom: 12px;
    grid-template-columns: 38px minmax(0, 1fr) 32px;
    gap: 10px;
  }

  .pwa-install-btn {
    grid-column: 1 / -1;
    width: 100%;
  }

  .pwa-install-icon {
    width: 38px;
    height: 38px;
  }
}
</style>
