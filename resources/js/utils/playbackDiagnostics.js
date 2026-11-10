// Local troubleshooting only. Never capture URLs, credentials, names or request bodies.
export function createPlaybackDiagnostics({ lessonId, videoType }) {
    if (!['localhost', '127.0.0.1', '[::1]'].includes(window.location.hostname)) {
        return { enabled: false, record() {}, flush() {}, dispose() {} };
    }
    const runId = crypto.randomUUID();
    let tabId;
    try {
        tabId = sessionStorage.getItem('aditya-playback-debug-tab') || crypto.randomUUID();
        sessionStorage.setItem('aditya-playback-debug-tab', tabId);
    } catch { tabId = runId; }
    let queue = [], timer;
    const allowed = ['httpStatus', 'seconds', 'duration', 'completed', 'playbackSeen', 'readySeen', 'method', 'name', 'message', 'value', 'visibility', 'online', 'controlled', 'navigationType'];
    function record(stage, fields = {}) {
        const details = {};
        for (const key of allowed) {
            if (fields[key] === undefined) continue;
            const value = fields[key];
            if (['number', 'boolean'].includes(typeof value)) details[key] = value;
            else if (typeof value === 'string') details[key] = value.replace(/https?:\/\/[^\s]+/g, '[url]').slice(0, 250);
        }
        queue.push({ stage, at: new Date().toISOString(), ...details });
        if (queue.length >= 20) flush();
        else if (!timer) timer = setTimeout(flush, 2000);
    }
    function flush() {
        clearTimeout(timer); timer = null;
        if (!queue.length) return;
        const body = JSON.stringify({ type: 'local-playback-diagnostic', run_id: runId, tab_id: tabId,
            lesson_id: lessonId, video_type: videoType, events: queue.splice(0, 20) });
        // Logging failures must never interrupt playback or recursively log themselves.
        try {
            if (navigator.sendBeacon?.('/api/client-error', new Blob([body], { type: 'application/json' }))) return;
            void fetch('/api/client-error', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body, keepalive: true }).catch(() => {});
        } catch { /* Best-effort diagnostics. */ }
    }
    function leaving() { record('page-leaving', { visibility: document.visibilityState }); flush(); }
    window.addEventListener('pagehide', leaving);
    return { enabled: true, record, flush, dispose() { flush(); window.removeEventListener('pagehide', leaving); } };
}
