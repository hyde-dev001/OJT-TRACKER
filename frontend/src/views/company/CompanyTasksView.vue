<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ActionAlert from '../../components/ActionAlert.vue'
import AppModal from '../../components/AppModal.vue'
import DatePickerField from '../../components/DatePickerField.vue'
import PageHeader from '../../components/PageHeader.vue'
import PaginationControls from '../../components/PaginationControls.vue'
import ProofPreviewModal from '../../components/ProofPreviewModal.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import { apiErrorMessage } from '../../services/api'
import {
  approveCompanyTask,
  createCompanyInternTask,
  downloadCompanyTaskProof,
  getCompanyInternTask,
  listCompanyInterns,
  listCompanyInternTasks,
  requestCompanyTaskChanges,
} from '../../services/ojt'
import { formatDate } from '../../utils/formatters'

const interns = ref([])
const route = useRoute()
const router = useRouter()
const selectedInternId = ref('')
const tasks = ref([])
const targetTask = ref(null)
const targetError = ref('')
const pagination = ref(null)
const currentPage = ref(1)
const loadingInterns = ref(true)
const loadingTasks = ref(false)
const internsError = ref('')
const tasksError = ref('')
const fieldErrors = ref({})
const actionAlert = ref({ open: false, variant: 'success', title: '', message: '' })
const creating = ref(false)
const assignmentModalOpen = ref(false)
const busyAction = ref('')
const downloadingTaskId = ref(null)
const previewingTaskId = ref(null)
const proofPreview = ref({ open: false, title: '', file: null })
const viewedProofIds = ref(new Set())
const reviewComments = reactive({})
const reviewErrors = reactive({})
const form = reactive({ title: '', description: '', due_date: '' })

const selectedIntern = computed(() => interns.value.find((intern) => intern.id === Number(selectedInternId.value)) ?? null)
const visibleTasks = computed(() => targetTask.value && !tasks.value.some((task) => task.id === targetTask.value.id)
  ? [targetTask.value, ...tasks.value]
  : tasks.value)
const showAlert = (variant, title, message) => { actionAlert.value = { open: true, variant, title, message } }
const openAssignmentModal = () => {
  fieldErrors.value = {}
  assignmentModalOpen.value = true
}
const closeAssignmentModal = () => {
  if (!creating.value) assignmentModalOpen.value = false
}

const loadTasks = async (page = 1) => {
  if (!selectedInternId.value) return false

  viewedProofIds.value = new Set()
  loadingTasks.value = true
  tasksError.value = ''
  targetError.value = ''
  currentPage.value = page
  try {
    const result = await listCompanyInternTasks(Number(selectedInternId.value), { page })
    tasks.value = result?.items ?? []
    pagination.value = result?.meta ?? null
    targetTask.value = null
    if (route.query.task_id) {
      if (Number(route.query.intern_id) !== Number(selectedInternId.value)) {
        targetError.value = 'This notification link does not match the selected intern.'
      } else {
        const taskId = Number(route.query.task_id)
        try {
          targetTask.value = tasks.value.find((task) => task.id === taskId) ?? await getCompanyInternTask(Number(selectedInternId.value), taskId)
          const hash = `#company-task-${taskId}`
          if (route.hash !== hash) await router.replace({ query: { ...route.query }, hash })
        } catch (error) {
          targetError.value = apiErrorMessage(error, 'Unable to open this notification. The task may no longer be available.')
        }
      }
    }
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
    const requestedInternId = Number(route.query.intern_id)
    const requestedIntern = interns.value.find((intern) => intern.id === requestedInternId)
    selectedInternId.value = route.query.intern_id
      ? requestedIntern ? String(requestedIntern.id) : ''
      : interns.value.length ? String(interns.value[0].id) : ''
    tasks.value = []
    pagination.value = null
    targetTask.value = null
    targetError.value = route.query.intern_id && !requestedIntern ? 'This notification link is no longer available.' : ''
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
  targetTask.value = null
  targetError.value = ''
  pagination.value = null
  fieldErrors.value = {}
  viewedProofIds.value = new Set()
  if (route.query.intern_id || route.query.task_id) {
    const query = { ...route.query }
    delete query.intern_id
    delete query.task_id
    await router.replace({ query, hash: '' })
    return
  }
  await loadTasks(1)
}

const firstError = (field) => fieldErrors.value[field]?.[0] ?? ''

const createTask = async () => {
  fieldErrors.value = {}
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
    assignmentModalOpen.value = false
    form.title = ''
    form.description = ''
    form.due_date = ''
    if (await loadTasks(1)) showAlert('success', 'Task assigned', 'The task was assigned to the selected intern.')
  } catch (error) {
    fieldErrors.value = error?.response?.data?.errors ?? {}
    showAlert('error', 'Task not assigned', apiErrorMessage(error, 'Unable to assign this task. Please try again.'))
  } finally {
    creating.value = false
  }
}

const review = async (task, decision) => {
  const comment = (reviewComments[task.id] ?? '').trim()
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
      showAlert('success', decision === 'approve' ? 'Task approved' : 'Changes requested', decision === 'approve' ? 'This task is complete.' : 'The intern can replace the proof and submit it again.')
    }
  } catch (error) {
    reviewErrors[task.id] = error?.response?.data?.errors?.comment?.[0] ?? ''
    showAlert('error', 'Review not saved', apiErrorMessage(error, 'Unable to review this task. Please try again.'))
  } finally {
    busyAction.value = ''
  }
}

const downloadProof = async (task) => {
  if (downloadingTaskId.value !== null) return

  downloadingTaskId.value = task.id
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
    showAlert('error', 'Proof not downloaded', apiErrorMessage(error, 'Unable to download this proof. Please try again.'))
  } finally {
    downloadingTaskId.value = null
  }
}

const viewProof = async (task) => {
  if (previewingTaskId.value !== null) return
  previewingTaskId.value = task.id
  try {
    const response = await downloadCompanyTaskProof(Number(selectedInternId.value), task.id)
    proofPreview.value = { open: true, title: task.title, file: response.data }
    viewedProofIds.value = new Set([...viewedProofIds.value, task.id])
  } catch (error) {
    showAlert('error', 'Proof not opened', apiErrorMessage(error, 'Unable to open this proof. Please try again.'))
  } finally {
    previewingTaskId.value = null
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

watch(() => [route.query.intern_id, route.query.task_id], ([internId, taskId]) => {
  if (internId || taskId) {
    loadInterns()
  } else {
    targetTask.value = null
    targetError.value = ''
    loadTasks(1)
  }
})
onMounted(loadInterns)
</script>

<template>
  <main class="mx-auto max-w-5xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
    <PageHeader title="Tasks" description="Assign tasks to your interns and review their proof submissions." />

    <ActionAlert :open="actionAlert.open" :variant="actionAlert.variant" :title="actionAlert.title" :message="actionAlert.message" @close="actionAlert.open = false" />
    <ProofPreviewModal :open="proofPreview.open" :title="proofPreview.title" :file="proofPreview.file" @close="proofPreview = { open: false, title: '', file: null }" />

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
      <div v-if="targetError" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert" data-testid="company-task-target-error">
        <p>{{ targetError }}</p>
        <a class="mt-2 inline-block font-semibold underline" href="/company/tasks">Back to tasks</a>
      </div>

      <section v-if="selectedIntern" class="space-y-4" aria-labelledby="company-tasks-list-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 id="company-tasks-list-title" class="text-xl font-semibold tracking-tight text-slate-950">{{ selectedIntern.name }}’s tasks</h2>
            <p class="mt-1 text-sm text-slate-600">Task completion and proof review are shown separately.</p>
          </div>
          <button type="button" class="app-button app-button--primary" data-testid="assign-task-open" @click="openAssignmentModal">Assign task</button>
        </div>
        <p v-if="loadingTasks" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600" role="status">Loading tasks...</p>
        <div v-else-if="tasksError" class="rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
          <p class="text-sm text-red-800">{{ tasksError }}</p>
          <button type="button" class="app-button app-button--secondary mt-3" data-testid="retry-company-tasks" @click="loadTasks(currentPage)">Try again</button>
        </div>
        <p v-else-if="!visibleTasks.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">No tasks assigned yet.</p>
        <div v-else class="space-y-4">
          <article v-for="task in visibleTasks" :id="`company-task-${task.id}`" :key="task.id" :data-testid="`company-task-${task.id}`" :data-notification-target="targetTask?.id === task.id ? 'true' : null" :class="['space-y-4 rounded-xl border bg-white p-5 shadow-sm sm:p-6', targetTask?.id === task.id ? 'border-amber-400 ring-2 ring-amber-100' : 'border-slate-200']">
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
            <div v-if="task.proof_available" class="flex flex-wrap gap-2">
              <button type="button" class="app-button app-button--secondary" :disabled="previewingTaskId === task.id" :data-testid="`view-task-proof-${task.id}`" @click="viewProof(task)">{{ previewingTaskId === task.id ? 'Loading proof...' : 'View proof' }}</button>
              <button type="button" class="app-button app-button--secondary" :disabled="downloadingTaskId === task.id" :data-testid="`download-task-proof-${task.id}`" @click="downloadProof(task)">{{ downloadingTaskId === task.id ? 'Downloading...' : 'Download proof' }}</button>
            </div>

            <div v-if="task.submission_status === 'pending_review'" class="space-y-3 border-t border-slate-200 pt-4">
              <p v-if="!viewedProofIds.has(task.id)" :id="`task-approval-proof-required-${task.id}`" class="text-sm text-slate-600">View the proof before approving.</p>
              <div>
                <label :for="`task-review-comment-${task.id}`" class="block text-sm font-semibold text-slate-800">Review comment <span class="font-normal text-slate-500">(optional for approval; required for changes)</span></label>
                <textarea :id="`task-review-comment-${task.id}`" v-model="reviewComments[task.id]" rows="2" maxlength="5000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" :aria-invalid="Boolean(reviewErrors[task.id])" :aria-describedby="reviewErrors[task.id] ? `task-review-comment-error-${task.id}` : undefined" />
                <p v-if="reviewErrors[task.id]" :id="`task-review-comment-error-${task.id}`" :data-testid="`task-review-comment-error-${task.id}`" class="mt-1 text-sm text-red-700" role="alert">{{ reviewErrors[task.id] }}</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="app-button app-button--primary" :disabled="Boolean(busyAction) || !viewedProofIds.has(task.id)" :aria-describedby="!viewedProofIds.has(task.id) ? `task-approval-proof-required-${task.id}` : undefined" :data-testid="`approve-task-${task.id}`" @click="review(task, 'approve')">{{ busyAction === `${task.id}:approve` ? 'Approving...' : 'Approve' }}</button>
                <button type="button" class="app-button app-button--secondary" :disabled="Boolean(busyAction)" :data-testid="`request-changes-task-${task.id}`" @click="review(task, 'request-changes')">{{ busyAction === `${task.id}:request-changes` ? 'Saving...' : 'Request changes' }}</button>
              </div>
            </div>
          </article>
          <PaginationControls :meta="pagination" @change="loadTasks" />
        </div>
      </section>
    </template>

    <AppModal :open="assignmentModalOpen" title="Assign a task" description="Assignments cannot be edited or deleted after creation." :busy="creating" @close="closeAssignmentModal">
      <form id="company-task-form" data-testid="company-task-form" class="space-y-4" @submit.prevent="createTask">
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
      </form>
      <template #footer>
        <button type="button" class="app-button app-button--secondary" :disabled="creating" @click="closeAssignmentModal">Cancel</button>
        <button type="submit" form="company-task-form" class="app-button app-button--primary" :disabled="creating">{{ creating ? 'Assigning...' : 'Assign task' }}</button>
      </template>
    </AppModal>
  </main>
</template>
