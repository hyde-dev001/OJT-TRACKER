<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import ActionAlert from '../../components/ActionAlert.vue'
import AppModal from '../../components/AppModal.vue'
import DatePickerField from '../../components/DatePickerField.vue'
import PageHeader from '../../components/PageHeader.vue'
import PaginationControls from '../../components/PaginationControls.vue'
import { apiErrorMessage } from '../../services/api'
import {
  endCompanyWorkLog,
  listCompanyInterns,
  listCompanyInternWorkLogs,
  startCompanyWorkLog,
  updateCompanyWorkLog,
} from '../../services/ojt'
import { todayInPhilippines } from '../../utils/dateBounds'
import { formatDate, formatDuration, formatTime } from '../../utils/formatters'

const interns = ref([])
const selectedInternId = ref('')
const logs = ref([])
const pagination = ref(null)
const currentPage = ref(1)
const loadingInterns = ref(true)
const loadingLogs = ref(false)
const internsError = ref('')
const logsError = ref('')
const actionAlert = ref({ open: false, variant: 'success', title: '', message: '' })
const fieldErrors = ref({})
const busyAction = ref('')
const editingLogId = ref(null)
const savingEdit = ref(false)
const editError = ref('')
const editErrors = ref({})
const editForm = reactive({ work_date: '', time_in: '', time_out: '', break_minutes: 0 })

const selectedIntern = computed(() => interns.value.find((intern) => intern.id === Number(selectedInternId.value)) ?? null)
const todayLog = computed(() => logs.value.find((log) => log.work_date === todayInPhilippines()) ?? null)
const openTodayLog = computed(() => todayLog.value?.status === 'in_progress' ? todayLog.value : null)
const editingLog = computed(() => logs.value.find((log) => log.id === editingLogId.value) ?? null)
const showAlert = (variant, title, message) => { actionAlert.value = { open: true, variant, title, message } }

const loadLogs = async (page = 1) => {
  if (!selectedInternId.value) return false

  loadingLogs.value = true
  logsError.value = ''
  currentPage.value = page

  try {
    const result = await listCompanyInternWorkLogs(Number(selectedInternId.value), { page })
    logs.value = result?.items ?? []
    pagination.value = result?.meta ?? null
    return true
  } catch (error) {
    logsError.value = apiErrorMessage(error, 'Unable to load this intern’s work hours.')
    return false
  } finally {
    loadingLogs.value = false
  }
}

const loadInterns = async () => {
  loadingInterns.value = true
  internsError.value = ''

  try {
    interns.value = await listCompanyInterns()
    selectedInternId.value = interns.value.length ? String(interns.value[0].id) : ''
    logs.value = []
    pagination.value = null
    if (selectedInternId.value) await loadLogs(1)
  } catch (error) {
    internsError.value = apiErrorMessage(error, 'Unable to load intern accounts. Please try again.')
  } finally {
    loadingInterns.value = false
  }
}

const selectIntern = async (event) => {
  selectedInternId.value = event.target.value
  logs.value = []
  pagination.value = null
  fieldErrors.value = {}
  await loadLogs(1)
}

const clearActionFeedback = () => {
  fieldErrors.value = {}
}

const showActionFailure = (error, fallback) => {
  fieldErrors.value = error?.response?.data?.errors ?? {}
  showAlert('error', 'Attendance action not saved', apiErrorMessage(error, fallback))
}

const recordTimeIn = async () => {
  if (!selectedInternId.value || busyAction.value) return

  clearActionFeedback()
  busyAction.value = 'time-in'
  try {
    await startCompanyWorkLog(Number(selectedInternId.value))
    if (await loadLogs(1)) showAlert('success', 'Time in recorded', 'The intern’s work session has started.')
  } catch (error) {
    showActionFailure(error, 'Unable to record time in. Please try again.')
  } finally {
    busyAction.value = ''
  }
}

const recordTimeOut = async () => {
  if (!selectedInternId.value || busyAction.value) return

  clearActionFeedback()
  busyAction.value = 'time-out'
  try {
    await endCompanyWorkLog(Number(selectedInternId.value), { break_minutes: Number(openTodayLog.value?.break_minutes ?? 0) })
    if (await loadLogs(1)) showAlert('success', 'Time out recorded', 'The intern’s work session has been saved.')
  } catch (error) {
    showActionFailure(error, 'Unable to record time out. Please try again.')
  } finally {
    busyAction.value = ''
  }
}

const openEdit = (log) => {
  clearActionFeedback()
  editError.value = ''
  editErrors.value = {}
  editingLogId.value = log.id
  Object.assign(editForm, {
    work_date: log.work_date,
    time_in: log.time_in,
    time_out: log.time_out ?? '',
    break_minutes: log.break_minutes ?? 0,
  })
}

const closeEdit = () => {
  if (savingEdit.value) return
  editingLogId.value = null
  editError.value = ''
  editErrors.value = {}
}

const saveEdit = async () => {
  if (!selectedInternId.value || !editingLog.value || savingEdit.value) return

  editError.value = ''
  editErrors.value = {}
  savingEdit.value = true
  try {
    await updateCompanyWorkLog(Number(selectedInternId.value), editingLog.value.id, {
      work_date: editForm.work_date,
      time_in: editForm.time_in,
      time_out: editForm.time_out || null,
      break_minutes: Number(editForm.break_minutes),
    })
    if (await loadLogs(currentPage.value)) {
      editingLogId.value = null
      showAlert('success', 'Work hours updated', 'The attendance record was corrected.')
    }
  } catch (error) {
    editErrors.value = error?.response?.data?.errors ?? {}
    editError.value = apiErrorMessage(error, 'Unable to update these work hours. Please try again.')
    showAlert('error', 'Work hours not updated', editError.value)
  } finally {
    savingEdit.value = false
  }
}

const changePage = (page) => loadLogs(page)
const firstError = (errors, field) => errors[field]?.[0] ?? ''

onMounted(loadInterns)
</script>

<template>
  <main class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
    <PageHeader title="Attendance" description="Record one daily work session for an intern and correct saved work hours when needed." />

    <ActionAlert :open="actionAlert.open" :variant="actionAlert.variant" :title="actionAlert.title" :message="actionAlert.message" @close="actionAlert.open = false" />

    <section class="space-y-4" aria-labelledby="attendance-title">
      <div>
        <h2 id="attendance-title" class="text-xl font-semibold tracking-tight text-slate-950">Intern attendance</h2>
        <p class="mt-1 text-sm text-slate-600">The server records time in and time out. Break minutes are entered at time out.</p>
      </div>

      <p v-if="loadingInterns" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600" role="status">Loading intern accounts...</p>
      <div v-else-if="internsError" class="rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
        <p class="text-sm text-red-800">{{ internsError }}</p>
        <button type="button" class="app-button app-button--secondary mt-3" data-testid="retry-company-attendance" @click="loadInterns">Try again</button>
      </div>
      <p v-else-if="!interns.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">No intern accounts yet. Create an intern account before recording attendance.</p>
      <template v-else>
        <div class="max-w-xl">
          <label for="attendance-intern-select" class="block text-sm font-semibold text-slate-800">Select intern</label>
          <select id="attendance-intern-select" data-testid="attendance-intern-select" :value="selectedInternId" class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" @change="selectIntern">
            <option v-for="intern in interns" :key="intern.id" :value="String(intern.id)">{{ intern.name }} — {{ intern.email }}</option>
          </select>
        </div>

        <section v-if="selectedIntern" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="today-session-title">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 id="today-session-title" class="text-lg font-semibold text-slate-950">Today’s session</h3>
              <p class="mt-1 text-sm text-slate-600">{{ formatDate(todayInPhilippines()) }} · {{ selectedIntern.name }}</p>
            </div>
            <span v-if="openTodayLog" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900">In progress</span>
            <span v-else-if="todayLog" class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">Completed</span>
          </div>

          <p v-if="loadingLogs" class="text-sm text-slate-600" role="status">Loading this intern’s work hours...</p>
          <div v-else-if="logsError" class="rounded-lg border border-red-200 bg-red-50 p-4" role="alert">
            <p class="text-sm text-red-800">{{ logsError }}</p>
            <button type="button" class="app-button app-button--secondary mt-3" data-testid="retry-company-work-logs" @click="loadLogs(currentPage)">Try again</button>
          </div>
          <template v-else-if="openTodayLog" class="space-y-3">
            <p class="text-sm text-slate-700">Time in: <span class="font-semibold">{{ formatTime(openTodayLog.time_in) }}</span></p>
            <p class="text-sm text-slate-600">Time out keeps the saved break minutes (0 by default). Use Edit work hours below to update the break if needed.</p>
            <button type="button" class="app-button app-button--primary" :data-testid="`time-out-${selectedIntern.id}`" :disabled="Boolean(busyAction)" @click="recordTimeOut">{{ busyAction === 'time-out' ? 'Recording time out...' : 'Time out' }}</button>
          </template>
          <div v-else-if="!loadingLogs && !logsError && todayLog" class="rounded-lg bg-slate-50 p-4 text-sm text-slate-700">
            One session is already recorded for today. You can edit its work hours below.
          </div>
          <div v-else-if="!loadingLogs && !logsError" class="space-y-3">
            <p v-if="firstError(fieldErrors, 'work_date')" class="text-sm text-red-700" role="alert">{{ firstError(fieldErrors, 'work_date') }}</p>
            <button type="button" class="app-button app-button--primary" :data-testid="`time-in-${selectedIntern.id}`" :disabled="Boolean(busyAction)" @click="recordTimeIn">{{ busyAction === 'time-in' ? 'Recording time in...' : 'Time in' }}</button>
          </div>
        </section>

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="work-sessions-title">
          <div>
            <h3 id="work-sessions-title" class="text-lg font-semibold text-slate-950">Work sessions</h3>
            <p class="mt-1 text-sm text-slate-600">Completed sessions count toward the intern’s rendered OJT hours.</p>
          </div>

          <template v-if="!loadingLogs && !logsError">
            <p v-if="!logs.length" class="rounded-lg border border-dashed border-slate-300 p-5 text-sm text-slate-600">No work sessions yet.</p>
            <div v-else class="space-y-3">
              <article v-for="log in logs" :key="log.id" class="space-y-4 rounded-lg border border-slate-200 p-4" :data-testid="`company-work-log-${log.id}`">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h4 class="font-semibold text-slate-950">{{ formatDate(log.work_date) }}</h4>
                    <span :class="log.status === 'in_progress' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-emerald-200 bg-emerald-50 text-emerald-800'" class="mt-2 inline-flex rounded-full border px-3 py-1 text-xs font-semibold">{{ log.status === 'in_progress' ? 'In progress' : 'Completed' }}</span>
                  </div>
                  <button type="button" class="app-button app-button--secondary" :data-testid="`edit-work-log-${log.id}`" @click="openEdit(log)">Edit work hours</button>
                </div>
                <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                  <div><dt class="font-medium text-slate-500">Time in</dt><dd class="mt-1 text-slate-900">{{ formatTime(log.time_in) }}</dd></div>
                  <div><dt class="font-medium text-slate-500">Time out</dt><dd class="mt-1 text-slate-900">{{ formatTime(log.time_out) }}</dd></div>
                  <div><dt class="font-medium text-slate-500">Break</dt><dd class="mt-1 text-slate-900">{{ log.break_minutes }} minutes</dd></div>
                  <div><dt class="font-medium text-slate-500">Rendered duration</dt><dd class="mt-1 text-slate-900">{{ log.rendered_minutes === null ? '—' : formatDuration(log.rendered_minutes) }}</dd></div>
                </dl>
              </article>
              <PaginationControls :meta="pagination" @change="changePage" />
            </div>
          </template>
        </section>
      </template>
    </section>

    <AppModal :open="editingLogId !== null" title="Edit work hours" description="Correct the work date, times, or break. The server recalculates the rendered duration." :busy="savingEdit" :close-on-backdrop="false" @close="closeEdit">
      <form v-if="editingLog" id="edit-work-log-form" class="space-y-4" data-testid="edit-work-log-form" @submit.prevent="saveEdit">
        <div>
          <label for="edit-work-log-date" class="block text-sm font-semibold text-slate-800">Work date</label>
          <DatePickerField id="edit-work-log-date" v-model="editForm.work_date" required :allowed-weekdays="selectedIntern?.work_days ?? []" allow-current-value :invalid="Boolean(firstError(editErrors, 'work_date'))" :describedby="firstError(editErrors, 'work_date') ? 'edit-work-log-date-error' : undefined" title="Choose work date" description="Keep the saved date or choose a workday in the current placement schedule." test-id-prefix="edit-work-log-date" />
          <p v-if="firstError(editErrors, 'work_date')" id="edit-work-log-date-error" class="mt-1 text-sm text-red-700" role="alert">{{ firstError(editErrors, 'work_date') }}</p>
          <p v-else class="mt-1 text-xs leading-5 text-slate-500">An existing date can remain if the placement schedule changed. New dates are checked against the current schedule.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="edit-work-log-time-in" class="block text-sm font-semibold text-slate-800">Time in</label>
            <input id="edit-work-log-time-in" v-model="editForm.time_in" type="time" required :aria-invalid="Boolean(firstError(editErrors, 'time_in'))" :aria-describedby="firstError(editErrors, 'time_in') ? 'edit-work-log-time-in-error' : undefined" class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <p v-if="firstError(editErrors, 'time_in')" id="edit-work-log-time-in-error" class="mt-1 text-sm text-red-700" role="alert">{{ firstError(editErrors, 'time_in') }}</p>
          </div>
          <div>
            <label for="edit-work-log-time-out" class="block text-sm font-semibold text-slate-800">Time out <span class="font-normal text-slate-500">(leave blank if still in progress)</span></label>
            <input id="edit-work-log-time-out" v-model="editForm.time_out" type="time" :aria-invalid="Boolean(firstError(editErrors, 'time_out'))" :aria-describedby="firstError(editErrors, 'time_out') ? 'edit-work-log-time-out-error' : undefined" class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
            <p v-if="firstError(editErrors, 'time_out')" id="edit-work-log-time-out-error" class="mt-1 text-sm text-red-700" role="alert">{{ firstError(editErrors, 'time_out') }}</p>
          </div>
        </div>
        <div class="max-w-xs">
          <label for="edit-work-log-break-minutes" class="block text-sm font-semibold text-slate-800">Break minutes</label>
          <input id="edit-work-log-break-minutes" v-model.number="editForm.break_minutes" type="number" min="0" step="1" required :aria-invalid="Boolean(firstError(editErrors, 'break_minutes'))" :aria-describedby="firstError(editErrors, 'break_minutes') ? 'edit-work-log-break-error' : undefined" class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="firstError(editErrors, 'break_minutes')" id="edit-work-log-break-error" class="mt-1 text-sm text-red-700" role="alert">{{ firstError(editErrors, 'break_minutes') }}</p>
        </div>
        <p v-if="editError" class="text-sm text-red-700" role="alert">{{ editError }}</p>
      </form>
      <template #footer>
        <button type="button" class="app-button app-button--secondary" :disabled="savingEdit" @click="closeEdit">Cancel</button>
        <button type="submit" form="edit-work-log-form" class="app-button app-button--primary" :disabled="savingEdit">{{ savingEdit ? 'Saving work hours...' : 'Save work hours' }}</button>
      </template>
    </AppModal>
  </main>
</template>
