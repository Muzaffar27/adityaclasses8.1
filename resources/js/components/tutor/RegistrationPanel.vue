<template>
    <section class="registration-panel" aria-labelledby="registrations-heading">
        <header class="panel-heading">
            <div><p class="eyebrow">2027 INTAKE</p><h1 id="registrations-heading">Registrations</h1><p>Review submitted details, follow up with families and track enrolment.</p></div>
            <div class="header-actions"><button type="button" class="secondary" title="Export registrations matching the current filters" :disabled="exporting || loading || searchPending" @click="exportCsv">{{ exporting ? 'Exporting…' : 'Export CSV' }}</button><button type="button" class="secondary" :disabled="loading" @click="loadList">Refresh list</button></div>
        </header>

        <div class="counts" aria-label="All registration counts">
            <button type="button" v-for="key in ['pending', 'new', 'contacted', 'enrolled']" :key="key" :class="{active: filters.status === key}" :aria-pressed="filters.status === key" @click="setStatus(key)"><span>{{ key === 'pending' ? 'Pending · new + contacted' : statusLabel(key) }}</span><strong>{{ counts[key] }}</strong></button>
        </div>

        <form class="filters" @submit.prevent="applyFilters">
            <div class="search-row"><label class="search-field">Search registrations<input v-model="filters.search" type="search" maxlength="100" placeholder="Student, parent, reference, email or phone" @input="scheduleSearch"></label><button type="submit" class="primary">Search</button><button type="button" class="secondary" @click="clearFilters">Clear filters</button></div>
            <div class="filter-selects">
                <label>Registration status<select aria-label="Filter by registration status" v-model="filters.status" @change="applyFilters"><option value="pending">Pending (new + contacted)</option><option value="new">New</option><option value="contacted">Contacted</option><option value="enrolled">Enrolled</option><option value="all">All registrations</option></select></label>
                <label>Grade<select aria-label="Filter by grade" v-model="filters.grade" @change="applyFilters"><option value="">All grades</option><option v-for="grade in grades" :key="grade">{{ grade }}</option></select></label>
                <label>Subject<select aria-label="Filter by subject" v-model="filters.subject" @change="applyFilters"><option value="">All subjects</option><option v-for="subject in subjects" :key="subject">{{ subject }}</option></select></label>
                <label>Payment<select aria-label="Filter by payment status" v-model="filters.payment" @change="applyFilters"><option value="">All payment statuses</option><option value="unverified">Not verified</option><option value="verified">Verified</option></select></label>
                <label>Email<select aria-label="Filter by email status" v-model="filters.email" @change="applyFilters"><option value="">All email statuses</option><option value="pending">Pending — not sent</option><option value="sent">Sent</option></select></label>
            </div>
        </form>
        <p v-if="exportError" class="error-message" role="alert">{{ exportError }}</p>

        <section ref="listPanel" class="inbox-list" aria-label="Registration list" :aria-busy="loading" tabindex="-1">
            <div class="list-toolbar">
                <p class="list-caption">Showing {{ meta.from || 0 }}–{{ meta.to || 0 }} of {{ meta.total }} registrations</p>
                <div class="list-preferences">
                    <label>Sort by<select v-model="filters.sort" aria-label="Sort registrations" @change="applyFilters"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="student">Student A–Z</option></select></label>
                    <label>Rows per page<select v-model.number="filters.per_page" aria-label="Rows per page" @change="applyFilters"><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label>
                </div>
                <nav v-if="meta.last_page > 1" class="quick-pages" aria-label="Quick page navigation"><button type="button" class="secondary" :disabled="loading || meta.page <= 1" @click="changePage(meta.page - 1)">Previous</button><span>{{ meta.page }} / {{ meta.last_page }}</span><button type="button" class="secondary" :disabled="loading || meta.page >= meta.last_page" @click="changePage(meta.page + 1)">Next</button></nav>
            </div>
            <p v-if="loading" class="state-message" role="status">Loading registrations…</p>
            <div v-else-if="listError" class="state-message" role="alert"><p>{{ listError }}</p><button type="button" class="secondary" @click="loadList">Retry loading</button></div>
            <div v-else-if="!rows.length" class="state-message"><h2>No registrations found</h2><p>Try a different name or clear the filters.</p></div>
            <table v-else class="registrations-table">
                <caption class="table-caption">Registration overview. Open a student to review all details and follow-up notes.</caption>
                <thead><tr><th scope="col">Student / parent</th><th scope="col">Classes</th><th scope="col">Status</th><th scope="col">Monthly fee</th><th scope="col">Submitted</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody><tr v-for="row in rows" :key="row.id" :data-registration-id="row.id" :class="{selected: selected?.id === row.id}">
                    <td class="student-cell"><strong>{{ row.student }}</strong><small class="parent-preview">{{ row.guardian }} · {{ row.email }}</small><span class="reference">{{ row.reference }}</span></td>
                    <td class="classes-cell"><strong>{{ row.grade }}</strong><span class="subject-preview" :title="row.subjects.join(', ')">{{ row.subjects.join(', ') }}</span></td>
                    <td class="progress-cell"><span class="badge" :class="row.registration_status">{{ statusLabel(row.registration_status) }}</span><small>{{ row.payment_status === 'verified' ? 'Payment verified' : 'Payment not verified' }}</small><small :class="{'email-pending': row.email_status === 'pending'}">Email {{ row.email_status }}</small></td>
                    <td class="amount-cell">{{ money(row.monthly_total) }}</td>
                    <td class="submitted-cell"><time>{{ date(row.submitted_at) }}</time></td>
                    <td class="action-cell"><button type="button" class="secondary" :aria-label="`View registration for ${row.student}`" :disabled="saving || detailLoading || resending" @click="openRegistration(row.id)">View details</button></td>
                </tr></tbody>
            </table>
            <nav v-if="meta.last_page > 1" class="pagination" aria-label="Registration pages">
                <div class="page-buttons"><button type="button" class="secondary" :disabled="loading || meta.page <= 1" @click="changePage(meta.page - 1)">Previous</button><template v-for="item in pageItems" :key="item"><span v-if="typeof item === 'string'" class="page-gap">…</span><button v-else type="button" class="secondary page-number" :class="{active: meta.page === item}" :aria-label="`Page ${item}`" :aria-current="meta.page === item ? 'page' : undefined" :disabled="loading" @click="changePage(item)">{{ item }}</button></template><button type="button" class="secondary" :disabled="loading || meta.page >= meta.last_page" @click="changePage(meta.page + 1)">Next</button></div>
                <form class="page-jump" @submit.prevent="changePage(jumpPage)"><label>Go to page<input v-model.number="jumpPage" type="number" :min="1" :max="meta.last_page" required aria-label="Go to page"></label><button type="submit" class="secondary" :disabled="loading">Go</button></form>
            </nav>
        </section>

        <dialog ref="detailDialog" class="registration-dialog" aria-labelledby="registration-details-title" @cancel.prevent="closeDetails">
            <header class="drawer-bar">
                <div class="drawer-title-line"><h2 id="registration-details-title">Registration details</h2><button type="button" class="secondary" :disabled="saving || resending" @click="closeDetails">Close details</button></div>
                <nav v-if="selected && !detailLoading" class="drawer-navigation" aria-label="Review registrations on this page">
                    <button type="button" class="secondary" aria-label="Previous registration" :disabled="selectedRowIndex <= 0 || saving || resending" @click="openRegistration(rows[selectedRowIndex - 1].id)">Previous</button>
                    <span>{{ selectedRowIndex >= 0 ? `${selectedRowIndex + 1} of ${rows.length} on this page` : 'Outside current filters' }}</span>
                    <button type="button" class="secondary" aria-label="Next registration" :disabled="selectedRowIndex < 0 || selectedRowIndex >= rows.length - 1 || saving || resending" @click="openRegistration(rows[selectedRowIndex + 1].id)">Next</button>
                </nav>
            </header>
            <section ref="detailPanel" class="registration-detail" aria-label="Registration details" :aria-busy="detailLoading">
                <p v-if="detailLoading" class="state-message" role="status">Loading registration details…</p>
                <div v-else-if="detailError" class="state-message" role="alert"><p>{{ detailError }}</p><button type="button" class="secondary" @click="openRegistration(requestedId, true)">Retry details</button></div>
                <div v-else-if="!selected" class="state-message"><h2>Select a registration</h2><p>Open a submission to see the student's details, timetable, fees and follow-up notes.</p></div>
                <template v-else>
                    <header class="detail-heading"><div><p class="eyebrow">REGISTRATION DETAILS</p><h2>{{ selected.student }}</h2><p class="reference">{{ selected.reference }}</p><p>Submitted {{ date(selected.submitted_at) }}</p></div><button type="button" class="secondary" :disabled="saving || resending" @click="openRegistration(selected.id)">Reload details</button></header>

                    <section class="detail-block"><h3>Student</h3><dl><div><dt>Full name</dt><dd>{{ selected.details.studentName }}</dd></div><div><dt>Grade</dt><dd>{{ selected.details.grade }}</dd></div><div><dt>School</dt><dd>{{ selected.details.school }}</dd></div><div><dt>Student type</dt><dd>{{ selected.details.returning }}</dd></div></dl></section>
                    <section class="detail-block"><h3>Parent / guardian</h3><dl><div><dt>Name</dt><dd>{{ selected.details.guardian }}</dd></div><div><dt>Phone</dt><dd>{{ selected.details.phone }}</dd></div><div><dt>Email</dt><dd>{{ selected.details.email }}</dd></div></dl></section>
                    <section class="detail-block"><h3>Selected classes &amp; fees</h3><p>{{ selected.details.subjects.join(', ') }}</p><ul v-if="selected.details.timetable?.length" class="timetable"><li v-for="(meeting, index) in selected.details.timetable" :key="index"><strong>{{ meeting.subject }}{{ meeting.batch ? ' · ' + meeting.batch : '' }}</strong><span v-if="meeting.note">{{ meeting.note }}</span><span>{{ meeting.day }} · {{ meeting.time }}</span><small>Starts {{ meeting.start }}</small></li></ul><p v-else>Timetable was not recorded on this earlier submission.</p><div class="fee-total"><span>Monthly tuition</span><strong>{{ money(selected.monthly_total) }}</strong><small v-if="selected.details.monthly_fee_per_subject">{{ selected.details.subjects.length }} subject(s) × {{ money(selected.details.monthly_fee_per_subject) }}</small></div></section>
                    <section class="detail-block"><h3>Parent notes &amp; consent</h3><p class="multiline">{{ selected.details.message || 'No additional notes.' }}</p><dl><div><dt>Contact consent</dt><dd>{{ selected.details.consent ? 'Given' : 'Not recorded' }}</dd></div><div><dt>Optional updates</dt><dd>{{ selected.details.updates ? 'Opted in' : 'Not opted in' }}</dd></div></dl></section>
                    <section class="detail-block"><h3>Acknowledgement email</h3><p>{{ selected.email_status === 'sent' ? 'Sent · ' + date(selected.acknowledged_at) : 'Pending — no successful send recorded.' }}</p><p v-if="selected.email_enabled === false" class="hint">Email sending is not enabled yet.</p><button v-if="selected.email_status === 'pending'" type="button" class="secondary resend-button" :disabled="resending || saving || selected.email_enabled === false" @click="resendAcknowledgement">{{ resending ? 'Sending acknowledgement…' : 'Resend acknowledgement' }}</button><p v-if="resendMessage" :class="selected.email_status === 'sent' ? 'saved-message' : 'error-message'" role="status">{{ resendMessage }}</p></section>

                    <form class="follow-up" @submit.prevent="saveRegistration">
                        <h3>Tutor follow-up</h3><p class="hint">Enrolled tracks this registration. Student login and course access are managed separately.</p>
                        <div class="follow-up-selects"><label>Registration status<select aria-label="Registration status" v-model="draft.registration_status" :disabled="saving || resending"><option value="new">New</option><option value="contacted">Contacted</option><option value="enrolled">Enrolled</option></select></label><label>Payment status<select aria-label="Payment status" v-model="draft.payment_status" :disabled="saving || resending"><option value="unverified">Not verified</option><option value="verified">Verified</option></select></label></div>
                        <label v-if="draft.payment_status === 'verified' && selected.payment_status !== 'verified'" class="payment-confirm"><input v-model="confirmPayment" type="checkbox" :disabled="saving || resending" required><span>I have checked the payment and confirm it was received.</span></label>
                        <p v-if="selected.payment_verified_at" class="hint">Payment verified by {{ selected.payment_verified_by || 'staff' }} · {{ date(selected.payment_verified_at) }}</p>
                        <label>Internal notes<textarea v-model="draft.staff_notes" rows="5" maxlength="5000" :disabled="saving || resending" placeholder="Follow-up details, questions or class arrangements"></textarea></label><p class="hint">Visible to tutors/admins only. {{ draft.staff_notes.length }}/5,000 characters.</p>
                        <p v-if="selected.staff_updated_at" class="hint">Last updated by {{ selected.staff_updated_by || 'staff' }} · {{ date(selected.staff_updated_at) }}</p>
                        <p v-if="saveError" class="error-message" role="alert">{{ saveError }}</p><p v-if="savedMessage" class="saved-message" role="status">{{ savedMessage }}</p>
                        <div class="save-actions"><button type="submit" class="primary" :disabled="saving || resending || !dirty || conflict">{{ saving ? 'Saving…' : 'Save follow-up' }}</button><span v-if="dirty" class="hint">Unsaved changes</span><button v-if="conflict" type="button" class="secondary" @click="openRegistration(selected.id)">Reload saved details</button></div>
                    </form>
                </template>
            </section>
        </dialog>
    </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import api from '../../api';

const rows = ref([]), counts = ref({ pending: 0, new: 0, contacted: 0, enrolled: 0 });
const grades = ref([]), subjects = ref([]), meta = ref({page: 1, last_page: 1, total: 0, from: null, to: null});
const filters = reactive({search: '', status: 'pending', grade: '', subject: '', payment: '', email: '', sort: 'newest', per_page: 25});
const loading = ref(false), listError = ref(''), exporting = ref(false), exportError = ref('');
const selected = ref(null), requestedId = ref(null), detailLoading = ref(false), detailError = ref(''), detailPanel = ref(null);
const draft = reactive({registration_status: 'new', payment_status: 'unverified', staff_notes: ''});
const confirmPayment = ref(false), saving = ref(false), saveError = ref(''), savedMessage = ref(''), conflict = ref(false);
const detailDialog = ref(null), listPanel = ref(null), jumpPage = ref(1), searchPending = ref(false);
const resending = ref(false), resendMessage = ref('');
const selectedRowIndex = computed(() => rows.value.findIndex(row => row.id === selected.value?.id));
let listSequence = 0, detailSequence = 0, searchTimer;
const pageItems = computed(() => {
    const numbers = [...new Set([1, meta.value.last_page, meta.value.page - 1, meta.value.page, meta.value.page + 1])]
        .filter(page => page >= 1 && page <= meta.value.last_page).sort((a, b) => a - b);
    const items = [];
    numbers.forEach((page, index) => {if (index && page - numbers[index - 1] > 1) items.push('gap-' + page); items.push(page);});
    return items;
});
const dirty = computed(() => !!selected.value && (draft.registration_status !== selected.value.registration_status
    || draft.payment_status !== selected.value.payment_status || draft.staff_notes !== (selected.value.staff_notes || '')));
const statusLabel = value => ({new: 'New', contacted: 'Contacted', enrolled: 'Enrolled'}[value] || value);
const money = value => value == null ? 'Not recorded' : `Rs ${Number(value).toLocaleString('en-MU')}`;
const date = value => value ? new Intl.DateTimeFormat('en-GB', {dateStyle: 'medium', timeStyle: 'short', timeZone: 'Indian/Mauritius'}).format(new Date(value)) : 'Not recorded';
const message = (error, fallback) => error.response?.status === 403 ? 'Only tutors and admins can access registrations.'
    : error.response?.status === 401 ? 'Your session expired. Please sign in again.'
    : error.response?.status === 422 ? Object.values(error.response.data.errors || {}).flat().join(' ') || fallback
    : error.response?.status === 503 ? 'Registration management setup is pending.' : fallback;
const params = () => Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));

async function loadList() {
    const sequence = ++listSequence;
    loading.value = true; listError.value = '';
    try {
        const {data} = await api.get('/admin/registrations', {params: {...params(), page: meta.value.page}});
        if (sequence !== listSequence) return;
        if (data.meta.page > data.meta.last_page) {meta.value.page = data.meta.last_page; await loadList(); return;}
        rows.value = data.data; meta.value = data.meta; jumpPage.value = data.meta.page; counts.value = data.counts; grades.value = data.grades; subjects.value = data.subjects;
    } catch (error) { if (sequence === listSequence) listError.value = message(error, 'Registrations could not be loaded. Please retry.'); }
    finally { if (sequence === listSequence) loading.value = false; }
}
function applyFilters() { clearTimeout(searchTimer); searchPending.value = false; meta.value.page = 1; loadList(); }
function scheduleSearch() { clearTimeout(searchTimer); searchPending.value = true; searchTimer = setTimeout(applyFilters, 350); }
function setStatus(status) { filters.status = status; applyFilters(); }
function clearFilters() { Object.assign(filters, {search: '', status: 'pending', grade: '', subject: '', payment: '', email: ''}); applyFilters(); }
async function changePage(page) {
    const number = Number(page);
    if (!Number.isInteger(number) || number < 1 || number > meta.value.last_page || loading.value) return;
    meta.value.page = number; await loadList(); await nextTick();
    if (listPanel.value?.getBoundingClientRect().top < 0) listPanel.value.scrollIntoView({block: 'start'});
}
async function closeDetails() {
    if (saving.value || resending.value) return;
    if (dirty.value && !window.confirm('Discard your unsaved follow-up edits?')) return;
    detailSequence++; detailLoading.value = false;
    if (selected.value) setSelected(selected.value);
    detailDialog.value?.close();
    await nextTick();
    const opener = listPanel.value?.querySelector(`[data-registration-id="${selected.value?.id}"] button`);
    (opener || listPanel.value)?.focus({preventScroll: true});
}
function setSelected(data) {
    selected.value = data;
    Object.assign(draft, {registration_status: data.registration_status, payment_status: data.payment_status, staff_notes: data.staff_notes || ''});
    confirmPayment.value = false; saveError.value = ''; conflict.value = false;
}
async function openRegistration(id, retry = false) {
    if (saving.value || resending.value) return;
    if (dirty.value && !retry && !window.confirm('Discard your unsaved follow-up edits and load saved details?')) return;
    const sequence = ++detailSequence;
    requestedId.value = id; detailLoading.value = true; detailError.value = ''; savedMessage.value = ''; resendMessage.value = '';
    await nextTick();
    if (!detailDialog.value.open) detailDialog.value.showModal();
    try {
        const {data} = await api.get(`/admin/registrations/${id}`);
        if (sequence !== detailSequence) return;
        setSelected(data);
        await nextTick();
        detailDialog.value.scrollTop = 0;
    } catch (error) { if (sequence === detailSequence) detailError.value = message(error, 'Registration details could not be loaded. Please retry.'); }
    finally { if (sequence === detailSequence) detailLoading.value = false; }
}
async function saveRegistration() {
    if (saving.value || resending.value || !selected.value || conflict.value) return;
    saving.value = true; saveError.value = ''; savedMessage.value = '';
    try {
        const payload = {...draft, version: selected.value.version};
        if (confirmPayment.value) payload.confirm_payment = true;
        const {data} = await api.put(`/admin/registrations/${selected.value.id}`, payload);
        setSelected(data); savedMessage.value = 'Follow-up saved.';
        await loadList();
    } catch (error) {
        conflict.value = error.response?.status === 409;
        saveError.value = conflict.value ? 'Another tutor updated this registration. Your edits are still here. Copy any notes you need before reloading saved details.'
            : message(error, 'Your changes were not saved. Please retry.');
    } finally { saving.value = false; }
}
async function resendAcknowledgement() {
    if (!selected.value || resending.value || saving.value) return;
    resending.value = true; resendMessage.value = '';
    try {
        const {data} = await api.post(`/admin/registrations/${selected.value.id}/resend-acknowledgement`, {}, {timeout: 20000});
        // Keep unsaved tutor edits and the version they were based on.
        selected.value.email_status = data.registration.email_status;
        selected.value.acknowledged_at = data.registration.acknowledged_at;
        resendMessage.value = data.message;
        await loadList();
    } catch (error) {
        resendMessage.value = error.response?.status === 429 ? 'Please wait a minute before retrying the email.'
            : message(error, 'The email send could not be confirmed. Reload the details before retrying.');
    } finally { resending.value = false; }
}
async function exportCsv() {
    exporting.value = true; exportError.value = '';
    try {
        const {data} = await api.get('/admin/registrations/export', {params: params(), responseType: 'blob'});
        const url = URL.createObjectURL(data), link = document.createElement('a');
        link.href = url; link.download = 'registrations.csv'; link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) { exportError.value = 'The export could not be downloaded. Please retry.'; }
    finally { exporting.value = false; }
}
watch(() => draft.payment_status, () => {confirmPayment.value = false;});
onMounted(loadList);
onBeforeUnmount(() => {clearTimeout(searchTimer); listSequence++; detailSequence++; detailDialog.value?.close();});
</script>

<style scoped>
.registration-panel {color:#e2e8f0; min-width:0; width:100%;}
.panel-heading {display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom:22px;}
.panel-heading h1 {font-size:28px; font-weight:700; margin:4px 0 8px; color:#f8fafc;}
.panel-heading p,.detail-heading > div > p:last-child {font-size:13px; color:#94a3b8;}
.eyebrow {font-size:10px !important; letter-spacing:1.6px; color:#a5b4fc !important;}
.header-actions {display:flex; gap:8px; flex-wrap:wrap;}
button {font:inherit; cursor:pointer;}
button:disabled {opacity:.5; cursor:default;}
.primary,.secondary {border-radius:9px; padding:10px 14px; font-size:12px; font-weight:600; min-height:40px;}
.primary {border:1px solid #6366f1; background:linear-gradient(135deg,#6366f1,#4f46e5); color:white;}
.secondary {border:1px solid #334155; background:#172136; color:#c7d2fe;}
button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible {outline:2px solid #a5b4fc; outline-offset:3px;}
.counts {display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:20px;}
.counts > div {background:rgba(99,102,241,.08); border:1px solid #334155; border-radius:12px; padding:15px;}
.counts span {display:block; font-size:11px; color:#94a3b8;}
.counts strong {display:block; font-size:27px; color:#c7d2fe; margin-top:5px;}
.filters {padding:18px; background:#141e30; border:1px solid #334155; border-radius:12px; margin-bottom:22px;}
label {display:block; font-size:11px; color:#a8b4c7;}
input:not([type=checkbox]),select,textarea {display:block; width:100%; min-width:0; background:#0f172a; color:#f1f5f9; border:1px solid #334155; border-radius:8px; padding:10px; margin-top:6px; font:inherit; font-size:13px;}
.search-row {display:flex; align-items:flex-end; gap:10px; margin-bottom:14px;}
.search-field {flex:1; min-width:0;}
.filter-selects {display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px;}
.inbox-grid {display:grid; grid-template-columns:minmax(0,.9fr) minmax(0,1.15fr); gap:20px; align-items:start;}
.inbox-list,.registration-detail {min-width:0;}
.list-caption {font-size:12px; color:#94a3b8; margin-bottom:12px;}
.registration-list {max-height:1000px; overflow-y:auto; padding-right:5px;}
.registration-card {padding:17px; border:1px solid #334155; border-radius:12px; background:#141e30; margin-bottom:12px;}
.registration-card.selected {border-color:#818cf8; background:#1b2440;}
.card-top,.card-bottom {display:flex; justify-content:space-between; align-items:center; gap:10px;}
.card-top time {font-size:10px; color:#94a3b8;}
.badge {display:inline-block; padding:4px 9px; border-radius:30px; background:#28334b; color:#c7d2fe; font-size:10px;}
.badge.new {background:#312e5b;} .badge.contacted {background:#3b321b; color:#fde68a;} .badge.enrolled {background:#123b34; color:#6ee7b7;}
.registration-card h2 {font-size:17px; color:#f8fafc; font-weight:600; margin:12px 0 6px;}
.card-grade {font-size:12px; line-height:1.7;} .card-parent {font-size:11px; color:#94a3b8; margin-top:7px; overflow-wrap:anywhere;}
.reference {font-size:10px; letter-spacing:.5px; color:#a5b4fc; overflow-wrap:anywhere; margin:9px 0;}
.card-bottom {border-top:1px solid #263246; margin-top:12px; padding-top:12px;}
.card-bottom strong {font-size:13px;} .card-bottom small {font-weight:400; color:#94a3b8; font-size:10px;}
.card-bottom p {font-size:10px; color:#94a3b8; margin-top:5px;}
.card-bottom button {flex-shrink:0;}
.state-message {padding:30px 20px; border:1px dashed #334155; border-radius:12px; font-size:13px; color:#94a3b8; line-height:1.8;}
.state-message h2 {color:#e2e8f0; font-size:17px; margin-bottom:8px;}.state-message button {margin-top:12px;}
.pagination {display:flex; justify-content:space-between; gap:8px; align-items:center; margin-top:14px; font-size:11px;}
.registration-detail {border:1px solid #334155; background:#141e30; border-radius:14px; padding:22px; scroll-margin-top:90px;}
.detail-heading {display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:18px;}
.detail-heading h2 {font-size:21px; color:#f8fafc; font-weight:600; margin-top:7px; overflow-wrap:anywhere;}
.detail-heading button {flex-shrink:0;}
.detail-block {border-top:1px solid #2b374b; padding:18px 0; font-size:12px; line-height:1.8;}
h3 {font-size:14px; font-weight:600; color:#f1f5f9; margin-bottom:12px;}
dl > div {display:grid; grid-template-columns:110px minmax(0,1fr); gap:12px; margin:8px 0;}
dt {color:#94a3b8;} dd {margin:0; overflow-wrap:anywhere;}
.timetable {list-style:none; padding:0; margin:10px 0;}
.timetable li {padding:12px 0; border-bottom:1px solid #263246;}
.timetable li:last-child {border:0;}.timetable span,.timetable small {display:block; color:#94a3b8;}
.fee-total {padding:14px; background:rgba(99,102,241,.1); border-radius:10px; margin-top:12px;}
.fee-total strong {display:block; font-size:25px; color:#c7d2fe;}.fee-total small {color:#94a3b8;}
.multiline {white-space:pre-wrap; overflow-wrap:anywhere;}
.follow-up {border-top:1px solid #334155; padding-top:18px;}
.follow-up-selects {display:grid; grid-template-columns:1fr 1fr; gap:12px; margin:16px 0;}
.hint {font-size:11px; color:#94a3b8; line-height:1.7; margin:8px 0;}
.payment-confirm {display:flex; align-items:flex-start; gap:9px; padding:12px; background:#23322a; border-radius:8px; margin:12px 0; color:#d1fae5; line-height:1.6;}
.payment-confirm input {margin-top:3px; flex-shrink:0;}
.error-message {color:#fda4af; font-size:12px; line-height:1.8; margin:12px 0;}
.saved-message {color:#6ee7b7; font-size:12px; margin:12px 0;}
.save-actions {display:flex; flex-wrap:wrap; align-items:center; gap:12px; margin-top:16px;}
@media(max-width:1200px) {.inbox-grid {grid-template-columns:1fr;} .registration-list {max-height:650px;} .filter-selects {grid-template-columns:1fr 1fr;}}
@media(max-width:600px) {
 .panel-heading {flex-direction:column; gap:12px;}.counts {grid-template-columns:1fr 1fr; gap:8px;}.counts > div {padding:12px;}
 .filters,.registration-detail {padding:15px;}.filter-selects,.follow-up-selects {grid-template-columns:1fr;}
 input:not([type=checkbox]),select,textarea {font-size:16px;}.detail-heading {flex-direction:column;}.card-bottom {align-items:flex-start;}
 dl > div {grid-template-columns:1fr; gap:0;}.search-row {flex-wrap:wrap;}.search-field {flex-basis:100%;}.registration-card {padding:14px;}
 .card-top {align-items:flex-start; flex-wrap:wrap;}.registration-list {max-height:600px;}
}

.counts > button {text-align:left; color:inherit; background:rgba(99,102,241,.08); border:1px solid #334155; border-radius:12px; padding:15px;}
.counts > button.active {border-color:#818cf8; background:rgba(99,102,241,.18);}
.filter-selects {grid-template-columns:repeat(auto-fit,minmax(175px,1fr));}
.list-toolbar {display:flex; justify-content:space-between; flex-wrap:wrap; align-items:center; gap:14px; margin-bottom:16px;}
.list-toolbar .list-caption {margin:0;}
.list-preferences,.quick-pages {display:flex; align-items:center; gap:10px;}
.list-preferences select {min-width:95px; margin-top:4px;}
.quick-pages {font-size:12px;}
.inbox-list {scroll-margin-top:100px;}
.registrations-table {width:100%; table-layout:fixed; border-collapse:collapse; font-size:12px; background:#141e30;}
.table-caption {text-align:left; font-size:11px; color:#94a3b8; padding:0 0 12px;}
.registrations-table th {text-align:left; color:#94a3b8; background:#192338; font-size:11px; font-weight:500; padding:12px 10px; border-bottom:1px solid #334155;}
.registrations-table th:nth-child(1) {width:28%;}.registrations-table th:nth-child(2) {width:22%;}
.registrations-table th:nth-child(3) {width:16%;}.registrations-table th:nth-child(4) {width:10%;}
.registrations-table th:nth-child(5) {width:13%;}.registrations-table th:nth-child(6) {width:11%;}
.registrations-table td {padding:12px 10px; border-bottom:1px solid #2b374b; vertical-align:middle; overflow-wrap:anywhere; line-height:1.6;}
.registrations-table tr.selected td {background:rgba(99,102,241,.13);}
.registrations-table tbody tr:hover td {background:rgba(99,102,241,.08);}
.student-cell > strong {display:block; font-size:13px; color:#f8fafc;}
.parent-preview {display:block; color:#94a3b8; font-size:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.student-cell .reference {display:block; font-size:9px; margin:3px 0 0;}
.classes-cell > strong {display:block; font-size:12px;}
.subject-preview {display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; color:#94a3b8; font-size:11px;}
.progress-cell small {display:block; font-size:10px; color:#94a3b8; margin-top:3px;}
.progress-cell .email-pending {color:#fde68a;}
.amount-cell {color:#c7d2fe; font-weight:600;}
.submitted-cell {font-size:10px; color:#94a3b8;}
.action-cell .secondary {padding:8px 10px; font-size:11px; white-space:nowrap;}
.pagination {align-items:flex-end; flex-wrap:wrap; margin-top:18px;}
.page-buttons {display:flex; gap:6px; align-items:center; flex-wrap:wrap;}
.page-number {min-width:38px; padding:8px;}
.page-number.active {background:#4338ca; border-color:#818cf8; color:white;}
.page-gap {padding:0 2px; color:#94a3b8;}
.page-jump {display:flex; align-items:flex-end; gap:8px;}
.page-jump input {max-width:85px;}
.registration-dialog {inset:0 0 0 auto; margin:0; width:min(680px,100vw); max-width:100vw; height:100dvh; max-height:100dvh; padding:0; border:0; border-left:1px solid #334155; background:#141e30; color:#e2e8f0; overflow-y:auto; overscroll-behavior:contain;}
.registration-dialog::backdrop {background:rgba(2,6,23,.7);}
:global(html:has(.registration-dialog[open])),:global(body:has(.registration-dialog[open])) {overflow:hidden;}
.drawer-bar {position:sticky; top:0; z-index:2; display:flex; justify-content:space-between; align-items:center; gap:12px; padding:15px 20px; background:#182237; border-bottom:1px solid #334155;}
.drawer-bar h2 {margin:0; font-size:15px; font-weight:600;}
.drawer-bar {display:block;}
.drawer-title-line,.drawer-navigation {display:flex; justify-content:space-between; align-items:center; gap:10px;}
.drawer-navigation {margin-top:12px; font-size:11px; color:#94a3b8;}
.registration-dialog .registration-detail {border:0; border-radius:0; padding:20px;}
.resend-button {margin-top:10px;}
.visually-hidden {position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0;}
@media(max-width:900px) {
 .registrations-table,.registrations-table tbody {display:block; background:none;}
 .registrations-table thead {display:none;}
 .registrations-table tbody tr {display:grid; grid-template-columns:minmax(0,1fr) auto; gap:10px; padding:14px; border:1px solid #334155; border-radius:12px; margin-bottom:10px; background:#141e30;}
 .registrations-table td {display:block; padding:0; border:0;}
 .registrations-table tr.selected {border-color:#818cf8;}
 .registrations-table tr.selected td,.registrations-table tbody tr:hover td {background:none;}
 .student-cell {grid-column:1/-1; grid-row:1;}.parent-preview {display:none;}
 .classes-cell {grid-column:1; grid-row:2;}.amount-cell {grid-column:2; grid-row:2; text-align:right;}
 .progress-cell {grid-column:1/-1; grid-row:3; display:flex !important; align-items:center; flex-wrap:wrap; gap:8px;}
 .progress-cell small {display:inline; margin:0;}.submitted-cell {grid-column:1; grid-row:4; align-self:center;}
 .action-cell {grid-column:2; grid-row:4;}
 .list-preferences {width:100%;}.list-preferences > label {flex:1;}
}
@media(max-width:410px) {
 .drawer-bar,.registration-dialog .registration-detail {padding:14px;}.drawer-bar {gap:8px;}
 .drawer-bar h2 {font-size:13px;}.counts > button {padding:12px;}
 .page-buttons .secondary {padding:8px; font-size:11px;}.page-number {min-width:32px;}
}
</style>
