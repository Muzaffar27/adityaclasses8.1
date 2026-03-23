import { defineStore } from "pinia";
import { ref, computed } from "vue";
import api from "../api";

export const useAuthStore = defineStore("auth", () => {
    // ── State ─────────────────────────────────────────────────────────────────
    const user = ref(null);

    // ── Getters ───────────────────────────────────────────────────────────────
    const isLoggedIn = computed(() => !!user.value);
    const isStudent = computed(() => user.value?.role === "student");
    const isTutor = computed(() => user.value?.role === "tutor");
    const isAdmin = computed(() => user.value?.role === "admin");

    // ── Actions ───────────────────────────────────────────────────────────────
    async function fetchUser() {
        try {
            console.log("fetchUser: attempting...");
            const { data } = await api.get("/me");
            console.log("fetchUser: success", data);
            user.value = data.user;
        } catch (err) {
            console.log(
                "fetchUser: failed",
                err.response?.status,
                err.response?.data
            );
            user.value = null;
        }
    }
    async function register(payload) {
        await api.get("/sanctum/csrf-cookie");
        const { data } = await api.post("/register", payload);
        user.value = data.user;
    }

    async function login(payload) {
        await api.get("/sanctum/csrf-cookie");
        const { data } = await api.post("/login", payload);
        user.value = data.user;
    }

    async function logout() {
        try {
            await api.post("/logout");
        } finally {
            user.value = null;
        }
    }

    return {
        user,
        isLoggedIn,
        isStudent,
        isTutor,
        isAdmin,
        fetchUser,
        register,
        login,
        logout,
    };
});
