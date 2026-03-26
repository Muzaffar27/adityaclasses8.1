import axios from "axios";
import { useLoaderStore } from "@/stores/loader"; // Use the @ alias for safety

console.log("API baseURL prod:", import.meta.env.VITE_API_URL);
console.log("API hardoced url: https://adityaclasses.mu/api");

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL,
});

// ── REQUEST INTERCEPTOR ─────────────────────────
api.interceptors.request.use(
    (config) => {
        // 1. Grab the store instance
        const loader = useLoaderStore();

        // 2. Trigger the loading animation
        loader.start();

        const token = localStorage.getItem("auth_token");
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        // Stop loader if the request itself fails to even send
        const loader = useLoaderStore();
        loader.stop();
        return Promise.reject(error);
    }
);

// ── RESPONSE INTERCEPTOR ────────────────────────
api.interceptors.response.use(
    (response) => {
        // 3. Stop the loading animation on success
        const loader = useLoaderStore();
        loader.stop();
        return response;
    },
    (error) => {
        // 4. Stop the loading animation on error (404, 500, etc.)
        const loader = useLoaderStore();
        loader.stop();

        if (error.response?.status === 401) {
            localStorage.removeItem("auth_token");
            // Consider using router.push('/login') if you pass router to this file
            window.location.href = "/login";
        }

        return Promise.reject(error);
    }
);

export default api;
