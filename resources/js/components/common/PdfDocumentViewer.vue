<template>
    <div ref="viewport" class="pdf-document-viewer">
        <div v-if="loading" class="pdf-viewer-state">Preparing document…</div>
        <div v-else-if="error" class="pdf-viewer-state is-error">The document could not be displayed.</div>
        <div v-show="!loading && !error" class="pdf-pages">
            <canvas v-for="pageNumber in pageCount" :key="pageNumber"
                :ref="element => setCanvas(element, pageNumber)"></canvas>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

const pdfWorkerSrc = `${pdfWorkerUrl}?v=20260920-mime`;

const props = defineProps({ url: { type: String, required: true } });
const viewport = ref(null);
const loading = ref(true);
const error = ref(false);
const pageCount = ref(0);
const canvases = new Map();
let documentTask = null;
let pdfDocument = null;
let renderVersion = 0;

function setCanvas(element, pageNumber) {
    if (element) canvases.set(pageNumber, element);
    else canvases.delete(pageNumber);
}

async function loadDocument() {
    const version = ++renderVersion;
    let failureStage = 'module-import';
    let renderingPage = null;
    await documentTask?.destroy?.();
    documentTask = null;
    pdfDocument = null;
    loading.value = true;
    error.value = false;
    pageCount.value = 0;
    canvases.clear();

    try {
        const pdfjs = await import('pdfjs-dist/build/pdf.mjs');
        failureStage = 'worker-setup';
        pdfjs.GlobalWorkerOptions.workerSrc = pdfWorkerSrc;
        failureStage = 'document-load';
        documentTask = pdfjs.getDocument(props.url);
        pdfDocument = await documentTask.promise;
        if (version !== renderVersion) return;
        pageCount.value = pdfDocument.numPages;
        await nextTick();
        loading.value = false;
        await nextTick();
        failureStage = 'page-render';
        await renderPages(version, pageNumber => {
            renderingPage = pageNumber;
        });
    } catch (loadError) {
        if (version !== renderVersion) return;
        console.error('PDF rendering failed:', loadError);
        reportPdfFailure(loadError, failureStage, renderingPage);
        error.value = true;
        loading.value = false;
    }
}

async function renderPages(version = ++renderVersion, onPage = () => {}) {
    if (!pdfDocument || !viewport.value) return;
    const availableWidth = Math.max(280, Math.min(viewport.value.clientWidth - 20, 1000));
    const outputScale = Math.min(window.devicePixelRatio || 1, 1.5);

    for (let pageNumber = 1; pageNumber <= pdfDocument.numPages; pageNumber++) {
        if (version !== renderVersion) return;
        onPage(pageNumber);
        const page = await pdfDocument.getPage(pageNumber);
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
        await page.render({
            canvasContext: context,
            viewport: displayViewport,
            transform: outputScale === 1 ? null : [outputScale, 0, 0, outputScale, 0, 0],
        }).promise;
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
onMounted(loadDocument);
onBeforeUnmount(() => {
    renderVersion++;
    documentTask?.destroy?.();
});
</script>

<style scoped>
.pdf-document-viewer { background: #252a33; height: 100%; overflow: auto; overscroll-behavior: contain; width: 100%; }
.pdf-pages { align-items: center; display: flex; flex-direction: column; gap: 0.75rem; padding: 0.65rem; }
.pdf-pages canvas { background: #fff; box-shadow: 0 4px 18px rgba(0, 0, 0, 0.3); display: block; max-width: 100%; }
.pdf-viewer-state { align-items: center; color: #dbeafe; display: flex; font-size: 0.85rem; font-weight: 700; height: 100%; justify-content: center; padding: 2rem; text-align: center; }
.pdf-viewer-state.is-error { color: #fecaca; }
</style>
