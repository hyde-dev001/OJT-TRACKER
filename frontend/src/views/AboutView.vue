<script setup>
import { RouterLink } from 'vue-router'
import PublicFooter from '../components/PublicFooter.vue'
import vReveal from '../directives/reveal'
</script>

<template>
  <main class="public-page bg-white text-slate-950">
    <section class="border-b border-slate-200">
      <div class="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[minmax(0,1fr)_minmax(300px,0.7fr)] lg:items-center lg:gap-20 lg:px-8">
        <div>
          <p class="motion-hero-item text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">About OJT Progress Tracker</p>
          <h1 class="motion-hero-item mt-6 max-w-3xl text-5xl font-semibold leading-[1.05] tracking-[-0.04em] text-slate-950 sm:text-6xl">
            Built for student interns who want a clearer finish line.
          </h1>
          <p class="motion-hero-item mt-6 max-w-2xl text-lg leading-8 text-slate-600">
            OJT Progress Tracker gives students one calm place to record rendered hours, organize personal tasks, and keep internship requirements within reach.
          </p>
          <RouterLink to="/register" class="motion-hero-item app-button app-button--primary mt-8 inline-flex items-center justify-center">Create your account</RouterLink>
        </div>

        <aside v-reveal class="rounded-2xl border border-slate-200 bg-slate-50 p-6 sm:p-8">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">A focused student workflow</p>
          <dl class="mt-8 space-y-6">
            <div class="flex items-start justify-between gap-6 border-b border-slate-200 pb-5">
              <dt class="text-slate-600">Rendered hours</dt>
              <dd class="font-semibold text-slate-950">Track</dd>
            </div>
            <div class="flex items-start justify-between gap-6 border-b border-slate-200 pb-5">
              <dt class="text-slate-600">Personal tasks</dt>
              <dd class="font-semibold text-slate-950">Organize</dd>
            </div>
            <div class="flex items-start justify-between gap-6">
              <dt class="text-slate-600">Requirements</dt>
              <dd class="font-semibold text-slate-950">Complete</dd>
            </div>
          </dl>
        </aside>
      </div>
    </section>

    <section class="bg-slate-50 px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
      <div class="mx-auto max-w-7xl">
        <div v-reveal class="max-w-2xl">
          <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Why it exists</p>
          <h2 class="mt-4 text-3xl font-semibold leading-tight tracking-[-0.03em] text-slate-950 sm:text-4xl">Progress is easier to finish when it is easy to see.</h2>
          <p class="mt-4 text-lg leading-8 text-slate-600">
            The tracker is designed around the small updates that add up to a completed internship.
          </p>
        </div>

        <div v-reveal data-testid="about-benefits" class="mt-10 grid gap-4 md:grid-cols-3">
          <article v-reveal class="motion-info-card motion-stagger-item rounded-xl border border-slate-200 bg-white p-6">
            <p class="text-sm font-semibold text-slate-500">01</p>
            <h3 class="mt-8 text-xl font-semibold tracking-tight text-slate-950">Know your hours</h3>
            <p class="mt-3 leading-7 text-slate-600">See rendered time, remaining hours, and your configured OJT goal together.</p>
          </article>
          <article v-reveal class="motion-info-card motion-stagger-item rounded-xl border border-slate-200 bg-white p-6">
            <p class="text-sm font-semibold text-slate-500">02</p>
            <h3 class="mt-8 text-xl font-semibold tracking-tight text-slate-950">Keep work moving</h3>
            <p class="mt-3 leading-7 text-slate-600">Use personal tasks and notes to make the next action easier to find.</p>
          </article>
          <article v-reveal class="motion-info-card motion-stagger-item rounded-xl border border-slate-200 bg-white p-6">
            <p class="text-sm font-semibold text-slate-500">03</p>
            <h3 class="mt-8 text-xl font-semibold tracking-tight text-slate-950">Finish with confidence</h3>
            <p class="mt-3 leading-7 text-slate-600">Keep requirements visible so fewer details are left until the last minute.</p>
          </article>
        </div>

        <p v-reveal class="mt-10 max-w-3xl border-l-2 border-slate-300 pl-4 text-sm leading-7 text-slate-600">
          OJT Progress Tracker is a personal tracking tool, not a replacement for official attendance records or school/company verification.
        </p>
      </div>
    </section>

    <section v-reveal class="px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
      <div class="mx-auto max-w-7xl rounded-2xl bg-slate-100 p-8 text-center sm:p-12">
        <h2 class="text-3xl font-semibold tracking-[-0.03em] text-slate-950 sm:text-4xl">Ready to make your progress visible?</h2>
        <p class="mx-auto mt-4 max-w-2xl text-lg leading-8 text-slate-600">Create your student account and start with the hours, tasks, and requirements already on your mind.</p>
        <RouterLink to="/register" class="app-button app-button--primary mt-8 inline-flex items-center justify-center">Create your account</RouterLink>
      </div>
    </section>

    <PublicFooter />
  </main>
</template>
