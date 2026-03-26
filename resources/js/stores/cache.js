import { defineStore } from "pinia";
import axios from "axios";

export const useSubjectStore = defineStore("subjects", {
    state: () => ({
        subjects: [],
        loaded: false,
        loading: false,
    }),

    actions: {
        async fetchSubjects() {
            if (this.loaded) return;

            this.loading = true;

            try {
                const { data } = await axios.get("/api/subjects");
                this.subjects = data;
                this.loaded = true;
            } finally {
                this.loading = false;
            }
        },
    },
});
