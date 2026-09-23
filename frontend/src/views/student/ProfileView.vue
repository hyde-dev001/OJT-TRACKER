<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { apiErrorMessage } from '../../services/api'
import ActionAlert from '../../components/ActionAlert.vue'
import DatePickerField from '../../components/DatePickerField.vue'
import PageHeader from '../../components/PageHeader.vue'
import PasswordRequirements from '../../components/PasswordRequirements.vue'
import { getStudentInternship } from '../../services/ojt'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const form = reactive({
  name: authStore.user?.name ?? '',
  start_date: '',
  end_date: '',
  work_days: [1, 2, 3, 4, 5],
  expected_hours_per_day: 8,
  current_password: '',
  password: '',
  password_confirmation: '',
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
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const formError = ref('')
const fieldErrors = ref({})
const actionAlert = ref({ open: false, variant: 'success', title: '', message: '' })
const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmationPassword = ref(false)
const currentPasswordType = computed(() => showCurrentPassword.value ? 'text' : 'password')
const newPasswordType = computed(() => showNewPassword.value ? 'text' : 'password')
const confirmationPasswordType = computed(() => showConfirmationPassword.value ? 'text' : 'password')
const minimumEndDate = computed(() => nextDate(form.start_date))
const dateRangeError = computed(() => (
  form.start_date && form.end_date && form.end_date <= form.start_date
    ? 'Target end date must be after your OJT start date.'
    : ''
))

const showAlert = (variant, title, message) => {
  actionAlert.value = { open: true, variant, title, message }
}

const closeAlert = () => {
  actionAlert.value.open = false
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

const load = async () => {
  loading.value = true
  error.value = ''

  try {
    const internship = await getStudentInternship()
    form.name = authStore.user?.name ?? ''
    form.start_date = internship?.start_date ?? ''
    form.end_date = internship?.end_date ?? ''
    form.work_days = Array.isArray(internship?.work_days) && internship.work_days.length
      ? [...internship.work_days]
      : [1, 2, 3, 4, 5]
    form.expected_hours_per_day = internship?.expected_hours_per_day ?? 8
  } catch (requestError) {
    error.value = apiErrorMessage(requestError, 'Unable to load your profile.')
  } finally {
    loading.value = false
  }
}

const save = async () => {
  if (saving.value) return

  if (dateRangeError.value) {
    fieldErrors.value = { end_date: [dateRangeError.value] }
    return
  }

  saving.value = true
  formError.value = ''
  fieldErrors.value = {}
  const changingPassword = Boolean(form.password || form.password_confirmation)
  const payload = {
    name: form.name,
    start_date: form.start_date,
    end_date: form.end_date,
    work_days: [...form.work_days].map(Number).sort((left, right) => left - right),
    expected_hours_per_day: Number(form.expected_hours_per_day),
  }

  if (form.password || form.password_confirmation) {
    payload.current_password = form.current_password
    payload.password = form.password
    payload.password_confirmation = form.password_confirmation
  }

  try {
    await authStore.updateProfile(payload)
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
    showCurrentPassword.value = false
    showNewPassword.value = false
    showConfirmationPassword.value = false
    showAlert(
      'success',
      changingPassword ? 'Password updated' : 'Profile updated',
      changingPassword
        ? 'Your password was changed successfully.'
        : 'Your profile and OJT schedule were saved.',
    )
  } catch (requestError) {
    fieldErrors.value = requestError?.response?.data?.errors ?? {}
    formError.value = apiErrorMessage(requestError, 'Unable to save your profile.')
    if (changingPassword) {
      showAlert('error', 'Password not changed', 'Please check your current password and the highlighted fields.')
    }
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
    <ActionAlert :open="actionAlert.open" :variant="actionAlert.variant" :title="actionAlert.title" :message="actionAlert.message" @close="closeAlert" />
    <PageHeader title="Profile" description="Update your personal details, OJT dates, and schedule." />

    <div v-if="loading" class="mt-8 rounded-xl border border-slate-200 bg-white p-6 text-slate-600" role="status">Loading profile...</div>
    <div v-else-if="error" class="mt-8 rounded-xl border border-red-200 bg-red-50 p-6 text-red-800" role="alert">
      <p>{{ error }}</p>
      <button type="button" class="app-button app-button--primary mt-4" @click="load">Try again</button>
    </div>
    <section v-else class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="profile-form-title">
      <h2 id="profile-form-title" class="text-lg font-semibold text-slate-950">Account details</h2>
      <form class="mt-5 space-y-5" @submit.prevent="save">
        <div>
          <label for="profile-name" class="block text-sm font-semibold text-slate-800">Name</label>
          <input id="profile-name" v-model="form.name" type="text" required maxlength="255" :aria-invalid="Boolean(fieldErrors.name)" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="fieldErrors.name" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.name[0] }}</p>
        </div>

        <div>
          <label for="profile-email" class="block text-sm font-semibold text-slate-800">Email</label>
          <input id="profile-email" :value="authStore.user?.email" type="email" readonly aria-readonly="true" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-500 outline-none" />
          <p class="mt-1 text-xs text-slate-500">Email cannot be changed here.</p>
        </div>

        <div>
          <label for="profile-start-date" class="block text-sm font-semibold text-slate-800">OJT start date</label>
          <DatePickerField
            id="profile-start-date"
            v-model="form.start_date"
            required
            :invalid="Boolean(fieldErrors.start_date)"
            title="Choose OJT start date"
            description="Select the date your internship begins."
            test-id-prefix="profile-start-date"
          />
          <p v-if="fieldErrors.start_date" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.start_date[0] }}</p>
        </div>

        <div>
          <label for="profile-end-date" class="block text-sm font-semibold text-slate-800">Target end date</label>
          <DatePickerField
            id="profile-end-date"
            v-model="form.end_date"
            required
            :min="minimumEndDate"
            :invalid="Boolean(fieldErrors.end_date || dateRangeError)"
            describedby="profile-end-date-help"
            title="Choose target end date"
            description="Select a date after your OJT start date."
            test-id-prefix="profile-end-date"
          />
          <p id="profile-end-date-help" class="mt-1 text-xs text-slate-500">Choose a date after your OJT start date.</p>
          <p v-if="dateRangeError || fieldErrors.end_date" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.end_date?.[0] ?? dateRangeError }}</p>
        </div>

        <fieldset data-testid="profile-work-days" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
          <legend class="px-1 text-sm font-semibold text-slate-800">OJT work days</legend>
          <p class="mt-1 text-xs leading-5 text-slate-500">Only selected weekdays can be used for new work logs.</p>
          <div class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-7">
            <label v-for="day in workDayOptions" :key="day.value" class="profile-day flex min-h-10 cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-2 text-sm font-semibold text-slate-700 has-[:checked]:border-slate-950 has-[:checked]:bg-slate-950 has-[:checked]:text-white">
              <input v-model="form.work_days" type="checkbox" :value="day.value" class="sr-only" :aria-label="day.label" />
              <span>{{ day.label }}</span>
            </label>
          </div>
          <p v-if="fieldErrors.work_days" class="mt-2 text-sm text-red-700" role="alert">{{ fieldErrors.work_days[0] }}</p>
        </fieldset>

        <div>
          <label for="profile-expected-hours-per-day" class="block text-sm font-semibold text-slate-800">Expected hours per day</label>
          <input id="profile-expected-hours-per-day" v-model="form.expected_hours_per_day" type="number" min="0.5" max="24" step="0.5" required :aria-invalid="Boolean(fieldErrors.expected_hours_per_day)" aria-describedby="profile-expected-hours-help" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p id="profile-expected-hours-help" class="mt-1 text-xs text-slate-500">Used as your planned daily schedule; progress still comes from saved work logs.</p>
          <p v-if="fieldErrors.expected_hours_per_day" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.expected_hours_per_day[0] }}</p>
        </div>

        <div class="border-t border-slate-200 pt-5">
          <h3 class="font-semibold text-slate-950">Change password <span class="font-normal text-slate-500">(optional)</span></h3>
          <p class="mt-1 text-sm text-slate-600">Leave both fields blank to keep your current password.</p>
          <div class="mt-4 space-y-4">
            <div>
              <label for="profile-current-password" class="block text-sm font-semibold text-slate-800">Current password</label>
              <div class="relative mt-1">
                <input id="profile-current-password" v-model="form.current_password" :type="currentPasswordType" autocomplete="current-password" :required="Boolean(form.password)" :aria-invalid="Boolean(fieldErrors.current_password)" class="block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2 pr-16 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
                <button type="button" data-testid="toggle-current-password" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-slate-700 hover:text-slate-950" :aria-label="showCurrentPassword ? 'Hide current password' : 'Show current password'" :aria-pressed="showCurrentPassword" @click="showCurrentPassword = !showCurrentPassword">{{ showCurrentPassword ? 'Hide' : 'Show' }}</button>
              </div>
              <p class="mt-1 text-xs text-slate-500">Required only when changing your password.</p>
              <p v-if="fieldErrors.current_password" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.current_password[0] }}</p>
            </div>
            <div>
              <label for="profile-password" class="block text-sm font-semibold text-slate-800">New password</label>
              <div class="relative mt-1">
                <input id="profile-password" v-model="form.password" :type="newPasswordType" autocomplete="new-password" minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}" title="Use at least 12 characters with uppercase, lowercase, a number, and a symbol." :aria-invalid="Boolean(fieldErrors.password)" aria-describedby="profile-password-help profile-password-rules" class="block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2 pr-16 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
                <button type="button" data-testid="toggle-new-password" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-slate-700 hover:text-slate-950" :aria-label="showNewPassword ? 'Hide new password' : 'Show new password'" :aria-pressed="showNewPassword" @click="showNewPassword = !showNewPassword">{{ showNewPassword ? 'Hide' : 'Show' }}</button>
              </div>
              <p id="profile-password-help" class="mt-1 text-xs text-slate-500">Your password must meet all of these requirements:</p>
              <PasswordRequirements :password="form.password" id-prefix="profile" />
              <p v-if="fieldErrors.password" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.password[0] }}</p>
            </div>
            <div>
              <label for="profile-password-confirmation" class="block text-sm font-semibold text-slate-800">Confirm new password</label>
              <div class="relative mt-1">
                <input id="profile-password-confirmation" v-model="form.password_confirmation" :type="confirmationPasswordType" autocomplete="new-password" minlength="12" :aria-invalid="Boolean(fieldErrors.password_confirmation)" class="block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2 pr-16 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
                <button type="button" data-testid="toggle-confirm-password" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-slate-700 hover:text-slate-950" :aria-label="showConfirmationPassword ? 'Hide confirmation password' : 'Show confirmation password'" :aria-pressed="showConfirmationPassword" @click="showConfirmationPassword = !showConfirmationPassword">{{ showConfirmationPassword ? 'Hide' : 'Show' }}</button>
              </div>
              <p v-if="fieldErrors.password_confirmation" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.password_confirmation[0] }}</p>
            </div>
          </div>
        </div>

        <p v-if="formError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ formError }}</p>
        <button type="submit" class="app-button app-button--primary w-full sm:w-auto" :disabled="saving">{{ saving ? 'Saving...' : 'Save changes' }}</button>
      </form>
    </section>
  </main>
</template>
