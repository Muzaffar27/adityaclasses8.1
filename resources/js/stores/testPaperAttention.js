import { defineStore } from 'pinia';
import { ref, watch } from 'vue';
import api from '../api';
import { useAuthStore } from './auth';

export const useTestPaperAttentionStore = defineStore('testPaperAttention', () => {
    const auth = useAuthStore();
    const total = ref(0);
    const availableCount = ref(0);
    const startedCount = ref(0);
    const next = ref(null);
    const error = ref(false);
    const loading = ref(false);
    let owner = null;
    let generation = 0;
    let flight = null;
    let controller = null;
    let lastFetched = 0;

    watch(() => ['student', 'tutor', 'admin'].includes(auth.user?.role) ? auth.user.id : null, id => {
        generation++;
        controller?.abort();
        owner = id;
        flight = null;
        lastFetched = 0;
        total.value = availableCount.value = startedCount.value = 0;
        next.value = null;
        error.value = loading.value = false;
    }, { immediate: true, flush: 'sync' });

    function refresh(force = false) {
        if (!owner) return Promise.resolve();
        if (flight) return force ? flight.then(() => refresh(true)) : flight;
        if (!force && Date.now() - lastFetched < 5000) return Promise.resolve();
        const current = generation;
        controller = new AbortController();
        loading.value = true;
        flight = api.get('/test-papers/attention', { signal: controller.signal }).then(({ data }) => {
            if (current !== generation) return;
            total.value = data.total || 0;
            availableCount.value = data.available_count || 0;
            startedCount.value = data.started_count || 0;
            next.value = data.next || null;
            error.value = false;
            lastFetched = Date.now();
        }).catch(requestError => {
            if (current === generation && requestError.code !== 'ERR_CANCELED') error.value = true;
        }).finally(() => {
            if (current !== generation) return;
            flight = null;
            loading.value = false;
        });
        return flight;
    }

    return { total, availableCount, startedCount, next, error, loading, refresh };
});
