<template>
    <div ref="viewport" class="pdf-document-viewer" :class="{ 'single-page': pageNumber }">
        <div v-if="loading" class="pdf-viewer-state">Preparing document…</div>
        <div v-else-if="error" class="pdf-viewer-state is-error">The document could not be displayed.</div>
        <div v-show="!loading && !error" class="pdf-pages">
            <canvas v-for="pageNumber in displayedPages" :key="pageNumber"
                :ref="element => setCanvas(element, pageNumber)"></canvas>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

const pdfWorkerSrc = `${pdfWorkerUrl}?v=20260920-mime`;

const props = defineProps({ url: { type: String, required: true }, pageNumber: { type: Number, default: null }, focusRect: { type: Object, default: null } });
const emit = defineEmits(['ready']);
const viewport = ref(null);
const loading = ref(true);
const error = ref(false);
const pageCount = ref(0);
const displayedPages = computed(() => props.pageNumber ? (pageCount.value ? [Math.max(1, Math.min(props.pageNumber, pageCount.value))] : []) : Array.from({ length: pageCount.value }, (_, i) => i + 1));
const canvases = new Map();
let documentTask = null;
let pdfDocument = null;
let documentVersion = 0;
let renderVersion = 0;
let pageTask = null;
let resizeObserver = null;
let resizeFrame = 0;

function setCanvas(element, pageNumber) {
    if (element) canvases.set(pageNumber, element);
    else canvases.delete(pageNumber);
}

async function disposeDocument(task) {
    try { await task?.destroy?.(); } catch { /* Closing during worker startup is expected. */ }
}

async function loadDocument() {
    const version = ++documentVersion;
    renderVersion++;
    pageTask?.cancel();
    let failureStage = 'module-import';
    const previousDocument = documentTask;
    documentTask = null;
    pdfDocument = null;
    loading.value = true;
    error.value = false;
    pageCount.value = 0;
    await disposeDocument(previousDocument);
    if (version !== documentVersion) return;

    try {
        const pdfjs = await import('pdfjs-dist/build/pdf.mjs');
        if (version !== documentVersion) return;
        failureStage = 'worker-setup';
        pdfjs.GlobalWorkerOptions.workerSrc = pdfWorkerSrc;
        failureStage = 'document-load';
        documentTask = pdfjs.getDocument(props.url);
        const loaded = await documentTask.promise;
        if (version !== documentVersion) return;
        pdfDocument = loaded;
        pageCount.value = pdfDocument.numPages;
        await renderPages();
    } catch (loadError) {
        if (version !== documentVersion) return;
        console.error('PDF rendering failed:', loadError);
        reportPdfFailure(loadError, failureStage, null);
        error.value = true;
        loading.value = false;
    }
}

async function renderPages() {
    if (!pdfDocument || !viewport.value) return;
    const version = ++renderVersion;
    const document = pdfDocument;
    const previousPage = pageTask;
    previousPage?.cancel();
    loading.value = true;
    error.value = false;
    if (previousPage) await previousPage.promise.catch(() => {});
    await nextTick();
    if (version !== renderVersion) return;
    const availableWidth = Math.max(props.pageNumber ? 120 : 280, Math.min(viewport.value.clientWidth - 20, 1000));
    const outputScale = Math.min(window.devicePixelRatio || 1, 1.5);
    let renderingPage = null;
    try {
        for (const pageNumber of displayedPages.value) {
            renderingPage = pageNumber;
            const page = await document.getPage(pageNumber);
            if (version !== renderVersion) return;
            const baseViewport = page.getViewport({ scale: 1 });
            const displayScale = availableWidth / baseViewport.width;
            const displayViewport = page.getViewport({ scale: displayScale });
            const canvas = canvases.get(pageNumber);
            if (!canvas) continue;
            const context = canvas.getContext('2d', { alpha: false });
            canvas.width = Math.floor(displayViewport.width * outputScale);
            canvas.height = Math.floor(displayViewport.height * outputScale);
            canvas.style.width = `${Math.floor(displayViewport.width)}px`;
            canvas.style.height = `${Math.floor(displayViewport.height)}px`;
            const task = page.render({
                canvasContext: context,
                viewport: displayViewport,
                transform: outputScale === 1 ? null : [outputScale, 0, 0, outputScale, 0, 0],
            });
            pageTask = task;
            try { await task.promise; } finally { if (pageTask === task) pageTask = null; }
            if (version !== renderVersion) return;
            const rect = props.focusRect;
            if (props.pageNumber === pageNumber && rect && [rect.x, rect.y, rect.width, rect.height].every(Number.isFinite) && rect.x >= 0 && rect.y >= 0 && rect.width > 0 && rect.height > 0) {
                const scale = displayScale * outputScale;
                context.save(); context.strokeStyle = '#f59e0b'; context.lineWidth = 3 * outputScale;
                context.strokeRect(rect.x * scale, rect.y * scale, Math.min(rect.width, baseViewport.width - rect.x) * scale, Math.min(rect.height, baseViewport.height - rect.y) * scale);
                context.restore();
                viewport.value.scrollTop = Math.max(0, rect.y * displayScale - viewport.value.clientHeight / 3);
            }
        }
        loading.value = false;
        emit('ready', props.url);
    } catch (loadError) {
        if (version !== renderVersion) return;
        console.error('PDF rendering failed:', loadError);
        reportPdfFailure(loadError, 'page-render', renderingPage);
        error.value = true;
        loading.value = false;
    }
}

async function reportPdfFailure(loadError, stage, pageNumber) {
    let cacheNames = [];
    let serviceWorkerState = null;

    try {
        if ('caches' in window) cacheNames = await window.caches.keys();
    } catch (_) { }

    try {
        const registration = await navigator.serviceWorker?.getRegistration();
        serviceWorkerState = {
            active: registration?.active?.state || null,
            waiting: registration?.waiting?.state || null,
            installing: registration?.installing?.state || null,
        };
    } catch (_) { }

    window.reportAdityaClientError?.('pdf-viewer-error', loadError, {
        source: 'PdfDocumentViewer',
        pdfStage: stage,
        pdfPage: pageNumber,
        pdfPages: pdfDocument?.numPages || null,
        pdfPath: safePath(props.url),
        workerPath: safePath(pdfWorkerSrc),
        online: navigator.onLine,
        viewportWidth: window.innerWidth,
        viewportHeight: window.innerHeight,
        devicePixelRatio: window.devicePixelRatio || 1,
        deviceMemory: navigator.deviceMemory || null,
        hardwareConcurrency: navigator.hardwareConcurrency || null,
        cacheNames,
        serviceWorkerState,
    });
}

function safePath(url) {
    try {
        return new URL(url, window.location.origin).pathname;
    } catch (_) {
        return '';
    }
}

watch(() => props.url, loadDocument);
watch(() => [props.pageNumber, props.focusRect], ([page], [previousPage]) => {
    if (page !== previousPage && viewport.value) viewport.value.scrollTop = 0;
    if (pdfDocument) void renderPages();
});
onMounted(() => {
    let lastWidth = viewport.value.getBoundingClientRect().width;
    resizeObserver = new ResizeObserver(() => {
        const width = viewport.value?.getBoundingClientRect().width ?? 0;
        if (width === lastWidth) return;
        lastWidth = width;
        cancelAnimationFrame(resizeFrame);
        if (width > 0) resizeFrame = requestAnimationFrame(() => void renderPages());
    });
    resizeObserver.observe(viewport.value);
    void loadDocument();
});
onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    cancelAnimationFrame(resizeFrame);
    documentVersion++;
    renderVersion++;
    pageTask?.cancel();
    void disposeDocument(documentTask);
});
</script>

<style scoped>
.pdf-document-viewer { background: var(--app-panel); height: 100%; overflow: auto; overscroll-behavior: contain; scrollbar-gutter: stable; width: 100%; }
.pdf-pages { align-items: center; display: flex; flex-direction: column; gap: 0.75rem; padding: 0.65rem; }
.pdf-pages canvas { background: #fff; box-shadow: 0 4px 18px rgba(0, 0, 0, 0.3); display: block; max-width: 100%; }
.single-page .pdf-pages canvas { width: 100% !important; height: auto !important; }
.pdf-viewer-state { align-items: center; color: #dbeafe; display: flex; font-size: 0.85rem; font-weight: 700; height: 100%; justify-content: center; padding: 2rem; text-align: center; }
.pdf-viewer-state.is-error { color: #fecaca; }
</style>
