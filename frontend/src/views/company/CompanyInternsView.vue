<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import DatePickerField from '../../components/DatePickerField.vue'
import PageHeader from '../../components/PageHeader.vue'
import PasswordRequirements from '../../components/PasswordRequirements.vue'
import { apiErrorMessage } from '../../services/api'
import {
  createCompanyIntern,
  listCompanyInterns,
  resetCompanyInternPassword,
  updateCompanyPlacement,
} from '../../services/ojt'
import { todayInPhilippines } from '../../utils/dateBounds'

const workDayOptions = [
  { value: 1, label: 'Mon' },
  { value: 2, label: 'Tue' },
  { value: 3, label: 'Wed' },
  { value: 4, label: 'Thu' },
  { value: 5, label: 'Fri' },
  { value: 6, label: 'Sat' },
  { value: 7, label: 'Sun' },
]

const newInternForm = reactive({
  first_name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirmation: '',
  start_date: todayInPhilippines(),
  end_date: '',
  work_days: [1, 2, 3, 4, 5],
  expected_hours_per_day: 8,
})
const placementForm = reactive({
  start_date: '',
  end_date: '',
  work_days: [],
  expected_hours_per_day: 8,
})
const resetForm = reactive({ password: '', password_confirmation: '' })
const interns = ref([])
const loading = ref(true)
const loadError = ref('')
const creating = ref(false)
const editingInternId = ref(null)
const savingPlacement = ref(false)
const resettingInternId = ref(null)
const savingReset = ref(false)
const createErrors = ref({})
const createError = ref('')
const placementErrors = ref({})
const placementError = ref('')
const resetErrors = ref({})
const resetError = ref('')
const successMessage = ref('')
const createFormElement = ref(null)
const minimumEndDate = computed(() => nextDate(newInternForm.start_date))
const minimumPlacementEndDate = computed(() => nextDate(placementForm.start_date))

const loadInterns = async () => {
  loading.value = true
  loadError.value = ''

  try {
    interns.value = await listCompanyInterns()
  } catch (error) {
    loadError.value = apiErrorMessage(error, 'Unable to load intern accounts. Please try again.')
  } finally {
    loading.value = false
  }
}

const replaceIntern = (updatedIntern) => {
  interns.value = interns.value.map((intern) => intern.id === updatedIntern.id ? updatedIntern : intern)
}

const createIntern = async () => {
  createErrors.value = {}
  createError.value = ''
  successMessage.value = ''

  if (createFormElement.value && !createFormElement.value.checkValidity()) {
    createFormElement.value.reportValidity()
    return
  }
  if (newInternForm.end_date <= newInternForm.start_date) {
    createErrors.value = { end_date: ['Target end date must be after the start date.'] }
    return
  }
  if (!newInternForm.work_days.length) {
    createErrors.value = { work_days: ['Select at least one work day.'] }
    return
  }
  if (newInternForm.password !== newInternForm.password_confirmation) {
    createErrors.value = { password_confirmation: ['Passwords must match.'] }
    return
  }

  creating.value = true
  try {
    const intern = await createCompanyIntern({
      ...newInternForm,
      work_days: [...newInternForm.work_days],
      expected_hours_per_day: Number(newInternForm.expected_hours_per_day),
    })
    interns.value = [intern, ...interns.value]
    newInternForm.password = ''
    newInternForm.password_confirmation = ''
    successMessage.value = 'Intern account created. Give the temporary password to the intern in person.'
  } catch (error) {
    createErrors.value = error?.response?.data?.errors ?? {}
    createError.value = apiErrorMessage(error, 'Unable to create this intern account. Please try again.')
  } finally {
    creating.value = false
  }
}

const startPlacementEdit = (intern) => {
  successMessage.value = ''
  editingInternId.value = intern.id
  resettingInternId.value = null
  placementForm.start_date = intern.start_date ?? ''
  placementForm.end_date = intern.end_date ?? ''
  placementForm.work_days = [...(intern.work_days ?? [])]
  placementForm.expected_hours_per_day = intern.expected_hours_per_day ?? 8
  placementErrors.value = {}
  placementError.value = ''
}

const savePlacement = async (internId) => {
  placementErrors.value = {}
  placementError.value = ''
  successMessage.value = ''

  if (placementForm.end_date <= placementForm.start_date) {
    placementErrors.value = { end_date: ['Target end date must be after the start date.'] }
    return
  }
  if (!placementForm.work_days.length) {
    placementErrors.value = { work_days: ['Select at least one work day.'] }
    return
  }

  savingPlacement.value = true
  try {
    const updatedIntern = await updateCompanyPlacement(internId, {
      start_date: placementForm.start_date,
      end_date: placementForm.end_date,
      work_days: [...placementForm.work_days],
      expected_hours_per_day: Number(placementForm.expected_hours_per_day),
    })
    replaceIntern(updatedIntern)
    editingInternId.value = null
    successMessage.value = 'Placement updated.'
  } catch (error) {
    placementErrors.value = error?.response?.data?.errors ?? {}
    placementError.value = apiErrorMessage(error, 'Unable to update this placement. Please try again.')
  } finally {
    savingPlacement.value = false
  }
}

const startPasswordReset = (intern) => {
  successMessage.value = ''
  resettingInternId.value = intern.id
  editingInternId.value = null
  resetForm.password = ''
  resetForm.password_confirmation = ''
  resetErrors.value = {}
  resetError.value = ''
}

const savePasswordReset = async (internId) => {
  resetErrors.value = {}
  resetError.value = ''
  successMessage.value = ''

  if (resetForm.password !== resetForm.password_confirmation) {
    resetErrors.value = { password_confirmation: ['Passwords must match.'] }
    return
  }

  savingReset.value = true
  try {
    const updatedIntern = await resetCompanyInternPassword(internId, { ...resetForm })
    replaceIntern(updatedIntern)
    resetForm.password = ''
    resetForm.password_confirmation = ''
    resettingInternId.value = null
    successMessage.value = 'Temporary password reset. Give the new password to the intern in person.'
  } catch (error) {
    resetErrors.value = error?.response?.data?.errors ?? {}
    resetError.value = apiErrorMessage(error, 'Unable to reset this password. Please try again.')
  } finally {
    savingReset.value = false
  }
}

const cancelPlacementEdit = () => {
  editingInternId.value = null
}

const cancelPasswordReset = () => {
  resettingInternId.value = null
  resetForm.password = ''
  resetForm.password_confirmation = ''
}

const workDayLabels = (days) => days.map((day) => workDayOptions.find((option) => option.value === Number(day))?.label).filter(Boolean).join(', ')

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

onMounted(loadInterns)
</script>

<template>
  <main class="mx-auto max-w-5xl space-y-8 px-4 py-10 sm:px-6 lg:px-8">
    <PageHeader title="Intern accounts" description="Create intern logins, manage placement schedules, and reset forgotten passwords." />

    <p v-if="successMessage" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ successMessage }}</p>

    <section class="space-y-4" aria-labelledby="intern-list-title">
      <div>
        <h2 id="intern-list-title" class="text-xl font-semibold tracking-tight text-slate-950">Company interns</h2>
        <p class="mt-1 text-sm text-slate-600">Only interns created for this company appear here.</p>
      </div>

      <p v-if="loading" class="text-sm text-slate-600" role="status">Loading intern accounts...</p>
      <div v-else-if="loadError" class="rounded-xl border border-red-200 bg-red-50 p-4" role="alert">
        <p class="text-sm text-red-800">{{ loadError }}</p>
        <button type="button" class="app-button app-button--secondary mt-3" data-testid="retry-company-interns" @click="loadInterns">Try again</button>
      </div>
      <p v-else-if="!interns.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">No intern accounts yet. Create an account below to get started.</p>

      <div v-else class="space-y-4">
        <article v-for="intern in interns" :key="intern.id" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" :data-testid="`company-intern-${intern.id}`">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <h3 class="text-lg font-semibold text-slate-950">{{ intern.name }}</h3>
              <p class="mt-1 break-all text-sm text-slate-600">{{ intern.email }}</p>
            </div>
            <span :class="intern.setup_complete ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900'" class="rounded-full border px-3 py-1 text-xs font-semibold">
              {{ intern.setup_complete ? 'Setup complete' : 'Needs initial setup' }}
            </span>
          </div>

          <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="font-medium text-slate-500">Start date</dt><dd class="mt-1 text-slate-900">{{ intern.start_date }}</dd></div>
            <div><dt class="font-medium text-slate-500">Target end date</dt><dd class="mt-1 text-slate-900">{{ intern.end_date }}</dd></div>
            <div><dt class="font-medium text-slate-500">Work days</dt><dd class="mt-1 text-slate-900">{{ workDayLabels(intern.work_days ?? []) }}</dd></div>
            <div><dt class="font-medium text-slate-500">Expected hours per work day</dt><dd class="mt-1 text-slate-900">{{ intern.expected_hours_per_day }} hours</dd></div>
          </dl>

          <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            <button type="button" class="app-button app-button--secondary" :data-testid="`edit-placement-${intern.id}`" @click="startPlacementEdit(intern)">Update placement</button>
            <button type="button" class="app-button app-button--secondary" :data-testid="`reset-password-${intern.id}`" @click="startPasswordReset(intern)">Reset password</button>
          </div>

          <form v-if="editingInternId === intern.id" class="space-y-4 rounded-lg bg-slate-50 p-4" :data-testid="`placement-form-${intern.id}`" @submit.prevent="savePlacement(intern.id)">
            <h4 class="font-semibold text-slate-950">Update placement schedule</h4>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label :for="`placement-start-date-${intern.id}`" class="block text-sm font-semibold text-slate-800">OJT start date</label>
                <DatePickerField :id="`placement-start-date-${intern.id}`" v-model="placementForm.start_date" required :invalid="Boolean(placementErrors.start_date)" title="Choose OJT start date" description="Select when this placement begins." :test-id-prefix="`placement-start-date-${intern.id}`" />
                <p v-if="placementErrors.start_date" class="mt-1 text-sm text-red-700" role="alert">{{ placementErrors.start_date[0] }}</p>
              </div>
              <div>
                <label :for="`placement-end-date-${intern.id}`" class="block text-sm font-semibold text-slate-800">Target end date</label>
                <DatePickerField :id="`placement-end-date-${intern.id}`" v-model="placementForm.end_date" required :min="minimumPlacementEndDate" :invalid="Boolean(placementErrors.end_date)" title="Choose target end date" description="Select a date after the start date." :test-id-prefix="`placement-end-date-${intern.id}`" />
                <p v-if="placementErrors.end_date" class="mt-1 text-sm text-red-700" role="alert">{{ placementErrors.end_date[0] }}</p>
              </div>
            </div>
            <fieldset class="rounded-lg border border-slate-200 bg-white p-4">
              <legend class="px-1 text-sm font-semibold text-slate-800">Work days</legend>
              <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-7">
                <label v-for="day in workDayOptions" :key="day.value" class="flex min-h-10 cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-2 text-sm font-semibold text-slate-700 has-[:checked]:border-slate-950 has-[:checked]:bg-slate-950 has-[:checked]:text-white">
                  <input v-model="placementForm.work_days" type="checkbox" :value="day.value" class="sr-only" :aria-label="day.label" />
                  <span>{{ day.label }}</span>
                </label>
              </div>
              <p v-if="placementErrors.work_days" class="mt-2 text-sm text-red-700" role="alert">{{ placementErrors.work_days[0] }}</p>
            </fieldset>
            <div>
              <label :for="`placement-expected-hours-${intern.id}`" class="block text-sm font-semibold text-slate-800">Expected hours per work day</label>
              <input :id="`placement-expected-hours-${intern.id}`" v-model="placementForm.expected_hours_per_day" type="number" min="0.5" max="24" step="0.5" required class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200 sm:max-w-xs" />
              <p v-if="placementErrors.expected_hours_per_day" class="mt-1 text-sm text-red-700" role="alert">{{ placementErrors.expected_hours_per_day[0] }}</p>
            </div>
            <p v-if="placementError" class="text-sm text-red-700" role="alert">{{ placementError }}</p>
            <div class="flex flex-wrap gap-2">
              <button type="submit" class="app-button app-button--primary" :disabled="savingPlacement">{{ savingPlacement ? 'Saving...' : 'Save placement' }}</button>
              <button type="button" class="app-button app-button--secondary" :disabled="savingPlacement" @click="cancelPlacementEdit">Cancel</button>
            </div>
          </form>

          <form v-if="resettingInternId === intern.id" class="space-y-4 rounded-lg bg-slate-50 p-4" :data-testid="`reset-password-form-${intern.id}`" @submit.prevent="savePasswordReset(intern.id)">
            <h4 class="font-semibold text-slate-950">Set a new temporary password</h4>
            <p class="text-sm leading-5 text-slate-600">Give the new password to the intern in person. They must change it at their next sign-in.</p>
            <div>
              <label :for="`reset-password-${intern.id}`" class="block text-sm font-semibold text-slate-800">New temporary password</label>
              <input :id="`reset-password-${intern.id}`" v-model="resetForm.password" type="password" required minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
              <PasswordRequirements :password="resetForm.password" :id-prefix="`reset-intern-${intern.id}`" />
              <p v-if="resetErrors.password" class="mt-1 text-sm text-red-700" role="alert">{{ resetErrors.password[0] }}</p>
            </div>
            <div>
              <label :for="`reset-password-confirmation-${intern.id}`" class="block text-sm font-semibold text-slate-800">Confirm temporary password</label>
              <input :id="`reset-password-confirmation-${intern.id}`" v-model="resetForm.password_confirmation" type="password" required minlength="12" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
              <p v-if="resetErrors.password_confirmation" class="mt-1 text-sm text-red-700" role="alert">{{ resetErrors.password_confirmation[0] }}</p>
            </div>
            <p v-if="resetError" class="text-sm text-red-700" role="alert">{{ resetError }}</p>
            <div class="flex flex-wrap gap-2">
              <button type="submit" class="app-button app-button--primary" :disabled="savingReset">{{ savingReset ? 'Saving...' : 'Save temporary password' }}</button>
              <button type="button" class="app-button app-button--secondary" :disabled="savingReset" @click="cancelPasswordReset">Cancel</button>
            </div>
          </form>
        </article>
      </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="create-intern-title">
      <h2 id="create-intern-title" class="text-xl font-semibold tracking-tight text-slate-950">Create intern account</h2>
      <p class="mt-1 text-sm text-slate-600">Set the intern's initial login and placement schedule. Share the temporary password in person.</p>

      <form ref="createFormElement" class="mt-5 space-y-5" data-testid="company-intern-create-form" @submit.prevent="createIntern">
        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label for="new-intern-first-name" class="block text-sm font-semibold text-slate-800">First name</label>
            <input id="new-intern-first-name" v-model="newInternForm.first_name" type="text" autocomplete="given-name" required maxlength="100" :aria-invalid="Boolean(createErrors.first_name)" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <p v-if="createErrors.first_name" class="mt-1 text-sm text-red-700" role="alert">{{ createErrors.first_name[0] }}</p>
          </div>
          <div>
            <label for="new-intern-last-name" class="block text-sm font-semibold text-slate-800">Last name</label>
            <input id="new-intern-last-name" v-model="newInternForm.last_name" type="text" autocomplete="family-name" required maxlength="100" :aria-invalid="Boolean(createErrors.last_name)" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <p v-if="createErrors.last_name" class="mt-1 text-sm text-red-700" role="alert">{{ createErrors.last_name[0] }}</p>
          </div>
        </div>

        <div>
          <label for="new-intern-email" class="block text-sm font-semibold text-slate-800">Intern email</label>
          <input id="new-intern-email" v-model="newInternForm.email" type="email" autocomplete="email" required maxlength="255" :aria-invalid="Boolean(createErrors.email)" :aria-describedby="createErrors.email ? 'new-intern-email-error' : undefined" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="createErrors.email" id="new-intern-email-error" data-testid="new-intern-email-error" class="mt-1 text-sm text-red-700" role="alert">{{ createErrors.email[0] }}</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label for="new-intern-password" class="block text-sm font-semibold text-slate-800">Temporary password</label>
            <input id="new-intern-password" v-model="newInternForm.password" type="password" autocomplete="new-password" required minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}" aria-describedby="new-intern-password-rules" :aria-invalid="Boolean(createErrors.password)" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <PasswordRequirements :password="newInternForm.password" id-prefix="new-intern" />
            <p v-if="createErrors.password" class="mt-1 text-sm text-red-700" role="alert">{{ createErrors.password[0] }}</p>
          </div>
          <div>
            <label for="new-intern-password-confirmation" class="block text-sm font-semibold text-slate-800">Confirm temporary password</label>
            <input id="new-intern-password-confirmation" v-model="newInternForm.password_confirmation" type="password" autocomplete="new-password" required minlength="12" :aria-invalid="Boolean(createErrors.password_confirmation)" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <p v-if="createErrors.password_confirmation" class="mt-1 text-sm text-red-700" role="alert">{{ createErrors.password_confirmation[0] }}</p>
          </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label for="new-intern-start-date" class="block text-sm font-semibold text-slate-800">OJT start date</label>
            <DatePickerField id="new-intern-start-date" v-model="newInternForm.start_date" required :invalid="Boolean(createErrors.start_date)" title="Choose OJT start date" description="Select when this placement begins." test-id-prefix="new-intern-start-date" />
            <p v-if="createErrors.start_date" class="mt-1 text-sm text-red-700" role="alert">{{ createErrors.start_date[0] }}</p>
          </div>
          <div>
            <label for="new-intern-end-date" class="block text-sm font-semibold text-slate-800">Target end date</label>
            <DatePickerField id="new-intern-end-date" v-model="newInternForm.end_date" required :min="minimumEndDate" :invalid="Boolean(createErrors.end_date)" title="Choose target end date" description="Select a date after the start date." test-id-prefix="new-intern-end-date" />
            <p v-if="createErrors.end_date" class="mt-1 text-sm text-red-700" role="alert">{{ createErrors.end_date[0] }}</p>
          </div>
        </div>

        <fieldset class="rounded-lg border border-slate-200 bg-slate-50 p-4">
          <legend class="px-1 text-sm font-semibold text-slate-800">Recurring work days</legend>
          <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-7">
            <label v-for="day in workDayOptions" :key="day.value" class="flex min-h-10 cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-2 text-sm font-semibold text-slate-700 has-[:checked]:border-slate-950 has-[:checked]:bg-slate-950 has-[:checked]:text-white">
              <input v-model="newInternForm.work_days" type="checkbox" :value="day.value" class="sr-only" :aria-label="day.label" />
              <span>{{ day.label }}</span>
            </label>
          </div>
          <p v-if="createErrors.work_days" class="mt-2 text-sm text-red-700" role="alert">{{ createErrors.work_days[0] }}</p>
        </fieldset>

        <div>
          <label for="new-intern-expected-hours" class="block text-sm font-semibold text-slate-800">Expected hours per work day</label>
          <input id="new-intern-expected-hours" v-model="newInternForm.expected_hours_per_day" type="number" min="0.5" max="24" step="0.5" required :aria-invalid="Boolean(createErrors.expected_hours_per_day)" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200 sm:max-w-xs" />
          <p v-if="createErrors.expected_hours_per_day" class="mt-1 text-sm text-red-700" role="alert">{{ createErrors.expected_hours_per_day[0] }}</p>
        </div>

        <p v-if="createError" class="text-sm text-red-700" role="alert">{{ createError }}</p>
        <button type="submit" class="app-button app-button--primary" :disabled="creating">{{ creating ? 'Creating account...' : 'Create intern account' }}</button>
      </form>
    </section>
  </main>
</template>
