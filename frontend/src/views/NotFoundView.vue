<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <main class="grid min-h-[70vh] place-items-center bg-slate-50 px-4 py-10 sm:px-6 lg:px-8">
    <section class="max-w-md text-center">
      <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">404</p>
      <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900">Page not found</h1>
      <p class="mt-3 text-slate-600">The page you requested does not exist.</p>
      <RouterLink
        to="/"
        class="mt-6 inline-flex rounded-full bg-indigo-600 px-5 py-3 font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
      >
        Return home
      </RouterLink>
    </section>
  </main>
</template>
