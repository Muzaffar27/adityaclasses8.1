import axios from "axios";

console.log("API baseURL:", import.meta.env.VITE_API_URL);

const api = axios.create({
    baseURL: "/api",
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
    withCredentials: true,
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 422) {
            const raw = error.response.data.errors ?? {};
            error.validationErrors = Object.fromEntries(
                Object.entries(raw).map(([k, v]) => [
                    k,
                    Array.isArray(v) ? v[0] : v,
                ])
            );
        }
        return Promise.reject(error);
    }
);

export default api;
