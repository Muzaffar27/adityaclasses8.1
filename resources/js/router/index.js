import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";

const RegisterPage = () => import("../components/auth/RegisterPage.vue");
const LoginPage = () => import("../components/auth/LoginPage.vue");
const Dashboard = () => import("../components/Dashboard.vue");
const Homepage = () => import("../components/HomePage.vue");

const routes = [
    {
        path: "/",
        name: "home",
        component: Homepage,
    },
    {
        path: "/register",
        name: "register",
        component: RegisterPage,
        meta: { guestOnly: true },
    },
    {
        path: "/login",
        name: "login",
        component: LoginPage,
        meta: { guestOnly: true },
    },
    {
        path: "/dashboard",
        name: "dashboard",
        component: Dashboard,
        meta: { requiresAuth: true },
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

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.user && localStorage.getItem("auth_token")) {
        await auth.fetchUser();
    }

    // 🚫 Block protected routes if not logged in
    if (to.meta.requiresAuth && !auth.isLoggedIn) {
        return { name: "login" };
    }

    // 🚫 Prevent logged-in users from going to login/register
    if (to.meta.guestOnly && auth.isLoggedIn) {
        return { name: "dashboard" };
    }
});

export default router;
