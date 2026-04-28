<template>
    <Layout title="Tutor Control Panel">

        <div class="admin-layout">

            <!-- SIDEBAR -->
            <div class="admin-sidebar">

                <p class="sidebar-title">Tools</p>

                <div class="sidebar-list">

                    <div v-for="tool in tools" :key="tool.key" class="sidebar-item"
                        :class="{ active: currentView.name === tool.key }" @click="openView(tool.key)">
                        <span class="icon">{{ tool.icon }}</span>

                        <div class="text">
                            <p class="name">{{ tool.label }}</p>
                            <p class="desc">Manage</p>
                        </div>

                    </div>

                </div>

            </div>

            <!-- WORKSPACE (IMPORTANT PART) -->
            <div class="admin-content">

                <component :is="activeToolComponent" v-bind="currentView.props" @navigate="handleNavigation" />
            </div>

        </div>

    </Layout>
</template>
<script setup>
import { ref, computed } from "vue";
import Layout from './common/Layout.vue';

/* tools */
import LessonTool from "./tools/LessonTool.vue";
import LessonList from "./Lesson/LessonList.vue";
import LessonAll from "./Lesson/LessonAll.vue";
import AccessRequestPage from "./AccessRequestPage.vue";
import PackageBuilder from "./PackageBuilder.vue";
import Announcement from "./Announcement.vue";
import PackageList from "./PackageList.vue";

const tools = [
    { key: "announcement", label: "Announcement", icon: "📢" },
    { key: "lessons", label: "Lessons", icon: "📚" },
    { key: "access", label: "Student Access", icon: "👥" },
    { key: "package", label: "Package", icon: "📂" },
    { key: "packageList", label: "Package List", icon: "📦" }
];

/* 🔥 NEW: central state */
const currentView = ref({
    name: "announcement",
    props: {}
});

/* component map */
const toolMap = {
    lessons: LessonAll,
    lessonList: LessonList,
    access: AccessRequestPage,
    package: PackageBuilder,
    packageList: PackageList,
    announcement: Announcement
};

/* active component */
const activeToolComponent = computed(() => {
    return toolMap[currentView.value.name];
});

/* sidebar click */
function openView(name) {
    currentView.value = {
        name,
        props: {}
    };
}

/* 🔥 universal navigation handler */
function handleNavigation(event) {
    // event = { name: 'lessonList', props: {...} }

    currentView.value = {
        name: event.name,
        props: event.props || {}
    };
}
</script>


<style scoped>
.admin-layout {
    display: flex;
    gap: 16px;
}

/* LEFT SIDEBAR */
.admin-sidebar {
    width: 240px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 16px;
    padding: 14px;
    backdrop-filter: blur(16px);
}

.sidebar-title {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #6366f1;
    margin-bottom: 10px;
}

.sidebar-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sidebar-item {
    display: flex;
    align-items: center;
    gap: 10px;

    padding: 10px;
    border-radius: 12px;

    cursor: pointer;
    transition: 0.2s ease;

    background: rgba(255, 255, 255, 0.02);
    border: 1px solid transparent;
}

.sidebar-item:hover {
    background: rgba(99, 102, 241, 0.08);
    border-color: rgba(99, 102, 241, 0.2);
    transform: translateX(2px);
}

.sidebar-item.active {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.4);
}

.icon {
    font-size: 18px;
}

.text .name {
    font-size: 0.75rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
}

.text .desc {
    font-size: 0.65rem;
    color: #94a3b8;
    margin: 0;
}

/* RIGHT SIDE */
.admin-content {
    flex: 1;
}
</style>