<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import ActionAlert from '../../components/ActionAlert.vue'
import AppModal from '../../components/AppModal.vue'
import DatePickerField from '../../components/DatePickerField.vue'
import PageHeader from '../../components/PageHeader.vue'
import PaginationControls from '../../components/PaginationControls.vue'
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
const showNewInternPassword = ref(false)
const showNewInternPasswordConfirmation = ref(false)
const interns = ref([])
const currentPage = ref(1)
const createModalOpen = ref(false)
const pageSize = 10
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
const actionAlert = ref({ open: false, variant: 'success', title: '', message: '' })
const createFormElement = ref(null)
const minimumEndDate = computed(() => nextDate(newInternForm.start_date))
const minimumPlacementEndDate = computed(() => nextDate(placementForm.start_date))
const editingIntern = computed(() => interns.value.find((intern) => intern.id === editingInternId.value) ?? null)
const resettingIntern = computed(() => interns.value.find((intern) => intern.id === resettingInternId.value) ?? null)
const visibleInterns = computed(() => interns.value.slice((currentPage.value - 1) * pageSize, currentPage.value * pageSize))
const pagination = computed(() => {
  const total = interns.value.length
  const lastPage = Math.max(1, Math.ceil(total / pageSize))
  return {
    current_page: currentPage.value,
    last_page: lastPage,
    per_page: pageSize,
    total,
    from: total ? (currentPage.value - 1) * pageSize + 1 : null,
    to: total ? Math.min(currentPage.value * pageSize, total) : null,
  }
})
const showAlert = (variant, title, message) => { actionAlert.value = { open: true, variant, title, message } }

const openCreateModal = () => {
  createErrors.value = {}
  createError.value = ''
  createModalOpen.value = true
}

const closeCreateModal = () => {
  if (creating.value) return
  createModalOpen.value = false
  newInternForm.password = ''
  newInternForm.password_confirmation = ''
  showNewInternPassword.value = false
  showNewInternPasswordConfirmation.value = false
}

const loadInterns = async () => {
  loading.value = true
  loadError.value = ''

  try {
    interns.value = await listCompanyInterns()
    currentPage.value = 1
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
    currentPage.value = 1
    newInternForm.password = ''
    newInternForm.password_confirmation = ''
    showNewInternPassword.value = false
    showNewInternPasswordConfirmation.value = false
    createModalOpen.value = false
    showAlert('success', 'Intern account created', 'Give the temporary password to the intern in person.')
  } catch (error) {
    createErrors.value = error?.response?.data?.errors ?? {}
    createError.value = apiErrorMessage(error, 'Unable to create this intern account. Please try again.')
    showAlert('error', 'Intern account not created', createError.value)
  } finally {
    creating.value = false
  }
}

const startPlacementEdit = (intern) => {
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
    showAlert('success', 'Placement updated', 'The intern’s work hours schedule was updated.')
  } catch (error) {
    placementErrors.value = error?.response?.data?.errors ?? {}
    placementError.value = apiErrorMessage(error, 'Unable to update this placement. Please try again.')
    showAlert('error', 'Placement not updated', placementError.value)
  } finally {
    savingPlacement.value = false
  }
}

const startPasswordReset = (intern) => {
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
    showAlert('success', 'Password reset', 'Give the new temporary password to the intern in person.')
  } catch (error) {
    resetErrors.value = error?.response?.data?.errors ?? {}
    resetError.value = apiErrorMessage(error, 'Unable to reset this password. Please try again.')
    showAlert('error', 'Password not reset', resetError.value)
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
  <main class="mx-auto max-w-5xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <PageHeader title="Intern accounts" description="Manage company interns, placement schedules, and password resets." />
      <button type="button" class="app-button app-button--primary" data-testid="open-create-intern-modal" @click="openCreateModal">Create intern account</button>
    </div>

    <ActionAlert :open="actionAlert.open" :variant="actionAlert.variant" :title="actionAlert.title" :message="actionAlert.message" @close="actionAlert.open = false" />

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
      <p v-else-if="!interns.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">No intern accounts yet. Create your first intern account to get started.</p>
      <div v-else class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-[900px] w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th scope="col" class="px-4 py-3 font-semibold">Intern</th>
              <th scope="col" class="px-4 py-3 font-semibold">Setup</th>
              <th scope="col" class="px-4 py-3 font-semibold">Placement</th>
              <th scope="col" class="px-4 py-3 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="intern in visibleInterns" :key="intern.id" :data-testid="'company-intern-' + intern.id">
              <td class="px-4 py-4 align-top">
                <p class="font-semibold text-slate-950">{{ intern.name }}</p>
                <p class="mt-1 text-slate-600">{{ intern.email }}</p>
              </td>
              <td class="px-4 py-4 align-top">
                <span :class="intern.setup_complete ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900'" class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold">
                  {{ intern.setup_complete ? 'Setup complete' : 'Needs initial setup' }}
                </span>
              </td>
              <td class="px-4 py-4 align-top text-slate-700">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-2">
                  <div><dt class="text-xs text-slate-500">Start date</dt><dd class="mt-0.5">{{ intern.start_date }}</dd></div>
                  <div><dt class="text-xs text-slate-500">Target end date</dt><dd class="mt-0.5">{{ intern.end_date }}</dd></div>
                  <div><dt class="text-xs text-slate-500">Work days</dt><dd class="mt-0.5">{{ workDayLabels(intern.work_days ?? []) }}</dd></div>
                  <div><dt class="text-xs text-slate-500">Hours per work day</dt><dd class="mt-0.5">{{ intern.expected_hours_per_day }} hours</dd></div>
                </dl>
              </td>
              <td class="px-4 py-4 align-top">
                <div class="flex flex-wrap gap-2">
                  <button type="button" class="app-button app-button--secondary" :data-testid="'edit-placement-' + intern.id" @click="startPlacementEdit(intern)">Update placement</button>
                  <button type="button" class="app-button app-button--secondary" :data-testid="'reset-password-' + intern.id" @click="startPasswordReset(intern)">Reset password</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationControls
        v-if="!loading && !loadError && interns.length"
        :meta="pagination"
        @change="currentPage = $event"
      />
    </section>

    <AppModal :open="createModalOpen" title="Create intern account" description="Set the intern's initial login and company placement schedule." :busy="creating" @close="closeCreateModal">
      <form ref="createFormElement" class="space-y-5" data-testid="company-intern-create-form" @submit.prevent="createIntern">
        <p class="text-sm text-slate-600">Share the temporary password with the intern in person. They must change it at their first sign-in.</p>
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
            <div class="relative mt-2">
              <input id="new-intern-password" v-model="newInternForm.password" :type="showNewInternPassword ? 'text' : 'password'" autocomplete="new-password" required minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}" aria-describedby="new-intern-password-rules" :aria-invalid="Boolean(createErrors.password)" class="block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 pr-20 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
              <button type="button" data-testid="toggle-new-intern-password" class="absolute inset-y-0 right-2 my-1 rounded-md px-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950" :aria-label="showNewInternPassword ? 'Hide temporary password' : 'Show temporary password'" :aria-pressed="showNewInternPassword" @click="showNewInternPassword = !showNewInternPassword">
                {{ showNewInternPassword ? 'Hide' : 'Show' }}
              </button>
            </div>
            <PasswordRequirements :password="newInternForm.password" id-prefix="new-intern" />
            <p v-if="createErrors.password" class="mt-1 text-sm text-red-700" role="alert">{{ createErrors.password[0] }}</p>
          </div>
          <div>
            <label for="new-intern-password-confirmation" class="block text-sm font-semibold text-slate-800">Confirm temporary password</label>
            <div class="relative mt-2">
              <input id="new-intern-password-confirmation" v-model="newInternForm.password_confirmation" :type="showNewInternPasswordConfirmation ? 'text' : 'password'" autocomplete="new-password" required minlength="12" :aria-invalid="Boolean(createErrors.password_confirmation)" class="block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 pr-20 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
              <button type="button" data-testid="toggle-new-intern-password-confirmation" class="absolute inset-y-0 right-2 my-1 rounded-md px-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950" :aria-label="showNewInternPasswordConfirmation ? 'Hide temporary password confirmation' : 'Show temporary password confirmation'" :aria-pressed="showNewInternPasswordConfirmation" @click="showNewInternPasswordConfirmation = !showNewInternPasswordConfirmation">
                {{ showNewInternPasswordConfirmation ? 'Hide' : 'Show' }}
              </button>
            </div>
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
        <div class="flex flex-wrap justify-end gap-2">
          <button type="button" class="app-button app-button--secondary" :disabled="creating" @click="closeCreateModal">Cancel</button>
          <button type="submit" class="app-button app-button--primary" :disabled="creating">{{ creating ? 'Creating account...' : 'Create intern account' }}</button>
        </div>
      </form>
    </AppModal>

    <AppModal :open="editingInternId !== null" title="Update placement schedule" description="Manage this intern's company placement details." :busy="savingPlacement" @close="cancelPlacementEdit">
      <form v-if="editingIntern" class="space-y-4" :data-testid="'placement-form-' + editingIntern.id" @submit.prevent="savePlacement(editingIntern.id)">
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label :for="'placement-start-date-' + editingIntern.id" class="block text-sm font-semibold text-slate-800">OJT start date</label>
            <DatePickerField :id="'placement-start-date-' + editingIntern.id" v-model="placementForm.start_date" required :invalid="Boolean(placementErrors.start_date)" title="Choose OJT start date" description="Select when this placement begins." :test-id-prefix="'placement-start-date-' + editingIntern.id" />
            <p v-if="placementErrors.start_date" class="mt-1 text-sm text-red-700" role="alert">{{ placementErrors.start_date[0] }}</p>
          </div>
          <div>
            <label :for="'placement-end-date-' + editingIntern.id" class="block text-sm font-semibold text-slate-800">Target end date</label>
            <DatePickerField :id="'placement-end-date-' + editingIntern.id" v-model="placementForm.end_date" required :min="minimumPlacementEndDate" :invalid="Boolean(placementErrors.end_date)" title="Choose target end date" description="Select a date after the start date." :test-id-prefix="'placement-end-date-' + editingIntern.id" />
            <p v-if="placementErrors.end_date" class="mt-1 text-sm text-red-700" role="alert">{{ placementErrors.end_date[0] }}</p>
          </div>
        </div>
        <fieldset class="rounded-lg border border-slate-200 bg-slate-50 p-4">
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
          <label :for="'placement-expected-hours-' + editingIntern.id" class="block text-sm font-semibold text-slate-800">Expected hours per work day</label>
          <input :id="'placement-expected-hours-' + editingIntern.id" v-model="placementForm.expected_hours_per_day" type="number" min="0.5" max="24" step="0.5" required class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200 sm:max-w-xs" />
          <p v-if="placementErrors.expected_hours_per_day" class="mt-1 text-sm text-red-700" role="alert">{{ placementErrors.expected_hours_per_day[0] }}</p>
        </div>
        <p v-if="placementError" class="text-sm text-red-700" role="alert">{{ placementError }}</p>
        <div class="flex flex-wrap justify-end gap-2">
          <button type="button" class="app-button app-button--secondary" :disabled="savingPlacement" @click="cancelPlacementEdit">Cancel</button>
          <button type="submit" class="app-button app-button--primary" :disabled="savingPlacement">{{ savingPlacement ? 'Saving...' : 'Save placement' }}</button>
        </div>
      </form>
    </AppModal>

    <AppModal :open="resettingInternId !== null" title="Reset intern password" description="Give the new temporary password to the intern in person. They must change it at their next sign-in." :busy="savingReset" @close="cancelPasswordReset">
      <form v-if="resettingIntern" class="space-y-4" :data-testid="'reset-password-form-' + resettingIntern.id" @submit.prevent="savePasswordReset(resettingIntern.id)">
        <div>
          <label :for="'reset-password-' + resettingIntern.id" class="block text-sm font-semibold text-slate-800">New temporary password</label>
          <input :id="'reset-password-' + resettingIntern.id" v-model="resetForm.password" type="password" required minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <PasswordRequirements :password="resetForm.password" :id-prefix="'reset-intern-' + resettingIntern.id" />
          <p v-if="resetErrors.password" class="mt-1 text-sm text-red-700" role="alert">{{ resetErrors.password[0] }}</p>
        </div>
        <div>
          <label :for="'reset-password-confirmation-' + resettingIntern.id" class="block text-sm font-semibold text-slate-800">Confirm temporary password</label>
          <input :id="'reset-password-confirmation-' + resettingIntern.id" v-model="resetForm.password_confirmation" type="password" required minlength="12" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="resetErrors.password_confirmation" class="mt-1 text-sm text-red-700" role="alert">{{ resetErrors.password_confirmation[0] }}</p>
        </div>
        <p v-if="resetError" class="text-sm text-red-700" role="alert">{{ resetError }}</p>
        <div class="flex flex-wrap justify-end gap-2">
          <button type="button" class="app-button app-button--secondary" :disabled="savingReset" @click="cancelPasswordReset">Cancel</button>
          <button type="submit" class="app-button app-button--primary" :disabled="savingReset">{{ savingReset ? 'Saving...' : 'Save temporary password' }}</button>
        </div>
      </form>
    </AppModal>
  </main>
</template>