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
  completeTask,
  createTask,
  deleteTask,
  getStudentInternship,
  listStudentTasks,
  startTask,
  updateTask,
} from '../../services/ojt'

const internship = ref(null)
const tasks = ref([])
const filter = ref('all')
const currentPage = ref(1)
const pagination = ref(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const fieldErrors = ref({})
const formError = ref('')
const formOpen = ref(false)
const editingId = ref(null)
const confirmTask = ref(null)
const confirmBusy = ref(false)
const actionAlert = ref({ open: false, variant: 'success', title: '', message: '' })

const filters = [
  ['all', 'All'],
  ['to_do', 'To Do'],
  ['in_progress', 'In Progress'],
  ['completed', 'Completed'],
]

const emptyForm = () => ({ title: '', description: '', due_date: '' })
const form = reactive(emptyForm())
const visibleTasks = computed(() => filter.value === 'all' ? tasks.value : tasks.value.filter((task) => task.status === filter.value))
const formTitle = computed(() => editingId.value ? 'Edit task' : 'Add task')
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
    const [internshipData, taskData] = await Promise.all([
      getStudentInternship(),
      listStudentTasks({ page: currentPage.value, filter: filter.value }),
    ])
    internship.value = internshipData
    tasks.value = Array.isArray(taskData) ? taskData : taskData?.items ?? []
    pagination.value = Array.isArray(taskData) ? null : taskData?.meta ?? null
    const lastPage = Number(pagination.value?.last_page) || 1
    if (currentPage.value > lastPage) return load(lastPage)
  } catch (requestError) {
    error.value = apiErrorMessage(requestError, 'Unable to load your tasks.')
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

const openEdit = (task) => {
  Object.assign(form, {
    title: task.title,
    description: task.description ?? '',
    due_date: task.due_date ?? '',
  })
  editingId.value = task.id
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
      await updateTask(editingId.value, payload)
    } else {
      await createTask(payload)
    }
    await load()
    formOpen.value = false
    resetForm()
    showAlert('success', 'Saved successfully', wasEditing
      ? 'Your task changes were saved.'
      : 'The task was added to your personal tracker.')
  } catch (requestError) {
    fieldErrors.value = requestError?.response?.data?.errors ?? {}
    formError.value = apiErrorMessage(requestError, 'Unable to save this task.')
  } finally {
    saving.value = false
  }
}

const run = async (task, action, message) => {
  if (saving.value) return

  saving.value = true
  try {
    await action(task.id)
    await load()
    showAlert('success', 'Updated successfully', message)
  } catch (requestError) {
    showAlert('error', 'Unable to update task', apiErrorMessage(requestError, 'Please try again.'))
  } finally {
    saving.value = false
  }
}

const openDelete = (task) => {
  confirmTask.value = task
}

const closeDelete = () => {
  if (!confirmBusy.value) confirmTask.value = null
}

const remove = async () => {
  if (!confirmTask.value || confirmBusy.value) return

  confirmBusy.value = true
  try {
    await deleteTask(confirmTask.value.id)
    await load()
    confirmTask.value = null
    showAlert('success', 'Deleted successfully', 'The task was removed from your tracker.')
  } catch (requestError) {
    confirmTask.value = null
    showAlert('error', 'Unable to delete task', apiErrorMessage(requestError, 'Please try again.'))
  } finally {
    confirmBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <PageHeader title="Tasks" description="Track personal OJT work from to-do through completion.">
      <template #action>
        <button type="button" class="app-button app-button--primary" data-testid="add-task" @click="openCreate">+ Add task</button>
      </template>
    </PageHeader>
    <ActionAlert :open="actionAlert.open" :variant="actionAlert.variant" :title="actionAlert.title" :message="actionAlert.message" @close="closeAlert" />

    <div v-if="loading" class="mt-8 rounded-xl border border-slate-200 bg-white p-6 text-slate-600" role="status">Loading tasks…</div>
    <div v-else-if="error" class="mt-8 rounded-xl border border-red-200 bg-red-50 p-6 text-red-800" role="alert">
      <p>{{ error }}</p>
      <button type="button" class="app-button app-button--primary mt-4" @click="load">Try again</button>
    </div>
    <template v-else>
      <div class="mt-8 flex flex-wrap gap-2" aria-label="Task filters">
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

      <EmptyState v-if="!visibleTasks.length" class="mt-6" :title="filter === 'all' ? 'No tasks yet' : 'No tasks match this filter'" :message="filter === 'all' ? 'Add a personal task to keep your OJT work organized.' : 'Try another filter or add a new task.'" />
      <div v-else class="mt-6 space-y-3">
        <article v-for="task in visibleTasks" :key="task.id" :data-testid="`task-${task.id}`" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 class="font-semibold text-slate-950">{{ task.title }}</h2>
              <p v-if="task.due_date" class="mt-1 text-sm text-slate-600">Due {{ formatDate(task.due_date) }}<span v-if="task.is_overdue" class="ml-2 font-semibold text-red-700">Overdue</span></p>
            </div>
            <StatusBadge :status="task.status" />
          </div>
          <p v-if="task.description" class="mt-3 text-sm leading-6 text-slate-700">{{ task.description }}</p>
          <div class="mt-4 flex flex-wrap gap-2">
            <button v-if="task.status === 'to_do'" :data-testid="`start-${task.id}`" type="button" class="app-button app-button--primary" :disabled="saving" @click="run(task, startTask, 'Task started.')">Start</button>
            <button v-if="task.status === 'in_progress'" :data-testid="`complete-${task.id}`" type="button" class="app-button app-button--primary" :disabled="saving" @click="run(task, completeTask, 'Task completed.')">Mark completed</button>
            <button v-if="task.status !== 'completed'" :data-testid="`edit-${task.id}`" type="button" class="app-button app-button--secondary" :disabled="saving" @click="openEdit(task)">Edit</button>
            <button v-if="task.status !== 'completed'" :data-testid="`delete-${task.id}`" type="button" class="app-button app-button--secondary text-red-700" :disabled="saving" @click="openDelete(task)">Delete</button>
          </div>
        </article>
      </div>
      <PaginationControls :meta="pagination" class="mt-6" @change="changePage" />
    </template>

    <AppModal :open="formOpen" :title="formTitle" description="Keep the task details short and actionable." :busy="saving" @close="closeForm">
      <form id="task-form" data-testid="task-form" class="space-y-4" @submit.prevent="save">
        <div>
          <label for="task-title" class="block text-sm font-semibold text-slate-800">Title</label>
          <input id="task-title" v-model="form.title" type="text" required maxlength="255" :aria-invalid="Boolean(fieldErrors.title)" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
          <p v-if="fieldErrors.title" class="mt-1 text-sm text-red-700" role="alert">{{ fieldErrors.title[0] }}</p>
        </div>
        <div>
          <label for="task-description" class="block text-sm font-semibold text-slate-800">Description <span class="font-normal text-slate-500">(optional)</span></label>
          <textarea id="task-description" v-model="form.description" rows="3" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
        </div>
        <div>
          <label for="task-due-date" class="block text-sm font-semibold text-slate-800">Due date <span class="font-normal text-slate-500">(optional)</span></label>
          <DatePickerField
            id="task-due-date"
            v-model="form.due_date"
            :min="dueDateMin"
            :max="dueDateMax"
            describedby="task-due-date-help"
            title="Choose task due date"
            description="Select a date within your OJT period."
            test-id-prefix="task-due-date"
          />
          <p id="task-due-date-help" class="mt-1 text-xs text-slate-500">Choose a date within your OJT period.</p>
        </div>
        <p v-if="formError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ formError }}</p>
      </form>
      <template #footer>
        <button type="button" class="app-button app-button--secondary" :disabled="saving" @click="closeForm">Cancel</button>
        <button type="submit" form="task-form" class="app-button app-button--primary" :disabled="saving">{{ saving ? 'Saving…' : editingId ? 'Save changes' : 'Add task' }}</button>
      </template>
    </AppModal>

    <ConfirmDialog
      :open="Boolean(confirmTask)"
      title="Delete this task?"
      message="This task will be permanently removed from your tracker."
      confirm-label="Delete task"
      busy-label="Deleting…"
      variant="danger"
      :busy="confirmBusy"
      @cancel="closeDelete"
      @confirm="remove"
    />
  </main>
</template>
