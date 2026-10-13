<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import ActionAlert from '../../components/ActionAlert.vue'
import PageHeader from '../../components/PageHeader.vue'
import PasswordRequirements from '../../components/PasswordRequirements.vue'
import { apiErrorMessage } from '../../services/api'
import { getInternInternship, updateInternRequiredHours } from '../../services/ojt'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const internship = ref(null)
const loading = ref(false)
const loadError = ref('')
const savingHours = ref(false)
const savingPassword = ref(false)
const hoursError = ref('')
const hoursErrors = ref({})
const passwordError = ref('')
const passwordErrors = ref({})
const actionAlert = ref({ open: false, variant: 'success', title: '', message: '' })
const hoursForm = reactive({ required_hours: '' })
const passwordForm = reactive({ current_password: '', password: '', password_confirmation: '' })
const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmationPassword = ref(false)
const workDayLabels = computed(() => (internship.value?.work_days ?? []).map((day) => (
  ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][Number(day) - 1]
)).filter(Boolean).join(', '))
const hasRequiredHours = computed(() => internship.value?.required_hours != null)
const mustChangePassword = computed(() => Boolean(authStore.user?.must_change_password))
const showPasswordForm = computed(() => mustChangePassword.value || (internship.value && hasRequiredHours.value))
const showAlert = (variant, title, message) => { actionAlert.value = { open: true, variant, title, message } }

async function loadInternship() {
  loading.value = true
  loadError.value = ''

  try {
    internship.value = await getInternInternship()
    hoursForm.required_hours = internship.value?.required_hours ?? ''
  } catch (error) {
    loadError.value = apiErrorMessage(error, 'Unable to load your OJT setup. Please try again.')
  } finally {
    loading.value = false
  }
}

async function saveHours() {
  savingHours.value = true
  hoursError.value = ''
  hoursErrors.value = {}

  try {
    internship.value = await updateInternRequiredHours(hoursForm.required_hours)
    hoursForm.required_hours = internship.value.required_hours
    showAlert('success', 'OJT hours saved', 'Your required OJT hours were saved.')
  } catch (error) {
    hoursErrors.value = error?.response?.data?.errors ?? {}
    hoursError.value = apiErrorMessage(error, 'Unable to save required OJT hours. Please try again.')
    showAlert('error', 'OJT hours not saved', hoursError.value)
  } finally {
    savingHours.value = false
  }
}

async function savePassword() {
  savingPassword.value = true
  passwordError.value = ''
  passwordErrors.value = {}

  const changingFirstPassword = Boolean(authStore.user?.must_change_password)
  try {
    await authStore.changeInternPassword({ ...passwordForm })
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
    showAlert('success', 'Password changed', 'Your password was updated.')
    if (changingFirstPassword) await loadInternship()
  } catch (error) {
    passwordErrors.value = error?.response?.data?.errors ?? {}
    passwordError.value = apiErrorMessage(error, 'Unable to change your password. Please try again.')
    showAlert('error', 'Password not changed', passwordError.value)
  } finally {
    savingPassword.value = false
  }
}

onMounted(() => {
  if (!authStore.user?.must_change_password) loadInternship()
})
</script>

<template>
  <main class="mx-auto max-w-3xl space-y-8 px-4 py-10 sm:px-6 lg:px-8">
    <PageHeader title="OJT account setup" description="Manage your required OJT hours and review the placement schedule set by your company." />

    <ActionAlert :open="actionAlert.open" :variant="actionAlert.variant" :title="actionAlert.title" :message="actionAlert.message" @close="actionAlert.open = false" />

    <section v-if="showPasswordForm" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="password-setup-title">
      <h2 id="password-setup-title" class="text-xl font-semibold tracking-tight text-slate-950">{{ mustChangePassword ? 'Change your temporary password' : 'Change password' }}</h2>
      <p v-if="mustChangePassword" class="mt-2 text-sm text-slate-600">Choose a permanent password before continuing to your OJT setup.</p>
      <form class="mt-5 space-y-5" data-testid="intern-password-form" @submit.prevent="savePassword">
        <div>
          <label for="intern-current-password" class="block text-sm font-semibold text-slate-800">{{ mustChangePassword ? 'Temporary password' : 'Current password' }}</label>
          <div class="relative mt-2">
            <input id="intern-current-password" v-model="passwordForm.current_password" :type="showCurrentPassword ? 'text' : 'password'" autocomplete="current-password" required class="block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 pr-16 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <button type="button" data-testid="show-intern-current-password" class="absolute inset-y-0 right-2 my-1 rounded-md px-2 text-xs font-semibold text-slate-600 hover:bg-slate-100" :aria-pressed="showCurrentPassword" @click="showCurrentPassword = !showCurrentPassword">{{ showCurrentPassword ? 'Hide' : 'Show' }}</button>
          </div>
          <p v-if="passwordErrors.current_password" class="mt-1 text-sm text-red-700" role="alert">{{ passwordErrors.current_password[0] }}</p>
        </div>
        <div>
          <label for="intern-new-password" class="block text-sm font-semibold text-slate-800">New password</label>
          <div class="relative mt-2">
            <input id="intern-new-password" v-model="passwordForm.password" :type="showNewPassword ? 'text' : 'password'" autocomplete="new-password" required minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}" class="block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 pr-16 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <button type="button" data-testid="show-intern-new-password" class="absolute inset-y-0 right-2 my-1 rounded-md px-2 text-xs font-semibold text-slate-600 hover:bg-slate-100" :aria-pressed="showNewPassword" @click="showNewPassword = !showNewPassword">{{ showNewPassword ? 'Hide' : 'Show' }}</button>
          </div>
          <PasswordRequirements :password="passwordForm.password" id-prefix="intern" />
          <p v-if="passwordErrors.password" class="mt-1 text-sm text-red-700" role="alert">{{ passwordErrors.password[0] }}</p>
        </div>
        <div>
          <label for="intern-password-confirmation" class="block text-sm font-semibold text-slate-800">Confirm new password</label>
          <div class="relative mt-2">
            <input id="intern-password-confirmation" v-model="passwordForm.password_confirmation" :type="showConfirmationPassword ? 'text' : 'password'" autocomplete="new-password" required minlength="12" class="block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 pr-16 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <button type="button" data-testid="show-intern-confirm-password" class="absolute inset-y-0 right-2 my-1 rounded-md px-2 text-xs font-semibold text-slate-600 hover:bg-slate-100" :aria-pressed="showConfirmationPassword" @click="showConfirmationPassword = !showConfirmationPassword">{{ showConfirmationPassword ? 'Hide' : 'Show' }}</button>
          </div>
          <p v-if="passwordErrors.password_confirmation" class="mt-1 text-sm text-red-700" role="alert">{{ passwordErrors.password_confirmation[0] }}</p>
        </div>
        <p v-if="passwordError" class="text-sm text-red-700" role="alert">{{ passwordError }}</p>
        <button type="submit" class="app-button app-button--primary" :disabled="savingPassword">{{ savingPassword ? 'Saving...' : mustChangePassword ? 'Change password' : 'Update password' }}</button>
      </form>
    </section>

    <template v-if="!mustChangePassword">
      <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-6 text-slate-600" role="status">Loading OJT setup...</div>
      <div v-else-if="loadError" class="rounded-xl border border-red-200 bg-red-50 p-6 text-red-800" role="alert">
        <p>{{ loadError }}</p>
        <button type="button" class="app-button app-button--secondary mt-4" @click="loadInternship">Try again</button>
      </div>
      <template v-else-if="internship">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="placement-title">
          <h2 id="placement-title" class="text-xl font-semibold tracking-tight text-slate-950">Company placement schedule</h2>
          <p class="mt-1 text-sm text-slate-600">These placement details are managed by your company.</p>
          <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="font-medium text-slate-500">OJT start date</dt><dd class="mt-1 text-slate-900">{{ internship.start_date }}</dd></div>
            <div><dt class="font-medium text-slate-500">Target end date</dt><dd class="mt-1 text-slate-900">{{ internship.end_date }}</dd></div>
            <div><dt class="font-medium text-slate-500">Work days</dt><dd class="mt-1 text-slate-900">{{ workDayLabels }}</dd></div>
            <div><dt class="font-medium text-slate-500">Expected hours per work day</dt><dd class="mt-1 text-slate-900">{{ internship.expected_hours_per_day }} hours</dd></div>
          </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="required-hours-title">
          <h2 id="required-hours-title" class="text-xl font-semibold tracking-tight text-slate-950">{{ hasRequiredHours ? 'Required OJT hours' : 'Enter the total number of hours your school requires' }}</h2>
          <form class="mt-5 space-y-4" data-testid="required-hours-form" @submit.prevent="saveHours">
            <div>
              <label for="required-ojt-hours" class="block text-sm font-semibold text-slate-800">Total required hours</label>
              <input id="required-ojt-hours" v-model="hoursForm.required_hours" type="number" min="1" max="10000" step="1" required :aria-invalid="Boolean(hoursErrors.required_hours)" aria-describedby="required-ojt-hours-help" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200 sm:max-w-xs" />
              <p id="required-ojt-hours-help" class="mt-1 text-xs text-slate-500">Enter a whole number from 1 to 10,000.</p>
              <p v-if="hoursErrors.required_hours" data-testid="required-hours-error" class="mt-1 text-sm text-red-700" role="alert">{{ hoursErrors.required_hours[0] }}</p>
            </div>
            <p v-if="hoursError" class="text-sm text-red-700" role="alert">{{ hoursError }}</p>
            <button type="submit" class="app-button app-button--primary" :disabled="savingHours">{{ savingHours ? 'Saving...' : hasRequiredHours ? 'Update required hours' : 'Save required hours' }}</button>
          </form>
        </section>
      </template>
    </template>
  </main>
</template>
