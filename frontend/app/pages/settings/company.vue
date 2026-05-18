<template>
  <section class="page">
    <h1>Company settings</h1>
    <p v-if="loading">Loading company...</p>
    <form v-else class="form-grid" @submit.prevent="submit">
      <label>
        Name
        <input v-model="form.name" required>
      </label>
      <label>
        Legal name
        <input v-model="form.legal_name">
      </label>
      <label>
        Trade license number
        <input v-model="form.trade_license_number">
      </label>
      <label>
        Tax registration number
        <input v-model="form.tax_registration_number">
      </label>
      <label>
        Country
        <input v-model="form.country" maxlength="2" required>
      </label>
      <label>
        Emirate
        <input v-model="form.emirate">
      </label>
      <label>
        Currency
        <input v-model="form.default_currency" maxlength="3" required>
      </label>
      <label>
        Timezone
        <input v-model="form.timezone" required>
      </label>
      <label>
        Status
        <select v-model="form.status">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
          <option value="suspended">Suspended</option>
        </select>
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="saved">Company saved.</p>
      <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : 'Save company' }}</button>
    </form>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const api = useApiClient()
const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const error = ref('')
const form = reactive<any>({})

onMounted(async () => {
  try {
    const response = await api.get<{ company: any }>('/company')
    Object.assign(form, response.data.company)
  } finally {
    loading.value = false
  }
})

async function submit() {
  saving.value = true
  saved.value = false
  error.value = ''

  try {
    const response = await api.put<{ company: any }>('/company', form)
    Object.assign(form, response.data.company)
    saved.value = true
  } catch {
    error.value = 'Unable to save company.'
  } finally {
    saving.value = false
  }
}
</script>
