<template>
  <section class="page">
    <header>
      <div>
        <h1>WPS providers & risk</h1>
        <p class="muted">Platform administration for supported providers and company salary-transfer exposure.</p>
      </div>
    </header>

    <section class="panel-grid">
      <form class="form-grid" @submit.prevent="saveProvider">
        <h2>{{ providerForm.id ? 'Edit provider' : 'Add provider' }}</h2>
        <label>Name<input v-model="providerForm.name" required></label>
        <label>Code<input v-model="providerForm.code" required></label>
        <label>
          Type
          <select v-model="providerForm.provider_type">
            <option value="bank">Bank</option>
            <option value="exchange_house">Exchange house</option>
            <option value="financial_institution">Financial institution</option>
            <option value="digital_wallet">Digital wallet</option>
            <option value="other">Other</option>
          </select>
        </label>
        <label>
          Integration
          <select v-model="providerForm.integration_type">
            <option value="manual_upload">Manual upload</option>
            <option value="file_export">File export</option>
            <option value="api">API</option>
          </select>
        </label>
        <label>
          Export profile
          <select v-model="providerForm.export_profile">
            <option value="generic">Generic SIF</option>
            <option value="fab">FAB</option>
            <option value="emirates_nbd">Emirates NBD</option>
          </select>
        </label>
        <label>Website<input v-model="providerForm.website" type="url"></label>
        <label>Contact email<input v-model="providerForm.contact_email" type="email"></label>
        <label>Contact phone<input v-model="providerForm.contact_phone"></label>
        <label>Status<select v-model="providerForm.status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
        <p v-if="providerError" class="error">{{ providerError }}</p>
        <div class="actions">
          <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : 'Save provider' }}</button>
          <button v-if="providerForm.id" type="button" class="secondary" @click="resetProvider">Cancel</button>
        </div>
      </form>

      <section>
        <h2>Providers</h2>
        <table>
          <thead><tr><th>Name</th><th>Type</th><th>Integration</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <tr v-for="provider in providers" :key="provider.id">
              <td>{{ provider.name }}</td>
              <td>{{ label(provider.provider_type) }}</td>
              <td>{{ label(provider.integration_type) }}</td>
              <td>{{ label(provider.status) }}</td>
              <td><button type="button" class="secondary" @click="editProvider(provider)">Edit</button></td>
            </tr>
          </tbody>
        </table>
      </section>
    </section>

    <section>
      <h2>Company WPS risk</h2>
      <p v-if="loading">Loading WPS risk...</p>
      <p v-else-if="loadError" class="error">{{ loadError }}</p>
      <table v-else>
        <thead><tr><th>Company</th><th>Risk</th><th>Open batches</th><th>Latest period</th><th>Due date</th></tr></thead>
        <tbody>
          <tr v-for="company in companies" :key="company.company_id">
            <td>{{ company.company_name }}</td>
            <td><span :class="['status', company.risk_status]">{{ label(company.risk_status) }}</span></td>
            <td>{{ company.open_alerts }}</td>
            <td>{{ company.periods[0]?.period_end || '-' }}</td>
            <td>{{ company.periods[0]?.due_date || '-' }}</td>
          </tr>
          <tr v-if="companies.length === 0"><td colspan="5">No active companies found.</td></tr>
        </tbody>
      </table>
    </section>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface Provider {
  id: number
  name: string
  code: string
  provider_type: string
  website: string | null
  contact_phone: string | null
  contact_email: string | null
  integration_type: string
  export_profile: string
  status: string
}
interface RiskCompany {
  company_id: number
  company_name: string
  risk_status: string
  open_alerts: number
  periods: Array<{ period_end: string, due_date: string | null }>
}

const api = useApiClient()
const providers = ref<Provider[]>([])
const companies = ref<RiskCompany[]>([])
const loading = ref(true)
const saving = ref(false)
const loadError = ref('')
const providerError = ref('')
const providerForm = reactive(emptyProvider())

onMounted(load)

function emptyProvider() {
  return {
    id: 0, name: '', code: '', provider_type: 'bank', website: '', contact_phone: '', contact_email: '',
    integration_type: 'file_export', export_profile: 'generic', status: 'active',
  }
}
async function load() {
  loading.value = true
  try {
    const [providerResponse, riskResponse] = await Promise.all([
      api.get<{ wps_providers: Provider[] }>('/platform/wps-providers'),
      api.get<{ companies: RiskCompany[] }>('/platform/wps-risk'),
    ])
    providers.value = providerResponse.data.wps_providers
    companies.value = riskResponse.data.companies
  } catch (cause) {
    loadError.value = apiErrorMessage(cause, 'Unable to load platform WPS data.')
  } finally {
    loading.value = false
  }
}
async function saveProvider() {
  saving.value = true
  providerError.value = ''
  const payload = {
    ...providerForm,
    website: providerForm.website || null,
    contact_email: providerForm.contact_email || null,
    contact_phone: providerForm.contact_phone || null,
  }
  try {
    if (providerForm.id) await api.put(`/platform/wps-providers/${providerForm.id}`, payload)
    else await api.post('/platform/wps-providers', payload)
    resetProvider()
    await load()
  } catch (cause) {
    providerError.value = apiErrorMessage(cause, 'Unable to save WPS provider.')
  } finally {
    saving.value = false
  }
}
function editProvider(provider: Provider) {
  Object.assign(providerForm, provider, {
    website: provider.website || '', contact_email: provider.contact_email || '', contact_phone: provider.contact_phone || '',
  })
}
function resetProvider() {
  Object.assign(providerForm, emptyProvider())
}
function label(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())
}
</script>

<style scoped>
.muted { color: #5d6a72; }
.panel-grid { display: grid; grid-template-columns: minmax(280px, 420px) 1fr; gap: 20px; }
.actions { display: flex; gap: 10px; }
.status { font-weight: 700; }
.status.overdue, .status.non_compliant { color: #a52631; }
.status.due_soon, .status.at_risk { color: #9a6110; }
.status.compliant, .status.paid { color: #176b54; }
@media (max-width: 980px) { .panel-grid { grid-template-columns: 1fr; } }
</style>
