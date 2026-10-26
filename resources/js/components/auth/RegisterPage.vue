<template>
    <div class="login-wrapper">
        <div class="login-card">

            <div class="auth-brand">
                <img :src="logoUrl" alt="Aditya Classes">
                <div>
                    <h1 class="login-title">Create Account</h1>
                    <p class="auth-subtitle">Start learning with Aditya Classes</p>
                </div>
            </div>

            <form @submit.prevent="submit">

                <div class="field">
                    <label>Name</label>
                    <input v-model="form.name" type="text" placeholder="Your name" class="input-field" />
                    <p v-if="errors.name" class="error-text">{{ errors.name }}</p>
                </div>

                <div class="field">
                    <label>Email</label>
                    <input v-model="form.email" type="email" placeholder="you@email.com" class="input-field" />
                    <p v-if="errors.email" class="error-text">{{ errors.email }}</p>
                </div>

                <div class="field">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input v-model="form.password" :type="showPassword ? 'text' : 'password'"
                            placeholder="Min 8 characters" class="input-field" />
                        <component :is="showPassword ? EyeSlashIcon : EyeIcon" class="eye-icon mt-1"
                            @click="showPassword = !showPassword" />
                    </div>
                    <p v-if="errors.password" class="error-text">{{ errors.password }}</p>
                </div>

                <div class="field">
                    <label>Confirm Password</label>
                    <div class="password-wrapper">
                        <input v-model="form.passwordConfirmation" :type="showConfirmPassword ? 'text' : 'password'"
                            placeholder="Repeat password" class="input-field" />
                        <component :is="showConfirmPassword ? EyeSlashIcon : EyeIcon" class="eye-icon mt-1"
                            @click="showConfirmPassword = !showConfirmPassword" />
                    </div>
                </div>

                <p v-if="form.passwordConfirmation && !passwordsMatch" class="error-text">
                    Passwords do not match
                </p>
                <p v-if="generalError" class="error-text">{{ generalError }}</p>

                <button type="submit" class="login-btn" :disabled="loading || !passwordsMatch">
                    {{ loading ?
                        'Registering...' : 'Register' }}
                </button>

            </form>

            <p class="register-link">
                Already have an account?
                <router-link to="/login">Login</router-link>
            </p>

        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const auth = useAuthStore()
const logoUrl = '/menu_logo.png';

const form = reactive({
    name: '',
    email: '',
    password: '',
    passwordConfirmation: '',
})

const errors = reactive({})
const generalError = ref('')
const loading = ref(false)
const showPassword = ref(false)
const showConfirmPassword = ref(false)

const passwordsMatch = computed(() =>
    form.password && form.password === form.passwordConfirmation
);

async function submit() {
    Object.keys(errors).forEach(k => delete errors[k])
    generalError.value = ''
    loading.value = true

    try {
        await auth.register({
            name: form.name,
            email: form.email,
            password: form.password,
            password_confirmation: form.passwordConfirmation,
        })
        router.replace({ name: 'dashboard' })
    } catch (err) {
        console.error('Full error:', err)
        console.error('Response:', err.response)
        console.error('Response data:', err.response?.data)
        console.error('Validation errors:', err.validationErrors)

        if (err.validationErrors) {
            Object.assign(errors, err.validationErrors)
        } else {
            generalError.value = err.response?.data?.message || 'Something went wrong. Please try again.'
        }
    } finally {
        loading.value = false
    }
}
</script>

<style scoped src="./auth.css"></style>

<style scoped>
.login-card {
    max-width: 460px;
}

.auth-brand {
    margin-bottom: 20px;
}
</style>
