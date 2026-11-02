<template>
    <section class="review-question-content" :aria-label="label">
        <div class="content-label">{{ label }}</div>
        <div class="content-body"><slot /></div>
        <div class="content-end">{{ endLabel }}</div>
    </section>
</template>

<script setup>
defineProps({ label: { type: String, default: 'Question content' }, endLabel: { type: String, default: 'End of question' } });
</script>

<style scoped>
.review-question-content {
    --app-text:#172a44; --app-muted:#526479; --app-panel:#eef3f9; --app-border:#bccbdc; --app-accent:#1d4ed8;
    min-width:0; margin:14px 0; padding:18px 20px 12px;
    color:#172a44; background:#fffdf7;
    border:1px solid #c7d3e2; border-left:5px solid #6098dc; border-radius:10px;
    box-shadow:0 3px 12px rgb(0 0 0 / .12);
}
.content-label,.content-end { font-family:system-ui,sans-serif; font-size:.7rem; font-weight:650; letter-spacing:.07em; color:#456181; }
.content-label { text-transform:uppercase; border-bottom:1px solid #d9e0e9; padding-bottom:10px; margin-bottom:14px; }
.content-body { min-width:0; font-family:Georgia,'Times New Roman',serif; font-size:1.16rem; line-height:1.75; }
.content-body :deep(.instructions) { color:#40516b; font-family:Georgia,'Times New Roman',serif; font-size:1rem; font-style:italic; line-height:1.65; margin:0 0 12px; }
.content-body :deep(.question-text) { color:#172a44; font-family:Georgia,'Times New Roman',serif; font-size:1.16rem; line-height:1.75; margin:0; }
.content-body :deep(.question-maths) { color:#172a44; margin:0; padding:12px 4px; }
.content-body :deep(math) { color:#172a44; }
.content-body :deep(.question-diagrams) { font-family:system-ui,sans-serif; font-size:.9rem; }
.content-end { border-top:1px solid #d9e0e9; margin-top:16px; padding-top:9px; text-align:right; letter-spacing:.025em; font-weight:500; }
@media(max-width:600px) { .review-question-content { padding:14px 12px 10px; margin:12px 0; }.content-body,.content-body :deep(.question-text) { font-size:1.08rem; } }
@media(forced-colors:active) { .review-question-content { border:2px solid CanvasText; }.content-label,.content-end { color:CanvasText; } }
</style>
