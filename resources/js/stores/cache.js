import { defineStore } from "pinia";
import api from "../api";

export const useCacheStore = defineStore("cache", {
    state: () => ({
        subjects: [],
        grades: [],
        subjectsLoaded: false,
        gradesLoaded: false,
        loading: false,
        pendingReq: 0,
        _interval: 0,
    }),

    actions: {
        async fetchAllMetadata() {
            // Only fetch if one of them isn't loaded yet
            if (this.subjectsLoaded && this.gradesLoaded) return;

            this.loading = true;

            try {
                // Run both requests in parallel for speed
                const [subRes, gradeRes] = await Promise.all([
                    api.get("/subjects"),
                    api.get("/grades"),
                ]);

                this.subjects = subRes.data;
                this.grades = gradeRes.data;

                this.subjectsLoaded = true;
                this.gradesLoaded = true;
            } catch (error) {
                console.error("Cache fetch failed", error);
            } finally {
                this.loading = false;
            }
        },

        // You can still keep individual fetchers if you only need one at a time
        async fetchSubjects() {
            if (this.subjectsLoaded) return;
            const { data } = await api.get("/subjects");
            this.subjects = data;
            this.subjectsLoaded = true;
        },

        async fetchPendingRequest() {
            try {
                const { data } = await api.get("/lesson-access/count");
                this.pendingReq = data.count;
            } catch {
                console.error("Failed to fetch pending requests");
            }
        },

        startPolling() {
            this.fetchPendingRequest();

            this._interval = setInterval(() => {
                this.fetchPendingRequest();
            }, 15000);
        },

        stopPolling() {
            clearInterval(this._interval);
        },

        decrementPending(count = 1) {
            this.pendingReq = Math.max(0, this.pendingReq - count);
        },

        setPending(count) {
            this.pendingReq = count;
        },
    },
});
