<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import DatePickerField from '../../components/DatePickerField.vue'
import PageHeader from '../../components/PageHeader.vue'
import PaginationControls from '../../components/PaginationControls.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import { apiErrorMessage } from '../../services/api'
import {
  approveCompanyTask,
  createCompanyInternTask,
  downloadCompanyTaskProof,
  listCompanyInterns,
  listCompanyInternTasks,
  requestCompanyTaskChanges,
} from '../../services/ojt'
import { formatDate } from '../../utils/formatters'

const interns = ref([])
const selectedInternId = ref('')
const tasks = ref([])
const pagination = ref(null)
const currentPage = ref(1)
const loadingInterns = ref(true)
const loadingTasks = ref(false)
const internsError = ref('')
const tasksError = ref('')
const actionError = ref('')
const fieldErrors = ref({})
const successMessage = ref('')
const creating = ref(false)
const busyAction = ref('')
const downloadingTaskId = ref(null)
const reviewComments = reactive({})
const reviewErrors = reactive({})
const form = reactive({ title: '', description: '', due_date: '' })

const selectedIntern = computed(() => interns.value.find((intern) => intern.id === Number(selectedInternId.value)) ?? null)

const loadTasks = async (page = 1) => {
  if (!selectedInternId.value) return false

  loadingTasks.value = true
  tasksError.value = ''
  currentPage.value = page
  try {
    const result = await listCompanyInternTasks(Number(selectedInternId.value), { page })
    tasks.value = result?.items ?? []
    pagination.value = result?.meta ?? null
    return true
  } catch (error) {
    tasksError.value = apiErrorMessage(error, 'Unable to load this intern’s tasks.')
    return false
  } finally {
    loadingTasks.value = false
  }
}

const loadInterns = async () => {
  loadingInterns.value = true
  internsError.value = ''
  try {
    interns.value = await listCompanyInterns()
    selectedInternId.value = interns.value.length ? String(interns.value[0].id) : ''
    tasks.value = []
    pagination.value = null
    if (selectedInternId.value) await loadTasks(1)
  } catch (error) {
    internsError.value = apiErrorMessage(error, 'Unable to load intern accounts. Please try again.')
  } finally {
    loadingInterns.value = false
  }
}

const selectIntern = async (event) => {
  selectedInternId.value = event.target.value
  tasks.value = []
  pagination.value = null
  successMessage.value = ''
  actionError.value = ''
  fieldErrors.value = {}
  await loadTasks(1)
}

const firstError = (field) => fieldErrors.value[field]?.[0] ?? ''

const createTask = async () => {
  fieldErrors.value = {}
  actionError.value = ''
  successMessage.value = ''
  if (!form.title.trim()) {
    fieldErrors.value = { title: ['Enter a task title.'] }
    return
  }
  if (!selectedInternId.value || creating.value) return

  creating.value = true
  try {
    await createCompanyInternTask(Number(selectedInternId.value), {
      title: form.title.trim(),
      description: form.description.trim() || null,
      due_date: form.due_date || null,
    })
    form.title = ''
    form.description = ''
    form.due_date = ''
    if (await loadTasks(1)) successMessage.value = 'Task assigned.'
  } catch (error) {
    fieldErrors.value = error?.response?.data?.errors ?? {}
    actionError.value = apiErrorMessage(error, 'Unable to assign this task. Please try again.')
  } finally {
    creating.value = false
  }
}

const review = async (task, decision) => {
  const comment = (reviewComments[task.id] ?? '').trim()
  actionError.value = ''
  reviewErrors[task.id] = ''

  if (decision === 'request-changes' && !comment) {
    reviewErrors[task.id] = 'Add a comment before requesting changes.'
    return
  }
  if (busyAction.value) return

  busyAction.value = `${task.id}:${decision}`
  try {
    if (decision === 'approve') {
      await approveCompanyTask(Number(selectedInternId.value), task.id, comment || undefined)
    } else {
      await requestCompanyTaskChanges(Number(selectedInternId.value), task.id, { comment })
    }
    reviewComments[task.id] = ''
    if (await loadTasks(currentPage.value)) {
      successMessage.value = decision === 'approve' ? 'Task approved.' : 'Changes requested.'
    }
  } catch (error) {
    reviewErrors[task.id] = error?.response?.data?.errors?.comment?.[0] ?? ''
    actionError.value = apiErrorMessage(error, 'Unable to review this task. Please try again.')
  } finally {
    busyAction.value = ''
  }
}

const downloadProof = async (task) => {
  if (downloadingTaskId.value !== null) return

  downloadingTaskId.value = task.id
  actionError.value = ''
  try {
    const response = await downloadCompanyTaskProof(Number(selectedInternId.value), task.id)
    const disposition = response.headers?.['content-disposition'] ?? ''
    const filename = /filename="?([^";]+)"?/i.exec(disposition)?.[1] ?? 'task-proof'
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (error) {
    actionError.value = apiErrorMessage(error, 'Unable to download this proof. Please try again.')
  } finally {
    downloadingTaskId.value = null
  }
}

const submissionLabel = (status) => ({
  not_submitted: 'Not submitted',
  pending_review: 'Pending review',
  changes_requested: 'Changes requested',
  approved: 'Approved',
}[status] ?? 'Unknown')

const submissionTone = (status) => ({
  not_submitted: 'bg-slate-100 text-slate-700',
  pending_review: 'bg-amber-50 text-amber-900',
  changes_requested: 'bg-red-50 text-red-800',
  approved: 'bg-emerald-50 text-emerald-800',
}[status] ?? 'bg-slate-100 text-slate-700')

onMounted(loadInterns)
</script>

<template>
  <main class="mx-auto max-w-5xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
    <PageHeader title="Tasks" description="Assign tasks to your interns and review their proof submissions." />

    <p v-if="successMessage" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ successMessage }}</p>
    <p v-if="actionError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ actionError }}</p>

    <p v-if="loadingInterns" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600" role="status">Loading intern accounts...</p>
    <div v-else-if="internsError" class="rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
      <p class="text-sm text-red-800">{{ internsError }}</p>
      <button type="button" class="app-button app-button--secondary mt-3" data-testid="retry-company-interns" @click="loadInterns">Try again</button>
    </div>
    <p v-else-if="!interns.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">No intern accounts yet. Create an intern account before assigning tasks.</p>
    <template v-else>
      <div class="max-w-xl">
        <label for="company-task-intern-select" class="block text-sm font-semibold text-slate-800">Select intern</label>
        <select id="company-task-intern-select" data-testid="company-task-intern-select" v-model="selectedInternId" class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" @change="selectIntern">
          <option v-for="intern in interns" :key="intern.id" :value="String(intern.id)">{{ intern.name }} — {{ intern.email }}</option>
        </select>
      </div>

      <form v-if="selectedIntern" data-testid="company-task-form" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" @submit.prevent="createTask">
        <div>
          <h2 class="text-lg font-semibold text-slate-950">Assign a task</h2>
          <p class="mt-1 text-sm text-slate-600">Assignments cannot be edited or deleted after creation.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label for="company-task-title" class="block text-sm font-semibold text-slate-800">Task title</label>
            <input id="company-task-title" v-model="form.title" maxlength="255" required class="mt-1 block min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" :aria-invalid="Boolean(firstError('title'))" :aria-describedby="firstError('title') ? 'company-task-title-error' : undefined" />
            <p v-if="firstError('title')" id="company-task-title-error" data-testid="company-task-title-error" class="mt-1 text-sm text-red-700" role="alert">{{ firstError('title') }}</p>
          </div>
          <div class="sm:col-span-2">
            <label for="company-task-description" class="block text-sm font-semibold text-slate-800">Description <span class="font-normal text-slate-500">(optional)</span></label>
            <textarea id="company-task-description" v-model="form.description" rows="3" maxlength="5000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          </div>
          <div>
            <label for="company-task-due-date" class="block text-sm font-semibold text-slate-800">Due date <span class="font-normal text-slate-500">(optional)</span></label>
            <DatePickerField id="company-task-due-date" v-model="form.due_date" :min="selectedIntern.start_date" :max="selectedIntern.end_date" :invalid="Boolean(firstError('due_date'))" :describedby="firstError('due_date') ? 'company-task-due-date-error' : undefined" />
            <p v-if="firstError('due_date')" id="company-task-due-date-error" class="mt-1 text-sm text-red-700" role="alert">{{ firstError('due_date') }}</p>
          </div>
        </div>
        <p v-if="actionError && fieldErrors.title" class="text-sm text-red-700" role="alert">{{ actionError }}</p>
        <button type="submit" class="app-button app-button--primary" :disabled="creating">{{ creating ? 'Assigning...' : 'Assign task' }}</button>
      </form>

      <section v-if="selectedIntern" class="space-y-4" aria-labelledby="company-tasks-list-title">
        <div>
          <h2 id="company-tasks-list-title" class="text-xl font-semibold tracking-tight text-slate-950">{{ selectedIntern.name }}’s tasks</h2>
          <p class="mt-1 text-sm text-slate-600">Task completion and proof review are shown separately.</p>
        </div>
        <p v-if="loadingTasks" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600" role="status">Loading tasks...</p>
        <div v-else-if="tasksError" class="rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
          <p class="text-sm text-red-800">{{ tasksError }}</p>
          <button type="button" class="app-button app-button--secondary mt-3" data-testid="retry-company-tasks" @click="loadTasks(currentPage)">Try again</button>
        </div>
        <p v-else-if="!tasks.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">No tasks assigned yet.</p>
        <div v-else class="space-y-4">
          <article v-for="task in tasks" :key="task.id" :data-testid="`company-task-${task.id}`" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="text-base font-semibold text-slate-950">{{ task.title }}</h3>
                <p v-if="task.description" class="mt-1 whitespace-pre-wrap text-sm text-slate-600">{{ task.description }}</p>
                <p v-if="task.due_date" class="mt-2 text-sm text-slate-600">Due {{ formatDate(task.due_date) }}</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <StatusBadge :status="task.status" />
                <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', submissionTone(task.submission_status)]">{{ submissionLabel(task.submission_status) }}</span>
              </div>
            </div>
            <p v-if="task.submission_comment" class="text-sm text-slate-700"><span class="font-semibold">Intern comment:</span> {{ task.submission_comment }}</p>
            <p v-if="task.review_comment" class="text-sm text-slate-700"><span class="font-semibold">Latest review comment:</span> {{ task.review_comment }}</p>
            <button v-if="task.proof_available" type="button" class="app-button app-button--secondary" :disabled="downloadingTaskId === task.id" :data-testid="`download-task-proof-${task.id}`" @click="downloadProof(task)">{{ downloadingTaskId === task.id ? 'Downloading...' : 'Download proof' }}</button>

            <div v-if="task.submission_status === 'pending_review'" class="space-y-3 border-t border-slate-200 pt-4">
              <div>
                <label :for="`task-review-comment-${task.id}`" class="block text-sm font-semibold text-slate-800">Review comment <span class="font-normal text-slate-500">(optional for approval; required for changes)</span></label>
                <textarea :id="`task-review-comment-${task.id}`" v-model="reviewComments[task.id]" rows="2" maxlength="5000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" :aria-invalid="Boolean(reviewErrors[task.id])" :aria-describedby="reviewErrors[task.id] ? `task-review-comment-error-${task.id}` : undefined" />
                <p v-if="reviewErrors[task.id]" :id="`task-review-comment-error-${task.id}`" :data-testid="`task-review-comment-error-${task.id}`" class="mt-1 text-sm text-red-700" role="alert">{{ reviewErrors[task.id] }}</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="app-button app-button--primary" :disabled="Boolean(busyAction)" :data-testid="`approve-task-${task.id}`" @click="review(task, 'approve')">{{ busyAction === `${task.id}:approve` ? 'Approving...' : 'Approve' }}</button>
                <button type="button" class="app-button app-button--secondary" :disabled="Boolean(busyAction)" :data-testid="`request-changes-task-${task.id}`" @click="review(task, 'request-changes')">{{ busyAction === `${task.id}:request-changes` ? 'Saving...' : 'Request changes' }}</button>
              </div>
            </div>
          </article>
          <PaginationControls :meta="pagination" @change="loadTasks" />
        </div>
      </section>
    </template>
  </main>
</template>
