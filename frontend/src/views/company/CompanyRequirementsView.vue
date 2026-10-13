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
  approveCompanyRequirement,
  createCompanyInternRequirement,
  downloadCompanyRequirementProof,
  getCompanyInternRequirement,
  listCompanyInterns,
  listCompanyInternRequirements,
  requestCompanyRequirementChanges,
} from '../../services/ojt'
import { formatDate } from '../../utils/formatters'

const interns = ref([])
const route = useRoute()
const router = useRouter()
const selectedInternId = ref('')
const requirements = ref([])
const targetRequirement = ref(null)
const targetError = ref('')
const pagination = ref(null)
const currentPage = ref(1)
const loadingInterns = ref(true)
const loadingRequirements = ref(false)
const internsError = ref('')
const requirementsError = ref('')
const fieldErrors = ref({})
const actionAlert = ref({ open: false, variant: 'success', title: '', message: '' })
const creating = ref(false)
const assignmentModalOpen = ref(false)
const busyAction = ref('')
const downloadingRequirementId = ref(null)
const previewingRequirementId = ref(null)
const proofPreview = ref({ open: false, title: '', file: null })
const viewedProofIds = ref(new Set())
const reviewComments = reactive({})
const reviewErrors = reactive({})
const form = reactive({ title: '', description: '', due_date: '', is_required: true })

const selectedIntern = computed(() => interns.value.find((intern) => intern.id === Number(selectedInternId.value)) ?? null)
const visibleRequirements = computed(() => targetRequirement.value && !requirements.value.some((requirement) => requirement.id === targetRequirement.value.id)
  ? [targetRequirement.value, ...requirements.value]
  : requirements.value)
const showAlert = (variant, title, message) => { actionAlert.value = { open: true, variant, title, message } }
const openAssignmentModal = () => {
  fieldErrors.value = {}
  assignmentModalOpen.value = true
}
const closeAssignmentModal = () => {
  if (!creating.value) assignmentModalOpen.value = false
}

const loadRequirements = async (page = 1) => {
  if (!selectedInternId.value) return false

  viewedProofIds.value = new Set()
  loadingRequirements.value = true
  requirementsError.value = ''
  targetError.value = ''
  currentPage.value = page
  try {
    const result = await listCompanyInternRequirements(Number(selectedInternId.value), { page })
    requirements.value = result?.items ?? []
    pagination.value = result?.meta ?? null
    targetRequirement.value = null
    if (route.query.requirement_id) {
      if (Number(route.query.intern_id) !== Number(selectedInternId.value)) {
        targetError.value = 'This notification link does not match the selected intern.'
      } else {
        const requirementId = Number(route.query.requirement_id)
        try {
          targetRequirement.value = requirements.value.find((requirement) => requirement.id === requirementId)
            ?? await getCompanyInternRequirement(Number(selectedInternId.value), requirementId)
          const hash = `#company-requirement-${requirementId}`
          if (route.hash !== hash) await router.replace({ query: { ...route.query }, hash })
        } catch (error) {
          targetError.value = apiErrorMessage(error, 'Unable to open this notification. The requirement may no longer be available.')
        }
      }
    }
    return true
  } catch (error) {
    requirementsError.value = apiErrorMessage(error, 'Unable to load this intern’s requirements.')
    return false
  } finally {
    loadingRequirements.value = false
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
    requirements.value = []
    pagination.value = null
    targetRequirement.value = null
    targetError.value = route.query.intern_id && !requestedIntern ? 'This notification link is no longer available.' : ''
    if (selectedInternId.value) await loadRequirements(1)
  } catch (error) {
    internsError.value = apiErrorMessage(error, 'Unable to load intern accounts. Please try again.')
  } finally {
    loadingInterns.value = false
  }
}

const selectIntern = async (event) => {
  selectedInternId.value = event.target.value
  requirements.value = []
  targetRequirement.value = null
  targetError.value = ''
  pagination.value = null
  fieldErrors.value = {}
  viewedProofIds.value = new Set()
  if (route.query.intern_id || route.query.requirement_id) {
    const query = { ...route.query }
    delete query.intern_id
    delete query.requirement_id
    await router.replace({ query, hash: '' })
    return
  }
  await loadRequirements(1)
}

const firstError = (field) => fieldErrors.value[field]?.[0] ?? ''

const createRequirement = async () => {
  fieldErrors.value = {}
  if (!form.title.trim()) {
    fieldErrors.value = { title: ['Enter a requirement title.'] }
    return
  }
  if (!selectedInternId.value || creating.value) return

  creating.value = true
  try {
    await createCompanyInternRequirement(Number(selectedInternId.value), {
      title: form.title.trim(),
      description: form.description.trim() || null,
      due_date: form.due_date || null,
      is_required: Boolean(form.is_required),
    })
    assignmentModalOpen.value = false
    form.title = ''
    form.description = ''
    form.due_date = ''
    form.is_required = true
    if (await loadRequirements(1)) showAlert('success', 'Requirement assigned', 'The requirement was assigned to the selected intern.')
  } catch (error) {
    fieldErrors.value = error?.response?.data?.errors ?? {}
    showAlert('error', 'Requirement not assigned', apiErrorMessage(error, 'Unable to assign this requirement. Please try again.'))
  } finally {
    creating.value = false
  }
}

const review = async (requirement, decision) => {
  const comment = (reviewComments[requirement.id] ?? '').trim()
  reviewErrors[requirement.id] = ''

  if (decision === 'request-changes' && !comment) {
    reviewErrors[requirement.id] = 'Add a comment before requesting changes.'
    return
  }
  if (busyAction.value) return

  busyAction.value = `${requirement.id}:${decision}`
  try {
    if (decision === 'approve') {
      await approveCompanyRequirement(Number(selectedInternId.value), requirement.id, comment || undefined)
    } else {
      await requestCompanyRequirementChanges(Number(selectedInternId.value), requirement.id, { comment })
    }
    reviewComments[requirement.id] = ''
    if (await loadRequirements(currentPage.value)) {
      showAlert('success', decision === 'approve' ? 'Requirement approved' : 'Changes requested', decision === 'approve' ? 'This requirement is complete.' : 'The intern can replace the proof and submit it again.')
    }
  } catch (error) {
    reviewErrors[requirement.id] = error?.response?.data?.errors?.comment?.[0] ?? ''
    showAlert('error', 'Review not saved', apiErrorMessage(error, 'Unable to review this requirement. Please try again.'))
  } finally {
    busyAction.value = ''
  }
}

const downloadProof = async (requirement) => {
  if (downloadingRequirementId.value !== null) return

  downloadingRequirementId.value = requirement.id
  try {
    const response = await downloadCompanyRequirementProof(Number(selectedInternId.value), requirement.id)
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
  } catch (error) {
    showAlert('error', 'Proof not downloaded', apiErrorMessage(error, 'Unable to download this proof. Please try again.'))
  } finally {
    downloadingRequirementId.value = null
  }
}

const viewProof = async (requirement) => {
  if (previewingRequirementId.value !== null) return
  previewingRequirementId.value = requirement.id
  try {
    const response = await downloadCompanyRequirementProof(Number(selectedInternId.value), requirement.id)
    proofPreview.value = { open: true, title: requirement.title, file: response.data }
    viewedProofIds.value = new Set([...viewedProofIds.value, requirement.id])
  } catch (error) {
    showAlert('error', 'Proof not opened', apiErrorMessage(error, 'Unable to open this proof. Please try again.'))
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

const submissionTone = (status) => ({
  not_submitted: 'bg-slate-100 text-slate-700',
  pending_review: 'bg-amber-50 text-amber-900',
  changes_requested: 'bg-red-50 text-red-800',
  approved: 'bg-emerald-50 text-emerald-800',
}[status] ?? 'bg-slate-100 text-slate-700')

watch(() => [route.query.intern_id, route.query.requirement_id], ([internId, requirementId]) => {
  if (internId || requirementId) {
    loadInterns()
  } else {
    targetRequirement.value = null
    targetError.value = ''
    loadRequirements(1)
  }
})
onMounted(loadInterns)
</script>

<template>
  <main class="mx-auto max-w-5xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
    <PageHeader title="Requirements" description="Assign internship requirements and review proof submitted by your interns." />

    <ActionAlert :open="actionAlert.open" :variant="actionAlert.variant" :title="actionAlert.title" :message="actionAlert.message" @close="actionAlert.open = false" />
    <ProofPreviewModal :open="proofPreview.open" :title="proofPreview.title" :file="proofPreview.file" @close="proofPreview = { open: false, title: '', file: null }" />

    <p v-if="loadingInterns" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600" role="status">Loading intern accounts...</p>
    <div v-else-if="internsError" class="rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
      <p class="text-sm text-red-800">{{ internsError }}</p>
      <button type="button" class="app-button app-button--secondary mt-3" data-testid="retry-company-interns" @click="loadInterns">Try again</button>
    </div>
    <p v-else-if="!interns.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">No intern accounts yet. Create an intern account before assigning requirements.</p>
    <template v-else>
      <div class="max-w-xl">
        <label for="company-requirement-intern-select" class="block text-sm font-semibold text-slate-800">Select intern</label>
        <select id="company-requirement-intern-select" data-testid="company-requirement-intern-select" v-model="selectedInternId" class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" @change="selectIntern">
          <option v-for="intern in interns" :key="intern.id" :value="String(intern.id)">{{ intern.name }} — {{ intern.email }}</option>
        </select>
      </div>
      <div v-if="targetError" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert" data-testid="company-requirement-target-error">
        <p>{{ targetError }}</p>
        <a class="mt-2 inline-block font-semibold underline" href="/company/requirements">Back to requirements</a>
      </div>

      <section v-if="selectedIntern" class="space-y-4" aria-labelledby="company-requirements-list-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 id="company-requirements-list-title" class="text-xl font-semibold tracking-tight text-slate-950">{{ selectedIntern.name }}’s requirements</h2>
            <p class="mt-1 text-sm text-slate-600">Requirement completion and proof review are shown separately.</p>
          </div>
          <button type="button" class="app-button app-button--primary" data-testid="assign-requirement-open" @click="openAssignmentModal">Assign requirement</button>
        </div>
        <p v-if="loadingRequirements" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600" role="status">Loading requirements...</p>
        <div v-else-if="requirementsError" class="rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
          <p class="text-sm text-red-800">{{ requirementsError }}</p>
          <button type="button" class="app-button app-button--secondary mt-3" data-testid="retry-company-requirements" @click="loadRequirements(currentPage)">Try again</button>
        </div>
        <p v-else-if="!visibleRequirements.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">No requirements assigned yet.</p>
        <div v-else class="space-y-4">
          <article v-for="requirement in visibleRequirements" :id="`company-requirement-${requirement.id}`" :key="requirement.id" :data-testid="`company-requirement-${requirement.id}`" :data-notification-target="targetRequirement?.id === requirement.id ? 'true' : null" :class="['space-y-4 rounded-xl border bg-white p-5 shadow-sm sm:p-6', targetRequirement?.id === requirement.id ? 'border-amber-400 ring-2 ring-amber-100' : 'border-slate-200']">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="text-base font-semibold text-slate-950">{{ requirement.title }}</h3>
                <p v-if="requirement.description" class="mt-1 whitespace-pre-wrap text-sm text-slate-600">{{ requirement.description }}</p>
                <p v-if="requirement.due_date" class="mt-2 text-sm text-slate-600">Due {{ formatDate(requirement.due_date) }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ requirement.is_required ? 'Required' : 'Optional' }}</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <StatusBadge :status="requirement.status" />
                <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', submissionTone(requirement.submission_status)]">{{ submissionLabel(requirement.submission_status) }}</span>
              </div>
            </div>
            <p v-if="requirement.submission_comment" class="text-sm text-slate-700"><span class="font-semibold">Intern comment:</span> {{ requirement.submission_comment }}</p>
            <p v-if="requirement.review_comment" class="text-sm text-slate-700"><span class="font-semibold">Latest review comment:</span> {{ requirement.review_comment }}</p>
            <div v-if="requirement.proof_available" class="flex flex-wrap gap-2">
              <button type="button" class="app-button app-button--secondary" :disabled="previewingRequirementId === requirement.id" :data-testid="`view-requirement-proof-${requirement.id}`" @click="viewProof(requirement)">{{ previewingRequirementId === requirement.id ? 'Loading proof...' : 'View proof' }}</button>
              <button type="button" class="app-button app-button--secondary" :disabled="downloadingRequirementId === requirement.id" :data-testid="`download-requirement-proof-${requirement.id}`" @click="downloadProof(requirement)">{{ downloadingRequirementId === requirement.id ? 'Downloading...' : 'Download proof' }}</button>
            </div>

            <div v-if="requirement.submission_status === 'pending_review'" class="space-y-3 border-t border-slate-200 pt-4">
              <p v-if="!viewedProofIds.has(requirement.id)" :id="`requirement-approval-proof-required-${requirement.id}`" class="text-sm text-slate-600">View the proof before approving.</p>
              <div>
                <label :for="`requirement-review-comment-${requirement.id}`" class="block text-sm font-semibold text-slate-800">Review comment <span class="font-normal text-slate-500">(optional for approval; required for changes)</span></label>
                <textarea :id="`requirement-review-comment-${requirement.id}`" v-model="reviewComments[requirement.id]" rows="2" maxlength="5000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" :aria-invalid="Boolean(reviewErrors[requirement.id])" :aria-describedby="reviewErrors[requirement.id] ? `requirement-review-comment-error-${requirement.id}` : undefined" />
                <p v-if="reviewErrors[requirement.id]" :id="`requirement-review-comment-error-${requirement.id}`" :data-testid="`requirement-review-comment-error-${requirement.id}`" class="mt-1 text-sm text-red-700" role="alert">{{ reviewErrors[requirement.id] }}</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="app-button app-button--primary" :disabled="Boolean(busyAction) || !viewedProofIds.has(requirement.id)" :aria-describedby="!viewedProofIds.has(requirement.id) ? `requirement-approval-proof-required-${requirement.id}` : undefined" :data-testid="`approve-requirement-${requirement.id}`" @click="review(requirement, 'approve')">{{ busyAction === `${requirement.id}:approve` ? 'Approving...' : 'Approve' }}</button>
                <button type="button" class="app-button app-button--secondary" :disabled="Boolean(busyAction)" :data-testid="`request-changes-requirement-${requirement.id}`" @click="review(requirement, 'request-changes')">{{ busyAction === `${requirement.id}:request-changes` ? 'Saving...' : 'Request changes' }}</button>
              </div>
            </div>
          </article>
          <PaginationControls :meta="pagination" @change="loadRequirements" />
        </div>
      </section>
    </template>

    <AppModal :open="assignmentModalOpen" title="Assign a requirement" description="Assignments cannot be edited or deleted after creation." :busy="creating" @close="closeAssignmentModal">
      <form id="company-requirement-form" data-testid="company-requirement-form" class="space-y-4" @submit.prevent="createRequirement">
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label for="company-requirement-title" class="block text-sm font-semibold text-slate-800">Requirement title</label>
            <input id="company-requirement-title" v-model="form.title" maxlength="255" required class="mt-1 block min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" :aria-invalid="Boolean(firstError('title'))" :aria-describedby="firstError('title') ? 'company-requirement-title-error' : undefined" />
            <p v-if="firstError('title')" id="company-requirement-title-error" data-testid="company-requirement-title-error" class="mt-1 text-sm text-red-700" role="alert">{{ firstError('title') }}</p>
          </div>
          <div class="sm:col-span-2">
            <label for="company-requirement-description" class="block text-sm font-semibold text-slate-800">Description <span class="font-normal text-slate-500">(optional)</span></label>
            <textarea id="company-requirement-description" v-model="form.description" rows="3" maxlength="5000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          </div>
          <div>
            <label for="company-requirement-due-date" class="block text-sm font-semibold text-slate-800">Due date <span class="font-normal text-slate-500">(optional)</span></label>
            <DatePickerField id="company-requirement-due-date" v-model="form.due_date" :min="selectedIntern.start_date" :max="selectedIntern.end_date" :invalid="Boolean(firstError('due_date'))" :describedby="firstError('due_date') ? 'company-requirement-due-date-error' : undefined" />
            <p v-if="firstError('due_date')" id="company-requirement-due-date-error" class="mt-1 text-sm text-red-700" role="alert">{{ firstError('due_date') }}</p>
          </div>
          <label class="flex min-h-11 items-center gap-3 text-sm font-medium text-slate-800">
            <input v-model="form.is_required" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-slate-950 focus:ring-slate-900" />
            Required for internship completion
          </label>
        </div>
      </form>
      <template #footer>
        <button type="button" class="app-button app-button--secondary" :disabled="creating" @click="closeAssignmentModal">Cancel</button>
        <button type="submit" form="company-requirement-form" class="app-button app-button--primary" :disabled="creating">{{ creating ? 'Assigning...' : 'Assign requirement' }}</button>
      </template>
    </AppModal>
  </main>
</template>
