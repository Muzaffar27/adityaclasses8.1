import { ref } from 'vue';

export function useLessonCollection(request, {
    transform = data => data,
    clearOnError = false,
    onError = error => console.error(error),
    onLoaded = null,
} = {}) {
    const lessons = ref([]);
    const loading = ref(false);

    async function loadLessons() {
        loading.value = true;
        try {
            const { data } = await request();
            lessons.value = transform(data);
            if (onLoaded) await onLoaded();
            return true;
        } catch (error) {
            onError(error);
            if (clearOnError) lessons.value = [];
            return false;
        } finally {
            loading.value = false;
        }
    }

    return { lessons, loading, loadLessons };
}
