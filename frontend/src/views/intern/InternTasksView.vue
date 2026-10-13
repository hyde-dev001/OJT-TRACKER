<script setup>
import { onMounted, reactive, ref } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import PaginationControls from '../../components/PaginationControls.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import { apiErrorMessage } from '../../services/api'
import { downloadInternTaskProof, listInternTasks, submitInternTaskProof } from '../../services/ojt'
import { formatDate } from '../../utils/formatters'

const tasks = ref([])
const pagination = ref(null)
const currentPage = ref(1)
const loading = ref(true)
const error = ref('')
const actionError = ref('')
const successMessage = ref('')
const busyTaskId = ref(null)
const downloadingTaskId = ref(null)
const proofFiles = reactive({})
const comments = reactive({})
const proofErrors = reactive({})

async function load(page = currentPage.value) {
  loading.value = true
  error.value = ''
  currentPage.value = page

  try {
    const result = await listInternTasks({ page })
    tasks.value = result?.items ?? []
    pagination.value = result?.meta ?? null
  } catch (requestError) {
    error.value = apiErrorMessage(requestError, 'Unable to load your assigned tasks.')
  } finally {
    loading.value = false
  }
}

function selectProof(task, event) {
  const file = event.target.files?.[0] ?? null
  proofFiles[task.id] = null
  proofErrors[task.id] = ''
  if (!file) return

  if (!['image/jpeg', 'image/png', 'image/webp', 'application/pdf'].includes(file.type)) {
    proofErrors[task.id] = 'Choose an image or PDF file.'
    return
  }
  if (file.size > 10 * 1024 * 1024) {
    proofErrors[task.id] = 'The file must be 10 MB or smaller.'
    return
  }
  proofFiles[task.id] = file
}

async function submitProof(task) {
  proofErrors[task.id] = ''
  actionError.value = ''
  successMessage.value = ''
  if (!proofFiles[task.id]) {
    proofErrors[task.id] = 'Choose an image or PDF file before submitting.'
    return
  }
  if (busyTaskId.value !== null) return

  busyTaskId.value = task.id
  const formData = new FormData()
  formData.append('proof', proofFiles[task.id])
  if (comments[task.id]?.trim()) formData.append('comment', comments[task.id].trim())

  try {
    const updated = await submitInternTaskProof(task.id, formData)
    tasks.value = tasks.value.map((item) => item.id === task.id ? updated : item)
    proofFiles[task.id] = null
    comments[task.id] = ''
    successMessage.value = 'Proof submitted for review.'
  } catch (requestError) {
    proofErrors[task.id] = requestError?.response?.data?.errors?.proof?.[0]
      ?? requestError?.response?.data?.errors?.comment?.[0]
      ?? ''
    actionError.value = apiErrorMessage(requestError, 'Unable to submit proof. Please try again.')
  } finally {
    busyTaskId.value = null
  }
}

async function downloadProof(task) {
  if (downloadingTaskId.value !== null) return
  downloadingTaskId.value = task.id
  actionError.value = ''
  try {
    const response = await downloadInternTaskProof(task.id)
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
  } catch (requestError) {
    actionError.value = apiErrorMessage(requestError, 'Unable to download this proof. Please try again.')
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

onMounted(() => load(1))
</script>

<template>
  <main class="mx-auto max-w-5xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
    <PageHeader title="Tasks" description="View tasks assigned by your company account holder and submit proof of your work." />

    <p v-if="successMessage" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ successMessage }}</p>
    <p v-if="actionError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ actionError }}</p>
    <p v-if="loading" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600" role="status">Loading tasks...</p>
    <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
      <p class="text-sm text-red-800">{{ error }}</p>
      <button type="button" class="app-button app-button--secondary mt-3" data-testid="retry-intern-tasks" @click="load(currentPage)">Try again</button>
    </div>
    <p v-else-if="!tasks.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">No assigned tasks yet.</p>
    <section v-else class="space-y-4" aria-label="Assigned tasks">
      <article v-for="task in tasks" :key="task.id" :data-testid="`intern-task-${task.id}`" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-base font-semibold text-slate-950">{{ task.title }}</h2>
            <p v-if="task.description" class="mt-1 whitespace-pre-wrap text-sm text-slate-600">{{ task.description }}</p>
            <p v-if="task.due_date" class="mt-2 text-sm text-slate-600">Due {{ formatDate(task.due_date) }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <StatusBadge :status="task.status" />
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ submissionLabel(task.submission_status) }}</span>
          </div>
        </div>

        <p v-if="task.submission_comment" class="text-sm text-slate-700"><span class="font-semibold">Your latest comment:</span> {{ task.submission_comment }}</p>
        <p v-if="task.review_comment" class="text-sm text-slate-700"><span class="font-semibold">Company account holder's comment:</span> {{ task.review_comment }}</p>
        <p v-if="task.submission_status === 'pending_review'" class="text-sm text-slate-600">Your submission is waiting for review.</p>
        <p v-if="task.submission_status === 'approved'" class="text-sm text-emerald-800">The company account holder approved this task.</p>
        <button v-if="task.proof_available" type="button" class="app-button app-button--secondary" :disabled="downloadingTaskId === task.id" :data-testid="`download-task-proof-${task.id}`" @click="downloadProof(task)">{{ downloadingTaskId === task.id ? 'Downloading...' : 'Download your proof' }}</button>

        <form v-if="['not_submitted', 'changes_requested'].includes(task.submission_status)" class="space-y-3 border-t border-slate-200 pt-4" :data-testid="`submit-task-proof-${task.id}`" @submit.prevent="submitProof(task)">
          <div>
            <label :for="`task-proof-${task.id}`" class="block text-sm font-semibold text-slate-800">Proof file <span class="font-normal text-slate-500">(one image or PDF, maximum 10 MB)</span></label>
            <input :id="`task-proof-${task.id}`" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="mt-2 block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-semibold file:text-slate-800" :aria-invalid="Boolean(proofErrors[task.id])" :aria-describedby="proofErrors[task.id] ? `task-proof-error-${task.id}` : undefined" @change="selectProof(task, $event)" />
            <p v-if="proofErrors[task.id]" :id="`task-proof-error-${task.id}`" :data-testid="`task-proof-error-${task.id}`" class="mt-1 text-sm text-red-700" role="alert">{{ proofErrors[task.id] }}</p>
          </div>
          <div>
            <label :for="`task-comment-${task.id}`" class="block text-sm font-semibold text-slate-800">Comment <span class="font-normal text-slate-500">(optional)</span></label>
            <textarea :id="`task-comment-${task.id}`" v-model="comments[task.id]" maxlength="5000" rows="2" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          </div>
          <button type="submit" class="app-button app-button--primary" :disabled="busyTaskId === task.id">{{ busyTaskId === task.id ? 'Submitting...' : task.submission_status === 'changes_requested' ? 'Resubmit proof' : 'Submit proof' }}</button>
        </form>
      </article>
      <PaginationControls :meta="pagination" @change="load" />
    </section>
  </main>
</template>
