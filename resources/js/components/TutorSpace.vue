<template>
    <Layout title="Tutor Space">
        <div class="admin-layout">
            <nav class="admin-sidebar" aria-label="Tutor tools">
                <p class="sidebar-title">Tutor tools</p>
                <div class="mobile-navigation">
                    <label for="tutor-section">Section</label>
                    <select id="tutor-section" :value="activeSection" @change="openSection(sections.find(section => section.key === $event.target.value))">
                        <option v-for="section in sections" :key="section.key" :value="section.key">{{ section.label }}</option>
                    </select>
                    <button type="button" class="mobile-review" @click="openView('questionReview')">Question Review <ReviewCount :count="reviewCount" :loading="countsLoading" :error="countsError" /></button>
                </div>
                <div v-for="section in sections" :key="section.key" class="tool-section">
                    <button type="button" class="section-button" :class="{ active: activeSection === section.key }"
                        :aria-expanded="section.tools ? activeSection === section.key : undefined"
                        :aria-controls="section.tools ? `tools-${section.key}` : undefined"
                        :aria-current="!section.tools && activeSection === section.key ? 'page' : undefined"
                        @click="openSection(section)">
                        <component :is="section.icon" class="tool-icon" aria-hidden="true" />
                        <span class="tool-label">{{ section.label }}</span>
                        <ReviewCount v-if="section.key === 'academics' && activeSection !== 'academics'" :count="reviewCount" :loading="countsLoading" :error="countsError" />
                        <ChevronDownIcon v-if="section.tools" class="chevron" :class="{ expanded: activeSection === section.key }" aria-hidden="true" />
                    </button>
                    <div v-if="section.tools && activeSection === section.key" :id="`tools-${section.key}`" class="section-tools">
                        <button v-for="tool in section.tools" :key="tool.key" type="button" class="tool-button"
                            :class="{ active: selectedTool === tool.key }" :aria-current="selectedTool === tool.key ? 'page' : undefined" @click="openView(tool.key)">
                            <span class="tool-label">{{ tool.label }}</span>
                            <ReviewCount v-if="tool.key === 'questionReview'" :count="reviewCount" :loading="countsLoading" :error="countsError" />
                        </button>
                    </div>
                </div>
                <div v-if="countsError" class="count-error" role="status">Review count unavailable. <button type="button" @click="loadReviewCount">Retry</button></div>
            </nav>
            <main class="admin-content">
                <component :is="activeToolComponent" v-bind="currentView.props" @navigate="handleNavigation" @review-counts="updateReviewCount" />
            </main>
        </div>
    </Layout>
</template>
<script setup>
import { ref, computed, defineAsyncComponent, onMounted, onBeforeUnmount, watch } from 'vue';
import { ChevronDownIcon, AcademicCapIcon, UsersIcon, MegaphoneIcon, GlobeAltIcon } from '@heroicons/vue/24/outline';
import Layout from './common/Layout.vue';
import ReviewCount from './tutor/ReviewCount.vue';
import { useAuthStore } from '../stores/auth';
import api from '../api';
import { useQuestionReviewSaves } from '../composables/questionReviewSaves';
import LessonList from './Lesson/LessonList.vue';
import LessonAll from './Lesson/LessonAll.vue';
import AccessRequestPage from './AccessRequestPage.vue';
import PackageBuilder from './PackageBuilder.vue';
import Announcement from './Announcement.vue';
import PackageList from './PackageList.vue';
import StudentManagement from './StudentManagement.vue';
import HomeImageManager from './HomeImageManager.vue';
import HomeDemoEditor from './HomeDemoEditor.vue';
import HomeFooterEditor from './HomeFooterEditor.vue';
import WhatsAppTest from './WhatsAppTest.vue';

const QuestionReviewPanel = defineAsyncComponent(() => import('./tutor/QuestionReviewPanel.vue'));
const RegistrationPanel = defineAsyncComponent(() => import('./tutor/RegistrationPanel.vue'));
const auth = useAuthStore();
const sections = computed(() => [
    { key: 'students', label: 'Students', icon: UsersIcon, tools: [...((auth.isTutor || auth.isAdmin) ? [{ key: 'registrations', label: 'Registrations' }] : []), { key: 'students', label: 'Student list' }, { key: 'access', label: 'Access requests' }] },
    { key: 'academics', label: 'Academics', icon: AcademicCapIcon, tools: [{ key: 'lessons', label: 'Lessons' }, { key: 'packageList', label: 'Packages' }, { key: 'questionReview', label: 'Question Review' }] },
    { key: 'communication', label: 'Communication', icon: MegaphoneIcon, tools: [{ key: 'announcement', label: 'Announcements' }, { key: 'whatsapp', label: 'WhatsApp' }] },
    { key: 'website', label: 'Website Content', icon: GlobeAltIcon, tools: [{ key: 'homeImages', label: 'Home images' }, { key: 'demoVideos', label: 'Demo videos' }, { key: 'footerContent', label: 'Footer content' }] }
]);
const currentView = ref({ name: auth.isTutor || auth.isAdmin ? 'registrations' : 'announcement', props: {} });
const selectedTool = computed(() => ({ package: 'packageList', lessonList: 'lessons' })[currentView.value.name] || currentView.value.name);
const activeSection = computed(() => sections.value.find(section => section.tools?.some(tool => tool.key === selectedTool.value))?.key);
const toolMap = { registrations: RegistrationPanel, questionReview: QuestionReviewPanel, lessons: LessonAll, lessonList: LessonList, access: AccessRequestPage, students: StudentManagement, whatsapp: WhatsAppTest, package: PackageBuilder, packageList: PackageList, demoVideos: HomeDemoEditor, footerContent: HomeFooterEditor, homeImages: HomeImageManager, announcement: Announcement };
const activeToolComponent = computed(() => toolMap[currentView.value.name]);
function openSection(section) {
    if (activeSection.value !== section.key) openView(section.tools?.[0].key || section.key);
}
function openView(name) {
    if (name === currentView.value.name) return;
    if (currentView.value.name === 'questionReview') loadReviewCount();
    currentView.value = { name, props: name === 'students' ? { embedded: true } : {} };
}
function handleNavigation(event) {
    if (!toolMap[event.name]) return;
    currentView.value = { name: event.name, props: event.props || {} };
}
const reviewCount = ref(null), countsLoading = ref(false), countsError = ref(false);
let countSerial = 0, disposed = false, lastCountAt = 0, countRefresh;
function updateReviewCount(counts) {
    if (!counts) { loadReviewCount(); return; }
    countSerial++;
    reviewCount.value = counts;
    countsError.value = false;
    countsLoading.value = false;
    lastCountAt = Date.now();
}
async function loadReviewCount() {
    if (!auth.isTutor && !auth.isAdmin) return;
    const serial = ++countSerial;
    countsLoading.value = true;
    try {
        const { data } = await api.get('/admin/question-reviews', { params: { status: 'pending', review_scope: 'all' } });
        if (disposed || serial !== countSerial) return;
        reviewCount.value = data.notification_counts;
        countsError.value = false;
        lastCountAt = Date.now();
    } catch {
        if (!disposed && serial === countSerial) { reviewCount.value = null; countsError.value = true; }
    } finally { if (!disposed && serial === countSerial) countsLoading.value = false; }
}
const reviewSaves = useQuestionReviewSaves();
watch(reviewSaves.result, () => { clearTimeout(countRefresh); countRefresh = setTimeout(loadReviewCount, 350); });
function refreshOnFocus() { if (Date.now() - lastCountAt > 60000) loadReviewCount(); }
onMounted(() => { loadReviewCount(); window.addEventListener('focus', refreshOnFocus); });
onBeforeUnmount(() => { disposed = true; clearTimeout(countRefresh); window.removeEventListener('focus', refreshOnFocus); });
</script>
<style scoped>
.admin-layout { display:flex; align-items:flex-start; gap:20px; }
.admin-sidebar { box-sizing:border-box; width:240px; flex-shrink:0; padding:12px; border:1px solid var(--app-border); border-radius:var(--app-radius); background:var(--app-glass); }
.sidebar-title { margin:4px 10px 12px; font-size:.7rem; text-transform:uppercase; letter-spacing:.12em; color:var(--app-muted); }
.tool-section + .tool-section { margin-top:5px; }
.section-button,.tool-button { display:flex; align-items:center; gap:9px; width:100%; min-height:44px; padding:10px; border:1px solid transparent; border-radius:10px; background:transparent; color:var(--app-text,#fff); text-align:left; font:inherit; font-size:.82rem; cursor:pointer; }
.section-button { font-weight:600; }
.section-button.active { background:rgba(99,102,241,.12); }
.tool-button.active { background:rgba(99,102,241,.2); border-color:rgba(129,140,248,.4); color:#c7d2fe; }
.section-button:hover,.tool-button:hover { background:rgba(99,102,241,.12); }
button:focus-visible { outline:2px solid #a5b4fc; outline-offset:2px; }
.tool-icon { width:19px; height:19px; flex-shrink:0; color:#a5b4fc; }
.tool-label { flex:1; min-width:0; overflow-wrap:anywhere; }
.chevron { width:14px; height:14px; flex-shrink:0; transform:rotate(-90deg); }
.chevron.expanded { transform:none; }
.section-tools { margin:5px 0 8px 18px; padding-left:8px; border-left:1px solid var(--app-border); }
.tool-button { font-size:.78rem; }
.mobile-navigation { display:none; }
.admin-content { flex:1; min-width:0; width:100%; }
.count-error { margin:12px 6px 2px; font-size:.72rem; color:var(--app-muted); }
.count-error button { margin-left:5px; background:transparent; border:0; color:#c7d2fe; text-decoration:underline; cursor:pointer; }
.admin-content :deep(.package-container) { padding:0 !important; }
@media(max-width:768px) {
    .admin-layout { flex-direction:column; gap:14px; }
    .admin-sidebar { width:100%; }
    .section-button { display:none; }
    .mobile-navigation { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
    .mobile-navigation label { font-size:.75rem; color:var(--app-muted); }
    .mobile-navigation select { box-sizing:border-box; flex:1; min-width:0; min-height:44px; max-width:100%; padding:8px; border:1px solid var(--app-border); border-radius:8px; background:var(--app-panel,#172033); color:var(--app-text,#fff); font:inherit; font-size:.85rem; }
    .mobile-review { display:flex; align-items:center; justify-content:space-between; gap:8px; width:100%; min-height:44px; border:0; border-radius:8px; padding:8px; color:var(--app-text,#fff); background:rgba(99,102,241,.1); font:inherit; font-size:.8rem; cursor:pointer; }
    .section-tools { display:flex; flex-wrap:wrap; gap:4px; margin:8px 0 0; padding:0; border:0; }
    .tool-button { width:auto; flex:1 1 auto; }
    .tool-section + .tool-section { margin:0; }
}
</style>
