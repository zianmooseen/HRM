<template>
  <section class="page">
    <header>
      <div>
        <h1>Compliance</h1>
        <p class="muted">UAE legal rule defaults and end-of-service gratuity estimates.</p>
      </div>
    </header>

    <section>
      <h2>Company Compliance Settings</h2>
      <form v-if="settings" class="form-grid" @submit.prevent="saveSettings">
        <label>
          Payroll day divisor
          <select v-model="settingsForm.payroll_day_divisor" required>
            <option value="calendar_30">30-day calendar</option>
            <option value="actual_calendar_days">Actual calendar days</option>
            <option value="working_days">Working days</option>
          </select>
        </label>
        <label>
          Annual leave accrual
          <select v-model="settingsForm.annual_leave_accrual_method" required>
            <option value="monthly">Monthly</option>
            <option value="annual">Annual</option>
            <option value="manual">Manual</option>
          </select>
        </label>
        <label class="checkbox-label">
          <input v-model="settingsForm.annual_leave_carry_forward_allowed" type="checkbox">
          Allow annual leave carry-forward
        </label>
        <label>
          Max carry-forward days
          <input v-model.number="settingsForm.annual_leave_max_carry_forward_days" type="number" min="0" max="365" step="0.5">
        </label>
        <label class="checkbox-label">
          <input v-model="settingsForm.public_holidays_count_as_annual_leave" type="checkbox">
          Count public holidays as annual leave
        </label>
        <label class="checkbox-label">
          <input v-model="settingsForm.sick_leave_requires_medical_certificate" type="checkbox">
          Require medical certificate for sick leave
        </label>
        <label>
          Sick leave notification days
          <input v-model.number="settingsForm.sick_leave_notification_days" type="number" min="0" max="30" required>
        </label>
        <label class="checkbox-label">
          <input v-model="settingsForm.emiratisation_monitoring_enabled" type="checkbox">
          Enable Emiratisation monitoring
        </label>
        <p v-if="settingsError" class="error">{{ settingsError }}</p>
        <p v-if="settingsSaved" class="muted">Compliance settings saved.</p>
        <button v-if="auth.hasPermission('settings.update')" type="submit" :disabled="savingSettings">
          {{ savingSettings ? 'Saving...' : 'Save compliance settings' }}
        </button>
      </form>
      <p v-else-if="loadingSettings">Loading compliance settings...</p>
    </section>

    <section>
      <h2>Gratuity Calculator</h2>
      <form class="form-grid" @submit.prevent="calculate">
        <label>
          Employee
          <select v-model.number="form.employee_id" required>
            <option disabled :value="0">Select employee</option>
            <option v-for="employee in employees" :key="employee.id" :value="employee.id">
              {{ employee.display_name }} ({{ employee.employee_code }})
            </option>
          </select>
        </label>
        <label>
          Termination date
          <input v-model="form.termination_date" type="date" required>
        </label>
        <label>
          Basic salary override
          <input v-model.number="form.basic_salary" type="number" min="0">
        </label>
        <label>
          Unpaid leave days
          <input v-model.number="form.unpaid_leave_days" type="number" min="0">
        </label>
        <p v-if="error" class="error">{{ error }}</p>
        <button type="submit" :disabled="calculating">{{ calculating ? 'Calculating...' : 'Calculate gratuity' }}</button>
      </form>

      <dl v-if="result" class="detail-list">
        <dt>Employee</dt>
        <dd>{{ result.employee.display_name }}</dd>
        <dt>Service</dt>
        <dd>{{ result.gratuity.service_years }} years ({{ result.gratuity.service_days }} days)</dd>
        <dt>Daily wage</dt>
        <dd>{{ result.gratuity.currency }} {{ result.gratuity.daily_wage }}</dd>
        <dt>Gratuity days</dt>
        <dd>{{ result.gratuity.gratuity_days }}</dd>
        <dt>Estimated gratuity</dt>
        <dd>{{ result.gratuity.currency }} {{ result.gratuity.gratuity_amount }}</dd>
        <dt>Maximum cap</dt>
        <dd>{{ result.gratuity.currency }} {{ result.gratuity.maximum_amount }}</dd>
      </dl>
    </section>

    <section>
      <h2>Active Legal Rules</h2>
      <p v-if="loadingRules">Loading legal rules...</p>
      <p v-else-if="!legalRuleSet" class="muted">No active legal rule set found.</p>
      <table v-else>
        <thead>
          <tr>
            <th>Rule set</th>
            <th>Rule key</th>
            <th>Value</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in legalRuleSet.items" :key="item.rule_key">
            <td>{{ legalRuleSet.name }} {{ legalRuleSet.version }}</td>
            <td>{{ item.rule_key }}</td>
            <td>{{ item.value }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface EmployeeOption {
  id: number
  employee_code: string
  display_name: string
}

interface LegalRuleItem {
  rule_key: string
  value: string | number | boolean
}

interface LegalRuleSet {
  name: string
  version: string
  items: LegalRuleItem[]
}

interface GratuityResult {
  employee: EmployeeOption
  gratuity: {
    service_days: number
    service_years: number
    daily_wage: number
    gratuity_days: number
    gratuity_amount: number
    maximum_amount: number
    currency: string
  }
}

const api = useApiClient()
const auth = useAuthStore()
const employees = ref<EmployeeOption[]>([])
const legalRuleSet = ref<LegalRuleSet | null>(null)
const settings = ref<ComplianceSettings | null>(null)
const result = ref<GratuityResult | null>(null)
const loadingRules = ref(true)
const loadingSettings = ref(true)
const calculating = ref(false)
const savingSettings = ref(false)
const error = ref('')
const settingsError = ref('')
const settingsSaved = ref(false)
const form = reactive({
  employee_id: 0,
  termination_date: new Date().toISOString().slice(0, 10),
  basic_salary: null as number | null,
  unpaid_leave_days: 0,
})
const settingsForm = reactive({
  payroll_day_divisor: 'calendar_30',
  annual_leave_accrual_method: 'monthly',
  annual_leave_carry_forward_allowed: true,
  annual_leave_max_carry_forward_days: null as number | null,
  public_holidays_count_as_annual_leave: false,
  sick_leave_requires_medical_certificate: true,
  sick_leave_notification_days: 3,
  emiratisation_monitoring_enabled: false,
})

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

onMounted(async () => {
  const [employeeResponse, rulesResponse, settingsResponse] = await Promise.all([
    api.get<{ employees: EmployeeOption[] }>('/employees'),
    api.get<{ legal_rule_set: LegalRuleSet | null }>('/compliance/legal-rules'),
    api.get<{ compliance_settings: ComplianceSettings }>('/compliance/settings'),
  ])
  employees.value = employeeResponse.data.employees
  legalRuleSet.value = rulesResponse.data.legal_rule_set
  setSettings(settingsResponse.data.compliance_settings)
  loadingRules.value = false
  loadingSettings.value = false
})

async function calculate() {
  calculating.value = true
  error.value = ''
  result.value = null

  try {
    const response = await api.post<GratuityResult>('/compliance/gratuity', {
      ...form,
      basic_salary: form.basic_salary || null,
    })
    result.value = response.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to calculate gratuity.')
  } finally {
    calculating.value = false
  }
}

async function saveSettings() {
  savingSettings.value = true
  settingsError.value = ''
  settingsSaved.value = false

  try {
    const response = await api.put<{ compliance_settings: ComplianceSettings }>('/compliance/settings', {
      ...settingsForm,
      annual_leave_max_carry_forward_days: settingsForm.annual_leave_max_carry_forward_days || null,
    })
    setSettings(response.data.compliance_settings)
    settingsSaved.value = true
  } catch (err) {
    settingsError.value = apiErrorMessage(err, 'Unable to save compliance settings.')
  } finally {
    savingSettings.value = false
  }
}

function setSettings(nextSettings: ComplianceSettings) {
  settings.value = nextSettings
  settingsForm.payroll_day_divisor = nextSettings.payroll_day_divisor
  settingsForm.annual_leave_accrual_method = nextSettings.annual_leave_accrual_method
  settingsForm.annual_leave_carry_forward_allowed = nextSettings.annual_leave_carry_forward_allowed
  settingsForm.annual_leave_max_carry_forward_days = nextSettings.annual_leave_max_carry_forward_days === null
    ? null
    : Number(nextSettings.annual_leave_max_carry_forward_days)
  settingsForm.public_holidays_count_as_annual_leave = nextSettings.public_holidays_count_as_annual_leave
  settingsForm.sick_leave_requires_medical_certificate = nextSettings.sick_leave_requires_medical_certificate
  settingsForm.sick_leave_notification_days = nextSettings.sick_leave_notification_days
  settingsForm.emiratisation_monitoring_enabled = nextSettings.emiratisation_monitoring_enabled
}
</script>

<style scoped>
.muted {
  margin: 6px 0 0;
  color: #5d6a72;
}

section h2 {
  margin: 0 0 12px;
}
</style>
