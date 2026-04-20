import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";
import SubjectPage from "../components/SubjectPage.vue";
import RegisterPage from "../components/auth/RegisterPage.vue";
import LoginPage from "../components/auth/LoginPage.vue";
import Dashboard from "../components/Dashboard.vue";
import HomePage from "../components/HomePage.vue";
import GradePage from "../components/GradePage.vue";
import LessonPage from "../components/LessonPage.vue";
import AccessRequestPage from "../components/AccessRequestPage.vue";

import lessonList from "../components/Lesson/LessonList.vue";
import LessonManagePage from "../components/Lesson/LessonManagePage.vue";

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
        path: "/lessonList/:grade_id/:subject_id",
        name: "lessonList",
        component: lessonList,
        meta: { requiresAuth: true },
    },
    {
        path: "/lessons/create",
        name: "lessonCreate",
        component: () => import("../components/Lesson/LessonEditForm.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/lessonManagement",
        name: "lessonManagement",
        component: LessonManagePage,
        meta: { requiresAuth: true },
    },
    {
        path: "/myCourses",
        name: "myCourses",
        component: () => import("../components/MyCourse.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/students",
        name: "studentManagement",
        component: () => import("../components/StudentManagement.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/profile",
        name: "profile",
        component: () => import("../components/Profile.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/package/:id",
        name: "package",
        component: () => import("../components/PackagePage.vue"),
    },
    {
        path: "/createPackage",
        name: "createPackage",
        component: () => import("../components/PackageBuilder.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/tutorSpace",
        name: "tutorSpace",
        component: () => import("../components/TutorSpace.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/subject",
        name: "subject",
        component: SubjectPage,
    },
    {
        path: "/grade/:id",
        name: "grade",
        component: GradePage,
    },
    {
        path: "/lessons/:subjectId/:gradeId",
        name: "lesson",
        component: LessonPage,
        meta: { requiresAuth: true },
    },
    {
        path: "/request-access",
        name: "request-access",
        component: AccessRequestPage,
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

    // Only fetch if main.js hasn't already done it
    if (auth.isInitialLoading && localStorage.getItem("auth_token")) {
        await auth.fetchUser();
    }

    // 🚫 Block protected routes if not logged in
    if (to.meta.requiresAuth && !auth.isLoggedIn) {
        return { name: "login" };
    }

    // 🚫 Prevent logged-in users from going to login/register
    if (to.meta.guestOnly && auth.isLoggedIn) {
        return { name: "home" };
    }
});

export default router;
