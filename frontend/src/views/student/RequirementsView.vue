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
import StatusBadge from '../../components/StatusBadge.vue'
import { internshipDateBounds } from '../../utils/dateBounds'
import { formatDate } from '../../utils/formatters'
import {
  completeRequirement,
  createRequirement,
  deleteRequirement,
  getStudentInternship,
  incompleteRequirement,
  listStudentRequirements,
  updateRequirement,
} from '../../services/ojt'

const internship = ref(null)
const requirements = ref([])
const filter = ref('all')
const currentPage = ref(1)
const pagination = ref(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const formError = ref('')
const fieldErrors = ref({})
const formOpen = ref(false)
const editingId = ref(null)
const confirmRequirement = ref(null)
const confirmBusy = ref(false)
const actionAlert = ref({ open: false, variant: 'success', title: '', message: '' })

const filters = [
  ['all', 'All'],
  ['incomplete', 'Incomplete'],
  ['completed', 'Completed'],
  ['overdue', 'Overdue'],
]

const emptyForm = () => ({
  title: '',
  description: '',
  due_date: '',
  is_required: true,
  student_notes: '',
})

const form = reactive(emptyForm())
const formTitle = computed(() => editingId.value ? 'Edit requirement' : 'Add requirement')
const dueDateMin = computed(() => internshipDateBounds(internship.value, false).min)
const dueDateMax = computed(() => internshipDateBounds(internship.value, false).max)

const showAlert = (variant, title, message) => {
  actionAlert.value = { open: true, variant, title, message }
}

const closeAlert = () => {
  actionAlert.value.open = false
}

const load = async (page = currentPage.value) => {
  currentPage.value = Math.max(1, Number(page) || 1)
  loading.value = true
  error.value = ''
  try {
    const [internshipData, requirementData] = await Promise.all([
      getStudentInternship(),
      listStudentRequirements({ page: currentPage.value, filter: filter.value }),
    ])
    internship.value = internshipData
    requirements.value = Array.isArray(requirementData) ? requirementData : requirementData?.items ?? []
    pagination.value = Array.isArray(requirementData) ? null : requirementData?.meta ?? null
    const lastPage = Number(pagination.value?.last_page) || 1
    if (currentPage.value > lastPage) return load(lastPage)
  } catch (requestError) {
    error.value = apiErrorMessage(requestError, 'Unable to load your requirements.')
  } finally {
    loading.value = false
  }
}

const changeFilter = (value) => {
  if (filter.value === value) return
  filter.value = value
  load(1)
}

const changePage = (page) => load(page)

const resetForm = () => {
  Object.assign(form, emptyForm())
  editingId.value = null
  fieldErrors.value = {}
  formError.value = ''
}

const openCreate = () => {
  resetForm()
  formOpen.value = true
}

const openEdit = (requirement) => {
  Object.assign(form, {
    title: requirement.title,
    description: requirement.description ?? '',
    due_date: requirement.due_date ?? '',
    is_required: Boolean(requirement.is_required),
    student_notes: requirement.student_notes ?? '',
  })
  editingId.value = requirement.id
  fieldErrors.value = {}
  formError.value = ''
  formOpen.value = true
}

const closeForm = () => {
  if (saving.value) return
  formOpen.value = false
  resetForm()
}

const save = async () => {
  if (saving.value || !internship.value) return

  saving.value = true
  formError.value = ''
  fieldErrors.value = {}
  const payload = { ...form, internship_id: internship.value.id, due_date: form.due_date || null }
  const wasEditing = Boolean(editingId.value)
  try {
    if (wasEditing) {
      await updateRequirement(editingId.value, payload)
    } else {
      await createRequirement(payload)
    }
    await load()
    formOpen.value = false
    resetForm()
    showAlert('success', 'Saved successfully', wasEditing
      ? 'Your requirement changes were saved.'
      : 'The requirement was added to your personal checklist.')
  } catch (requestError) {
    fieldErrors.value = requestError?.response?.data?.errors ?? {}
    formError.value = apiErrorMessage(requestError, 'Unable to save this requirement.')
  } finally {
    saving.value = false
  }
}

const toggle = async (requirement, action, message) => {
  if (saving.value) return

  saving.value = true
  try {
    await action(requirement.id)
    await load()
    showAlert('success', 'Updated successfully', message)
  } catch (requestError) {
    showAlert('error', 'Unable to update requirement', apiErrorMessage(requestError, 'Please try again.'))
  } finally {
    saving.value = false
  }
}

const openDelete = (requirement) => {
  confirmRequirement.value = requirement
}

const closeDelete = () => {
  if (!confirmBusy.value) confirmRequirement.value = null
}

const remove = async () => {
  if (!confirmRequirement.value || confirmBusy.value) return

  confirmBusy.value = true
  try {
    await deleteRequirement(confirmRequirement.value.id)
    await load()
    confirmRequirement.value = null
    showAlert('success', 'Deleted successfully', 'The requirement was removed from your tracker.')
  } catch (requestError) {
    confirmRequirement.value = null
    showAlert('error', 'Unable to delete requirement', apiErrorMessage(requestError, 'Please try again.'))
  } finally {
    confirmBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <PageHeader title="Requirements" description="Keep your internship requirements and completion notes in one place.">
      <template #action>
        <button type="button" class="app-button app-button--primary" data-testid="add-requirement" @click="openCreate">+ Add requirement</button>
      </template>
    </PageHeader>
    <ActionAlert :open="actionAlert.open" :variant="actionAlert.variant" :title="actionAlert.title" :message="actionAlert.message" @close="closeAlert" />

    <div v-if="loading" class="mt-8 rounded-xl border border-slate-200 bg-white p-6 text-slate-600" role="status">Loading requirements…</div>
    <div v-else-if="error" class="mt-8 rounded-xl border border-red-200 bg-red-50 p-6 text-red-800" role="alert">
      <p>{{ error }}</p>
      <button type="button" class="app-button app-button--primary mt-4" @click="load">Try again</button>
    </div>
    <template v-else>
      <div class="mt-8 flex flex-wrap gap-2" aria-label="Requirement filters">
        <button
          v-for="entry in filters"
          :key="entry[0]"
          :data-testid="`filter-${entry[0]}`"
          type="button"
          :class="['rounded-full px-3 py-2 text-sm font-semibold', filter === entry[0] ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50']"
          @click="changeFilter(entry[0])"
        >
          {{ entry[1] }}
        </button>
      </div>

      <EmptyState v-if="!requirements.length" class="mt-6" :data-testid="filter === 'all' ? undefined : 'requirements-filter-empty'" :title="filter === 'all' ? 'No requirements yet' : 'No requirements match this filter'" :message="filter === 'all' ? 'Add a personal requirement to keep your OJT checklist visible.' : 'Try another filter or add a new requirement.'" />
      <div v-else class="mt-6 space-y-3">
        <article v-for="requirement in requirements" :key="requirement.id" :data-testid="`requirement-${requirement.id}`" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="font-semibold text-slate-950">{{ requirement.title }}</h2>
              <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ requirement.is_required ? 'Required' : 'Optional' }}</span>
            </div>
            <p v-if="requirement.due_date" class="mt-1 text-sm text-slate-600">Due {{ formatDate(requirement.due_date) }}<span v-if="requirement.is_overdue" class="ml-2 font-semibold text-red-700">Overdue</span></p>
          </div>
          <StatusBadge :status="requirement.status" />
        </div>
        <p v-if="requirement.description" class="mt-3 text-sm leading-6 text-slate-700">{{ requirement.description }}</p>
        <p v-if="requirement.student_notes" class="mt-3 text-sm leading-6 text-slate-600"><span class="font-semibold text-slate-800">Notes:</span> {{ requirement.student_notes }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
          <button v-if="requirement.status === 'incomplete'" :data-testid="`complete-${requirement.id}`" type="button" class="app-button app-button--primary" :disabled="saving" @click="toggle(requirement, completeRequirement, 'Requirement completed.')">Mark completed</button>
         <button v-else :data-testid="`incomplete-${requirement.id}`" type="button" class="app-button app-button--secondary" :disabled="saving" @click="toggle(requirement, incompleteRequirement, 'Requirement marked incomplete.')">Mark incomplete</button>
          <button v-if="requirement.status === 'incomplete'" :data-testid="`edit-${requirement.id}`" type="button" class="app-button app-button--secondary" :disabled="saving" @click="openEdit(requirement)">Edit</button>
          <button v-if="requirement.status === 'incomplete'" :data-testid="`delete-${requirement.id}`" type="button" class="app-button app-button--secondary text-red-700" :disabled="saving" @click="openDelete(requirement)">Delete</button>
        </div>
        </article>
      </div>
      <PaginationControls :meta="pagination" class="mt-6" @change="changePage" />
    </template>

    <AppModal :open="formOpen" :title="formTitle" description="Track the requirement and any personal notes you want to remember." :busy="saving" @close="closeForm">
      <form id="requirement-form" data-testid="requirement-form" class="space-y-4" @submit.prevent="save">
        <div>
          <label for="requirement-title" class="block text-sm font-semibold text-slate-800">Title</label>
          <input id="requirement-title" v-model="form.title" type="text" required maxlength="255" :aria-invalid="Boolean(fieldErrors.title)" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="fieldErrors.title" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.title[0] }}</p>
        </div>
        <div>
          <label for="requirement-description" class="block text-sm font-semibold text-slate-800">Description <span class="font-normal text-slate-500">(optional)</span></label>
          <textarea id="requirement-description" v-model="form.description" rows="3" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="requirement-due-date" class="block text-sm font-semibold text-slate-800">Due date <span class="font-normal text-slate-500">(optional)</span></label>
            <DatePickerField
              id="requirement-due-date"
              v-model="form.due_date"
              :min="dueDateMin"
              :max="dueDateMax"
              describedby="requirement-due-date-help"
              title="Choose requirement due date"
              description="Select a date within your OJT period."
              test-id-prefix="requirement-due-date"
            />
            <p id="requirement-due-date-help" class="mt-1 text-xs text-slate-500">Choose a date within your OJT period.</p>
          </div>
          <label class="flex items-center gap-2 self-end pb-2 text-sm font-semibold text-slate-800"><input v-model="form.is_required" type="checkbox" class="h-4 w-4 rounded border-slate-300" /> Required</label>
        </div>
        <div>
          <label for="requirement-notes" class="block text-sm font-semibold text-slate-800">Personal notes <span class="font-normal text-slate-500">(optional)</span></label>
          <textarea id="requirement-notes" v-model="form.student_notes" rows="3" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
        </div>
        <p v-if="formError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ formError }}</p>
      </form>
      <template #footer>
        <button type="button" class="app-button app-button--secondary" :disabled="saving" @click="closeForm">Cancel</button>
        <button type="submit" form="requirement-form" class="app-button app-button--primary" :disabled="saving">{{ saving ? 'Saving…' : editingId ? 'Save changes' : 'Add requirement' }}</button>
      </template>
    </AppModal>

    <ConfirmDialog
      :open="Boolean(confirmRequirement)"
      title="Delete this requirement?"
      message="This requirement will be permanently removed from your tracker."
      confirm-label="Delete requirement"
      busy-label="Deleting…"
      variant="danger"
      :busy="confirmBusy"
      @cancel="closeDelete"
      @confirm="remove"
    />
  </main>
</template>
