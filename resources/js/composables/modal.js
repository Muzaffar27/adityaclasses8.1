import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

export function useModal(props, emit, { lockScroll = false, emitCancel = false } = {}) {
    const modalRoot = ref(null);
    let previousBodyOverflow = '';

    if (lockScroll) {
        watch(() => props.modelValue, async visible => {
            if (visible) {
                previousBodyOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                await nextTick();
                modalRoot.value?.querySelector('input')?.focus();
            } else {
                document.body.style.overflow = previousBodyOverflow;
            }
        });

        onBeforeUnmount(() => {
            document.body.style.overflow = previousBodyOverflow;
        });
    }

    function close() {
        emit('update:modelValue', false);
        if (emitCancel) emit('cancel');
    }

    function handleOverlay() {
        if (props.closeOnOverlay && !props.loading) close();
    }

    return { modalRoot, close, handleOverlay };
}
