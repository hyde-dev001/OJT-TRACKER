<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { apiErrorMessage } from '../../services/api'
import ActionAlert from '../../components/ActionAlert.vue'
import AppModal from '../../components/AppModal.vue'
import ConfirmDialog from '../../components/ConfirmDialog.vue'
import DatePickerField from '../../components/DatePickerField.vue'
import EmptyState from '../../components/EmptyState.vue'
import PageHeader from '../../components/PageHeader.vue'
import PaginationControls from '../../components/PaginationControls.vue'
import ProgressBar from '../../components/ProgressBar.vue'
import { internshipDateBounds, todayInPhilippines } from '../../utils/dateBounds'
import { formatDate, formatDuration, formatTime } from '../../utils/formatters'
import {
  createWorkLog,
  deleteWorkLog,
  getStudentInternship,
  listStudentWorkLogs,
  updateStudentInternship,
  updateWorkLog,
} from '../../services/ojt'

const internship = ref(null)
const logs = ref([])
const pagination = ref(null)
const currentPage = ref(1)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const formError = ref('')
const fieldErrors = ref({})
const editingId = ref(null)
const formOpen = ref(false)
const viewingLog = ref(null)
const confirmLog = ref(null)
const confirmBusy = ref(false)
const goalFormOpen = ref(false)
const goalHours = ref(0)
const goalSaving = ref(false)
const goalError = ref('')
const actionAlert = ref({ open: false, variant: 'success', title: '', message: '' })

const emptyForm = () => ({
  work_date: todayInPhilippines(),
  time_in: '',
  time_out: '',
  break_minutes: 0,
  accomplishment_summary: '',
})

const form = reactive(emptyForm())
const editingLabel = computed(() => editingId.value ? 'Edit work log' : 'Add work log')
const confirmBusyLabel = computed(() => confirmBusy.value ? 'Deleting...' : 'Delete work log')
const internshipHasNotStarted = computed(() => {
  const startDate = internship.value?.start_date
  return Boolean(startDate && startDate > todayInPhilippines())
})
const workDateMin = computed(() => internshipHasNotStarted.value ? undefined : internshipDateBounds(internship.value).min)
const workDateMax = computed(() => internshipHasNotStarted.value ? undefined : internshipDateBounds(internship.value).max)
const configuredWorkDays = computed(() => {
  const workDays = internship.value?.work_days

  return Array.isArray(workDays) && workDays.length
    ? workDays.map(Number)
    : [1, 2, 3, 4, 5, 6, 7]
})

const showAlert = (variant, title, message) => {
  actionAlert.value = { open: true, variant, title, message }
}

const closeAlert = () => {
  actionAlert.value.open = false
}

const load = async () => {
  loading.value = true
  error.value = ''

  try {
    const [internshipData, logPage] = await Promise.all([
      getStudentInternship(),
      listStudentWorkLogs({ page: currentPage.value }),
    ])
    internship.value = internshipData
    if (Array.isArray(logPage)) {
      logs.value = logPage
      pagination.value = null
    } else {
      logs.value = logPage?.items ?? []
      pagination.value = logPage?.meta ?? null
    }
  } catch (requestError) {
    error.value = apiErrorMessage(requestError, 'Unable to load your work-hour records.')
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  Object.assign(form, emptyForm())
  editingId.value = null
  fieldErrors.value = {}
  formError.value = ''
}

const openCreate = () => {
  if (internshipHasNotStarted.value) return

  resetForm()
  form.work_date = findDefaultWorkDate()
  formOpen.value = true
}

const editLog = (log) => {
  Object.assign(form, {
    work_date: log.work_date,
    time_in: log.time_in,
    time_out: log.time_out,
    break_minutes: log.break_minutes,
    accomplishment_summary: log.accomplishment_summary ?? '',
  })
  editingId.value = log.id
  fieldErrors.value = {}
  formError.value = ''
  formOpen.value = true
}

const closeForm = () => {
  if (saving.value) return
  formOpen.value = false
  resetForm()
}

const selectWorkDate = (value) => {
  form.work_date = value
  if (fieldErrors.value.work_date) {
    const { work_date: _workDate, ...remainingErrors } = fieldErrors.value
    fieldErrors.value = remainingErrors
  }
}

const save = async () => {
  if (saving.value) return

  saving.value = true
  formError.value = ''
  fieldErrors.value = {}
  const payload = { ...form, break_minutes: Number(form.break_minutes) }
  const wasEditing = Boolean(editingId.value)

  try {
    if (wasEditing) {
      await updateWorkLog(editingId.value, payload)
    } else {
      await createWorkLog(payload)
    }
    await load()
    formOpen.value = false
    resetForm()
    showAlert('success', 'Saved successfully', wasEditing
      ? 'Your work log changes were saved.'
      : 'Your work log was saved and counted toward your OJT hours.')
  } catch (requestError) {
    fieldErrors.value = requestError?.response?.data?.errors ?? {}
    formError.value = apiErrorMessage(requestError, 'Unable to save this work log.')
  } finally {
    saving.value = false
  }
}

const openConfirm = (log) => {
  confirmLog.value = log
}

const closeConfirm = () => {
  if (!confirmBusy.value) confirmLog.value = null
}

const remove = async () => {
  if (!confirmLog.value || confirmBusy.value) return

  confirmBusy.value = true
  try {
    await deleteWorkLog(confirmLog.value.id)
    await load()
    if (pagination.value?.last_page && currentPage.value > pagination.value.last_page) {
      currentPage.value = pagination.value.last_page
      await load()
    }
    confirmLog.value = null
    showAlert('success', 'Deleted successfully', 'The work log was removed from your tracker.')
  } catch (requestError) {
    confirmLog.value = null
    showAlert('error', 'Unable to delete work log', apiErrorMessage(requestError, 'Please try again.'))
  } finally {
    confirmBusy.value = false
  }
}

const openGoalForm = () => {
  goalHours.value = internship.value?.progress?.required_hours ?? 0
  goalError.value = ''
  goalFormOpen.value = true
}

const closeGoalForm = () => {
  if (!goalSaving.value) goalFormOpen.value = false
}

const saveGoal = async () => {
  if (goalSaving.value) return

  goalSaving.value = true
  goalError.value = ''
  try {
    internship.value = await updateStudentInternship(Number(goalHours.value))
    goalFormOpen.value = false
    showAlert('success', 'Saved successfully', `Your tracker now requires ${internship.value.progress.required_hours} hours total for OJT.`)
  } catch (requestError) {
    goalError.value = apiErrorMessage(requestError, 'Unable to update your required OJT hours.')
  } finally {
    goalSaving.value = false
  }
}

const changePage = async (page) => {
  currentPage.value = page
  await load()
}

onMounted(load)

function isSelectableWorkDate(value) {
  const withinBounds = (!workDateMin.value || value >= workDateMin.value)
    && (!workDateMax.value || value <= workDateMax.value)

  if (!withinBounds) return false

  const isExistingDate = editingId.value && value === form.work_date
  return isExistingDate || configuredWorkDays.value.includes(weekdayNumber(value))
}

function findDefaultWorkDate() {
  const today = todayInPhilippines()
  let candidate = today

  if (workDateMin.value && candidate < workDateMin.value) candidate = workDateMin.value
  if (workDateMax.value && candidate > workDateMax.value) candidate = workDateMax.value

  for (let offset = 0; offset < 7; offset += 1) {
    const value = dateInputValue(addDays(candidate, -offset))
    if (isSelectableWorkDate(value)) return value
  }

  return candidate
}

function weekdayNumber(value) {
  const [year, month, day] = value.split('-').map(Number)
  const weekday = new Date(year, month - 1, day).getDay()
  return weekday === 0 ? 7 : weekday
}

function addDays(value, amount) {
  const [year, month, day] = value.split('-').map(Number)
  return new Date(year, month - 1, day + amount)
}

function dateInputValue(date) {
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-')
}
</script>

<template>
  <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <ActionAlert :open="actionAlert.open" :variant="actionAlert.variant" :title="actionAlert.title" :message="actionAlert.message" @close="closeAlert" />

    <PageHeader title="Work Hours" description="Record daily OJT activity and track your own rendered progress.">
      <template #action>
        <button type="button" class="app-button app-button--primary" data-testid="add-work-log" :disabled="loading || internshipHasNotStarted" @click="openCreate">+ Add work log</button>
      </template>
    </PageHeader>

    <div v-if="loading" class="mt-8 rounded-xl border border-slate-200 bg-white p-6 text-slate-600" role="status">Loading work hours...</div>
    <div v-else-if="error" class="mt-8 rounded-xl border border-red-200 bg-red-50 p-6 text-red-800" role="alert">
      <p>{{ error }}</p>
      <button type="button" class="app-button app-button--primary mt-4" @click="load">Try again</button>
    </div>
    <template v-else>
      <section class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="progress-title">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">OJT progress</p>
            <h2 id="progress-title" class="mt-2 text-xl font-semibold text-slate-950">Rendered hours</h2>
          </div>
          <div class="flex items-center gap-3">
            <p class="text-2xl font-semibold tracking-tight text-slate-950">{{ internship.progress.completed_hours }} / {{ internship.progress.required_hours }} hours</p>
            <button type="button" class="app-button app-button--secondary" data-testid="edit-hours-goal" @click="openGoalForm">Edit total hours</button>
          </div>
        </div>
        <div class="mt-5">
          <ProgressBar
            :percentage="internship.progress.percentage"
            :completed-label="`${internship.progress.completed_hours} hours completed`"
            :required-label="`${internship.progress.required_hours} hours required`"
            :remaining-label="`${internship.progress.remaining_hours} hours remaining`"
          />
        </div>
        <div data-testid="internship-period" class="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">OJT period</p>
          <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatDate(internship.start_date) }} - {{ formatDate(internship.end_date) }}</p>
        </div>
        <p class="mt-4 text-sm text-slate-600">New work logs count immediately and can be edited later.</p>
      </section>

      <section v-if="internshipHasNotStarted" data-testid="not-started-state" class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-5 sm:p-6" role="status">
        <h2 class="text-lg font-semibold text-slate-950">Your OJT hasn't started yet</h2>
        <p class="mt-2 text-sm leading-6 text-slate-700">Your OJT hasn't started yet. Work logging will be available on {{ formatDate(internship.start_date) }}.</p>
      </section>

      <section v-else class="mt-8" aria-labelledby="logs-title">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h2 id="logs-title" class="text-xl font-semibold text-slate-950">Work logs</h2>
            <p class="mt-1 text-sm text-slate-600">Save a work log, then edit it whenever details change.</p>
          </div>
          <span class="text-sm text-slate-500">{{ pagination?.total ?? logs.length }} record{{ (pagination?.total ?? logs.length) === 1 ? '' : 's' }}</span>
        </div>

        <EmptyState v-if="!logs.length" class="mt-5" title="No work logs yet" message="Add your first work log to begin tracking OJT hours." />
        <div v-else class="mt-5 space-y-3">
          <article v-for="log in logs" :key="log.id" :data-testid="`work-log-${log.id}`" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="font-semibold text-slate-950">{{ formatDate(log.work_date) }}</h3>
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ formatTime(log.time_in) }}-{{ formatTime(log.time_out) }} · {{ formatDuration(log.rendered_minutes) }}</p>
                <p v-if="log.accomplishment_summary" class="mt-3 text-sm leading-6 text-slate-700">{{ log.accomplishment_summary }}</p>
              </div>
              <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                <button type="button" class="app-button app-button--secondary" :data-testid="`view-log-${log.id}`" @click="viewingLog = log">View</button>
                <button type="button" class="app-button app-button--secondary" :data-testid="`edit-log-${log.id}`" @click="editLog(log)">Edit</button>
                <button type="button" class="app-button app-button--secondary text-red-700" :data-testid="`delete-log-${log.id}`" @click="openConfirm(log)">Delete</button>
              </div>
            </div>
          </article>
        </div>
        <PaginationControls :meta="pagination" class="mt-6" @change="changePage" />
      </section>
    </template>

    <AppModal :open="formOpen" :title="editingLabel" description="Enter the work schedule and a short accomplishment summary." :busy="saving" @close="closeForm">
      <form id="work-log-form" data-testid="work-log-form" class="space-y-4" @submit.prevent="save">
        <div>
          <label for="work-date" class="block text-sm font-semibold text-slate-800">Work date</label>
          <DatePickerField
            id="work-date"
            :model-value="form.work_date"
            :min="workDateMin ?? ''"
            :max="workDateMax ?? ''"
            :allowed-weekdays="configuredWorkDays"
            :allow-current-value="Boolean(editingId)"
            :invalid="Boolean(fieldErrors.work_date)"
            describedby="work-date-help"
            title="Choose work date"
            description="Select an available day from your configured OJT work schedule."
            test-id-prefix="work-date"
            @update:model-value="selectWorkDate"
          />
          <p id="work-date-help" class="mt-1 text-xs text-slate-500">Only configured OJT workdays can be selected.</p>
          <p v-if="fieldErrors.work_date" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.work_date[0] }}</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="time-in" class="block text-sm font-semibold text-slate-800">Time in</label>
            <input id="time-in" v-model="form.time_in" type="time" required :aria-invalid="Boolean(fieldErrors.time_in)" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <p v-if="fieldErrors.time_in" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.time_in[0] }}</p>
          </div>
          <div>
            <label for="time-out" class="block text-sm font-semibold text-slate-800">Time out</label>
            <input id="time-out" v-model="form.time_out" type="time" required :aria-invalid="Boolean(fieldErrors.time_out)" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <p v-if="fieldErrors.time_out" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.time_out[0] }}</p>
          </div>
        </div>
        <div>
          <label for="break-minutes" class="block text-sm font-semibold text-slate-800">Break minutes</label>
          <input id="break-minutes" v-model="form.break_minutes" type="number" min="0" required :aria-invalid="Boolean(fieldErrors.break_minutes)" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="fieldErrors.break_minutes" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.break_minutes[0] }}</p>
        </div>
        <div>
          <label for="accomplishment" class="block text-sm font-semibold text-slate-800">Accomplishment summary <span class="font-normal text-slate-500">(optional)</span></label>
          <textarea id="accomplishment" v-model="form.accomplishment_summary" rows="3" :aria-invalid="Boolean(fieldErrors.accomplishment_summary)" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="fieldErrors.accomplishment_summary" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.accomplishment_summary[0] }}</p>
        </div>
        <p v-if="formError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ formError }}</p>
      </form>
      <template #footer>
        <button type="button" class="app-button app-button--secondary" data-testid="cancel-work-log" :disabled="saving" @click="closeForm">Cancel</button>
        <button type="submit" form="work-log-form" class="app-button app-button--primary" :disabled="saving">{{ saving ? 'Saving...' : editingId ? 'Save changes' : 'Save work log' }}</button>
      </template>
    </AppModal>

    <AppModal :open="goalFormOpen" title="Edit OJT goal" description="Set the total number of hours required for this internship." :busy="goalSaving" @close="closeGoalForm">
      <form id="internship-form" class="space-y-4" @submit.prevent="saveGoal">
        <div>
          <label for="goal-hours" class="block text-sm font-semibold text-slate-800">Total required OJT hours</label>
          <input id="goal-hours" v-model="goalHours" type="number" min="1" max="10000" required class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="goalError" class="mt-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ goalError }}</p>
        </div>
      </form>
      <template #footer>
        <button type="button" class="app-button app-button--secondary" :disabled="goalSaving" @click="closeGoalForm">Cancel</button>
        <button type="submit" form="internship-form" class="app-button app-button--primary" :disabled="goalSaving">{{ goalSaving ? 'Saving...' : 'Save goal' }}</button>
      </template>
    </AppModal>

    <AppModal v-if="viewingLog" :open="Boolean(viewingLog)" title="Work log details" description="Review the saved schedule and accomplishment summary." @close="viewingLog = null">
      <dl class="grid gap-4 text-sm sm:grid-cols-2">
        <div><dt class="font-semibold text-slate-500">Date</dt><dd class="mt-1 text-slate-900">{{ formatDate(viewingLog.work_date) }}</dd></div>
        <div><dt class="font-semibold text-slate-500">Schedule</dt><dd class="mt-1 text-slate-900">{{ formatTime(viewingLog.time_in) }}-{{ formatTime(viewingLog.time_out) }}</dd></div>
        <div><dt class="font-semibold text-slate-500">Rendered</dt><dd class="mt-1 text-slate-900">{{ formatDuration(viewingLog.rendered_minutes) }}</dd></div>
      </dl>
      <p v-if="viewingLog.accomplishment_summary" class="mt-5 text-sm leading-6 text-slate-700">{{ viewingLog.accomplishment_summary }}</p>
    </AppModal>

    <ConfirmDialog
      :open="Boolean(confirmLog)"
      title="Delete this work log?"
      message="This work log will be permanently removed from your tracker."
      confirm-label="Delete work log"
      :busy-label="confirmBusyLabel"
      variant="danger"
      :busy="confirmBusy"
      @cancel="closeConfirm"
      @confirm="remove"
    />
  </main>
</template>
