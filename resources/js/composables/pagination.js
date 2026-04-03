import { computed, ref, unref } from "vue";

export function Pagination(items, perPageCount = 5) {
    const currentPage = ref(1);
    const perPage = ref(perPageCount);

    const allTopics = computed(() => {
        const raw = unref(items); // 🔥 FIX: ensures reactivity safety

        const groups = {};

        raw.forEach((lesson) => {
            const topic = lesson.topic || "General";

            if (!groups[topic]) {
                groups[topic] = [];
            }

            groups[topic].push(lesson);
        });

        return Object.entries(groups).map(([topic, lessons]) => ({
            topic,
            lessons,
        }));
    });

    const totalPages = computed(() =>
        Math.ceil(allTopics.value.length / perPage.value)
    );

    const paginatedTopics = computed(() => {
        const start = (currentPage.value - 1) * perPage.value;
        const end = start + perPage.value;

        return allTopics.value.slice(start, end);
    });

    function goToPage(page) {
        if (page < 1 || page > totalPages.value) return;
        currentPage.value = page;
    }

    function nextPage() {
        if (currentPage.value < totalPages.value) currentPage.value++;
    }

    function prevPage() {
        if (currentPage.value > 1) currentPage.value--;
    }

    const visiblePages = computed(() => {
        const total = totalPages.value;
        const current = currentPage.value;

        if (total <= 7) {
            return Array.from({ length: total }, (_, i) => i + 1);
        }

        const pages = [];

        const push = (p) => pages.push(p);

        push(1);

        if (current > 3) {
            push("...");
        }

        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);

        for (let i = start; i <= end; i++) {
            push(i);
        }

        if (current < total - 2) {
            push("...");
        }

        if (total > 1) {
            push(total);
        }

        return pages;
    });

    return {
        currentPage,
        perPage,
        paginatedTopics,
        totalPages,
        visiblePages,
        goToPage,
        nextPage,
        prevPage,
    };
}
