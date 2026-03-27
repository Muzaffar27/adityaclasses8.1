import { defineStore } from "pinia";
import { ref, computed } from "vue";
import api from "../api";

export const useAuthStore = defineStore("auth", () => {
    // ── State ─────────────────────────────────────────────────────────────────
    const user = ref(null);
    const isInitialLoading = ref(true);

    // ── Getters ───────────────────────────────────────────────────────────────
    const isLoggedIn = computed(() => !!user.value);
    const isStudent = computed(() => user.value?.role === "student");
    const isTutor = computed(() => user.value?.role === "tutor");
    const isAdmin = computed(() => user.value?.role === "admin");

    // ── Actions ───────────────────────────────────────────────────────────────
    async function fetchUser() {
        const token = localStorage.getItem("auth_token");
        console.log("fetchUser called, token:", token);

        if (!token) {
            user.value = null;
            isInitialLoading.value = false;
            return;
        }

        try {
            const { data } = await api.get("/me");
            console.log("fetchUser success:", data);
            user.value = data.user;
        } catch (err) {
            const status = err.response?.status;
            console.error("fetchUser failed:", status, err.response?.data);

            // Only wipe token if explicitly unauthorized
            if (status === 401) {
                localStorage.removeItem("auth_token");
                user.value = null;
            }
            // Network errors, 500s, etc. — leave the token alone
        } finally {
            isInitialLoading.value = false;
        }
    }

    async function register(payload) {
        // await api.get("/sanctum/csrf-cookie");
        const { data } = await api.post("/register", payload);
        user.value = data.user;
        localStorage.setItem("auth_token", data.token);
    }

    async function login(payload) {
        // await api.get("/sanctum/csrf-cookie");
        const { data } = await api.post("/login", payload);
        console.log("Login response data:", data);
        user.value = data.user;
        localStorage.setItem("auth_token", data.token); // save token
    }

    async function logout() {
        try {
            // Optional: only call API if token exists
            if (localStorage.getItem("auth_token")) {
                await api.post("/logout");
            }
        } catch (err) {
            console.error("Logout API failed, clearing local state anyway.");
        } finally {
            // CRITICAL: Clear local state FIRST before any redirects
            user.value = null;
            localStorage.removeItem("auth_token");
        }
    }

    return {
        user,
        isLoggedIn,
        isInitialLoading,
        isStudent,
        isTutor,
        isAdmin,
        fetchUser,
        register,
        login,
        logout,
    };
});
