<template>
    <div class="login-wrapper">
        <div class="login-card">

            <div class="auth-brand">
                <img :src="`${baseUrl}menu_logo.png`" alt="Aditya Classes">
                <div>
                    <h1 class="login-title">Welcome Back</h1>
                    <p class="auth-subtitle">Continue your learning journey</p>
                </div>
            </div>

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
                <p v-if="generalError" class="error-text mt-2">{{ generalError }}</p>

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
const baseUrl = import.meta.env.BASE_URL || '/'

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

<style scoped>
.login-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background:
        radial-gradient(circle at top, rgba(79, 70, 229, 0.24), transparent 34%),
        linear-gradient(135deg, #0f172a, #111827 52%, #020617);
}

.login-card {
    width: 100%;
    max-width: 420px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.42);
    backdrop-filter: blur(18px);
}

.auth-brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 24px;
    text-align: center;
}

.auth-brand img {
    width: 82px;
    height: 82px;
    object-fit: contain;
    transform: scale(1.8);
    transform-origin: center;
    filter: drop-shadow(0 10px 22px rgba(99, 102, 241, 0.45));
    margin-bottom: 2px;
}

.login-title {
    color: #fff;
    font-size: 1.35rem;
    font-weight: 800;
    margin: 0;
}

.auth-subtitle {
    color: rgba(203, 213, 225, 0.8);
    font-size: 0.78rem;
    margin: 2px 0 0;
}

.field label,
.remember {
    color: rgba(203, 213, 225, 0.88);
}

.input-field {
    background: rgba(15, 23, 42, 0.82);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #fff;
}

.login-btn {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    box-shadow: 0 10px 22px rgba(79, 70, 229, 0.28);
}

.register-link {
    text-align: center;
}

.register-link a {
    color: #a5b4fc;
}

.eye-icon {
    color: rgba(203, 213, 225, 0.8);
}
</style>
