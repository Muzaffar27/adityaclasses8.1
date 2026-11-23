<template>
    <article class="test-card dashboard-papers" aria-labelledby="dashboard-papers-heading">
        <header class="papers-heading">
            <span class="papers-icon"><ClipboardDocumentCheckIcon aria-hidden="true" /></span>
            <div><h3 id="dashboard-papers-heading">Test papers</h3><p>Oldest unfinished papers first</p></div>
            <strong v-if="attention.total" class="paper-count">{{ attention.total }} to do</strong>
        </header>
        <p v-if="error" class="papers-message" role="status">Could not load your papers. <button type="button" @click="load(currentPage)">Try again</button></p>
        <p v-else-if="loading && !papers.length" class="papers-message" role="status">Loading your papers…</p>
        <p v-else-if="!papers.length" class="papers-message">Your first paper will appear after three completed topics and enough approved questions.</p>
        <ol v-else class="papers-list" :aria-busy="loading">
            <li v-for="item in papers" :key="item.id" :class="{ 'is-next': item.id === attention.next?.id }">
                <div class="paper-details">
                    <span v-if="item.id === attention.next?.id" class="next-label">Up next</span>
                    <strong>{{ item.subject }}</strong>
                    <small>{{ item.grade }}<template v-if="item.available_at"> · {{ paperDate(item.available_at) }}</template></small>
                    <span class="paper-status">{{ statusLabel(item.status) }}</span>
                </div>
                <router-link :to="{ name: 'testPaper', params: { paper: item.id } }" :aria-label="`${actionLabel(item.status)} ${item.subject} test paper`">{{ actionLabel(item.status) }}<ArrowUpRightIcon aria-hidden="true" /></router-link>
            </li>
        </ol>
        <footer>
            <nav v-if="lastPage > 1" aria-label="Dashboard test paper pages">
                <button type="button" :disabled="loading || currentPage === 1" @click="load(currentPage - 1)">Previous</button>
                <span>{{ currentPage }} / {{ lastPage }}</span>
                <button type="button" :disabled="loading || currentPage === lastPage" @click="load(currentPage + 1)">Next</button>
            </nav>
            <router-link :to="{ name: 'testPapers' }">View all papers <ArrowUpRightIcon aria-hidden="true" /></router-link>
        </footer>
    </article>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { ArrowUpRightIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline';
import api from '../api';
import { useAuthStore } from '../stores/auth';
import { useTestPaperAttentionStore } from '../stores/testPaperAttention';

const auth = useAuthStore();
const attention = useTestPaperAttentionStore();
const papers = ref([]), loading = ref(false), error = ref(false), currentPage = ref(1), lastPage = ref(1);
let requestNumber = 0, controller;
const statusLabel = status => ({ available: 'Ready to start', started: 'In progress', submitted: 'Submitted', awaiting_review: 'Awaiting review', marked: 'Completed', cancelled: 'Cancelled' }[status] || 'Preparing');
const actionLabel = status => status === 'available' ? 'Start' : status === 'started' ? 'Resume' : 'View';
const paperDate = value => {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Indian/Mauritius' }).format(date);
};
async function load(page = 1) {
    controller?.abort();
    const request = ++requestNumber;
    if (!auth.user?.id) { papers.value = []; loading.value = error.value = false; return; }
    controller = new AbortController();
    loading.value = true; error.value = false;
    try {
        const { data } = await api.get('/test-papers', { params: { page, per_page: 5 }, signal: controller.signal });
        if (request !== requestNumber) return;
        papers.value = data.data || [];
        currentPage.value = data.current_page || page;
        lastPage.value = data.last_page || 1;
    } catch (e) {
        if (request === requestNumber && e.code !== 'ERR_CANCELED') error.value = true;
    } finally { if (request === requestNumber) loading.value = false; }
}
watch(() => auth.user?.id, () => { papers.value = []; currentPage.value = lastPage.value = 1; load(); }, { immediate: true, flush: 'sync' });
watch(() => [attention.total, attention.next?.id, attention.next?.status], () => load());
onBeforeUnmount(() => { requestNumber++; controller?.abort(); });
</script>

<style scoped>
.dashboard-papers { min-width: 0; padding: 20px; border: 1px solid #f472b64d; border-radius: 20px; background: linear-gradient(145deg, #3b192d, #21131d); color: #fce7f3; }
.papers-heading { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.papers-heading > div { flex: 1; min-width: 0; }
.papers-heading h3 { margin: 0; color: #fce7f3; font-size: 16px; font-weight: 700; }
.papers-heading p { margin: 3px 0 0; color: #d7b6c8; font-size: 12px; }
.papers-icon { display: grid; place-items: center; width: 38px; height: 40px; border-radius: 11px; flex-shrink: 0; background: #ec489924; color: #f9a8d4; }
.papers-icon svg { width: 21px; height: 21px; }
.paper-count { flex-shrink: 0; padding: 5px 9px; border-radius: 20px; background: #ec489929; color: #fce7f3; font-size: 12px; }
.papers-list { list-style: none; margin: 0; padding: 0; }
.papers-list li { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-top: 1px solid #f9a8d41c; }
.paper-details { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.paper-details strong, .paper-details small, .paper-status, .next-label { display: block; }
.paper-details strong { color: #fff1f7; font-size: 14px; font-weight: 650; }
.paper-details small { margin-top: 3px; font-size: 11px; color: #d7b6c8; }
.paper-status { margin-top: 4px; color: #e6cad9; font-size: 11px; }
.next-label { margin-bottom: 4px; color: #f9a8d4; font-size: 10px; text-transform: uppercase; letter-spacing: .08em; font-weight: 750; }
.papers-list a { display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0; min-height: 44px; padding: 8px 11px; border: 1px solid #f472b64d; border-radius: 10px; color: #fce7f3; background: #ec48991a; font-size: 12px; font-weight: 650; text-decoration: none; }
.papers-list .is-next a { background: #f9a8d4; border-color: #f9a8d4; color: #4a1631; }
.dashboard-papers a:hover { filter: brightness(1.12); }
.dashboard-papers a:focus-visible, .dashboard-papers button:focus-visible { outline: 3px solid #f9a8d4; outline-offset: 3px; }
.dashboard-papers a svg { width: 14px; height: 14px; }
.papers-message { margin: 16px 0; color: #e6cad9; font-size: 13px; line-height: 1.6; }
.dashboard-papers footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
.dashboard-papers footer > a { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; color: #f9a8d4; font-size: 12px; font-weight: 650; }
.dashboard-papers nav { display: flex; align-items: center; gap: 8px; font-size: 12px; }
.dashboard-papers button { min-height: 44px; border: 1px solid #f472b64d; border-radius: 8px; padding: 6px 9px; background: #ec48991a; color: #fce7f3; font: inherit; cursor: pointer; }
.dashboard-papers button:disabled { opacity: .45; cursor: default; }
@media (max-width: 600px) { .dashboard-papers { padding: 16px; } .papers-heading { gap: 8px; flex-wrap: wrap; } }
</style>
