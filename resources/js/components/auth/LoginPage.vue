<template>
    <div class="login-wrapper">
        <div class="login-card">

            <h1 class="login-title">Welcome Back 👋</h1>

            <form @submit.prevent="submit">

                <div class="field">
                    <label>Email</label>
                    <input v-model="form.email" type="email" placeholder="you@email.com" class="input-field" />
                    <p v-if="errors.email" class="error-text ml-2">{{ errors.email[0] }}</p>
                </div>

                <div class="field">
                    <label>Password</label>

                    <div class="password-wrapper">
                        <input v-model="form.password" :type="showPassword ? 'text' : 'password'"
                            placeholder="Your password" class="input-field" />

                        <component :is="showPassword ? EyeSlashIcon : EyeIcon" class="eye-icon mt-1"
                            @click="showPassword = !showPassword" title="Toggle password visibility" />
                    </div>

                    <p v-if="errors.password" class="error-text ml-2">{{ errors.password[0] }}</p>
                </div>

                <div class="remember">
                    <label>
                        <input v-model="form.remember" type="checkbox" />
                        Remember me
                    </label>
                </div>

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
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const auth = useAuthStore()

const form = reactive({
    email: '',
    password: '',
    remember: true,
})

const errors = reactive({})
const generalError = ref('')

const loading = ref(false)

const showPassword = ref(false)

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
        if (err.response && err.response.data.errors) {
            Object.assign(errors, err.response.data.errors)
        } else {
            generalError.value = 'Invalid credentials. Please try again.'
        }
    } finally {
        loading.value = false
    }
}
</script>
