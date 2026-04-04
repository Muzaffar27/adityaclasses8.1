import { computed, ref, unref, watch } from "vue";

export function Pagination(items, perPageCount = 5, options = {}) {
    const { type = "flat" } = options;

    const currentPage = ref(1);
    const perPage = ref(perPageCount);

    // Reset page when data changes
    watch(
        () => unref(items),
        () => {
            currentPage.value = 1;
        }
    );

    // ✅ GROUPED MODE (LESSONS)
    const groupedData = computed(() => {
        if (type !== "grouped") return [];

        const raw = unref(items);
        const groups = {};

        raw.forEach((item) => {
            const topic = item.topic || "General";

            if (!groups[topic]) {
                groups[topic] = [];
            }

            groups[topic].push(item);
        });

        return Object.entries(groups).map(([topic, lessons]) => ({
            topic,
            lessons,
        }));
    });

    // ✅ FLAT MODE (STUDENTS)
    const flatData = computed(() => {
        if (type !== "flat") return [];
        return unref(items);
    });

    // ✅ TOTAL PAGES
    const totalPages = computed(() => {
        const length =
            type === "grouped"
                ? groupedData.value.length
                : flatData.value.length;

        return Math.ceil(length / perPage.value) || 1;
    });

    // ✅ PAGINATED OUTPUT
    const paginatedData = computed(() => {
        const start = (currentPage.value - 1) * perPage.value;
        const end = start + perPage.value;

        if (type === "grouped") {
            return groupedData.value.slice(start, end);
        }

        return flatData.value.slice(start, end);
    });

    // ✅ NAVIGATION
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

    // ✅ PAGE NUMBERS (your nice ellipsis logic)
    const visiblePages = computed(() => {
        const total = totalPages.value;
        const current = currentPage.value;

        if (total <= 7) {
            return Array.from({ length: total }, (_, i) => i + 1);
        }

        const pages = [];

        const push = (p) => pages.push(p);

        push(1);

        if (current > 3) push("...");

        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);

        for (let i = start; i <= end; i++) {
            push(i);
        }

        if (current < total - 2) push("...");

        if (total > 1) push(total);

        return pages;
    });

    return {
        currentPage,
        perPage,
        totalPages,
        visiblePages,
        paginatedData,

        // alias for backward compatibility (LESSONS)
        paginatedTopics: paginatedData,

        goToPage,
        nextPage,
        prevPage,
    };
}
