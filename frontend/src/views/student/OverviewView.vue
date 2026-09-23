<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import EmptyState from '../../components/EmptyState.vue'
import PageHeader from '../../components/PageHeader.vue'
import ProgressBar from '../../components/ProgressBar.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import { getStudentOverview } from '../../services/ojt'
import { formatDate, formatDuration } from '../../utils/formatters'

const overview = ref(null)
const loading = ref(true)
const error = ref('')

const load = async () => {
  loading.value = true
  error.value = ''

  try {
    overview.value = await getStudentOverview()
  } catch (requestError) {
    overview.value = null
    if (requestError?.response?.status === 404) return
    error.value = "We couldn't load your OJT overview."
  } finally {
    loading.value = false
  }
}

const displayMinutes = (minutes) => minutes === null || minutes === undefined
  ? 'Not available'
  : formatDuration(minutes)

const formatWorkDays = (workDays) => {
  const days = [...new Set((workDays ?? []).map(Number))]
    .filter((day) => day >= 1 && day <= 7)
    .sort((left, right) => left - right)

  if (!days.length) return 'Schedule not configured'
  if (days.join(',') === '1,2,3,4,5') return 'Mon-Fri'

  return days.map((day) => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][day - 1]).join(' · ')
}

const paceDescription = (status) => {
  const descriptions = {
    not_started: 'Your OJT has not started yet. Your configured schedule will begin on the start date.',
    on_track: 'Your current pace is within your expected OJT schedule.',
    at_risk: 'Your current pace is above your expected OJT schedule.',
    complete: 'You have reached the required tracked OJT hours.',
    deadline_passed: 'Your target end date has passed while tracked hours are still remaining.',
    pace_unavailable: 'Your OJT work schedule is incomplete, so your required pace cannot be calculated yet.',
  }

  return descriptions[status] ?? 'Your pace is based on your saved work logs and configured schedule.'
}

const attentionLabel = (item) => ({
  deadline_passed: 'Deadline passed',
  at_risk: 'At risk',
  overdue: 'Overdue',
  due_today: 'Due today',
  due_soon: 'Due soon',
  no_due_date: 'Required',
}[item.reason] ?? 'Needs attention')

const attentionTone = (item) => ({
  deadline_passed: 'bg-red-50 text-red-700 ring-red-200',
  at_risk: 'bg-amber-50 text-amber-700 ring-amber-200',
  overdue: 'bg-red-50 text-red-700 ring-red-200',
  due_today: 'bg-amber-50 text-amber-700 ring-amber-200',
  due_soon: 'bg-amber-50 text-amber-700 ring-amber-200',
  no_due_date: 'bg-slate-100 text-slate-700 ring-slate-200',
}[item.reason] ?? 'bg-slate-100 text-slate-700 ring-slate-200')

const attentionDetail = (item) => {
  if (item.reason === 'at_risk') {
    return `You need ${displayMinutes(overview.value.pace.required_daily_minutes)} per scheduled OJT day. Your expected OJT day is ${displayMinutes(overview.value.pace.expected_daily_minutes)}.`
  }

  if (item.reason === 'deadline_passed') {
    return `Your target date was ${formatDate(item.due_date)} and tracked hours remain.`
  }

  if (item.due_date) return `Due ${formatDate(item.due_date)}.`
  return 'This required item has no due date yet.'
}

onMounted(load)
</script>

<template>
  <main data-testid="student-overview" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <PageHeader title="Overview" description="See your current OJT status, priorities, and completion readiness." />

    <div v-if="loading" data-testid="overview-loading" class="mt-8 space-y-3 motion-safe:animate-pulse" role="status" aria-label="Loading your OJT overview">
      <div class="h-4 w-32 rounded bg-slate-200" />
      <div class="h-24 rounded-xl border border-slate-200 bg-white" />
      <div class="h-40 rounded-xl border border-slate-200 bg-white" />
    </div>

    <div v-else-if="error" data-testid="overview-error" class="mt-8 rounded-xl border border-red-200 bg-red-50 p-6 text-red-800" role="alert">
      <p>{{ error }}</p>
      <button type="button" data-testid="overview-retry" class="app-button app-button--primary mt-4" @click="load">Try again</button>
    </div>

    <EmptyState
      v-else-if="!overview"
      data-testid="overview-empty"
      class="mt-8"
      title="No OJT setup found"
      message="Complete your OJT setup before using the progress assistant."
    />

    <template v-else>
      <div data-testid="overview-context" class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-600">
        <span>{{ formatDate(overview.internship.start_date) }} - {{ formatDate(overview.internship.end_date) }}</span>
        <span aria-hidden="true">·</span>
        <span>{{ formatWorkDays(overview.internship.work_days) }} · {{ displayMinutes(overview.internship.expected_daily_minutes) }} expected/day</span>
      </div>

      <section data-testid="overview-progress" class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="overview-progress-title">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">OJT Progress</p>
            <h2 id="overview-progress-title" class="mt-2 text-xl font-semibold text-slate-950">Rendered hours</h2>
          </div>
          <div data-testid="overview-progress-meta" class="flex flex-wrap items-baseline justify-end gap-x-2 gap-y-1 text-right">
            <p data-testid="overview-rendered-hours" class="text-2xl font-semibold tracking-tight text-slate-950">{{ displayMinutes(overview.progress.rendered_minutes) }} of {{ displayMinutes(overview.progress.required_minutes) }} completed</p>
            <p data-testid="overview-progress-percentage" class="text-sm font-semibold text-slate-600">{{ overview.progress.percentage }}%</p>
          </div>
        </div>
        <div class="mt-5">
          <ProgressBar
            :percentage="overview.progress.percentage"
            :completed-label="`${displayMinutes(overview.progress.rendered_minutes)} rendered`"
            :required-label="`${displayMinutes(overview.progress.required_minutes)} required`"
            :remaining-label="`${displayMinutes(overview.progress.remaining_minutes)} remaining`"
          />
        </div>
      </section>

      <section data-testid="overview-pace" class="mt-6 rounded-xl border-2 border-slate-300 bg-slate-50 p-5 shadow-sm sm:p-6" aria-labelledby="overview-pace-title">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Required Pace</p>
            <h2 id="overview-pace-title" class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Current Pace</h2>
          </div>
          <StatusBadge data-testid="overview-status" :status="overview.pace.status" />
        </div>
        <p v-if="overview.pace.status !== 'pace_unavailable'" class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">{{ paceDescription(overview.pace.status) }}</p>

        <div v-if="overview.pace.status === 'pace_unavailable'" data-testid="overview-pace-unavailable" class="mt-5 rounded-lg border border-slate-300 bg-white p-4">
          <p class="text-sm font-semibold text-slate-900">{{ paceDescription(overview.pace.status) }}</p>
          <p class="mt-2 text-sm text-slate-600">Review your OJT setup before relying on pace guidance.</p>
          <RouterLink to="/student/profile" class="mt-3 inline-flex font-semibold text-slate-950 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-950">Review OJT setup <span aria-hidden="true">→</span></RouterLink>
        </div>

        <div v-else class="mt-5 grid gap-3 sm:grid-cols-[1.4fr_1fr_1fr]">
          <div class="rounded-lg border border-slate-300 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Needed per scheduled OJT day</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ displayMinutes(overview.pace.required_daily_minutes) }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Expected OJT day</p>
            <p class="mt-2 text-xl font-semibold text-slate-950">{{ displayMinutes(overview.pace.expected_daily_minutes) }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Scheduled days remaining</p>
            <p class="mt-2 text-xl font-semibold text-slate-950">{{ overview.pace.remaining_scheduled_days }}</p>
          </div>
        </div>
      </section>

      <section data-testid="overview-attention" class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="overview-attention-title">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Next steps</p>
            <h2 id="overview-attention-title" class="mt-2 text-xl font-semibold text-slate-950">Needs Attention</h2>
          </div>
          <span v-if="overview.attention.additional_count" class="text-sm text-slate-500">+{{ overview.attention.additional_count }} more</span>
        </div>

        <div v-if="!overview.attention.items.length" data-testid="overview-attention-empty" class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
          <p class="font-semibold">Nothing urgent right now.</p>
          <p class="mt-1">Keep logging your OJT hours and completing your requirements.</p>
        </div>
        <ul v-else class="mt-5 space-y-3">
          <li v-for="item in overview.attention.items" :key="`${item.type}-${item.id ?? item.priority}`" data-testid="overview-attention-item" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1', attentionTone(item)]">{{ attentionLabel(item) }}</span>
            <RouterLink :to="item.href" class="mt-3 block font-semibold text-slate-950 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-950">{{ item.title }} <span aria-hidden="true">→</span></RouterLink>
            <p class="mt-1 text-sm text-slate-600">{{ attentionDetail(item) }}</p>
          </li>
        </ul>
      </section>

      <section data-testid="overview-completion" class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="overview-completion-title">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Completion Readiness</p>
            <h2 id="overview-completion-title" class="mt-2 text-xl font-semibold text-slate-950">{{ overview.completion.ready ? 'Ready' : 'Not ready' }}</h2>
          </div>
          <StatusBadge data-testid="overview-completion-status" :status="overview.completion.ready ? 'ready' : 'not_ready'" />
        </div>

        <template v-if="overview.completion.ready">
          <ul class="mt-5 space-y-2 text-sm text-slate-700">
            <li><span aria-hidden="true" class="mr-2 text-emerald-700">✓</span>Required OJT hours reached</li>
            <li><span aria-hidden="true" class="mr-2 text-emerald-700">✓</span>All required internship requirements completed</li>
          </ul>
          <p class="mt-4 text-sm leading-6 text-slate-600">Your tracked OJT requirements are complete.</p>
        </template>

        <template v-else>
          <h3 class="mt-5 text-sm font-semibold text-slate-900">Still needed:</h3>
          <ul class="mt-2 space-y-2 text-sm text-slate-700">
            <li v-if="overview.completion.remaining_minutes > 0">{{ displayMinutes(overview.completion.remaining_minutes) }} of OJT hours</li>
            <li v-for="requirement in overview.completion.incomplete_required_requirements" :key="requirement.id">
              <RouterLink to="/student/requirements" class="font-semibold text-slate-950 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-950">{{ requirement.title }}</RouterLink>
              <span v-if="requirement.due_date" class="text-slate-600"> · due {{ formatDate(requirement.due_date) }}</span>
            </li>
          </ul>
          <p class="mt-4 text-xs leading-5 text-slate-500">Tasks and optional requirements do not block completion readiness.</p>
        </template>
      </section>
    </template>
  </main>
</template>
