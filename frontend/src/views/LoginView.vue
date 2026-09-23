<script setup>
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { apiErrorMessage } from '../services/api'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()
const form = reactive({ email: '', password: '' })
const errors = ref({})
const formError = ref('')
const showPassword = ref(false)
const passwordType = computed(() => showPassword.value ? 'text' : 'password')

const submit = async () => {
  errors.value = {}
  formError.value = ''

  try {
    await authStore.login(form)
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/student/')
      ? route.query.redirect
      : '/student/overview'
    await router.push(redirect)
  } catch (error) {
    errors.value = error?.response?.data?.errors ?? {}
    formError.value = errors.value.auth?.[0] || apiErrorMessage(error, 'Unable to sign in. Please try again.')
  }
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center px-4 py-12 sm:px-6" aria-labelledby="login-title">
    <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="login-title">
      <RouterLink to="/" data-testid="auth-brand" class="auth-brand inline-flex items-center gap-3" aria-label="OJT Progress Tracker home">
        <span data-testid="auth-brand-logo" class="grid h-9 w-9 place-items-center rounded-lg bg-slate-950 text-sm font-bold text-white" aria-hidden="true">O</span>
        <span data-testid="auth-brand-label" class="auth-brand__label text-sm font-semibold tracking-tight text-slate-950">OJT Progress Tracker</span>
      </RouterLink>
      <h1 id="login-title" class="mt-8 text-3xl font-semibold tracking-tight text-slate-950">Welcome back</h1>
      <p class="mt-2 text-sm leading-6 text-slate-600">Sign in to track your internship progress and personal work.</p>

      <form class="mt-6 space-y-5" @submit.prevent="submit">
        <div>
          <label for="email" class="block text-sm font-semibold text-slate-800">Email</label>
          <input id="email" v-model="form.email" type="email" autocomplete="email" required :aria-invalid="Boolean(errors.email)" :aria-describedby="errors.email ? 'email-error' : undefined" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="errors.email" id="email-error" class="mt-1 text-sm text-red-700" role="alert">{{ errors.email[0] }}</p>
        </div>

        <div>
          <label for="password" class="block text-sm font-semibold text-slate-800">Password</label>
          <div class="relative mt-2">
            <input id="password" v-model="form.password" :type="passwordType" autocomplete="current-password" required :aria-invalid="Boolean(errors.password)" :aria-describedby="errors.password ? 'password-error' : undefined" class="block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 pr-20 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <button type="button" class="absolute inset-y-0 right-2 my-1 rounded-md px-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950" data-toggle-password :aria-pressed="showPassword" @click="showPassword = !showPassword">
              {{ showPassword ? 'Hide' : 'Show' }}
            </button>
          </div>
          <p v-if="errors.password" id="password-error" class="mt-1 text-sm text-red-700" role="alert">{{ errors.password[0] }}</p>
        </div>

        <p v-if="formError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm leading-5 text-red-800" role="alert">{{ formError }}</p>

        <button type="submit" :disabled="authStore.loading" class="app-button app-button--primary w-full">
          {{ authStore.loading ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>
      <p class="mt-6 text-center text-sm text-slate-600">
        New student?
        <RouterLink to="/register" class="font-semibold text-slate-950 underline underline-offset-4">Create an account</RouterLink>
      </p>
    </section>
  </main>
</template>
