<template>
    <div class="login-wrapper">
        <div class="login-card">

            <h1 class="login-title">Welcome Back 👋</h1>

            <form @submit.prevent="submit">

                <div class="field">
                    <label>Email</label>
                    <input v-model="form.email" type="email" placeholder="you@email.com" class="input-field" />
                    <p v-if="errors.email" class="error-text">{{ errors.email }}</p>
                </div>

                <div class="field">
                    <label>Password</label>
                    <input v-model="form.password" type="password" placeholder="Your password" class="input-field" />
                    <p v-if="errors.password" class="error-text">{{ errors.password }}</p>
                </div>

                <div class="remember">
                    <label>
                        <input v-model="form.remember" type="checkbox" />
                        Remember me
                    </label>
                </div>

                <p v-if="generalError" class="error-text">{{ generalError }}</p>

                <button type="submit" class="login-btn" :disabled="loading">
                    {{ loading ? 'Logging in...' : 'Login' }}
                </button>

            </form>

            <p class="register-link">
                No account yet?
                <router-link to="/register">Register</router-link>
            </p>

        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const form = reactive({
    email: '',
    password: '',
    remember: false,
})

const errors = reactive({})
const generalError = ref('')
const loading = ref(false)

async function submit() {
    Object.keys(errors).forEach(k => delete errors[k])
    generalError.value = ''
    loading.value = true

    try {
        await auth.login({
            email: form.email,
            password: form.password,
            remember: form.remember,
        })
        router.push({ name: 'home' })
    } catch (err) {
        if (err.validationErrors) {
            Object.assign(errors, err.validationErrors)
        } else {
            generalError.value = 'Invalid credentials. Please try again.'
        }
    } finally {
        loading.value = false
    }
}
</script>
