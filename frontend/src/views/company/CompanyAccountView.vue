<script setup>
import { reactive, ref } from 'vue'
import ActionAlert from '../../components/ActionAlert.vue'
import PageHeader from '../../components/PageHeader.vue'
import PasswordRequirements from '../../components/PasswordRequirements.vue'
import { apiErrorMessage } from '../../services/api'
import { changeCompanyPassword } from '../../services/ojt'

const form = reactive({ current_password: '', password: '', password_confirmation: '' })
const errors = ref({})
const error = ref('')
const saving = ref(false)
const alert = ref({ open: false, variant: 'success', title: '', message: '' })

async function savePassword() {
  errors.value = {}
  error.value = ''
  saving.value = true

  try {
    await changeCompanyPassword({ ...form })
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
    alert.value = { open: true, variant: 'success', title: 'Password changed', message: 'Your company account password was updated.' }
  } catch (requestError) {
    errors.value = requestError?.response?.data?.errors ?? {}
    error.value = apiErrorMessage(requestError, 'Unable to change your password. Please try again.')
    alert.value = { open: true, variant: 'error', title: 'Password not changed', message: error.value }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <main class="mx-auto max-w-3xl space-y-7 px-4 py-10 sm:px-6 lg:px-8">
    <PageHeader title="Change password" description="Update the password for your company account." />
    <ActionAlert :open="alert.open" :variant="alert.variant" :title="alert.title" :message="alert.message" @close="alert.open = false" />

    <form class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" data-testid="company-password-form" @submit.prevent="savePassword">
      <div>
        <label for="company-current-password" class="block text-sm font-semibold text-slate-800">Current password</label>
        <input id="company-current-password" v-model="form.current_password" type="password" autocomplete="current-password" required class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
        <p v-if="errors.current_password" class="mt-1 text-sm text-red-700" role="alert">{{ errors.current_password[0] }}</p>
      </div>
      <div>
        <label for="company-new-password" class="block text-sm font-semibold text-slate-800">New password</label>
        <input id="company-new-password" v-model="form.password" type="password" autocomplete="new-password" required minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
        <PasswordRequirements :password="form.password" id-prefix="company-password" />
        <p v-if="errors.password" class="mt-1 text-sm text-red-700" role="alert">{{ errors.password[0] }}</p>
      </div>
      <div>
        <label for="company-password-confirmation" class="block text-sm font-semibold text-slate-800">Confirm new password</label>
        <input id="company-password-confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required minlength="12" class="mt-2 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200" />
        <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-700" role="alert">{{ errors.password_confirmation[0] }}</p>
      </div>
      <p v-if="error" class="text-sm text-red-700" role="alert">{{ error }}</p>
      <button type="submit" class="app-button app-button--primary" :disabled="saving">{{ saving ? 'Saving...' : 'Change password' }}</button>
    </form>
  </main>
</template>
