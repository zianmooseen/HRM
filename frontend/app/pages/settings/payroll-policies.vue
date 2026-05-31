<template>
  <section class="page">
    <header>
      <div>
        <h1>Payroll Policies</h1>
        <p class="muted">Configure payroll calculation basis used by salary-linked leave and payroll outputs.</p>
      </div>
    </header>

    <form v-if="settings" class="form-grid" @submit.prevent="save">
      <label>
        Daily wage divisor
        <select v-model="form.payroll_day_divisor" required>
          <option value="calendar_30">30-day calendar</option>
          <option value="actual_calendar_days">Actual calendar days</option>
          <option value="working_days">Working days in month</option>
        </select>
      </label>
      <section class="policy-preview">
        <strong>{{ divisorLabel }}</strong>
        <span>{{ divisorDescription }}</span>
      </section>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="saved" class="muted">Payroll policy saved.</p>
      <button v-if="auth.hasPermission('settings.update')" type="submit" :disabled="saving">
        {{ saving ? 'Saving...' : 'Save payroll policy' }}
      </button>
    </form>
    <p v-else-if="loading">Loading payroll policy...</p>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

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

const api = useApiClient()
const auth = useAuthStore()
const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const error = ref('')
const settings = ref<ComplianceSettings | null>(null)
const form = reactive({
  payroll_day_divisor: 'calendar_30',
})

const divisorLabel = computed(() => divisorLabels[form.payroll_day_divisor] || divisorLabels.calendar_30)
const divisorDescription = computed(() => divisorDescriptions[form.payroll_day_divisor] || divisorDescriptions.calendar_30)
const divisorLabels: Record<string, string> = {
  calendar_30: 'Basic salary / 30',
  actual_calendar_days: 'Basic salary / actual days in month',
  working_days: 'Basic salary / working days in month',
}
const divisorDescriptions: Record<string, string> = {
  calendar_30: 'Uses a fixed divisor for daily wage calculations.',
  actual_calendar_days: 'Uses 28, 29, 30, or 31 days based on the leave month.',
  working_days: 'Uses weekdays in the leave month and excludes weekends.',
}

onMounted(load)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get<{ compliance_settings: ComplianceSettings }>('/compliance/settings')
    setSettings(response.data.compliance_settings)
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to load payroll policy.')
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!settings.value) return

  saving.value = true
  saved.value = false
  error.value = ''

  try {
    // Feature flow step 1: payroll policy edits preserve the wider compliance settings payload while changing only payroll calculation basis.
    const response = await api.put<{ compliance_settings: ComplianceSettings }>('/compliance/settings', {
      ...settings.value,
      payroll_day_divisor: form.payroll_day_divisor,
      annual_leave_max_carry_forward_days: settings.value.annual_leave_max_carry_forward_days
        ? Number(settings.value.annual_leave_max_carry_forward_days)
        : null,
    })
    setSettings(response.data.compliance_settings)
    saved.value = true
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to save payroll policy.')
  } finally {
    saving.value = false
  }
}

function setSettings(nextSettings: ComplianceSettings) {
  settings.value = nextSettings
  form.payroll_day_divisor = nextSettings.payroll_day_divisor
}
</script>

<style scoped>
.muted {
  margin: 6px 0 0;
  color: #5d6a72;
}

.policy-preview {
  display: grid;
  gap: 4px;
  align-self: end;
  color: #35444c;
}

.policy-preview strong {
  color: #102027;
}
</style>
