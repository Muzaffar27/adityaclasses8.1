<template>
    <GlassModal :model-value="dialog.open" :type="dialog.type" :title="dialog.title" :message="dialog.message"
        :confirm-text="dialog.confirmText" :cancel-text="dialog.cancelText" @update:modelValue="handleModelUpdate"
        @confirm="confirm" @cancel="cancel">
        <template #footer>
            <button v-if="dialog.type !== 'confirm'" class="button is-primary has-text-white is-small"
                @click="confirm">
                {{ dialog.confirmText }}
            </button>
        </template>
    </GlassModal>
</template>

<script setup>
import GlassModal from './GlassModal.vue';
import { useDialogState } from '../../composables/dialog';

const { dialog, confirm, cancel } = useDialogState();

function handleModelUpdate(value) {
    if (!value) {
        cancel();
    }
}
</script>
