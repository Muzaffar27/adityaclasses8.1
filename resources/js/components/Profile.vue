<template>
    <Layout title="My Profile" :loading="false" :showBack="false">

        <div class="columns">

            <!-- Left: Account Info -->
            <div class="column is-4">
                <div class="box is-shadowless has-background-transparent">

                    <div class="has-text-centered mb-4">
                        <div class="profile-avatar mx-auto mb-3">{{ userInitials }}</div>
                        <p class="has-text-white has-text-weight-semibold is-size-5">{{ user.name }}</p>
                        <p style="color: rgba(255,255,255,0.45); font-size: 0.85rem">{{ user.email }}</p>
                        <span class="tag is-dark-accent mt-2" style="text-transform: capitalize">{{ user.role }}</span>
                    </div>

                    <hr style="background: rgba(255,255,255,0.07); height: 1px; border: none;">

                    <div class="profile-meta mt-3">
                        <div class="profile-meta-row">
                            <span class="profile-meta-label">Member since</span>
                            <span class="profile-meta-value">{{ joinedDate }}</span>
                        </div>
                        <!-- <div class="profile-meta-row">
                            <span class="profile-meta-label">Email status</span>
                            <span v-if="user.email_verified_at" class="tag is-success is-light is-small">Verified</span>
                            <span v-else class="tag is-warning is-light is-small">Unverified</span>
                        </div> -->
                        <div class="profile-meta-row">
                            <span class="profile-meta-label">Role</span>
                            <span class="profile-meta-value" style="text-transform: capitalize">{{ user.role }}</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right: Forms -->
            <div class="column is-8">

                <!-- Personal Info -->
                <div class="box is-shadowless has-background-transparent mb-4"
                    style="border-left: 4px solid #4f46e5; animation: slideDown 0.2s ease-out;">

                    <h3 class="is-size-6 has-text-weight-bold has-text-white mb-4">Personal Information</h3>

                    <div class="columns is-multiline">
                        <div class="column is-6">
                            <label class="label is-small">Full Name</label>
                            <input class="input is-small" v-model="infoForm.name" placeholder="Your full name" />
                            <p v-if="infoErrors.name" class="help is-danger">{{ infoErrors.name[0] }}</p>
                        </div>
                        <div class="column is-6">
                            <label class="label is-small">Email Address</label>
                            <input class="input is-small" type="email" v-model="infoForm.email"
                                placeholder="Your email" />
                            <p v-if="infoErrors.email" class="help is-danger">{{ infoErrors.email[0] }}</p>
                        </div>
                    </div>

                    <div class="buttons is-right mt-2">
                        <button class="button is-small is-primary has-text-white" :class="{ 'is-loading': infoLoading }"
                            :disabled="infoLoading" @click="updateInfo">
                            Save Changes
                        </button>
                    </div>

                </div>

                <!-- Change Password -->
                <div class="box is-shadowless has-background-transparent mb-4"
                    style="border-left: 4px solid #4f46e5; animation: slideDown 0.2s ease-out;">

                    <h3 class="is-size-6 has-text-weight-bold has-text-white mb-4">Change Password</h3>

                    <div class="columns is-multiline">
                        <div class="column is-12">
                            <label class="label is-small">Current Password</label>
                            <input class="input is-small" type="password" v-model="passwordForm.current_password"
                                placeholder="••••••••" />
                            <p v-if="passwordErrors.current_password" class="help is-danger">{{
                                passwordErrors.current_password[0] }}</p>
                        </div>
                        <div class="column is-6">
                            <label class="label is-small">New Password</label>
                            <input class="input is-small" type="password" v-model="passwordForm.password"
                                placeholder="••••••••" />
                            <p v-if="passwordErrors.password" class="help is-danger">{{ passwordErrors.password[0] }}
                            </p>

                            <!-- Strength bar -->
                            <div v-if="passwordForm.password" class="mt-2">
                                <p style="font-size: 0.7rem; color: rgba(255,255,255,0.4); margin-bottom: 4px">
                                    Strength: <span :style="{ color: strengthColor }">{{ strengthLabel }}</span>
                                </p>
                                <div style="height: 3px; background: rgba(255,255,255,0.08); border-radius: 4px">
                                    <div :style="{
                                        width: strengthPercent + '%',
                                        background: strengthColor,
                                        height: '100%',
                                        borderRadius: '4px',
                                        transition: 'all 0.3s ease'
                                    }"></div>
                                </div>
                            </div>
                        </div>
                        <div class="column is-6">
                            <label class="label is-small">Confirm New Password</label>
                            <input class="input is-small" type="password" v-model="passwordForm.password_confirmation"
                                placeholder="••••••••" />
                        </div>
                    </div>

                    <div class="buttons is-right mt-2">
                        <button class="button is-small is-primary has-text-white"
                            :class="{ 'is-loading': passwordLoading }" :disabled="passwordLoading"
                            @click="updatePassword">
                            Update Password
                        </button>
                    </div>

                </div>

                <!-- Danger Zone
                <div class="box is-shadowless has-background-transparent"
                    style="border-left: 4px solid #f14668; animation: slideDown 0.2s ease-out;">

                    <h3 class="is-size-6 has-text-weight-bold mb-2" style="color: #f14668">Danger Zone</h3>
                    <p class="is-size-7 mb-3" style="color: rgba(255,255,255,0.45)">
                        Once you delete your account, all data will be permanently removed.
                    </p>

                    <div class="buttons">
                        <button class="button is-small is-danger is-outlined" @click="showDeleteModal = true">
                            Delete My Account
                        </button>
                    </div>

                </div> -->

            </div>
        </div>

        <!-- Success Modal -->
        <Modal v-model="showSuccessModal" :title="successMessage" type="alert">
            <template #footer>
                <button class="button is-primary is-small has-text-white" @click="showSuccessModal = false">OK</button>
            </template>
        </Modal>

        <!-- Error Modal -->
        <Modal v-model="showErrorModal" :title="errorMessage" type="alert">
            <template #footer>
                <button class="button is-danger is-small has-text-white" @click="showErrorModal = false">Got it</button>
            </template>
        </Modal>

        <!-- Delete Confirm Modal -->
        <Modal v-model="showDeleteModal" title="Delete Account" type="alert" :closeOnOverlay="!deleteLoading">
            <div class="mt-2">
                <p class="is-size-7 mb-3" style="color: rgba(255,255,255,0.6)">
                    Enter your password to confirm. This <strong style="color: #f14668">cannot be undone</strong>.
                </p>
                <label class="label is-small">Password</label>
                <input class="input is-small" type="password" v-model="deletePassword" placeholder="••••••••" />
                <p v-if="deleteError" class="help is-danger mt-1">{{ deleteError }}</p>
            </div>
            <template #footer>
                <button class="button is-small is-danger has-text-white" :class="{ 'is-loading': deleteLoading }"
                    :disabled="deleteLoading" @click="deleteAccount">
                    Yes, Delete
                </button>
                <button class="button is-small is-light" :disabled="deleteLoading" @click="showDeleteModal = false">
                    Cancel
                </button>
            </template>
        </Modal>

    </Layout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api from '../api'
import Layout from './common/Layout.vue'
import Modal from './common/GlassModal.vue'
const router = useRouter()
const authStore = useAuthStore()
const user = computed(() => authStore.user)

// ── Initials ──────────────────────────────────────────────
const userInitials = computed(() =>
    user.value?.name?.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) ?? '?'
)

// ── Joined date ───────────────────────────────────────────
const joinedDate = computed(() =>
    user.value?.created_at
        ? new Date(user.value.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long' })
        : '—'
)

// ── Modals ────────────────────────────────────────────────
const showSuccessModal = ref(false)
const showErrorModal = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const showSuccess = (msg) => { successMessage.value = msg; showSuccessModal.value = true }
const showError = (msg) => { errorMessage.value = msg; showErrorModal.value = true }

// ── Info form ─────────────────────────────────────────────
const infoForm = ref({ name: user.value?.name || '', email: user.value?.email || '' })
const infoErrors = ref({})
const infoLoading = ref(false)

async function updateInfo() {
    infoLoading.value = true
    infoErrors.value = {}

    try {
        const { data } = await api.put('/updateUserInfo', infoForm.value)
        authStore.user = data.user
        showSuccess('Profile updated successfully!')
    } catch (e) {
        if (e.response?.status === 422) {
            infoErrors.value = e.response.data.errors
        } else {
            showError('Failed to update profile. Please try again.')
        }
    } finally {
        infoLoading.value = false
    }
}

// ── Password form ─────────────────────────────────────────
const passwordForm = ref({ current_password: '', password: '', password_confirmation: '' })
const passwordErrors = ref({})
const passwordLoading = ref(false)

const strengthPercent = computed(() => {
    const p = passwordForm.value.password
    if (!p) return 0
    let score = 0
    if (p.length >= 8) score += 25
    if (/[A-Z]/.test(p)) score += 25
    if (/[0-9]/.test(p)) score += 25
    if (/[^A-Za-z0-9]/.test(p)) score += 25
    return score
})

const strengthLabel = computed(() => {
    const s = strengthPercent.value
    if (s <= 25) return 'Weak'
    if (s <= 50) return 'Fair'
    if (s <= 75) return 'Good'
    return 'Strong'
})

const strengthColor = computed(() => {
    const s = strengthPercent.value
    if (s <= 25) return '#f14668'
    if (s <= 50) return '#ffdd57'
    if (s <= 75) return '#4ade80'
    return '#22c55e'
})

async function updatePassword() {
    passwordLoading.value = true
    passwordErrors.value = {}
    try {
        await api.put('/updateUserPwd', passwordForm.value)
        passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
        showSuccess('Password updated successfully!')
    } catch (e) {
        if (e.response?.status === 422) {
            passwordErrors.value = e.response.data.errors
        } else {
            showError('Failed to update password. Please try again.')
        }
    } finally {
        passwordLoading.value = false
    }
}

// ── Delete account ────────────────────────────────────────
const showDeleteModal = ref(false)
const deletePassword = ref('')
const deleteError = ref('')
const deleteLoading = ref(false)

async function deleteAccount() {
    deleteLoading.value = true
    deleteError.value = ''
    try {
        await api.delete('/profile', { data: { password: deletePassword.value } })
        authStore.logout()
        router.push({ name: 'login' })
    } catch (e) {
        deleteError.value = e.response?.data?.message || 'Incorrect password.'
    } finally {
        deleteLoading.value = false
    }
}
</script>

<style scoped>
.label.is-small {
    color: white;
    font-weight: 600;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.profile-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(79, 70, 229, 0.25);
    border: 2px solid rgba(79, 70, 229, 0.5);
    color: #a5b4fc;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    font-weight: 700;
}

.profile-meta {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.profile-meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.profile-meta-label {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.8rem;
}

.profile-meta-value {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.82rem;
    font-weight: 500;
}
</style>