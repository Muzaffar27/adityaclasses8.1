import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";
import SubjectPage from "../components/SubjectPage.vue";
import RegisterPage from "../components/auth/RegisterPage.vue";
import LoginPage from "../components/auth/LoginPage.vue";
import Dashboard from "../components/Dashboard.vue";
import HomePage from "../components/HomePage.vue";

const routes = [
    {
        path: "/",
        name: "home",
        component: HomePage,
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
        path: "/subject",
        name: "subject",
        component: SubjectPage,
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
