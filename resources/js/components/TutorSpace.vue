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
                            <p class="desc">{{ tool.desc }}</p>
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
import StudentManagement from "./StudentManagement.vue";
import HomeImageManager from "./HomeImageManager.vue";
import HomeDemoEditor from "./HomeDemoEditor.vue";
import HomeFooterEditor from "./HomeFooterEditor.vue";

const tools = [
    { key: "announcement", label: "Announcement", icon: "\u{1F4E2}", desc: "Site notice" },
    { key: "access", label: "Request Access", icon: "\u{1F465}", desc: "Approvals" },
    { key: "students", label: "Students", icon: "\u{1F393}", desc: "List" },
    { key: "lessons", label: "Lessons", icon: "\u{1F4DA}", desc: "Create & Edit" },
    { key: "package", label: "Package", icon: "\u{1F4C2}", desc: "Create" },
    { key: "packageList", label: "Package List", icon: "\u{1F4E6}", desc: "List" },
    { key: "homeImages", label: "Home Images", icon: "\u{1F5BC}\uFE0F", desc: "Manage" },
    { key: "demoVideos", label: "Demo Videos", icon: "\u{1F3AC}", desc: "Samples" },
    { key: "footerContent", label: "Footer Content", icon: "\u{1F3E0}", desc: "Contact" }
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
    students: StudentManagement,
    package: PackageBuilder,
    packageList: PackageList,
    demoVideos: HomeDemoEditor,
    footerContent: HomeFooterEditor,
    homeImages: HomeImageManager,
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
        props: name === "students" ? { embedded: true } : {}
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
    line-height: 1.1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* RIGHT SIDE */
.admin-content {
    flex: 1;
}
</style>
