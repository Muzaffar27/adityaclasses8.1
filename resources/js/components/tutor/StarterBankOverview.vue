<template>
    <section class="bank-overview" aria-label="Question bank progress">
        <div class="bank-modes" aria-label="Review selection">
            <button type="button" class="mode starter-mode" :class="{ active: scope === 'starter' }" :aria-pressed="scope === 'starter'" :disabled="disabled" @click="emit('scope', 'starter')">
                <span class="mode-label">Starter bank</span><span class="mode-value">{{ selection.target_per_topic }}<small> / topic</small></span><span class="mode-count">{{ selection.remaining }} to review</span>
            </button>
            <button type="button" class="mode variety-mode" :class="{ active: scope === 'all' }" :aria-pressed="scope === 'all'" :disabled="disabled" @click="emit('scope', 'all')">
                <span class="mode-label">More variety <span aria-hidden="true">↗</span></span><span class="mode-value">{{ selection.library_pending }}</span><span class="mode-count">All questions</span>
            </button>
        </div>
        <div class="goal-row"><span><strong>{{ selection.approved_for_goal }}</strong> / {{ selection.goal_total }} approved</span><span>More approved = more variety</span></div>
        <progress :value="selection.approved_for_goal" :max="selection.goal_total || 1" aria-label="Starter bank approval progress" />
    </section>
</template>
<script setup>
defineProps({ selection: { type: Object, required: true }, scope: String, disabled: Boolean });
const emit = defineEmits(['scope']);
</script>
<style scoped>
.bank-overview { min-width:0; margin:10px 0 12px; }.bank-modes { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
button { box-sizing:border-box; cursor:pointer; font:inherit; color:var(--app-text); background:var(--app-panel); border:1px solid var(--app-border); border-radius:10px; min-width:0; }button:disabled { opacity:.55; cursor:default; }button:focus-visible { outline:3px solid var(--app-accent); outline-offset:3px; }
.mode { display:grid; grid-template-columns:1fr auto; align-items:center; gap:4px 10px; padding:12px 16px; text-align:left; min-height:92px; }.mode-label { font-size:.9rem; font-weight:650; }.mode-value { grid-column:2; grid-row:1 / 3; font-size:2rem; font-weight:750; line-height:1.1; }.mode-value small { display:block; font-size:.7rem; font-weight:500; margin-top:4px; color:var(--app-muted); }.mode-count { font-size:.78rem; color:var(--app-muted); }.mode.active { border-color:#7db4ff; background:rgba(59,130,246,.13); box-shadow:inset 0 0 0 1px #7db4ff; }.mode.active .mode-value { color:#a9d0ff; }
.goal-row { display:flex; justify-content:space-between; flex-wrap:wrap; gap:4px 12px; font-size:.76rem; color:var(--app-muted); margin:10px 0 5px; }.goal-row strong { color:var(--app-text); }progress { appearance:none; display:block; width:100%; height:6px; border:0; border-radius:8px; background:var(--app-border); }progress::-webkit-progress-bar { background:var(--app-border); border-radius:8px; }progress::-webkit-progress-value { background:#60a5fa; border-radius:8px; }progress::-moz-progress-bar { background:#60a5fa; border-radius:8px; }
@media(max-width:650px) { .mode { padding:11px; min-height:100px; grid-template-columns:1fr; gap:6px; }.mode-value { grid-column:1; grid-row:auto; font-size:1.7rem; }.mode-value small { display:inline; margin:0; }.mode-label { font-size:.8rem; }.mode-count { font-size:.72rem; }.topic-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }}
@media(forced-colors:active) { .mode.active { outline:2px solid Highlight; }}
</style>
