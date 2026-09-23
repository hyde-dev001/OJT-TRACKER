<script setup>
import { RouterLink } from 'vue-router'

const currentYear = new Date().getFullYear()
</script>

<template>
  <footer class="bg-slate-950 text-slate-300">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
      <div class="grid gap-10 md:grid-cols-[minmax(0,1fr)_minmax(220px,0.7fr)] md:gap-16">
        <div>
          <RouterLink to="/" class="text-lg font-semibold tracking-tight text-white">OJT Progress Tracker</RouterLink>
          <p class="mt-4 max-w-sm text-sm leading-6 text-slate-400">A focused workspace for students tracking their internship progress.</p>
        </div>
        <div class="grid grid-cols-2 gap-8 text-sm">
          <div>
            <p class="font-semibold text-white">Workspace</p>
            <div class="mt-4 space-y-3">
              <RouterLink to="/register" class="block text-slate-400 transition hover:text-white">Create account</RouterLink>
              <RouterLink to="/login" class="block text-slate-400 transition hover:text-white">Sign in</RouterLink>
            </div>
          </div>
          <div>
            <p class="font-semibold text-white">Explore</p>
            <div class="mt-4 space-y-3">
              <RouterLink to="/" class="block text-slate-400 transition hover:text-white">Home</RouterLink>
              <RouterLink to="/about" class="block text-slate-400 transition hover:text-white">About</RouterLink>
            </div>
          </div>
        </div>
      </div>
      <div class="mt-12 flex flex-col gap-3 border-t border-slate-800 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
        <p>Built to make daily OJT progress easier to follow.</p>
        <p>© {{ currentYear }} OJT Progress Tracker</p>
      </div>
    </div>
  </footer>
</template>
