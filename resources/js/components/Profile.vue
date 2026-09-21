<template>
    <Layout title="My Profile" :loading="false">
        <div v-if="user" class="profile-page">
            <div v-if="profile.must_change_password" class="password-notice" role="alert">
                <div>
                    <strong>Your temporary password needs to be changed.</strong>
                    <p>Choose a private password below before continuing with your lessons.</p>
                </div>
                <a href="#security" class="notice-link">Change now</a>
            </div>

            <div class="profile-grid">
                <aside class="summary-card">
                    <div class="profile-avatar">{{ userInitials }}</div>
                    <h2>{{ user.name }}</h2>
                    <p class="summary-email">{{ user.email }}</p>

                    <div class="summary-tags">
                        <span class="summary-tag">{{ roleLabel }}</span>
                        <span v-if="isStudent && profile.grade" class="summary-tag grade-tag">
                            {{ profile.grade.name }}
                        </span>
                    </div>

                    <div class="summary-meta">
                        <div>
                            <span>Member since</span>
                            <strong>{{ joinedDate }}</strong>
                        </div>
                        <div v-if="isStudent">
                            <span>Academic year</span>
                            <strong>{{ profile.academic_year || 'Not set' }}</strong>
                        </div>
                    </div>
                </aside>

                <main class="profile-content">
                    <section class="profile-card">
                        <div class="section-heading">
                            <div>
                                <p class="eyebrow">Account</p>
                                <h3>Personal information</h3>
                            </div>
                            <p>Keep your contact details up to date.</p>
                        </div>

                        <form @submit.prevent="updateInfo">
                            <div class="form-grid">
                                <div class="field-block">
                                    <label for="profile-name">Full name</label>
                                    <input id="profile-name" v-model.trim="infoForm.name" class="profile-input"
                                        autocomplete="name" placeholder="Your full name" />
                                    <p v-if="infoErrors.name" class="field-error">{{ infoErrors.name[0] }}</p>
                                </div>

                                <div class="field-block">
                                    <label for="profile-email">Email address</label>
                                    <input id="profile-email" v-model.trim="infoForm.email" class="profile-input"
                                        type="email" autocomplete="email" inputmode="email" placeholder="you@email.com" />
                                    <p v-if="infoErrors.email" class="field-error">{{ infoErrors.email[0] }}</p>
                                </div>

                                <div v-if="isStudent" class="field-block form-span">
                                    <label for="profile-phone">Your phone number <span>(optional)</span></label>
                                    <input id="profile-phone" v-model.trim="infoForm.student_phone" class="profile-input"
                                        type="tel" autocomplete="tel" inputmode="tel" placeholder="e.g. 5 123 4567" />
                                    <p v-if="infoErrors.student_phone" class="field-error">
                                        {{ infoErrors.student_phone[0] }}
                                    </p>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button class="button is-primary has-text-white" type="submit"
                                    :class="{ 'is-loading': infoLoading }" :disabled="infoLoading">
                                    Save changes
                                </button>
                            </div>
                        </form>
                    </section>

                    <section v-if="isStudent" class="profile-card academic-card">
                        <div class="section-heading">
                            <div>
                                <p class="eyebrow">Student details</p>
                                <h3>Class and parent contact</h3>
                            </div>
                            <span class="managed-badge">Update anytime</span>
                        </div>

                        <form @submit.prevent="updateInfo">
                            <div class="form-grid">
                                <div class="field-block">
                                    <label for="profile-grade">Class</label>
                                    <select id="profile-grade" v-model="studentForm.grade_id" class="profile-input">
                                        <option :value="null">Select your class</option>
                                        <option v-for="grade in grades" :key="grade.id" :value="grade.id">
                                            {{ grade.name }}
                                        </option>
                                    </select>
                                    <p v-if="infoErrors.grade_id" class="field-error">{{ infoErrors.grade_id[0] }}</p>
                                </div>

                                <div class="field-block">
                                    <label for="profile-academic-year">Academic year <span>(optional)</span></label>
                                    <input id="profile-academic-year" v-model.trim="studentForm.academic_year"
                                        class="profile-input" placeholder="e.g. 2026" />
                                    <p v-if="infoErrors.academic_year" class="field-error">
                                        {{ infoErrors.academic_year[0] }}
                                    </p>
                                </div>

                                <div class="field-block">
                                    <label for="profile-guardian-name">Parent / guardian name <span>(optional)</span></label>
                                    <input id="profile-guardian-name" v-model.trim="studentForm.guardian_name"
                                        class="profile-input" autocomplete="off" placeholder="Parent or guardian name" />
                                    <p v-if="infoErrors.guardian_name" class="field-error">
                                        {{ infoErrors.guardian_name[0] }}
                                    </p>
                                </div>

                                <div class="field-block">
                                    <label for="profile-guardian-relationship">Relationship <span>(optional)</span></label>
                                    <input id="profile-guardian-relationship"
                                        v-model.trim="studentForm.guardian_relationship" class="profile-input"
                                        autocomplete="off" placeholder="e.g. Mother, father or guardian" />
                                    <p v-if="infoErrors.guardian_relationship" class="field-error">
                                        {{ infoErrors.guardian_relationship[0] }}
                                    </p>
                                </div>

                                <div class="field-block">
                                    <label for="profile-guardian-phone">Parent WhatsApp number</label>
                                    <div v-if="parentPhoneLocked" class="locked-phone">
                                        <strong>{{ profile.guardian_phone }}</strong>
                                        <span class="locked-badge">Tutor managed</span>
                                    </div>
                                    <input v-else id="profile-guardian-phone"
                                        v-model.trim="studentForm.guardian_phone" class="profile-input" type="tel"
                                        inputmode="tel" autocomplete="off" placeholder="e.g. 5 123 4567" />
                                    <p v-if="infoErrors.guardian_phone" class="field-error">
                                        {{ infoErrors.guardian_phone[0] }}
                                    </p>
                                    <p class="field-note">
                                        {{ parentPhoneLocked
                                            ? 'Only your tutor can change or remove this number.'
                                            : 'You can add this once. After saving, only your tutor can change it.' }}
                                    </p>
                                </div>

                                <div class="detail-item consent-item">
                                    <span>WhatsApp report sharing</span>
                                    <strong :class="profile.guardian_report_consent_at ? 'consent-on' : ''">
                                        {{ profile.guardian_report_consent_at ? 'Approved' : 'Not approved' }}
                                    </strong>
                                    <small>Your tutor manages this permission.</small>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button class="button is-primary has-text-white" type="submit"
                                    :class="{ 'is-loading': infoLoading }" :disabled="infoLoading">
                                    Save student details
                                </button>
                            </div>
                        </form>
                    </section>

                    <section id="security" class="profile-card security-card">
                        <div class="section-heading">
                            <div>
                                <p class="eyebrow">Security</p>
                                <h3>Change password</h3>
                            </div>
                            <p>Use at least 8 characters.</p>
                        </div>

                        <form @submit.prevent="updatePassword">
                            <div class="form-grid">
                                <div class="field-block form-span">
                                    <label for="current-password">Current password</label>
                                    <input id="current-password" v-model="passwordForm.current_password"
                                        class="profile-input" type="password" autocomplete="current-password"
                                        placeholder="Enter your current password" />
                                    <p v-if="passwordErrors.current_password" class="field-error">
                                        {{ passwordErrors.current_password[0] }}
                                    </p>
                                </div>

                                <div class="field-block">
                                    <label for="new-password">New password</label>
                                    <input id="new-password" v-model="passwordForm.password" class="profile-input"
                                        type="password" autocomplete="new-password" placeholder="At least 8 characters" />
                                    <p v-if="passwordErrors.password" class="field-error">
                                        {{ passwordErrors.password[0] }}
                                    </p>
                                    <div v-if="passwordForm.password" class="strength-row">
                                        <div class="strength-track">
                                            <span :style="{ width: `${strengthPercent}%`, background: strengthColor }"></span>
                                        </div>
                                        <small :style="{ color: strengthColor }">{{ strengthLabel }}</small>
                                    </div>
                                </div>

                                <div class="field-block">
                                    <label for="confirm-password">Confirm new password</label>
                                    <input id="confirm-password" v-model="passwordForm.password_confirmation"
                                        class="profile-input" type="password" autocomplete="new-password"
                                        placeholder="Repeat your new password" />
                                </div>
                            </div>

                            <div class="form-actions">
                                <button class="button is-primary has-text-white" type="submit"
                                    :class="{ 'is-loading': passwordLoading }" :disabled="passwordLoading">
                                    Update password
                                </button>
                            </div>
                        </form>
                    </section>
                </main>
            </div>
        </div>

        <Modal v-model="showSuccessModal" :title="successMessage">
            <template #footer>
                <button class="button is-primary is-small has-text-white" @click="showSuccessModal = false">OK</button>
            </template>
        </Modal>

        <Modal v-model="showErrorModal" :title="errorMessage">
            <template #footer>
                <button class="button is-danger is-small has-text-white" @click="showErrorModal = false">Got it</button>
            </template>
        </Modal>
    </Layout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import api from '../api'
import Layout from './common/Layout.vue'
import Modal from './common/GlassModal.vue'

const authStore = useAuthStore()
const user = computed(() => authStore.user)
const isStudent = computed(() => user.value?.role === 'student')
const profile = computed(() => user.value?.student_profile || {})
const parentPhoneLocked = computed(() => Boolean(profile.value.guardian_phone?.trim()))

const userInitials = computed(() =>
    user.value?.name?.split(' ').filter(Boolean).map(word => word[0]).join('').toUpperCase().slice(0, 2) || '?'
)

const roleLabel = computed(() => {
    const labels = { student: 'Student', tutor: 'Tutor', admin: 'Administrator' }
    return labels[user.value?.role] || 'Account'
})

const joinedDate = computed(() => user.value?.created_at
    ? new Date(user.value.created_at).toLocaleDateString('en-MU', { month: 'short', year: 'numeric' })
    : 'Not available'
)

const showSuccessModal = ref(false)
const showErrorModal = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const showSuccess = (message) => {
    successMessage.value = message
    showSuccessModal.value = true
}

const showError = (message) => {
    errorMessage.value = message
    showErrorModal.value = true
}

const infoForm = ref({ name: '', email: '', student_phone: '' })
const studentForm = ref({
    grade_id: null,
    academic_year: '',
    guardian_name: '',
    guardian_relationship: '',
    guardian_phone: '',
})
const grades = ref([])
const infoErrors = ref({})
const infoLoading = ref(false)

watch(user, (value) => {
    if (!value) return
    infoForm.value = {
        name: value.name || '',
        email: value.email || '',
        student_phone: value.student_profile?.student_phone || '',
    }
    studentForm.value = {
        grade_id: value.student_profile?.grade_id || null,
        academic_year: value.student_profile?.academic_year || '',
        guardian_name: value.student_profile?.guardian_name || '',
        guardian_relationship: value.student_profile?.guardian_relationship || '',
        guardian_phone: value.student_profile?.guardian_phone || '',
    }
}, { immediate: true })

watch(isStudent, async (value) => {
    if (!value || grades.value.length) return

    try {
        const { data } = await api.get('/grades')
        grades.value = data

        if (profile.value.grade?.id && !grades.value.some(grade => grade.id === profile.value.grade.id)) {
            grades.value.unshift(profile.value.grade)
        }
    } catch (error) {
        console.error('Failed to load grades', error)
    }
}, { immediate: true })

async function updateInfo() {
    infoLoading.value = true
    infoErrors.value = {}

    try {
        const payload = isStudent.value
            ? { ...infoForm.value, ...studentForm.value }
            : infoForm.value
        const { data } = await api.put('/updateUserInfo', payload)
        authStore.user = data.user
        showSuccess('Profile updated successfully')
    } catch (error) {
        if (error.response?.status === 422) {
            infoErrors.value = error.response.data.errors
        } else {
            showError('Failed to update your profile. Please try again.')
        }
    } finally {
        infoLoading.value = false
    }
}

const passwordForm = ref({ current_password: '', password: '', password_confirmation: '' })
const passwordErrors = ref({})
const passwordLoading = ref(false)

const strengthPercent = computed(() => {
    const password = passwordForm.value.password
    if (!password) return 0

    let score = 0
    if (password.length >= 8) score += 25
    if (/[A-Z]/.test(password)) score += 25
    if (/[0-9]/.test(password)) score += 25
    if (/[^A-Za-z0-9]/.test(password)) score += 25
    return score
})

const strengthLabel = computed(() => {
    if (strengthPercent.value <= 25) return 'Weak'
    if (strengthPercent.value <= 50) return 'Fair'
    if (strengthPercent.value <= 75) return 'Good'
    return 'Strong'
})

const strengthColor = computed(() => {
    if (strengthPercent.value <= 25) return '#fb7185'
    if (strengthPercent.value <= 50) return '#facc15'
    if (strengthPercent.value <= 75) return '#4ade80'
    return '#22c55e'
})

async function updatePassword() {
    passwordLoading.value = true
    passwordErrors.value = {}

    try {
        const { data } = await api.put('/updateUserPwd', passwordForm.value)
        authStore.user = data.user
        passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
        showSuccess('Password updated successfully')
    } catch (error) {
        if (error.response?.status === 422) {
            passwordErrors.value = error.response.data.errors
        } else {
            showError('Failed to update your password. Please try again.')
        }
    } finally {
        passwordLoading.value = false
    }
}
</script>

<style scoped>
.profile-page {
    margin: 0 auto;
    max-width: 1120px;
}

.password-notice {
    align-items: center;
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.35);
    border-radius: 14px;
    color: #fef3c7;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding: 1rem 1.15rem;
}

.password-notice p {
    color: rgba(254, 243, 199, 0.74);
    font-size: 0.78rem;
    margin-top: 0.15rem;
}

.notice-link {
    background: #f59e0b;
    border-radius: 9px;
    color: #291500;
    flex: 0 0 auto;
    font-size: 0.76rem;
    font-weight: 800;
    padding: 0.55rem 0.8rem;
}

.profile-grid {
    align-items: start;
    display: grid;
    gap: 1rem;
    grid-template-columns: minmax(220px, 0.7fr) minmax(0, 2fr);
}

.summary-card,
.profile-card {
    background: linear-gradient(145deg, rgba(30, 41, 59, 0.74), rgba(15, 23, 42, 0.78));
    border: 1px solid rgba(148, 163, 184, 0.13);
    border-radius: 18px;
    box-shadow: 0 16px 40px rgba(2, 6, 23, 0.2);
}

.summary-card {
    padding: 1.4rem;
    position: sticky;
    top: 104px;
    text-align: center;
}

.profile-avatar {
    align-items: center;
    background: linear-gradient(135deg, #4f46e5, #818cf8);
    border: 3px solid rgba(255, 255, 255, 0.11);
    border-radius: 22px;
    box-shadow: 0 14px 28px rgba(79, 70, 229, 0.28);
    color: white;
    display: flex;
    font-size: 1.55rem;
    font-weight: 850;
    height: 76px;
    justify-content: center;
    margin: 0 auto 1rem;
    width: 76px;
}

.summary-card h2 {
    color: white;
    font-size: 1.15rem;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.summary-email {
    color: rgba(203, 213, 225, 0.65);
    font-size: 0.78rem;
    margin-top: 0.2rem;
    overflow-wrap: anywhere;
}

.summary-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    justify-content: center;
    margin: 1rem 0 1.15rem;
}

.summary-tag,
.managed-badge {
    background: rgba(99, 102, 241, 0.12);
    border: 1px solid rgba(129, 140, 248, 0.23);
    border-radius: 999px;
    color: #c7d2fe;
    font-size: 0.66rem;
    font-weight: 800;
    padding: 0.35rem 0.58rem;
}

.grade-tag {
    background: rgba(14, 165, 233, 0.1);
    border-color: rgba(56, 189, 248, 0.25);
    color: #bae6fd;
}

.summary-meta {
    border-top: 1px solid rgba(148, 163, 184, 0.12);
    display: grid;
    gap: 0.85rem;
    padding-top: 1rem;
    text-align: left;
}

.summary-meta div {
    display: flex;
    font-size: 0.75rem;
    gap: 0.5rem;
    justify-content: space-between;
}

.summary-meta span {
    color: rgba(203, 213, 225, 0.55);
}

.summary-meta strong {
    color: #f8fafc;
}

.profile-content {
    display: grid;
    gap: 1rem;
    min-width: 0;
}

.profile-card {
    padding: 1.35rem;
}

.section-heading {
    align-items: flex-start;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    margin-bottom: 1.15rem;
}

.section-heading h3 {
    color: #f8fafc;
    font-size: 1rem;
    font-weight: 800;
}

.section-heading > p {
    color: rgba(203, 213, 225, 0.55);
    font-size: 0.72rem;
    margin-top: 0.15rem;
    text-align: right;
}

.eyebrow {
    color: #818cf8;
    font-size: 0.62rem;
    font-weight: 850;
    letter-spacing: 0.1em;
    margin-bottom: 0.16rem;
    text-transform: uppercase;
}

.form-grid,
.detail-grid {
    display: grid;
    gap: 0.9rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.form-span {
    grid-column: 1 / -1;
}

.field-block label {
    color: rgba(226, 232, 240, 0.78);
    display: block;
    font-size: 0.72rem;
    font-weight: 750;
    margin-bottom: 0.42rem;
}

.field-block label span {
    color: rgba(203, 213, 225, 0.45);
    font-weight: 600;
}

.profile-input {
    background: rgba(2, 6, 23, 0.34);
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 10px;
    color: white;
    font-size: 16px;
    min-height: 44px;
    padding: 0 0.8rem;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
    width: 100%;
}

.profile-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.16);
    outline: none;
}

select.profile-input option {
    background: #0f172a;
    color: #f8fafc;
}

.field-error {
    color: #fda4af;
    font-size: 0.68rem;
    margin-top: 0.32rem;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 1rem;
}

.form-actions .button {
    border-radius: 10px;
    font-size: 0.76rem;
    font-weight: 800;
    min-height: 40px;
}

.academic-card {
    border-color: rgba(56, 189, 248, 0.17);
}

.locked-phone {
    align-items: center;
    background: rgba(2, 6, 23, 0.25);
    border: 1px solid rgba(148, 163, 184, 0.13);
    border-radius: 10px;
    display: flex;
    gap: 0.65rem;
    justify-content: space-between;
    min-height: 44px;
    padding: 0.55rem 0.75rem;
}

.locked-phone strong {
    color: #e2e8f0;
    font-size: 0.78rem;
    overflow-wrap: anywhere;
}

.locked-badge {
    background: rgba(148, 163, 184, 0.11);
    border-radius: 999px;
    color: rgba(226, 232, 240, 0.65);
    flex: 0 0 auto;
    font-size: 0.6rem;
    font-weight: 800;
    padding: 0.28rem 0.48rem;
}

.field-note {
    color: rgba(186, 230, 253, 0.62);
    font-size: 0.65rem;
    line-height: 1.4;
    margin-top: 0.35rem;
}

.detail-item {
    background: rgba(2, 6, 23, 0.22);
    border: 1px solid rgba(148, 163, 184, 0.09);
    border-radius: 11px;
    min-width: 0;
    padding: 0.75rem;
}

.detail-item span,
.detail-item strong {
    display: block;
}

.detail-item span {
    color: rgba(203, 213, 225, 0.5);
    font-size: 0.64rem;
    margin-bottom: 0.22rem;
}

.detail-item strong {
    color: #e2e8f0;
    font-size: 0.78rem;
    overflow-wrap: anywhere;
}

.detail-item .consent-on {
    color: #86efac;
}

.consent-item {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 68px;
}

.consent-item small {
    color: rgba(203, 213, 225, 0.46);
    font-size: 0.64rem;
    margin-top: 0.28rem;
}

.security-card {
    background: linear-gradient(145deg, rgba(69, 10, 10, 0.48), rgba(24, 12, 20, 0.88));
    border-color: rgba(248, 113, 113, 0.3);
    box-shadow: 0 16px 40px rgba(2, 6, 23, 0.24), inset 0 1px rgba(254, 202, 202, 0.035);
}

.security-card .eyebrow {
    color: #fca5a5;
}

.security-card .profile-input:focus {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.14);
}

.security-card .form-actions .button {
    background: #b91c1c;
    border-color: transparent;
}

.security-card .form-actions .button:hover {
    background: #dc2626;
}

.strength-row {
    align-items: center;
    display: flex;
    gap: 0.55rem;
    margin-top: 0.45rem;
}

.strength-track {
    background: rgba(148, 163, 184, 0.12);
    border-radius: 99px;
    flex: 1;
    height: 4px;
    overflow: hidden;
}

.strength-track span {
    display: block;
    height: 100%;
    transition: width 0.2s ease;
}

.strength-row small {
    font-size: 0.62rem;
    font-weight: 800;
}

@media (max-width: 768px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }

    .summary-card {
        position: static;
    }

    .form-grid,
    .detail-grid {
        grid-template-columns: 1fr;
    }

    .form-span {
        grid-column: auto;
    }

    .profile-card {
        padding: 1rem;
    }

    .section-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 0.45rem;
    }

    .section-heading > p {
        text-align: left;
    }

    .password-notice {
        align-items: flex-start;
        flex-direction: column;
    }

    .notice-link,
    .form-actions .button {
        text-align: center;
        width: 100%;
    }
}
</style>
