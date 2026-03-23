<template>
    <div style="max-width: 400px; margin: 60px auto; padding: 0 1rem;">
        <h1>Login</h1>

        <form @submit.prevent="submit">
            <div>
                <label>Email</label><br>
                <input v-model="form.email" type="email" placeholder="you@email.com" />
                <p v-if="errors.email" style="color:red;">{{ errors.email }}</p>
            </div>

            <div>
                <label>Password</label><br>
                <input v-model="form.password" type="password" placeholder="Your password" />
                <p v-if="errors.password" style="color:red;">{{ errors.password }}</p>
            </div>

            <div>
                <label>
                    <input v-model="form.remember" type="checkbox" />
                    Remember me for 90 days
                </label>
            </div>

            <p v-if="generalError" style="color:red;">{{ generalError }}</p>

            <br>
            <button type="submit" :disabled="loading">
                {{ loading ? 'Logging in...' : 'Login' }}
            </button>
        </form>

        <p>No account yet? <router-link to="/register">Register</router-link></p>
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
        router.push({ name: 'dashboard' })
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
