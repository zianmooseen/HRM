<template>
  <section class="page">
    <header>
      <div>
        <h1>MoHRE & WPS setup</h1>
        <p class="muted">Register company establishments and map each one to its WPS provider profile.</p>
      </div>
    </header>

    <p v-if="loading">Loading compliance setup...</p>
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <template v-else>
      <section class="panel-grid">
        <form class="form-grid" @submit.prevent="saveEstablishment">
          <h2>{{ establishmentForm.id ? 'Edit establishment' : 'Add establishment' }}</h2>
          <label>
            Establishment name
            <input v-model="establishmentForm.establishment_name" required>
          </label>
          <label>
            MoHRE establishment number
            <input v-model="establishmentForm.mohre_establishment_number" required>
          </label>
          <label>
            Labour file number
            <input v-model="establishmentForm.labour_file_number">
          </label>
          <label>
            Establishment card number
            <input v-model="establishmentForm.establishment_card_number">
          </label>
          <label>
            Trade license number
            <input v-model="establishmentForm.trade_license_number">
          </label>
          <label>
            Emirate
            <input v-model="establishmentForm.emirate">
          </label>
          <label>
            Issue date
            <input v-model="establishmentForm.issue_date" type="date">
          </label>
          <label>
            Expiry date
            <input v-model="establishmentForm.expiry_date" type="date">
          </label>
          <label>
            Status
            <select v-model="establishmentForm.status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="expired">Expired</option>
              <option value="under_review">Under review</option>
            </select>
          </label>
          <label class="check">
            <input v-model="establishmentForm.wps_required" type="checkbox">
            WPS required
          </label>
          <label v-if="!establishmentForm.wps_required">
            Exemption reason
            <textarea v-model="establishmentForm.wps_exemption_reason" rows="3" required />
          </label>
          <p v-if="formError" class="error">{{ formError }}</p>
          <div class="actions">
            <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : 'Save establishment' }}</button>
            <button v-if="establishmentForm.id" type="button" class="secondary" @click="resetEstablishment">Cancel</button>
          </div>
        </form>

        <form class="form-grid" @submit.prevent="saveSetting">
          <h2>WPS provider mapping</h2>
          <label>
            Establishment
            <select v-model.number="settingForm.mohre_establishment_id" required>
              <option disabled :value="0">Select establishment</option>
              <option v-for="item in establishments" :key="item.id" :value="item.id">{{ item.establishment_name }}</option>
            </select>
          </label>
          <label>
            WPS provider
            <select v-model.number="settingForm.wps_provider_id" required>
              <option disabled :value="0">Select provider</option>
              <option v-for="provider in providers" :key="provider.id" :value="provider.id">{{ provider.name }}</option>
            </select>
          </label>
          <label>
            Payroll due day
            <input v-model.number="settingForm.payroll_due_day" type="number" min="1" max="28" required>
          </label>
          <label>
            Salary period
            <select v-model="settingForm.salary_period_type">
              <option value="monthly">Monthly</option>
              <option value="weekly">Weekly</option>
              <option value="biweekly">Biweekly</option>
              <option value="custom">Custom</option>
            </select>
          </label>
          <label>
            Currency
            <input v-model="settingForm.payment_currency" maxlength="3" required>
          </label>
          <label>
            Agent code
            <input v-model="settingForm.agent_code">
          </label>
          <label>
            Sender ID
            <input v-model="settingForm.sender_id">
          </label>
          <label>
            Provider customer reference
            <input v-model="settingForm.provider_customer_reference">
          </label>
          <label>
            Provider portal URL
            <input v-model="settingForm.provider_portal_url" type="url">
          </label>
          <label class="check"><input v-model="settingForm.sif_export_enabled" type="checkbox"> SIF export enabled</label>
          <label class="check"><input v-model="settingForm.auto_mark_paid_allowed" type="checkbox"> Allow automatic paid status</label>
          <p v-if="settingError" class="error">{{ settingError }}</p>
          <button type="submit" :disabled="savingSetting">{{ savingSetting ? 'Saving...' : 'Save WPS mapping' }}</button>
        </form>
      </section>

      <section>
        <h2>Registered establishments</h2>
        <table>
          <thead><tr><th>Name</th><th>MoHRE number</th><th>Emirate</th><th>WPS</th><th>Provider</th><th></th></tr></thead>
          <tbody>
            <tr v-for="item in establishments" :key="item.id">
              <td>{{ item.establishment_name }}</td>
              <td>{{ item.mohre_establishment_number }}</td>
              <td>{{ item.emirate || '-' }}</td>
              <td>{{ item.wps_required ? 'Required' : 'Exempt' }}</td>
              <td>{{ item.wps_setting?.provider?.name || 'Not mapped' }}</td>
              <td class="table-actions">
                <button type="button" class="secondary" @click="editEstablishment(item)">Edit</button>
                <button type="button" class="secondary" @click="editSetting(item)">Configure WPS</button>
              </td>
            </tr>
            <tr v-if="establishments.length === 0"><td colspan="6">No MoHRE establishments configured.</td></tr>
          </tbody>
        </table>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface Provider { id: number, name: string }
interface Setting {
  mohre_establishment_id: number
  wps_provider_id: number
  payroll_due_day: number
  salary_period_type: string
  payment_currency: string
  sif_export_enabled: boolean
  provider_portal_url: string | null
  provider_customer_reference: string | null
  auto_mark_paid_allowed: boolean
  agent_code: string | null
  sender_id: string | null
  status: string
  provider?: Provider | null
}
interface Establishment {
  id: number
  establishment_name: string
  mohre_establishment_number: string
  labour_file_number: string | null
  establishment_card_number: string | null
  trade_license_number: string | null
  emirate: string | null
  status: string
  issue_date: string | null
  expiry_date: string | null
  wps_required: boolean
  wps_exemption_reason: string | null
  notes: string | null
  wps_setting?: Setting | null
}

const api = useApiClient()
const loading = ref(true)
const saving = ref(false)
const savingSetting = ref(false)
const loadError = ref('')
const formError = ref('')
const settingError = ref('')
const establishments = ref<Establishment[]>([])
const providers = ref<Provider[]>([])
const establishmentForm = reactive(emptyEstablishment())
const settingForm = reactive(emptySetting())

onMounted(load)

function emptyEstablishment() {
  return {
    id: 0, branch_id: null as number | null, establishment_name: '', mohre_establishment_number: '',
    labour_file_number: '', establishment_card_number: '', trade_license_number: '', emirate: '',
    status: 'active', issue_date: '', expiry_date: '', wps_required: true, wps_exemption_reason: '', notes: '',
  }
}
function emptySetting() {
  return {
    mohre_establishment_id: 0, wps_provider_id: 0, payroll_due_day: 1, salary_period_type: 'monthly',
    payment_currency: 'AED', sif_export_enabled: true, provider_portal_url: '',
    provider_customer_reference: '', auto_mark_paid_allowed: false, agent_code: '', sender_id: '', status: 'active',
  }
}
async function load() {
  loading.value = true
  try {
    const [establishmentResponse, settingResponse] = await Promise.all([
      api.get<{ mohre_establishments: Establishment[] }>('/mohre-establishments'),
      api.get<{ wps_settings: Setting[], wps_providers: Provider[] }>('/wps-settings'),
    ])
    establishments.value = establishmentResponse.data.mohre_establishments
    providers.value = settingResponse.data.wps_providers
  } catch (error) {
    loadError.value = apiErrorMessage(error, 'Unable to load MoHRE and WPS setup.')
  } finally {
    loading.value = false
  }
}
async function saveEstablishment() {
  saving.value = true
  formError.value = ''
  const payload = {
    ...establishmentForm,
    branch_id: establishmentForm.branch_id || null,
    issue_date: establishmentForm.issue_date || null,
    expiry_date: establishmentForm.expiry_date || null,
    wps_exemption_reason: establishmentForm.wps_exemption_reason || null,
    notes: establishmentForm.notes || null,
  }
  try {
    if (establishmentForm.id) await api.put(`/mohre-establishments/${establishmentForm.id}`, payload)
    else await api.post('/mohre-establishments', payload)
    resetEstablishment()
    await load()
  } catch (error) {
    formError.value = apiErrorMessage(error, 'Unable to save establishment.')
  } finally {
    saving.value = false
  }
}
async function saveSetting() {
  savingSetting.value = true
  settingError.value = ''
  try {
    await api.post('/wps-settings', {
      ...settingForm,
      provider_portal_url: settingForm.provider_portal_url || null,
      provider_customer_reference: settingForm.provider_customer_reference || null,
      agent_code: settingForm.agent_code || null,
      sender_id: settingForm.sender_id || null,
    })
    await load()
  } catch (error) {
    settingError.value = apiErrorMessage(error, 'Unable to save WPS mapping.')
  } finally {
    savingSetting.value = false
  }
}
function editEstablishment(item: Establishment) {
  Object.assign(establishmentForm, emptyEstablishment(), item, {
    issue_date: item.issue_date || '', expiry_date: item.expiry_date || '',
    wps_exemption_reason: item.wps_exemption_reason || '', notes: item.notes || '',
  })
}
function editSetting(item: Establishment) {
  Object.assign(settingForm, emptySetting(), item.wps_setting || {}, { mohre_establishment_id: item.id })
}
function resetEstablishment() {
  Object.assign(establishmentForm, emptyEstablishment())
}
</script>

<style scoped>
.muted { color: #5d6a72; }
.panel-grid { display: grid; grid-template-columns: repeat(2, minmax(280px, 1fr)); gap: 20px; }
.check { display: flex; align-items: center; gap: 8px; }
.check input { width: auto; }
.actions { display: flex; gap: 10px; }
@media (max-width: 900px) { .panel-grid { grid-template-columns: 1fr; } }
</style>
