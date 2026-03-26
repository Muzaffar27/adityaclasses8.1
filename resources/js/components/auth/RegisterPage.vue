<template>
    <div style="max-width: 400px; margin: 60px auto; padding: 0 1rem;">
        <h1>Register</h1>

        <form @submit.prevent="submit">
            <div>
                <label>Name</label><br>
                <input v-model="form.name" type="text" placeholder="Your name" />
                <p v-if="errors.name" style="color:red;">{{ errors.name }}</p>
            </div>

            <div>
                <label>Email</label><br>
                <input v-model="form.email" type="email" placeholder="you@email.com" />
                <p v-if="errors.email" style="color:red;">{{ errors.email }}</p>
            </div>

            <div>
                <label>Password</label><br>
                <input v-model="form.password" type="password" placeholder="Min 8 characters" />
                <p v-if="errors.password" style="color:red;">{{ errors.password }}</p>
            </div>

            <div>
                <label>Confirm Password</label><br>
                <input v-model="form.passwordConfirmation" type="password" placeholder="Repeat password" />
            </div>

            <p v-if="generalError" style="color:red;">{{ generalError }}</p>

            <br>
            <button type="submit" :disabled="loading">
                {{ loading ? 'Registering...' : 'Register' }}
            </button>
        </form>

        <p>Already have an account? <router-link to="/login">Login</router-link></p>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const form = reactive({
    name: '',
    email: '',
    password: '',
    passwordConfirmation: '',
})

const errors = reactive({})
const generalError = ref('')
const loading = ref(false)
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
        router.push({ name: 'home' })
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
