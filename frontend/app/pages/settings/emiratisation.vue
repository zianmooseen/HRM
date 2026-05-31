<template>
  <section class="page">
    <header>
      <div>
        <h1>Emiratisation</h1>
        <p class="muted">Monitor UAE citizen workforce targets using company category and active employee records.</p>
      </div>
    </header>

    <form v-if="company && settings" class="form-grid" @submit.prevent="saveSettings">
      <label class="checkbox-label">
        <input v-model="companyForm.emiratisation_applicable" type="checkbox">
        Emiratisation applies to this company
      </label>
      <label class="checkbox-label">
        <input v-model="settingsForm.emiratisation_monitoring_enabled" type="checkbox">
        Enable monitoring
      </label>
      <label>
        Company category
        <select v-model="companyForm.emiratisation_category">
          <option value="not_applicable">Not applicable</option>
          <option value="large_50_plus">Large company, 50+ workers</option>
          <option value="selected_20_to_49">Selected 20-49 worker company</option>
        </select>
      </label>
      <label>
        Economic sector code
        <input v-model="companyForm.economic_sector_code">
      </label>
      <label>
        MoHRE establishment number
        <input v-model="companyForm.mohre_establishment_number">
      </label>
      <p v-if="settingsError" class="error">{{ settingsError }}</p>
      <p v-if="settingsSaved" class="muted">Emiratisation settings saved.</p>
      <button v-if="auth.hasPermission('settings.update')" type="submit" :disabled="savingSettings">
        {{ savingSettings ? 'Saving...' : 'Save settings' }}
      </button>
    </form>
    <p v-else-if="loading">Loading Emiratisation setup...</p>

    <section v-if="snapshot" class="metrics-grid">
      <article>
        <span>Compliance status</span>
        <strong>{{ label(snapshot.compliance_status) }}</strong>
      </article>
      <article>
        <span>Active workers</span>
        <strong>{{ snapshot.total_active_workers }}</strong>
      </article>
      <article>
        <span>Skilled workers</span>
        <strong>{{ snapshot.total_skilled_workers }}</strong>
      </article>
      <article>
        <span>Skilled UAE citizens</span>
        <strong>{{ snapshot.total_skilled_uae_citizens }}</strong>
      </article>
      <article>
        <span>Required UAE citizens</span>
        <strong>{{ snapshot.required_uae_citizens }}</strong>
      </article>
      <article>
        <span>Missing UAE citizens</span>
        <strong>{{ snapshot.missing_uae_citizens }}</strong>
      </article>
      <article>
        <span>Estimated contribution exposure</span>
        <strong>{{ money(snapshot.estimated_contribution_amount) }}</strong>
      </article>
    </section>

    <section>
      <div class="button-row">
        <button type="button" class="secondary" :disabled="loading" @click="loadCompliance">Refresh calculation</button>
        <button
          v-if="auth.hasPermission('settings.update')"
          type="button"
          :disabled="savingSnapshot || !snapshot"
          @click="saveSnapshot"
        >
          {{ savingSnapshot ? 'Saving...' : 'Save snapshot' }}
        </button>
      </div>
      <p v-if="snapshotError" class="error">{{ snapshotError }}</p>
      <p v-if="snapshotSaved" class="muted">Snapshot saved.</p>
    </section>

    <section v-if="latestSnapshot">
      <h2>Latest Saved Snapshot</h2>
      <dl class="detail-list">
        <dt>Date</dt>
        <dd>{{ latestSnapshot.snapshot_date }}</dd>
        <dt>Status</dt>
        <dd>{{ label(latestSnapshot.compliance_status) }}</dd>
        <dt>Required</dt>
        <dd>{{ latestSnapshot.required_uae_citizens }}</dd>
        <dt>Missing</dt>
        <dd>{{ latestSnapshot.missing_uae_citizens }}</dd>
      </dl>
    </section>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface Company {
  name: string
  legal_name: string | null
  trade_license_number: string | null
  tax_registration_number: string | null
  country: string
  emirate: string | null
  default_currency: string
  timezone: string
  status: string
  emiratisation_applicable: boolean
  emiratisation_category: string
  economic_sector_code: string | null
  mohre_establishment_number: string | null
}

interface ComplianceSettings {
  payroll_day_divisor: string
  annual_leave_accrual_method: string
  annual_leave_carry_forward_allowed: boolean
  annual_leave_max_carry_forward_days: string | null
  public_holidays_count_as_annual_leave: boolean
  sick_leave_requires_medical_certificate: boolean
  sick_leave_notification_days: number
  emiratisation_monitoring_enabled: boolean
}

interface EmiratisationSnapshot {
  snapshot_date?: string
  total_active_workers: number
  total_skilled_workers: number
  total_active_uae_citizens: number
  total_skilled_uae_citizens: number
  required_uae_citizens: number
  missing_uae_citizens: number
  estimated_contribution_amount: string | number
  compliance_status: string
}

const api = useApiClient()
const auth = useAuthStore()
const loading = ref(true)
const savingSettings = ref(false)
const savingSnapshot = ref(false)
const settingsSaved = ref(false)
const snapshotSaved = ref(false)
const settingsError = ref('')
const snapshotError = ref('')
const company = ref<Company | null>(null)
const settings = ref<ComplianceSettings | null>(null)
const snapshot = ref<EmiratisationSnapshot | null>(null)
const latestSnapshot = ref<EmiratisationSnapshot | null>(null)
const companyForm = reactive<Company>({
  name: '',
  legal_name: null,
  trade_license_number: null,
  tax_registration_number: null,
  country: 'AE',
  emirate: null,
  default_currency: 'AED',
  timezone: 'Asia/Dubai',
  status: 'active',
  emiratisation_applicable: false,
  emiratisation_category: 'not_applicable',
  economic_sector_code: null,
  mohre_establishment_number: null,
})
const settingsForm = reactive({
  emiratisation_monitoring_enabled: false,
})

onMounted(loadAll)

async function loadAll() {
  loading.value = true
  settingsError.value = ''
  snapshotError.value = ''

  try {
    const [companyResponse, settingsResponse] = await Promise.all([
      api.get<{ company: Company }>('/company'),
      api.get<{ compliance_settings: ComplianceSettings }>('/compliance/settings'),
    ])
    setCompany(companyResponse.data.company)
    setSettings(settingsResponse.data.compliance_settings)
    await loadCompliance()
  } catch (err) {
    settingsError.value = apiErrorMessage(err, 'Unable to load Emiratisation setup.')
  } finally {
    loading.value = false
  }
}

async function loadCompliance() {
  snapshotError.value = ''

  try {
    const response = await api.get<{ snapshot: EmiratisationSnapshot, latest_snapshot: EmiratisationSnapshot | null }>('/compliance/emiratisation')
    snapshot.value = response.data.snapshot
    latestSnapshot.value = response.data.latest_snapshot
  } catch (err) {
    snapshotError.value = apiErrorMessage(err, 'Unable to calculate Emiratisation status.')
  }
}

async function saveSettings() {
  if (!settings.value) return

  savingSettings.value = true
  settingsSaved.value = false
  settingsError.value = ''

  try {
    const [companyResponse, settingsResponse] = await Promise.all([
      api.put<{ company: Company }>('/company', companyPayload()),
      api.put<{ compliance_settings: ComplianceSettings }>('/compliance/settings', {
        ...settings.value,
        annual_leave_max_carry_forward_days: settings.value.annual_leave_max_carry_forward_days
          ? Number(settings.value.annual_leave_max_carry_forward_days)
          : null,
        emiratisation_monitoring_enabled: settingsForm.emiratisation_monitoring_enabled,
      }),
    ])
    setCompany(companyResponse.data.company)
    setSettings(settingsResponse.data.compliance_settings)
    settingsSaved.value = true
    await loadCompliance()
  } catch (err) {
    settingsError.value = apiErrorMessage(err, 'Unable to save Emiratisation settings.')
  } finally {
    savingSettings.value = false
  }
}

async function saveSnapshot() {
  savingSnapshot.value = true
  snapshotSaved.value = false
  snapshotError.value = ''

  try {
    const response = await api.post<{ snapshot: EmiratisationSnapshot }>('/compliance/emiratisation/snapshot', {})
    latestSnapshot.value = response.data.snapshot
    snapshotSaved.value = true
  } catch (err) {
    snapshotError.value = apiErrorMessage(err, 'Unable to save Emiratisation snapshot.')
  } finally {
    savingSnapshot.value = false
  }
}

function setCompany(nextCompany: Company) {
  company.value = nextCompany
  Object.assign(companyForm, nextCompany)
}

function setSettings(nextSettings: ComplianceSettings) {
  settings.value = nextSettings
  settingsForm.emiratisation_monitoring_enabled = nextSettings.emiratisation_monitoring_enabled
}

function companyPayload() {
  return {
    ...companyForm,
    country: companyForm.country.toUpperCase(),
    default_currency: companyForm.default_currency.toUpperCase(),
    legal_name: companyForm.legal_name || null,
    trade_license_number: companyForm.trade_license_number || null,
    tax_registration_number: companyForm.tax_registration_number || null,
    emirate: companyForm.emirate || null,
    economic_sector_code: companyForm.economic_sector_code || null,
    mohre_establishment_number: companyForm.mohre_establishment_number || null,
  }
}

function label(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function money(value: string | number) {
  return `AED ${Number(value).toFixed(2)}`
}
</script>

<style scoped>
.muted {
  margin: 6px 0 0;
  color: #5d6a72;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.metrics-grid article {
  display: grid;
  gap: 6px;
  border: 1px solid #d7dee4;
  border-radius: 8px;
  padding: 16px;
}

.metrics-grid span {
  color: #5d6a72;
  font-size: 13px;
}

.metrics-grid strong {
  color: #102027;
  font-size: 24px;
}
</style>
