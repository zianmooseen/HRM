<template>
  <section class="page">
    <h1>Company settings</h1>
    <section class="billing-panel">
      <h2>Billing</h2>
      <p v-if="billingLoading">Loading billing status...</p>
      <template v-else-if="billing?.company_subscription">
        <p><strong>{{ billing.company_subscription.plan?.name || 'Current plan' }}</strong></p>
        <p class="muted">
          {{ label(billing.company_subscription.status) }}
          <span v-if="billing.company_subscription.current_period_ends_on">
            until {{ billing.company_subscription.current_period_ends_on }}
          </span>
        </p>
        <p class="muted">{{ billing.billing_invoices.length }} recent invoice{{ billing.billing_invoices.length === 1 ? '' : 's' }}</p>
      </template>
      <p v-else class="muted">No subscription assigned yet.</p>
    </section>
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
        MoHRE establishment number
        <input v-model="form.mohre_establishment_number">
      </label>
      <label>
        WPS bank name
        <input v-model="form.wps_bank_name">
      </label>
      <label>
        WPS agent code
        <input v-model="form.wps_agent_code">
      </label>
      <label>
        WPS sender ID
        <input v-model="form.wps_file_sender_id">
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
const billing = ref<any | null>(null)
const billingLoading = ref(true)

onMounted(async () => {
  try {
    const [companyResponse, billingResponse] = await Promise.all([
      api.get<{ company: any }>('/company'),
      api.get<{ company_subscription: any, billing_invoices: any[] }>('/billing/current'),
    ])
    Object.assign(form, companyResponse.data.company)
    billing.value = billingResponse.data
  } finally {
    loading.value = false
    billingLoading.value = false
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

function label(value: string) {
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}
</script>

<style scoped>
.billing-panel {
  display: grid;
  gap: 6px;
  margin-bottom: 20px;
  border: 1px solid #d8dee4;
  border-radius: 8px;
  background: #ffffff;
  padding: 16px;
}

.billing-panel h2,
.billing-panel p {
  margin: 0;
}

.muted {
  color: #5d6a72;
}
</style>
