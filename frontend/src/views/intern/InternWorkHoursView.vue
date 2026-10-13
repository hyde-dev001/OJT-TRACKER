<script setup>
import { onMounted, ref } from 'vue'
import AppModal from '../../components/AppModal.vue'
import EmptyState from '../../components/EmptyState.vue'
import PageHeader from '../../components/PageHeader.vue'
import PaginationControls from '../../components/PaginationControls.vue'
import ProgressBar from '../../components/ProgressBar.vue'
import { apiErrorMessage } from '../../services/api'
import { getInternInternship, listInternWorkLogs } from '../../services/ojt'
import { formatDate, formatDuration, formatTime } from '../../utils/formatters'

const internship = ref(null)
const logs = ref([])
const pagination = ref(null)
const currentPage = ref(1)
const loading = ref(true)
const error = ref('')
const viewingLog = ref(null)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const [internshipData, logPage] = await Promise.all([
      getInternInternship(),
      listInternWorkLogs({ page: currentPage.value }),
    ])
    internship.value = internshipData
    logs.value = logPage?.items ?? []
    pagination.value = logPage?.meta ?? null
  } catch (requestError) {
    error.value = apiErrorMessage(requestError, 'Unable to load your work-hour records.')
  } finally {
    loading.value = false
  }
}

async function changePage(page) {
  currentPage.value = page
  await load()
}

onMounted(load)
</script>

<template>
  <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <PageHeader title="Work Hours" description="Review your company-recorded OJT hours and work sessions." />

    <div v-if="loading" class="mt-8 rounded-xl border border-slate-200 bg-white p-6 text-slate-600" role="status">Loading work hours...</div>
    <div v-else-if="error" class="mt-8 rounded-xl border border-red-200 bg-red-50 p-6 text-red-800" role="alert">
      <p>{{ error }}</p>
      <button type="button" class="app-button app-button--primary mt-4" data-testid="retry-work-hours" @click="load">Try again</button>
    </div>
    <template v-else>
      <section class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="intern-progress-title">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">OJT progress</p>
          <h2 id="intern-progress-title" class="mt-2 text-xl font-semibold text-slate-950">Rendered hours</h2>
        </div>
        <div class="mt-5">
          <ProgressBar
            v-if="internship.progress?.percentage != null && internship.progress?.required_hours != null"
            :percentage="internship.progress.percentage"
            :completed-label="`${internship.progress.completed_hours} hours completed`"
            :required-label="`${internship.progress.required_hours} hours required`"
            :remaining-label="internship.progress.remaining_hours == null ? '' : `${internship.progress.remaining_hours} hours remaining`"
          />
          <div v-else>
            <p class="font-semibold text-slate-950">{{ internship.progress?.completed_hours ?? 0 }} hours completed</p>
            <p class="mt-2 text-sm text-slate-600">Progress percentage is unavailable until required hours are set</p>
          </div>
        </div>
      </section>

      <section class="mt-8" aria-labelledby="intern-work-sessions-title">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h2 id="intern-work-sessions-title" class="text-xl font-semibold text-slate-950">Work sessions</h2>
            <p class="mt-1 text-sm text-slate-600">Your company account holder records and correct these sessions.</p>
          </div>
          <span class="text-sm text-slate-500">{{ pagination?.total ?? logs.length }} record{{ (pagination?.total ?? logs.length) === 1 ? '' : 's' }}</span>
        </div>

        <EmptyState v-if="!logs.length" class="mt-5" title="No work sessions yet" message="Work sessions recorded by your company will appear here." />
        <div v-else class="mt-5 space-y-3">
          <article v-for="log in logs" :key="log.id" :data-testid="`intern-work-log-${log.id}`" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <h3 class="font-semibold text-slate-950">{{ formatDate(log.work_date) }}</h3>
              <button type="button" class="app-button app-button--secondary" :data-testid="`view-work-log-${log.id}`" :aria-label="`View work session details for ${formatDate(log.work_date)}`" @click="viewingLog = log">View</button>
            </div>
            <p class="mt-2 text-sm text-slate-600">{{ formatTime(log.time_in) }} - {{ log.time_out ? formatTime(log.time_out) : 'In progress' }}<template v-if="log.time_out && log.rendered_minutes != null"> · {{ formatDuration(log.rendered_minutes) }}</template></p>
          </article>
        </div>
        <PaginationControls :meta="pagination" class="mt-6" @change="changePage" />
      </section>

      <AppModal v-if="viewingLog" :open="Boolean(viewingLog)" title="Work session details" description="Review the time and break recorded for this session." @close="viewingLog = null">
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
          <div><dt class="font-semibold text-slate-500">Date</dt><dd class="mt-1 text-slate-900">{{ formatDate(viewingLog.work_date) }}</dd></div>
          <div><dt class="font-semibold text-slate-500">Time in</dt><dd class="mt-1 text-slate-900">{{ formatTime(viewingLog.time_in) }}</dd></div>
          <div><dt class="font-semibold text-slate-500">Time out</dt><dd class="mt-1 text-slate-900">{{ viewingLog.time_out ? formatTime(viewingLog.time_out) : 'In progress' }}</dd></div>
          <div><dt class="font-semibold text-slate-500">Break</dt><dd class="mt-1 text-slate-900">{{ viewingLog.break_minutes == null ? '—' : `${viewingLog.break_minutes} minutes` }}</dd></div>
          <div><dt class="font-semibold text-slate-500">Rendered hours</dt><dd class="mt-1 text-slate-900">{{ viewingLog.rendered_minutes == null ? 'Not available while in progress' : formatDuration(viewingLog.rendered_minutes) }}</dd></div>
        </dl>
        <template #footer>
          <button type="button" class="app-button app-button--secondary" data-testid="close-work-session-details" @click="viewingLog = null">Close</button>
        </template>
      </AppModal>
    </template>
  </main>
</template>
