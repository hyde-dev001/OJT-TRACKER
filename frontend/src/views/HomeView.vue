<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { RouterLink } from 'vue-router'
import PublicFooter from '../components/PublicFooter.vue'
import vReveal from '../directives/reveal'

const previewRenderedHours = ref(0)
const previewPercentage = ref(0)
const previewRemainingHours = computed(() => 500 - previewRenderedHours.value)
let progressTimer = null
let progressFrame = null
let progressStarted = false

const prefersReducedMotion = () => (
  typeof window !== 'undefined'
  && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
)

const startProgressAnimation = () => {
  if (progressStarted) return
  progressStarted = true

  if (prefersReducedMotion()) {
    previewRenderedHours.value = 340
    previewPercentage.value = 68
    return
  }

  progressTimer = window.setTimeout(() => {
    const startedAt = performance.now()
    const animate = (timestamp) => {
      const progress = Math.min(1, (timestamp - startedAt) / 1100)
      const eased = 1 - ((1 - progress) ** 3)
      previewRenderedHours.value = Math.round(340 * eased)
      previewPercentage.value = Math.round(68 * eased)

      if (progress < 1) {
        progressFrame = window.requestAnimationFrame(animate)
      }
    }

    progressFrame = window.requestAnimationFrame(animate)
  }, 180)
}

onBeforeUnmount(() => {
  if (progressTimer) window.clearTimeout(progressTimer)
  if (progressFrame) window.cancelAnimationFrame(progressFrame)
})
</script>

<template>
  <main class="public-page bg-white text-slate-950">
    <section id="progress-preview" class="border-b border-slate-200">
      <div class="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[minmax(0,1fr)_minmax(360px,0.85fr)] lg:items-center lg:gap-16 lg:px-8">
        <div>
          <p class="motion-hero-item text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Student OJT workspace</p>
          <h1 class="motion-hero-item mt-6 max-w-2xl text-5xl font-semibold leading-[1.05] tracking-[-0.04em] text-slate-950 sm:text-6xl">
            Track your OJT progress with clarity.
          </h1>
          <p class="motion-hero-item mt-6 max-w-xl text-lg leading-8 text-slate-600">
            Record rendered hours, organize tasks, and keep internship requirements visible in one focused workspace.
          </p>
          <div class="public-hero-actions motion-hero-item mt-8 flex flex-wrap gap-3">
            <RouterLink to="/register" class="app-button app-button--primary inline-flex items-center justify-center">
              Create student account
            </RouterLink>
            <RouterLink to="/login" class="app-button app-button--secondary inline-flex items-center justify-center">
              Sign in
            </RouterLink>
          </div>
        </div>

        <div v-reveal="startProgressAnimation" data-testid="home-progress-preview" class="rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm sm:p-7">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Progress preview</p>
              <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">OJT progress</h2>
            </div>
            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600">Example</span>
          </div>

          <div class="mt-8 rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex items-end justify-between gap-4">
              <div>
                <p class="text-sm text-slate-500">Rendered hours</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-950"><span data-testid="preview-rendered-hours">{{ previewRenderedHours }}</span> <span class="text-base font-normal text-slate-500">/ 500 hours</span></p>
              </div>
              <p class="text-sm font-semibold text-slate-700"><span data-testid="preview-percentage">{{ previewPercentage }}</span>%</p>
            </div>
            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Example rendered hours progress" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="previewPercentage">
              <div class="preview-progress-bar h-full rounded-full bg-slate-950" :style="{ width: previewPercentage + '%' }"></div>
            </div>
            <p class="mt-3 text-sm text-slate-500"><span data-testid="preview-remaining-hours">{{ previewRemainingHours }}</span> hours remaining</p>
          </div>

          <div id="how-it-works" class="mt-4 space-y-3">
            <div v-reveal class="motion-info-card motion-stagger-item flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3">
              <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">01</span>
              <div>
                <p class="font-semibold text-slate-900">Work hours</p>
                <p class="text-sm text-slate-500">Log each day and see your total grow.</p>
              </div>
            </div>
            <div v-reveal class="motion-info-card motion-stagger-item flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3">
              <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">02</span>
              <div>
                <p class="font-semibold text-slate-900">Tasks</p>
                <p class="text-sm text-slate-500">Keep personal work organized and moving.</p>
              </div>
            </div>
            <div v-reveal class="motion-info-card motion-stagger-item flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3">
              <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">03</span>
              <div>
                <p class="font-semibold text-slate-900">Requirements</p>
                <p class="text-sm text-slate-500">Know what still needs your attention.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="features" class="px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
      <div class="mx-auto max-w-7xl">
        <div v-reveal class="max-w-2xl">
          <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Everything in one place</p>
          <h2 class="mt-4 text-3xl font-semibold leading-tight tracking-[-0.03em] text-slate-950 sm:text-4xl">
            A simple workspace for the work that matters.
          </h2>
          <p class="mt-4 text-lg leading-8 text-slate-600">
            Less hunting through notes and spreadsheets. More time knowing exactly where your internship stands.
          </p>
        </div>

        <div v-reveal data-testid="home-feature-grid" class="mt-10 grid gap-4 md:grid-cols-3">
          <article v-reveal class="motion-info-card motion-stagger-item rounded-xl border border-transparent bg-slate-100 p-6">
            <p class="text-sm font-semibold text-slate-500">01</p>
            <h3 class="mt-8 text-xl font-semibold tracking-tight text-slate-950">Work hours</h3>
            <p class="mt-3 leading-7 text-slate-600">Capture daily schedules and rendered time without losing the details.</p>
          </article>
          <article v-reveal class="motion-info-card motion-stagger-item rounded-xl border border-transparent bg-slate-100 p-6">
            <p class="text-sm font-semibold text-slate-500">02</p>
            <h3 class="mt-8 text-xl font-semibold tracking-tight text-slate-950">Personal tasks</h3>
            <p class="mt-3 leading-7 text-slate-600">Turn the next thing you need to do into a visible, manageable task.</p>
          </article>
          <article v-reveal class="motion-info-card motion-stagger-item rounded-xl border border-transparent bg-slate-100 p-6">
            <p class="text-sm font-semibold text-slate-500">03</p>
            <h3 class="mt-8 text-xl font-semibold tracking-tight text-slate-950">Requirements</h3>
            <p class="mt-3 leading-7 text-slate-600">Keep requirements and completion notes together until your checklist is clear.</p>
          </article>
        </div>
      </div>
    </section>

    <section v-reveal class="px-4 pb-16 sm:px-6 sm:pb-24 lg:px-8">
      <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-6 rounded-2xl bg-slate-100 p-8 sm:p-12 md:flex-row md:items-center">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Start with a clearer view</p>
          <h2 class="mt-3 max-w-xl text-3xl font-semibold leading-tight tracking-[-0.03em] text-slate-950">Make your OJT progress visible from day one.</h2>
        </div>
        <RouterLink to="/register" class="app-button app-button--primary inline-flex shrink-0 items-center justify-center">Create your account</RouterLink>
      </div>
    </section>

    <PublicFooter />
  </main>
</template>
