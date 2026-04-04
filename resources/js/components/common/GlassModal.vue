<template>
    <transition name="fade">
        <div v-if="modelValue" class="modal-overlay" @click.self="handleOverlay">

            <div class="glass-modal glass-card">

                <!-- HEADER -->
                <div v-if="title" class="modal-header">
                    {{ title }}
                </div>

                <div v-else-if="$slots.header" class="modal-header">
                    <slot name="header" />
                </div>

                <!-- BODY -->
                <div class="modal-body">
                    <slot />

                    <!-- optional message prop -->
                    <p v-if="message">{{ message }}</p>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer">

                    <!-- DEFAULT ACTIONS (confirm mode) -->
                    <template v-if="type === 'confirm'">

                        <button class="button is-primary has-text-white is-small" @click="onConfirmClick"
                            :disabled="loading">
                            {{ confirmText }}
                        </button>

                        <button class="button is-light is-small" @click="close" :disabled="loading">
                            {{ cancelText }}
                        </button>

                    </template>

                    <!-- CUSTOM FOOTER SLOT -->
                    <slot v-else name="footer" />

                </div>

            </div>

        </div>
    </transition>
</template>

<script setup>
const props = defineProps({
    modelValue: Boolean,

    // 🔥 reusable props
    title: String,
    message: String,

    // alert | confirm
    type: {
        type: String,
        default: "alert"
    },

    confirmText: {
        type: String,
        default: "Confirm"
    },

    cancelText: {
        type: String,
        default: "Cancel"
    },

    closeOnOverlay: {
        type: Boolean,
        default: true
    },

    loading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits([
    "update:modelValue",
    "confirm",
    "cancel"
]);

const close = () => {
    emit("update:modelValue", false);
    emit("cancel");
};

const onConfirmClick = () => {
    emit("confirm");
};

const handleOverlay = () => {
    if (props.closeOnOverlay && !props.loading) {
        close();
    }
};
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999;
}

.glass-modal {
    width: 300px;
    max-width: 90%;
    height: auto;

    display: flex;
    flex-direction: column;
    gap: 10px;
    text-align: center;

    padding: 14px;
    border-radius: 14px;

    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(12px);

    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);

    transform: translateZ(0);
    will-change: transform, opacity;

    animation: pop 0.12s ease-out forwards;
}

@keyframes pop {
    from {
        transform: scale(0.9);
        opacity: 0;
    }

    to {
        transform: scale(1);
        opacity: 1;
    }
}

.modal-header {
    font-weight: 600;
    font-size: 14px;
}

.modal-footer {
    display: flex;
    justify-content: center;
    gap: 8px;
}
</style>