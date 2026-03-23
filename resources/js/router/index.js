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
        meta: { guestOnly: true },
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

    if (!auth.user) await auth.fetchUser();

    const guestRoutes = ["login", "register", "home"];
    const authRoutes = ["dashboard"];

    if (authRoutes.includes(to.name) && !auth.isLoggedIn) {
        return { name: "login" };
    }

    if (guestRoutes.includes(to.name) && auth.isLoggedIn) {
        return { name: "dashboard" };
    }
});
export default router;
