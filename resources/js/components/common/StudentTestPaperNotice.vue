<template>
    <aside v-if="visible" class="paper-notice" role="status" aria-label="Test paper reminder">
        <span class="notice-icon"><ClipboardDocumentCheckIcon aria-hidden="true" /></span>
        <div class="notice-copy">
            <strong>{{ attention.next.status === 'started' ? 'Finish your test paper' : 'Your test paper is ready' }}</strong>
            <p>{{ attention.next.subject }} · Your oldest unfinished paper</p>
        </div>
        <router-link :to="{ name: 'testPaper', params: { paper: attention.next.id } }">
            {{ attention.next.status === 'started' ? 'Resume test paper' : 'Open test paper' }}<ArrowUpRightIcon aria-hidden="true" />
        </router-link>
    </aside>
</template>

<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowUpRightIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline';
import { useAuthStore } from '../../stores/auth';
import { useTestPaperAttentionStore } from '../../stores/testPaperAttention';

const auth = useAuthStore();
const route = useRoute();
const attention = useTestPaperAttentionStore();
const visible = computed(() => auth.isLoggedIn && attention.next
    && !(route.name === 'testPaper' && route.params.paper === attention.next.id));
let interval;
function refresh() {
    if (auth.isLoggedIn && !document.hidden && navigator.onLine) attention.refresh();
}
watch(() => auth.user?.id, refresh);
onMounted(() => {
    refresh();
    interval = setInterval(refresh, 30000);
    window.addEventListener('focus', refresh);
    window.addEventListener('online', refresh);
    document.addEventListener('visibilitychange', refresh);
});
onUnmounted(() => {
    clearInterval(interval);
    window.removeEventListener('focus', refresh);
    window.removeEventListener('online', refresh);
    document.removeEventListener('visibilitychange', refresh);
});
</script>

<style scoped>
.paper-notice {
    display: grid;
    grid-template-columns: 22px minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    box-sizing: border-box;
    width: 100%;
    margin: 0 auto 22px;
    padding: 12px 14px;
    border: 1px solid #f472b64d;
    border-radius: 14px;
    background: linear-gradient(110deg, #3b192d, #25131f);
    color: #fce7f3;
}
.notice-icon {
    display: grid;
    place-items: center;
    width: 22px;
    color: #f9a8d4;
}
.notice-icon svg { width: 21px; height: 21px; }
.notice-copy { min-width: 0; overflow-wrap: anywhere; }
.notice-copy strong { display: block; color: #f9a8d4; font-size: 14px; font-weight: 650; line-height: 1.4; }
.notice-copy p { margin: 3px 0 0; color: #d7b6c8; font-size: 12px; line-height: 1.5; }
.paper-notice a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-sizing: border-box;
    min-height: 44px;
    padding: 10px 13px;
    border: 1px solid #f472b64d;
    border-radius: 10px;
    background: #ec48991f;
    color: #fce7f3;
    font-size: 12px;
    font-weight: 750;
    line-height: 1.4;
    text-decoration: none;
    white-space: nowrap;
    transition: background .15s;
}
.paper-notice a svg { width: 15px; height: 15px; }
.paper-notice a:hover { background: #ec489938; }
.paper-notice a:focus-visible { outline: 3px solid #f9a8d4; outline-offset: 4px; }
@media (max-width: 600px) {
    .paper-notice { grid-template-columns: 22px minmax(0, 1fr); row-gap: 10px; }
    .notice-icon { align-self: start; margin-top: 2px; }
    .paper-notice a { grid-column: 2; justify-self: start; }
}
</style>
