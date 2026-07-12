<template>
  <div v-if="shouldShowButton" class="pwa-install-control">
    <button class="pwa-install-nav-btn" type="button" @click="handleInstallClick">
      <DevicePhoneMobileIcon class="pwa-install-nav-icon" />
      <span class="pwa-install-nav-text">Install</span>
    </button>

    <div v-if="showInstructions" class="pwa-install-help" role="dialog" aria-modal="false">
      <p class="pwa-install-help-title">Install Aditya Classes</p>
      <p class="pwa-install-help-text">
        Tap Share, then choose Add to Home Screen.
      </p>
      <button type="button" class="pwa-install-help-close" @click="showInstructions = false">
        Got it
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { DevicePhoneMobileIcon } from "@heroicons/vue/24/outline";

const deferredPrompt = ref(null);
const isInstallable = ref(false);
const isIosSafari = ref(false);
const isStandalone = ref(false);
const showInstructions = ref(false);

const shouldShowButton = computed(() => {
  if (isStandalone.value) {
    return false;
  }

  return isInstallable.value || isIosSafari.value;
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
  window.adityaPwaInstallPrompt = event;
}

function handleInstallableEvent() {
  if (window.adityaPwaInstallPrompt) {
    deferredPrompt.value = window.adityaPwaInstallPrompt;
    isInstallable.value = true;
  }
}

function handleAppInstalled() {
  deferredPrompt.value = null;
  isInstallable.value = false;
  isStandalone.value = true;
  showInstructions.value = false;
}

async function handleInstallClick() {
  if (!deferredPrompt.value) {
    showInstructions.value = true;
    return;
  }

  deferredPrompt.value.prompt();
  await deferredPrompt.value.userChoice;
  deferredPrompt.value = null;
  isInstallable.value = false;
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
.pwa-install-control {
  position: relative;
}

.pwa-install-nav-btn {
  align-items: center;
  background: rgba(245, 158, 11, 0.14);
  border: 1px solid rgba(245, 158, 11, 0.46);
  border-radius: 999px;
  color: #fbbf24;
  cursor: pointer;
  display: inline-flex;
  font-size: 0.76rem;
  font-weight: 800;
  gap: 6px;
  height: 36px;
  padding: 0 12px;
  transition: background 0.18s ease, transform 0.18s ease, border-color 0.18s ease;
  white-space: nowrap;
}

.pwa-install-nav-btn:hover,
.pwa-install-nav-btn:focus {
  background: rgba(245, 158, 11, 0.22);
  border-color: rgba(251, 191, 36, 0.72);
}

.pwa-install-nav-btn:active {
  transform: scale(0.96);
}

.pwa-install-nav-icon {
  height: 18px;
  width: 18px;
}

.pwa-install-help {
  background: rgba(15, 23, 42, 0.97);
  border: 1px solid rgba(245, 158, 11, 0.32);
  border-radius: 12px;
  box-shadow: 0 18px 42px rgba(0, 0, 0, 0.35);
  padding: 12px;
  position: absolute;
  right: 0;
  top: calc(100% + 10px);
  width: 230px;
  z-index: 350;
}

.pwa-install-help-title,
.pwa-install-help-text {
  margin: 0;
}

.pwa-install-help-title {
  color: #fff;
  font-size: 0.86rem;
  font-weight: 800;
}

.pwa-install-help-text {
  color: #cbd5e1;
  font-size: 0.78rem;
  line-height: 1.35;
  margin-top: 4px;
}

.pwa-install-help-close {
  background: #f59e0b;
  border: 0;
  border-radius: 999px;
  color: #451a03;
  cursor: pointer;
  font-size: 0.74rem;
  font-weight: 800;
  margin-top: 10px;
  padding: 7px 12px;
}

@media (max-width: 768px) {
  .pwa-install-nav-btn {
    height: 34px;
    padding: 0 10px;
  }

  .pwa-install-nav-text {
    display: none;
  }

  .pwa-install-help {
    right: -46px;
  }
}
</style>
