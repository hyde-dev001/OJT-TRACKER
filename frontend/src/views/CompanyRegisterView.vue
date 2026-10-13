<script setup>
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import PasswordRequirements from '../components/PasswordRequirements.vue'
import { apiErrorMessage } from '../services/api'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const router = useRouter()
const formElement = ref(null)
const form = reactive({
  company_name: '',
  first_name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirmation: '',
})
const errors = ref({})
const formError = ref('')

const submit = async () => {
  errors.value = {}
  formError.value = ''

  if (formElement.value && !formElement.value.checkValidity()) {
    formElement.value.reportValidity()
    return
  }

  if (form.password !== form.password_confirmation) {
    errors.value = { password_confirmation: ['Passwords must match.'] }
    return
  }

  try {
    await authStore.registerCompany({ ...form })
    await router.push('/company/interns')
  } catch (error) {
    errors.value = error?.response?.data?.errors ?? {}
    formError.value = apiErrorMessage(error, 'Unable to register your company. Please try again.')
  }
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center px-4 py-12 sm:px-6" aria-labelledby="company-register-title">
    <section class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10" aria-labelledby="company-register-title">
      <RouterLink to="/" data-testid="auth-brand" class="auth-brand inline-flex items-center gap-3" aria-label="OJT Progress Tracker home">
        <span data-testid="auth-brand-logo" class="grid h-9 w-9 place-items-center rounded-lg bg-slate-950 text-sm font-bold text-white" aria-hidden="true">O</span>
        <span data-testid="auth-brand-label" class="auth-brand__label text-sm font-semibold tracking-tight text-slate-950">OJT Progress Tracker</span>
      </RouterLink>

      <h1 id="company-register-title" class="mt-8 text-3xl font-semibold tracking-tight text-slate-950">Register your company</h1>
      <p class="mt-2 text-sm leading-6 text-slate-600">Create the company account you will use to set up intern OJT accounts.</p>

      <form ref="formElement" class="mt-6 space-y-5" @submit.prevent="submit">
        <div>
          <label for="company-name" class="block text-sm font-semibold text-slate-800">Company name</label>
          <input id="company-name" v-model="form.company_name" type="text" autocomplete="organization" required maxlength="255" :aria-invalid="Boolean(errors.company_name)" :aria-describedby="errors.company_name ? 'company-name-error' : undefined" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="errors.company_name" id="company-name-error" class="mt-1 text-sm text-red-700" role="alert">{{ errors.company_name[0] }}</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 sm:gap-3">
          <div>
            <label for="company-first-name" class="block text-sm font-semibold text-slate-800">Account holder first name</label>
            <input id="company-first-name" v-model="form.first_name" type="text" autocomplete="given-name" required maxlength="100" :aria-invalid="Boolean(errors.first_name)" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <p v-if="errors.first_name" data-testid="company-first-name-error" class="mt-1 text-sm text-red-700" role="alert">{{ errors.first_name[0] }}</p>
          </div>
          <div>
            <label for="company-last-name" class="block text-sm font-semibold text-slate-800">Account holder last name</label>
            <input id="company-last-name" v-model="form.last_name" type="text" autocomplete="family-name" required maxlength="100" :aria-invalid="Boolean(errors.last_name)" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <p v-if="errors.last_name" data-testid="company-last-name-error" class="mt-1 text-sm text-red-700" role="alert">{{ errors.last_name[0] }}</p>
          </div>
        </div>

        <div>
          <label for="company-email" class="block text-sm font-semibold text-slate-800">Account holder email</label>
          <input id="company-email" v-model="form.email" type="email" autocomplete="email" required maxlength="255" :aria-invalid="Boolean(errors.email)" :aria-describedby="errors.email ? 'company-email-error' : undefined" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="errors.email" id="company-email-error" data-testid="company-email-error" class="mt-1 text-sm text-red-700" role="alert">{{ errors.email[0] }}</p>
        </div>

        <div>
          <label for="company-password" class="block text-sm font-semibold text-slate-800">Password</label>
          <input id="company-password" v-model="form.password" type="password" autocomplete="new-password" required minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}" aria-describedby="company-password-help company-register-password-rules" :aria-invalid="Boolean(errors.password)" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p id="company-password-help" class="mt-1 text-xs leading-5 text-slate-500">Use at least 12 characters with uppercase, lowercase, a number, and a symbol.</p>
          <PasswordRequirements :password="form.password" id-prefix="company-register" />
          <p v-if="errors.password" data-testid="company-password-error" class="mt-1 text-sm text-red-700" role="alert">{{ errors.password[0] }}</p>
        </div>

        <div>
          <label for="company-password-confirmation" class="block text-sm font-semibold text-slate-800">Confirm password</label>
          <input id="company-password-confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required minlength="12" :aria-invalid="Boolean(errors.password_confirmation)" :aria-describedby="errors.password_confirmation ? 'company-password-confirmation-error' : undefined" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="errors.password_confirmation" id="company-password-confirmation-error" class="mt-1 text-sm text-red-700" role="alert">{{ errors.password_confirmation[0] }}</p>
        </div>

        <p v-if="formError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm leading-5 text-red-800" role="alert">{{ formError }}</p>
        <button type="submit" class="app-button app-button--primary w-full" :disabled="authStore.loading" :aria-busy="authStore.loading">
          {{ authStore.loading ? 'Creating company account...' : 'Create company account' }}
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-slate-600">
        Creating a personal student account?
        <RouterLink to="/register" class="font-semibold text-slate-950 underline underline-offset-4">Register as a student</RouterLink>
      </p>
      <p class="mt-3 text-center text-sm text-slate-600">
        Already registered?
        <RouterLink to="/login" class="font-semibold text-slate-950 underline underline-offset-4">Sign in</RouterLink>
      </p>
    </section>
  </main>
</template>
