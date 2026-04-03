<template>
    <transition name="fade">
        <div v-if="modelValue" class="modal is-active">
            <div class="modal-background" @click="close"></div>

            <div class="modal-card">
                <!-- HEADER -->
                <header class="modal-card-head">
                    <p class="modal-card-title">{{ title }}</p>
                    <button class="delete" @click="close"></button>
                </header>

                <!-- BODY -->
                <section class="modal-card-body">
                    <slot />
                </section>

                <!-- FOOTER -->
                <footer class="modal-card-foot is-justify-content-flex-end">
                    <slot name="actions" />
                </footer>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { nextTick, watch } from 'vue';

const props = defineProps({
    modelValue: Boolean,
    title: String
});

watch(
    () => props.modelValue,
    async (val) => {
        if (val) {
            await nextTick();
            document.querySelector('.modal input')?.focus();
        }
    }
);

const emit = defineEmits(['update:modelValue']);

const close = () => emit('update:modelValue', false);
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

/* smoother modal */
.modal-card {
    border-radius: 12px;
    animation: slideUp 0.2s ease;
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