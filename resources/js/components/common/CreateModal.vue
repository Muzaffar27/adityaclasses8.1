<template>
    <Teleport to="body">
        <transition name="fade">
            <div v-if="modelValue" ref="modalRoot" class="modal is-active">
                <div class="modal-background" @click="close"></div>

                <div class="modal-card" :class="{ 'is-wide': wide }">
                    <header class="modal-card-head">
                        <div class="modal-heading-copy">
                            <p class="modal-card-title">{{ title }}</p>
                            <p v-if="subtitle" class="modal-card-subtitle">{{ subtitle }}</p>
                        </div>
                        <button class="delete modal-close-button" aria-label="Close" @click="close"></button>
                    </header>

                    <section class="modal-card-body">
                        <slot />
                    </section>

                    <footer v-if="$slots.actions" class="modal-card-foot is-justify-content-flex-end">
                        <slot name="actions" />
                    </footer>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: Boolean,
    title: String,
    subtitle: { type: String, default: '' },
    wide: { type: Boolean, default: false }
});

const modalRoot = ref(null);
let previousBodyOverflow = '';

watch(
    () => props.modelValue,
    async (val) => {
        if (val) {
            previousBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            await nextTick();
            modalRoot.value?.querySelector('input')?.focus();
        } else {
            document.body.style.overflow = previousBodyOverflow;
        }
    }
);

const emit = defineEmits(['update:modelValue']);

const close = () => emit('update:modelValue', false);

onBeforeUnmount(() => {
    document.body.style.overflow = previousBodyOverflow;
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.modal {
    z-index: 10000;
}

.modal-card {
    border-radius: 12px;
    animation: slideUp 0.2s ease;
    overflow: hidden;
}

.modal-card-head {
    align-items: center;
    background: linear-gradient(135deg, #1e293b, #172033);
    border-bottom: 1px solid rgba(148, 163, 184, 0.2);
    gap: 1rem;
    padding: 1rem 1.15rem;
}

.modal-heading-copy {
    min-width: 0;
}

.modal-card-title {
    color: #f8fafc;
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.25;
}

.modal-card-subtitle {
    color: #94a3b8;
    font-size: 0.73rem;
    line-height: 1.35;
    margin-top: 0.2rem;
}

.modal-close-button {
    background-color: rgba(255, 255, 255, 0.09);
    border: 1px solid rgba(148, 163, 184, 0.25);
    flex: 0 0 34px;
    height: 34px;
    margin-left: auto;
    max-height: 34px;
    max-width: 34px;
    min-height: 34px;
    min-width: 34px;
    transition: background-color 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
    width: 34px;
}

.modal-close-button:hover,
.modal-close-button:focus {
    background-color: rgba(99, 102, 241, 0.3);
    border-color: rgba(165, 180, 252, 0.55);
    transform: scale(1.04);
}

.modal-card.is-wide {
    height: min(92vh, 920px);
    width: min(1120px, calc(100vw - 2rem));
}

.modal-card.is-wide .modal-card-head {
    flex: 0 0 auto;
}

.modal-card.is-wide .modal-card-body {
    overflow-y: auto;
    padding: 0;
}

@media (max-width: 768px) {
    .modal-card.is-wide {
        height: calc(100dvh - 1rem);
        margin: 0.5rem;
        max-height: none;
        width: calc(100vw - 1rem);
    }
}

@keyframes slideUp {
    from {
        transform: translateY(10px);
        opacity: 0;
    }

    to {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>
