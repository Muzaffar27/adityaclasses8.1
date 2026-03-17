import { createRouter, createWebHistory } from "vue-router";

// Import your pages
import Dashboard from "../components/Dashboard.vue";
import Homepage from "../components/HomePage.vue";

const routes = [
    {
        path: "/",
        name: "home",
        component: Homepage,
    },
    {
        path: "/dashboard",
        name: "dashboard",
        component: Dashboard,
    },
    {
        path: "/:catchAll(.*)",
        redirect: "/",
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
