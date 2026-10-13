<script setup>
import { onMounted, reactive, ref } from 'vue'
import ActionAlert from '../../components/ActionAlert.vue'
import PageHeader from '../../components/PageHeader.vue'
import PaginationControls from '../../components/PaginationControls.vue'
import ProofPreviewModal from '../../components/ProofPreviewModal.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import { apiErrorMessage } from '../../services/api'
import { downloadInternRequirementProof, listInternRequirements, submitInternRequirementProof } from '../../services/ojt'
import { formatDate } from '../../utils/formatters'

const requirements = ref([])
const pagination = ref(null)
const currentPage = ref(1)
const loading = ref(true)
const error = ref('')
const actionAlert = ref({ open: false, variant: 'success', title: '', message: '' })
const busyRequirementId = ref(null)
const downloadingRequirementId = ref(null)
const previewingRequirementId = ref(null)
const proofPreview = ref({ open: false, title: '', file: null })
const proofFiles = reactive({})
const comments = reactive({})
const proofErrors = reactive({})
const showAlert = (variant, title, message) => { actionAlert.value = { open: true, variant, title, message } }

async function load(page = currentPage.value) {
  loading.value = true
  error.value = ''
  currentPage.value = page

  try {
    const result = await listInternRequirements({ page })
    requirements.value = result?.items ?? []
    pagination.value = result?.meta ?? null
  } catch (requestError) {
    error.value = apiErrorMessage(requestError, 'Unable to load your assigned requirements.')
  } finally {
    loading.value = false
  }
}

function selectProof(requirement, event) {
  const file = event.target.files?.[0] ?? null
  proofFiles[requirement.id] = null
  proofErrors[requirement.id] = ''
  if (!file) return

  if (!['image/jpeg', 'image/png', 'image/webp', 'application/pdf'].includes(file.type)) {
    proofErrors[requirement.id] = 'Choose an image or PDF file.'
    return
  }
  if (file.size > 10 * 1024 * 1024) {
    proofErrors[requirement.id] = 'The file must be 10 MB or smaller.'
    return
  }
  proofFiles[requirement.id] = file
}

async function submitProof(requirement) {
  proofErrors[requirement.id] = ''
  if (!proofFiles[requirement.id]) {
    proofErrors[requirement.id] = 'Choose an image or PDF file before submitting.'
    return
  }
  if (busyRequirementId.value !== null) return

  busyRequirementId.value = requirement.id
  const formData = new FormData()
  formData.append('proof', proofFiles[requirement.id])
  if (comments[requirement.id]?.trim()) formData.append('comment', comments[requirement.id].trim())

  try {
    const updated = await submitInternRequirementProof(requirement.id, formData)
    requirements.value = requirements.value.map((item) => item.id === requirement.id ? updated : item)
    proofFiles[requirement.id] = null
    comments[requirement.id] = ''
    showAlert('success', 'Proof submitted', 'Your proof was sent to the company account holder for review.')
  } catch (requestError) {
    proofErrors[requirement.id] = requestError?.response?.data?.errors?.proof?.[0]
      ?? requestError?.response?.data?.errors?.comment?.[0]
      ?? ''
    showAlert('error', 'Proof not submitted', apiErrorMessage(requestError, 'Unable to submit proof. Please try again.'))
  } finally {
    busyRequirementId.value = null
  }
}

async function downloadProof(requirement) {
  if (downloadingRequirementId.value !== null) return
  downloadingRequirementId.value = requirement.id
  try {
    const response = await downloadInternRequirementProof(requirement.id)
    const disposition = response.headers?.['content-disposition'] ?? ''
    const filename = /filename="?([^";]+)"?/i.exec(disposition)?.[1] ?? 'requirement-proof'
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (requestError) {
    showAlert('error', 'Proof not downloaded', apiErrorMessage(requestError, 'Unable to download this proof. Please try again.'))
  } finally {
    downloadingRequirementId.value = null
  }
}

async function viewProof(requirement) {
  if (previewingRequirementId.value !== null) return
  previewingRequirementId.value = requirement.id
  try {
    const response = await downloadInternRequirementProof(requirement.id)
    proofPreview.value = { open: true, title: requirement.title, file: response.data }
  } catch (requestError) {
    showAlert('error', 'Proof not opened', apiErrorMessage(requestError, 'Unable to open this proof. Please try again.'))
  } finally {
    previewingRequirementId.value = null
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
    <PageHeader title="Requirements" description="View requirements assigned by your company account holder and submit proof of completion." />

    <ActionAlert :open="actionAlert.open" :variant="actionAlert.variant" :title="actionAlert.title" :message="actionAlert.message" @close="actionAlert.open = false" />
    <ProofPreviewModal :open="proofPreview.open" :title="proofPreview.title" :file="proofPreview.file" @close="proofPreview = { open: false, title: '', file: null }" />
    <p v-if="loading" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600" role="status">Loading requirements...</p>
    <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
      <p class="text-sm text-red-800">{{ error }}</p>
      <button type="button" class="app-button app-button--secondary mt-3" data-testid="retry-intern-requirements" @click="load(currentPage)">Try again</button>
    </div>
    <p v-else-if="!requirements.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">No assigned requirements yet.</p>
    <section v-else class="space-y-4" aria-label="Assigned requirements">
      <article v-for="requirement in requirements" :key="requirement.id" :data-testid="`intern-requirement-${requirement.id}`" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-base font-semibold text-slate-950">{{ requirement.title }}</h2>
            <p v-if="requirement.description" class="mt-1 whitespace-pre-wrap text-sm text-slate-600">{{ requirement.description }}</p>
            <p v-if="requirement.due_date" class="mt-2 text-sm text-slate-600">Due {{ formatDate(requirement.due_date) }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <StatusBadge :status="requirement.status" />
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ requirement.is_required ? 'Required' : 'Optional' }}</span>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ submissionLabel(requirement.submission_status) }}</span>
          </div>
        </div>

        <p v-if="requirement.submission_comment" class="text-sm text-slate-700"><span class="font-semibold">Your latest comment:</span> {{ requirement.submission_comment }}</p>
        <p v-if="requirement.review_comment" class="text-sm text-slate-700"><span class="font-semibold">Company account holder's comment:</span> {{ requirement.review_comment }}</p>
        <p v-if="requirement.submission_status === 'pending_review'" class="text-sm text-slate-600">Your submission is waiting for review.</p>
        <p v-if="requirement.submission_status === 'approved'" class="text-sm text-emerald-800">The company account holder approved this requirement.</p>
        <div v-if="requirement.proof_available" class="flex flex-wrap gap-2">
          <button type="button" class="app-button app-button--secondary" :disabled="previewingRequirementId === requirement.id" :data-testid="`view-requirement-proof-${requirement.id}`" @click="viewProof(requirement)">{{ previewingRequirementId === requirement.id ? 'Loading proof...' : 'View proof' }}</button>
          <button type="button" class="app-button app-button--secondary" :disabled="downloadingRequirementId === requirement.id" :data-testid="`download-requirement-proof-${requirement.id}`" @click="downloadProof(requirement)">{{ downloadingRequirementId === requirement.id ? 'Downloading...' : 'Download your proof' }}</button>
        </div>

        <form v-if="['not_submitted', 'changes_requested'].includes(requirement.submission_status)" class="space-y-3 border-t border-slate-200 pt-4" :data-testid="`submit-requirement-proof-${requirement.id}`" @submit.prevent="submitProof(requirement)">
          <div>
            <label :for="`requirement-proof-${requirement.id}`" class="block text-sm font-semibold text-slate-800">Proof file <span class="font-normal text-slate-500">(one image or PDF, maximum 10 MB)</span></label>
            <input :id="`requirement-proof-${requirement.id}`" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="mt-2 block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-semibold file:text-slate-800" :aria-invalid="Boolean(proofErrors[requirement.id])" :aria-describedby="proofErrors[requirement.id] ? `requirement-proof-error-${requirement.id}` : undefined" @change="selectProof(requirement, $event)" />
            <p v-if="proofErrors[requirement.id]" :id="`requirement-proof-error-${requirement.id}`" :data-testid="`requirement-proof-error-${requirement.id}`" class="mt-1 text-sm text-red-700" role="alert">{{ proofErrors[requirement.id] }}</p>
          </div>
          <div>
            <label :for="`requirement-comment-${requirement.id}`" class="block text-sm font-semibold text-slate-800">Comment <span class="font-normal text-slate-500">(optional)</span></label>
            <textarea :id="`requirement-comment-${requirement.id}`" v-model="comments[requirement.id]" maxlength="5000" rows="2" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          </div>
          <button type="submit" class="app-button app-button--primary" :disabled="busyRequirementId === requirement.id">{{ busyRequirementId === requirement.id ? 'Submitting...' : requirement.submission_status === 'changes_requested' ? 'Resubmit proof' : 'Submit proof' }}</button>
        </form>
      </article>
      <PaginationControls :meta="pagination" @change="load" />
    </section>
  </main>
</template>
