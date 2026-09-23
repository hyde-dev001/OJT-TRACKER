<script setup>
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api, { apiErrorMessage } from '../services/api'
import DatePickerField from '../components/DatePickerField.vue'
import PasswordRequirements from '../components/PasswordRequirements.vue'
import { useAuthStore } from '../stores/auth'
import { todayInPhilippines } from '../utils/dateBounds'

const authStore = useAuthStore()
const router = useRouter()
const step = ref(1)
const accountForm = ref(null)
const ojtForm = ref(null)
const form = reactive({
  name: '',
  suffix: '',
  email: '',
  password: '',
  password_confirmation: '',
  required_hours: 500,
  start_date: todayInPhilippines(),
  end_date: '',
  work_days: [1, 2, 3, 4, 5],
  expected_hours_per_day: 8,
})
const workDayOptions = [
  { value: 1, label: 'Mon' },
  { value: 2, label: 'Tue' },
  { value: 3, label: 'Wed' },
  { value: 4, label: 'Thu' },
  { value: 5, label: 'Fri' },
  { value: 6, label: 'Sat' },
  { value: 7, label: 'Sun' },
]
const errors = ref({})
const formError = ref('')
const checkingEmail = ref(false)
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)
const passwordType = computed(() => showPassword.value ? 'text' : 'password')
const passwordConfirmationType = computed(() => showPasswordConfirmation.value ? 'text' : 'password')
const minimumEndDate = computed(() => nextDate(form.start_date))
const dateRangeError = computed(() => (
  form.start_date && form.end_date && form.end_date <= form.start_date
    ? 'Target end date must be after your OJT start date.'
    : ''
))

const accountErrorFields = ['name', 'suffix', 'email', 'password', 'password_confirmation']

const validateAccount = () => {
  errors.value = {}
  formError.value = ''

  if (accountForm.value && !accountForm.value.checkValidity()) {
    accountForm.value.reportValidity()
    return false
  }

  if (form.password !== form.password_confirmation) {
    errors.value = { password_confirmation: ['Passwords must match.'] }
    return false
  }

  return true
}

const checkEmailAvailability = async () => {
  checkingEmail.value = true

  try {
    const response = await api.get('/register/check-email', { params: { email: form.email } })

    if (response?.data?.available === false) {
      errors.value = { email: ['An account already exists with this email.'] }
      return false
    }

    return true
  } catch (error) {
    const emailErrors = error?.response?.data?.errors?.email

    if (emailErrors) {
      errors.value = { email: Array.isArray(emailErrors) ? emailErrors : [emailErrors] }
    } else {
      formError.value = apiErrorMessage(error, 'Unable to check this email. Please try again.')
    }

    return false
  } finally {
    checkingEmail.value = false
  }
}

const continueToOjt = async () => {
  if (checkingEmail.value || !validateAccount()) return

  if (await checkEmailAvailability()) step.value = 2
}

const goBack = () => {
  errors.value = {}
  formError.value = ''
  step.value = 1
}

const submit = async () => {
  errors.value = {}
  formError.value = ''

  if (dateRangeError.value) {
    errors.value = { end_date: [dateRangeError.value] }
    return
  }

  if (!form.work_days.length) {
    errors.value = { work_days: ['Select at least one OJT work day.'] }
    return
  }

  if (ojtForm.value && !ojtForm.value.checkValidity()) {
    ojtForm.value.reportValidity()
    return
  }

  try {
    await authStore.register({
      ...form,
      required_hours: Number(form.required_hours),
      expected_hours_per_day: Number(form.expected_hours_per_day),
      work_days: [...form.work_days],
    })
    await router.push('/student/overview')
  } catch (error) {
    errors.value = error?.response?.data?.errors ?? {}
    if (accountErrorFields.some((field) => errors.value[field])) {
      step.value = 1
    }
    formError.value = apiErrorMessage(error, 'Unable to register. Please try again.')
  }
}

function nextDate(value) {
  if (!value) return ''

  const [year, month, day] = value.split('-').map(Number)
  const date = new Date(year, month - 1, day + 1)

  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-')
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center px-4 py-12 sm:px-6" aria-labelledby="register-title">
    <section class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm sm:p-10" aria-labelledby="register-title">
      <RouterLink to="/" data-testid="auth-brand" class="auth-brand inline-flex items-center gap-3" aria-label="OJT Progress Tracker home">
        <span data-testid="auth-brand-logo" class="grid h-9 w-9 place-items-center rounded-lg bg-slate-950 text-sm font-bold text-white" aria-hidden="true">O</span>
        <span data-testid="auth-brand-label" class="auth-brand__label text-sm font-semibold tracking-tight text-slate-950">OJT Progress Tracker</span>
      </RouterLink>

      <div class="mt-8 flex items-center justify-between gap-4">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Step {{ step }} of 2</p>
        <div class="flex gap-1.5" aria-hidden="true">
          <span class="h-1.5 w-12 rounded-full" :class="step >= 1 ? 'bg-slate-950' : 'bg-slate-200'" />
          <span class="h-1.5 w-12 rounded-full" :class="step >= 2 ? 'bg-slate-950' : 'bg-slate-200'" />
        </div>
      </div>

      <div v-if="step === 1" data-testid="registration-step-1">
        <h1 id="register-title" class="mt-5 text-3xl font-semibold tracking-tight text-slate-950">Create your account</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Set up your student account first.</p>
        <p class="mt-6 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Account information</p>

        <form ref="accountForm" data-testid="account-setup-form" class="mt-3 space-y-5" @submit.prevent="continueToOjt">
          <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_9rem]">
            <div>
              <label for="register-name" class="block text-sm font-semibold text-slate-800">Name</label>
              <input id="register-name" v-model="form.name" type="text" autocomplete="name" required :aria-invalid="Boolean(errors.name)" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
              <p v-if="errors.name" data-testid="field-error-name" class="mt-1 text-sm text-red-700" role="alert">{{ errors.name[0] }}</p>
            </div>
            <div>
              <label for="register-suffix" class="block text-sm font-semibold text-slate-800">Suffix <span class="font-normal text-slate-500">(optional)</span></label>
              <select id="register-suffix" v-model="form.suffix" :aria-invalid="Boolean(errors.suffix)" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200">
                <option value="">None</option>
                <option value="Jr.">Jr.</option>
                <option value="Sr.">Sr.</option>
                <option value="II">II</option>
                <option value="III">III</option>
                <option value="IV">IV</option>
                <option value="V">V</option>
              </select>
              <p v-if="errors.suffix" class="mt-1 text-sm text-red-700" role="alert">{{ errors.suffix[0] }}</p>
            </div>
          </div>

          <div>
            <label for="register-email" class="block text-sm font-semibold text-slate-800">Email</label>
            <input id="register-email" v-model="form.email" type="email" autocomplete="email" required :aria-invalid="Boolean(errors.email)" :aria-describedby="errors.email ? 'register-email-error' : undefined" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <p v-if="errors.email" id="register-email-error" data-testid="field-error-email" class="mt-1 text-sm text-red-700" role="alert">{{ errors.email[0] }}</p>
          </div>

          <div>
            <label for="register-password" class="block text-sm font-semibold text-slate-800">Password</label>
            <div class="relative mt-2">
              <input id="register-password" v-model="form.password" :type="passwordType" autocomplete="new-password" required minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}" title="Use at least 12 characters with uppercase, lowercase, a number, and a symbol." aria-describedby="register-password-help register-password-rules" :aria-invalid="Boolean(errors.password)" class="block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 pr-20 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
              <button type="button" data-testid="toggle-register-password" class="absolute inset-y-0 right-2 my-1 rounded-md px-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" @click="showPassword = !showPassword">
                {{ showPassword ? 'Hide' : 'Show' }}
              </button>
            </div>
            <p id="register-password-help" class="mt-1 text-xs leading-5 text-slate-500">Your password must meet all of these requirements:</p>
            <PasswordRequirements :password="form.password" id-prefix="register" />
            <p v-if="errors.password" data-testid="field-error-password" class="mt-1 text-sm text-red-700" role="alert">{{ errors.password[0] }}</p>
          </div>

          <div>
            <label for="register-password-confirmation" class="block text-sm font-semibold text-slate-800">Confirm password</label>
            <div class="relative mt-2">
              <input id="register-password-confirmation" v-model="form.password_confirmation" :type="passwordConfirmationType" autocomplete="new-password" required minlength="12" :aria-invalid="Boolean(errors.password_confirmation)" class="block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 pr-20 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
              <button type="button" data-testid="toggle-register-password-confirmation" class="absolute inset-y-0 right-2 my-1 rounded-md px-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950" :aria-label="showPasswordConfirmation ? 'Hide password confirmation' : 'Show password confirmation'" :aria-pressed="showPasswordConfirmation" @click="showPasswordConfirmation = !showPasswordConfirmation">
                {{ showPasswordConfirmation ? 'Hide' : 'Show' }}
              </button>
            </div>
            <p v-if="errors.password_confirmation" data-testid="field-error-password-confirmation" class="mt-1 text-sm text-red-700" role="alert">{{ errors.password_confirmation[0] }}</p>
          </div>

          <p v-if="formError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm leading-5 text-red-800" role="alert">{{ formError }}</p>
          <button type="submit" data-testid="continue-registration" class="app-button app-button--primary w-full" :disabled="checkingEmail" :aria-busy="checkingEmail">
            {{ checkingEmail ? 'Checking email...' : 'Continue' }}
          </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
          Already registered?
          <RouterLink to="/login" class="font-semibold text-slate-950 underline underline-offset-4">Sign in</RouterLink>
        </p>
      </div>

      <div v-else data-testid="registration-step-2">
        <h1 id="register-title" class="mt-5 text-3xl font-semibold tracking-tight text-slate-950">Set up your OJT</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Tell us about your internship so we can calculate your progress and tracking period correctly.</p>
        <p class="mt-6 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Internship tracking information</p>

        <form ref="ojtForm" data-testid="ojt-setup-form" class="mt-3 space-y-5" @submit.prevent="submit">
          <div>
            <label for="register-required-hours" class="block text-sm font-semibold text-slate-800">Required OJT hours</label>
            <input id="register-required-hours" v-model="form.required_hours" type="number" min="1" max="10000" step="1" required :aria-invalid="Boolean(errors.required_hours)" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <p v-if="errors.required_hours" data-testid="field-error-required-hours" class="mt-1 text-sm text-red-700" role="alert">{{ errors.required_hours[0] }}</p>
          </div>

          <div class="grid gap-5 sm:grid-cols-2">
            <div>
              <label for="register-start-date" class="block text-sm font-semibold text-slate-800">OJT Start Date</label>
              <DatePickerField
                id="register-start-date"
                v-model="form.start_date"
                required
                :invalid="Boolean(errors.start_date)"
                title="Choose OJT start date"
                description="Select the date your internship begins."
                test-id-prefix="register-start-date"
              />
              <p v-if="errors.start_date" data-testid="field-error-start-date" class="mt-1 text-sm text-red-700" role="alert">{{ errors.start_date[0] }}</p>
            </div>
            <div>
              <label for="register-end-date" class="block text-sm font-semibold text-slate-800">Target End Date</label>
              <DatePickerField
                id="register-end-date"
                v-model="form.end_date"
                required
                :min="minimumEndDate"
                :invalid="Boolean(errors.end_date || dateRangeError)"
                describedby="register-end-date-help"
                title="Choose target end date"
                description="Select a date after your OJT start date."
                test-id-prefix="register-end-date"
              />
              <p id="register-end-date-help" class="mt-1 text-xs leading-5 text-slate-500">Choose a date after your OJT start date.</p>
              <p v-if="dateRangeError || errors.end_date" data-testid="end-date-error" class="mt-1 text-sm text-red-700" role="alert">{{ errors.end_date?.[0] ?? dateRangeError }}</p>
            </div>
          </div>

          <fieldset data-testid="register-work-days" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <legend class="px-1 text-sm font-semibold text-slate-800">OJT work days</legend>
            <p class="mt-1 text-xs leading-5 text-slate-500">Select the weekdays you expect to report for OJT.</p>
            <div class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-7">
              <label v-for="day in workDayOptions" :key="day.value" class="register-day flex min-h-10 cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-2 text-sm font-semibold text-slate-700 has-[:checked]:border-slate-950 has-[:checked]:bg-slate-950 has-[:checked]:text-white">
                <input v-model="form.work_days" type="checkbox" :value="day.value" class="sr-only" :aria-label="day.label" />
                <span>{{ day.label }}</span>
              </label>
            </div>
            <p v-if="errors.work_days" data-testid="field-error-work-days" class="mt-2 text-sm text-red-700" role="alert">{{ errors.work_days[0] }}</p>
          </fieldset>

          <div>
            <label for="register-expected-hours-per-day" class="block text-sm font-semibold text-slate-800">Expected hours per day</label>
            <input id="register-expected-hours-per-day" v-model="form.expected_hours_per_day" type="number" min="0.5" max="24" step="0.5" required :aria-invalid="Boolean(errors.expected_hours_per_day)" aria-describedby="register-expected-hours-help" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <p id="register-expected-hours-help" class="mt-1 text-xs leading-5 text-slate-500">Used only as your planned daily schedule; progress still comes from saved work logs.</p>
            <p v-if="errors.expected_hours_per_day" data-testid="field-error-expected-hours" class="mt-1 text-sm text-red-700" role="alert">{{ errors.expected_hours_per_day[0] }}</p>
          </div>

          <p v-if="formError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm leading-5 text-red-800" role="alert">{{ formError }}</p>
          <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
            <button type="button" data-testid="back-registration" class="app-button app-button--secondary" @click="goBack">Back</button>
            <button type="submit" data-testid="start-tracking" class="app-button app-button--primary">Start tracking</button>
          </div>
        </form>
      </div>
    </section>
  </main>
</template>
