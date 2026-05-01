import { reactive } from 'vue';

const dialog = reactive({
    open: false,
    type: 'alert',
    title: 'Notice',
    message: '',
    confirmText: 'OK',
    cancelText: 'Cancel',
    resolve: null,
});

function normalizeOptions(options, defaults = {}) {
    if (typeof options === 'string') {
        return {
            ...defaults,
            message: options,
        };
    }

    return {
        ...defaults,
        ...(options || {}),
    };
}

function openDialog(options) {
    if (dialog.resolve) {
        dialog.resolve(false);
    }

    Object.assign(dialog, {
        open: true,
        type: options.type,
        title: options.title,
        message: options.message,
        confirmText: options.confirmText,
        cancelText: options.cancelText,
    });

    return new Promise((resolve) => {
        dialog.resolve = resolve;
    });
}

export function showAlert(options) {
    return openDialog(normalizeOptions(options, {
        type: 'alert',
        title: 'Notice',
        confirmText: 'OK',
        cancelText: 'Cancel',
    }));
}

export function showConfirm(options) {
    return openDialog(normalizeOptions(options, {
        type: 'confirm',
        title: 'Confirm',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
    }));
}

export function useDialogState() {
    const settle = (value) => {
        dialog.open = false;

        if (dialog.resolve) {
            dialog.resolve(value);
            dialog.resolve = null;
        }
    };

    return {
        dialog,
        confirm: () => settle(true),
        cancel: () => settle(false),
    };
}
