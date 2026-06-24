<template>
  <section class="page">
    <header>
      <div>
        <h1>Payroll</h1>
        <p class="muted">Set up allowances and deductions, run payroll periods, and review generated payslips.</p>
      </div>
    </header>

    <section class="panel-grid">
      <form class="form-grid" @submit.prevent="createComponent">
        <h2>Allowances & Deductions</h2>
        <label>
          Code
          <input v-model="componentForm.code" required>
        </label>
        <label>
          Name
          <input v-model="componentForm.name" required>
        </label>
        <label>
          Type
          <select v-model="componentForm.type" required>
            <option value="earning">Allowance</option>
            <option value="deduction">Deduction</option>
          </select>
        </label>
        <label>
          Status
          <select v-model="componentForm.status" required>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </label>
        <p v-if="componentError" class="error">{{ componentError }}</p>
        <button type="submit" :disabled="savingComponent">{{ savingComponent ? 'Saving...' : 'Create allowance or deduction' }}</button>
      </form>

      <form class="form-grid" @submit.prevent="assignComponent">
        <h2>Assign to Employee</h2>
        <label>
          Employee
          <select v-model.number="assignmentForm.employee_id" required>
            <option disabled :value="0">Select employee</option>
            <option v-for="employee in employees" :key="employee.id" :value="employee.id">
              {{ employee.display_name }} ({{ employee.employee_code }})
            </option>
          </select>
        </label>
        <label>
          Allowance or deduction
          <select v-model.number="assignmentForm.salary_component_id" required>
            <option disabled :value="0">Select allowance or deduction</option>
            <option v-for="component in components" :key="component.id" :value="component.id">
              {{ component.name }} · {{ component.type === 'deduction' ? 'Deduction' : 'Allowance' }}
            </option>
          </select>
        </label>
        <label>
          Amount
          <input v-model.number="assignmentForm.amount" type="number" min="0" required>
        </label>
        <label>
          Effective from
          <input v-model="assignmentForm.effective_from" type="date" required>
        </label>
        <p v-if="assignmentError" class="error">{{ assignmentError }}</p>
        <button type="submit" :disabled="savingAssignment">{{ savingAssignment ? 'Saving...' : 'Assign pay item' }}</button>
      </form>

      <form class="form-grid" @submit.prevent="createPeriod">
        <h2>Payroll Period</h2>
        <label v-if="canViewWpsSettings">
          MoHRE establishment
          <select v-model.number="periodForm.mohre_establishment_id">
            <option :value="0">Use company default</option>
            <option v-for="setting in wpsSettings" :key="setting.mohre_establishment_id" :value="setting.mohre_establishment_id">
              {{ setting.establishment?.establishment_name || `Establishment #${setting.mohre_establishment_id}` }}
            </option>
          </select>
        </label>
        <label v-if="canViewWpsSettings">
          WPS provider
          <select v-model.number="periodForm.wps_provider_id">
            <option :value="0">Use establishment provider</option>
            <option v-for="provider in wpsProviders" :key="provider.id" :value="provider.id">{{ provider.name }}</option>
          </select>
        </label>
        <label>
          Period start
          <input v-model="periodForm.period_start" type="date" required>
        </label>
        <label>
          Period end
          <input v-model="periodForm.period_end" type="date" required>
        </label>
        <label>
          Pay date
          <input v-model="periodForm.pay_date" type="date">
        </label>
        <label v-if="canViewWpsSettings">
          WPS due date
          <input v-model="periodForm.payroll_due_date" type="date">
        </label>
        <p v-if="periodError" class="error">{{ periodError }}</p>
        <button type="submit" :disabled="savingPeriod">{{ savingPeriod ? 'Saving...' : 'Create period' }}</button>
      </form>
    </section>

    <section>
      <h2>Payroll Periods</h2>
      <p v-if="loading">Loading payroll...</p>
      <p v-else-if="loadError" class="error">{{ loadError }}</p>
      <table v-else>
        <thead>
          <tr>
            <th>Period</th>
            <th>Pay date</th>
            <th>Status</th>
            <th>Payslips</th>
            <th></th>
          </tr>
          <tr class="column-filter-row">
            <th><TableColumnFilter v-model="periodColumnFilters.period" label="Filter payroll period" /></th>
            <th><TableColumnFilter v-model="periodColumnFilters.payDate" label="Filter pay date" type="date" /></th>
            <th><TableColumnFilter v-model="periodColumnFilters.status" label="Filter payroll status" /></th>
            <th><TableColumnFilter v-model="periodColumnFilters.payslips" label="Filter payslip count" /></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="period in filteredPeriods" :key="period.id">
            <tr :class="{ 'period-row-active': selectedPeriod?.id === period.id }">
              <td>{{ period.period_start }} to {{ period.period_end }}</td>
              <td>{{ period.pay_date || '-' }}</td>
              <td>{{ label(period.status) }}</td>
              <td>{{ period.payslips_count ?? 0 }}</td>
              <td class="table-actions">
                <button type="button" class="secondary" @click="togglePeriod(period)">
                  {{ selectedPeriod?.id === period.id ? 'Close' : 'Open' }}
                </button>
                <button
                  v-if="period.status !== 'approved'"
                  type="button"
                  class="secondary"
                  @click="runPeriod(period)"
                >
                  Run
                </button>
                <button
                  v-if="period.status === 'processed'"
                  type="button"
                  @click="approvePeriod(period)"
                >
                  Approve
                </button>
              </td>
            </tr>
            <tr v-if="selectedPeriod?.id === period.id" class="period-detail-row">
              <td colspan="5">
                <section class="period-detail">
                  <header class="period-detail-heading">
                    <div>
                      <h3>Payslips</h3>
                      <p class="muted">{{ selectedPeriod.period_start }} to {{ selectedPeriod.period_end }}</p>
                    </div>
                    <button
                      v-if="selectedPeriod.status === 'approved' && canGenerateSalaryTransfers"
                      type="button"
                      :disabled="generatingWps"
                      @click="generateWps(selectedPeriod)"
                    >
                      {{ generatingWps ? 'Generating...' : 'Generate WPS export' }}
                    </button>
                  </header>

                  <section v-if="wpsErrors.length" class="wps-validation">
                    <strong>WPS export cannot be generated yet</strong>
                    <p>Correct the following employee details and try again:</p>
                    <ul>
                      <li v-for="message in wpsErrors" :key="message">{{ message }}</li>
                    </ul>
                  </section>

                  <table class="payslip-table">
                    <thead>
                      <tr>
                        <th>Employee</th>
                        <th>Gross</th>
                        <th>Deductions</th>
                        <th>Net</th>
                        <th>Status</th>
                        <th>Pay items</th>
                      </tr>
                      <tr class="column-filter-row">
                        <th><TableColumnFilter v-model="payslipColumnFilters.employee" label="Filter payslip employee" /></th>
                        <th><TableColumnFilter v-model="payslipColumnFilters.gross" label="Filter gross pay" /></th>
                        <th><TableColumnFilter v-model="payslipColumnFilters.deductions" label="Filter deductions" /></th>
                        <th><TableColumnFilter v-model="payslipColumnFilters.net" label="Filter net pay" /></th>
                        <th><TableColumnFilter v-model="payslipColumnFilters.status" label="Filter payslip status" /></th>
                        <th><TableColumnFilter v-model="payslipColumnFilters.items" label="Filter pay items" /></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="payslip in filteredPayslips" :key="payslip.id">
                        <td>{{ payslip.employee?.display_name || '-' }}</td>
                        <td>{{ payslip.gross_pay }}</td>
                        <td>{{ payslip.total_deductions }}</td>
                        <td>{{ payslip.net_pay }}</td>
                        <td>{{ label(payslip.status) }}</td>
                        <td>
                          <ul class="payslip-items">
                            <li v-for="item in payslip.items || []" :key="item.id">
                              <span>{{ item.label }}</span>
                              <strong>{{ item.type === 'deduction' ? '-' : '+' }}{{ item.amount }}</strong>
                            </li>
                          </ul>
                        </td>
                      </tr>
                      <tr v-if="filteredPayslips.length === 0">
                        <td colspan="6">Run payroll to generate payslips.</td>
                      </tr>
                    </tbody>
                  </table>
                </section>
              </td>
            </tr>
          </template>
          <tr v-if="filteredPeriods.length === 0">
            <td colspan="5">No payroll periods found.</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="canViewSalaryTransfers">
      <div class="section-heading">
        <div>
          <h2>WPS Exports</h2>
          <p class="muted">Generated payroll files for UAE Wage Protection System submission tracking.</p>
        </div>
        <NuxtLink class="secondary-link" to="/payroll/wps">Open WPS operations</NuxtLink>
      </div>
      <table>
        <thead>
          <tr>
            <th>Batch</th>
            <th>Salary month</th>
            <th>Records</th>
            <th>Total</th>
            <th>Status</th>
            <th></th>
          </tr>
          <tr class="column-filter-row">
            <th><TableColumnFilter v-model="wpsColumnFilters.batch" label="Filter WPS batch" /></th>
            <th><TableColumnFilter v-model="wpsColumnFilters.month" label="Filter salary month" /></th>
            <th><TableColumnFilter v-model="wpsColumnFilters.records" label="Filter WPS record count" /></th>
            <th><TableColumnFilter v-model="wpsColumnFilters.total" label="Filter WPS total" /></th>
            <th><TableColumnFilter v-model="wpsColumnFilters.status" label="Filter WPS status" /></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="batch in filteredWpsBatches" :key="batch.id">
            <td>{{ batch.batch_number }}</td>
            <td>{{ batch.salary_month }}</td>
            <td>{{ batch.record_count }}</td>
            <td>{{ batch.total_amount }}</td>
            <td>{{ label(batch.status) }}</td>
            <td class="table-actions">
              <a class="secondary-link" :href="downloadWpsUrl(batch)" target="_blank">Download</a>
              <button v-if="batch.status === 'generated'" type="button" class="secondary" @click="updateWpsStatus(batch, 'submitted')">Mark submitted</button>
              <button v-if="batch.status === 'submitted'" type="button" class="secondary" @click="updateWpsStatus(batch, 'processing')">Mark processing</button>
              <button v-if="['submitted', 'processing'].includes(batch.status)" type="button" class="secondary" @click="updateWpsStatus(batch, 'accepted')">Mark accepted</button>
              <button v-if="['submitted', 'processing'].includes(batch.status)" type="button" class="secondary" @click="updateWpsStatus(batch, 'partially_accepted')">Mark partial</button>
              <button v-if="['submitted', 'processing'].includes(batch.status)" type="button" class="secondary" @click="updateWpsStatus(batch, 'rejected')">Mark rejected</button>
            </td>
          </tr>
          <tr v-if="filteredWpsBatches.length === 0">
            <td colspan="6">No WPS exports generated yet.</td>
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

interface SalaryComponent {
  id: number
  code: string
  name: string
  type: string
  status: string
}

interface PayrollPeriod {
  id: number
  period_start: string
  period_end: string
  pay_date: string | null
  payroll_due_date: string | null
  wps_status: string
  status: string
  payslips_count?: number
}

interface WpsProvider {
  id: number
  name: string
}

interface WpsSetting {
  mohre_establishment_id: number
  establishment?: { establishment_name: string } | null
}

interface WpsPayrollBatch {
  id: number
  batch_number: string
  payroll_period_id: number
  salary_month: string
  record_count: number
  total_amount: string
  status: string
  provider: string
  bank_reference: string | null
}

interface Payslip {
  id: number
  gross_pay: string
  total_deductions: string
  net_pay: string
  status: string
  employee?: EmployeeOption | null
  items?: PayslipItem[]
}

interface PayslipItem {
  id: number
  label: string
  type: string
  amount: string
}

const api = useApiClient()
const auth = useAuthStore()
const employees = ref<EmployeeOption[]>([])
const components = ref<SalaryComponent[]>([])
const periods = ref<PayrollPeriod[]>([])
const payslips = ref<Payslip[]>([])
const wpsBatches = ref<WpsPayrollBatch[]>([])
const wpsSettings = ref<WpsSetting[]>([])
const wpsProviders = ref<WpsProvider[]>([])
const canViewWpsSettings = computed(() => auth.hasPermission('wps_settings.view'))
const canViewSalaryTransfers = computed(() => auth.hasPermission('salary_transfers.view'))
const canGenerateSalaryTransfers = computed(() => auth.hasPermission('salary_transfers.generate'))
const { filters: periodColumnFilters, filteredRows: filteredPeriods } = useTableColumnFilters(
  periods,
  [
    { key: 'period', value: period => `${period.period_start} to ${period.period_end}` },
    { key: 'payDate', value: period => period.pay_date },
    { key: 'status', value: period => label(period.status) },
    { key: 'payslips', value: period => period.payslips_count ?? 0 },
  ],
)
const { filters: payslipColumnFilters, filteredRows: filteredPayslips } = useTableColumnFilters(
  payslips,
  [
    { key: 'employee', value: payslip => payslip.employee?.display_name },
    { key: 'gross', value: payslip => payslip.gross_pay },
    { key: 'deductions', value: payslip => payslip.total_deductions },
    { key: 'net', value: payslip => payslip.net_pay },
    { key: 'status', value: payslip => label(payslip.status) },
    { key: 'items', value: payslip => (payslip.items || []).map(item => `${item.label} ${item.amount}`).join(' ') },
  ],
)
const { filters: wpsColumnFilters, filteredRows: filteredWpsBatches } = useTableColumnFilters(
  wpsBatches,
  [
    { key: 'batch', value: batch => batch.batch_number },
    { key: 'month', value: batch => batch.salary_month },
    { key: 'records', value: batch => batch.record_count },
    { key: 'total', value: batch => batch.total_amount },
    { key: 'status', value: batch => label(batch.status) },
  ],
)
const selectedPeriod = ref<PayrollPeriod | null>(null)
const loading = ref(true)
const loadError = ref('')
const componentError = ref('')
const assignmentError = ref('')
const periodError = ref('')
const wpsErrors = ref<string[]>([])
const savingComponent = ref(false)
const savingAssignment = ref(false)
const savingPeriod = ref(false)
const generatingWps = ref(false)
const config = useRuntimeConfig()
const today = new Date().toISOString().slice(0, 10)
const componentForm = reactive({
  code: '',
  name: '',
  type: 'earning',
  is_taxable: false,
  is_recurring: true,
  status: 'active',
})
const assignmentForm = reactive({
  employee_id: 0,
  salary_component_id: 0,
  amount: 0,
  effective_from: today,
  effective_to: null as string | null,
  status: 'active',
})
const periodForm = reactive({
  mohre_establishment_id: 0,
  wps_provider_id: 0,
  period_start: today.slice(0, 8) + '01',
  period_end: today,
  pay_date: '',
  payroll_due_date: '',
})

onMounted(async () => {
  await loadAll()
})

async function loadAll() {
  loading.value = true
  loadError.value = ''

  try {
    const [employeeResponse, componentResponse, periodResponse] = await Promise.all([
      api.get<{ employees: EmployeeOption[] }>('/employees'),
      api.get<{ salary_components: SalaryComponent[] }>('/salary-components'),
      api.get<{ payroll_periods: PayrollPeriod[] }>('/payroll-periods'),
    ])
    employees.value = employeeResponse.data.employees
    components.value = componentResponse.data.salary_components
    periods.value = periodResponse.data.payroll_periods

    if (canViewWpsSettings.value) {
      try {
        const wpsResponse = await api.get<{ wps_settings: WpsSetting[], wps_providers: WpsProvider[] }>('/wps-settings')
        wpsSettings.value = wpsResponse.data.wps_settings
        wpsProviders.value = wpsResponse.data.wps_providers
      } catch (err) {
        wpsErrors.value = apiErrorMessages(err, 'Unable to load WPS settings.')
      }
    }

    if (canViewSalaryTransfers.value) {
      try {
        await loadWpsBatches()
      } catch (err) {
        wpsErrors.value = apiErrorMessages(err, 'Unable to load WPS exports.')
      }
    }
  } catch {
    loadError.value = 'Unable to load payroll data.'
  } finally {
    loading.value = false
  }
}

async function createComponent() {
  savingComponent.value = true
  componentError.value = ''

  try {
    await api.post('/salary-components', componentForm)
    componentForm.code = ''
    componentForm.name = ''
    componentForm.type = 'earning'
    await loadAll()
  } catch (err) {
    componentError.value = apiErrorMessage(err, 'Unable to create allowance or deduction.')
  } finally {
    savingComponent.value = false
  }
}

async function assignComponent() {
  savingAssignment.value = true
  assignmentError.value = ''

  try {
    await api.post('/employee-salary-components', assignmentForm)
    assignmentForm.employee_id = 0
    assignmentForm.salary_component_id = 0
    assignmentForm.amount = 0
  } catch (err) {
    assignmentError.value = apiErrorMessage(err, 'Unable to assign pay item.')
  } finally {
    savingAssignment.value = false
  }
}

async function createPeriod() {
  savingPeriod.value = true
  periodError.value = ''

  try {
    await api.post('/payroll-periods', {
      ...periodForm,
      mohre_establishment_id: periodForm.mohre_establishment_id || null,
      wps_provider_id: periodForm.wps_provider_id || null,
      pay_date: periodForm.pay_date || null,
      payroll_due_date: periodForm.payroll_due_date || null,
    })
    await loadAll()
  } catch (err) {
    periodError.value = apiErrorMessage(err, 'Unable to create payroll period.')
  } finally {
    savingPeriod.value = false
  }
}

async function openPeriod(period: PayrollPeriod) {
  const response = await api.get<{ payroll_period: PayrollPeriod, payslips: Payslip[] }>(`/payroll-periods/${period.id}`)
  selectedPeriod.value = response.data.payroll_period
  payslips.value = response.data.payslips
  wpsErrors.value = []

  if (canViewSalaryTransfers.value) {
    await loadWpsBatches(period.id)
  }
}

async function togglePeriod(period: PayrollPeriod) {
  if (selectedPeriod.value?.id === period.id) {
    selectedPeriod.value = null
    payslips.value = []
    wpsErrors.value = []

    return
  }

  await openPeriod(period)
}

async function runPeriod(period: PayrollPeriod) {
  await api.post(`/payroll-periods/${period.id}/run`, {})
  await loadAll()
  await openPeriod(period)
}

async function approvePeriod(period: PayrollPeriod) {
  await api.post(`/payroll-periods/${period.id}/approve`, {})
  await loadAll()
  await openPeriod(period)
}

async function loadWpsBatches(periodId?: number) {
  const query = periodId ? `?payroll_period_id=${periodId}` : ''
  const response = await api.get<{ wps_payroll_batches: WpsPayrollBatch[] }>(`/wps-payroll-batches${query}`)
  wpsBatches.value = response.data.wps_payroll_batches
}

async function generateWps(period: PayrollPeriod) {
  generatingWps.value = true
  wpsErrors.value = []

  try {
    // Feature flow step 4: payroll users generate WPS only from the selected approved payroll period.
    await api.post(`/payroll-periods/${period.id}/wps-export`, {})
    await loadWpsBatches(period.id)
  } catch (err) {
    wpsErrors.value = apiErrorMessages(err, 'Unable to generate WPS export.')
  } finally {
    generatingWps.value = false
  }
}

async function updateWpsStatus(batch: WpsPayrollBatch, status: string) {
  const rejection_reason = status === 'rejected' ? 'Rejected by WPS processor. Review external submission response.' : null
  const bank_reference = status === 'submitted'
    ? window.prompt('Bank submission reference (optional):') || null
    : batch.bank_reference

  await api.post(`/wps-payroll-batches/${batch.id}/status`, { status, rejection_reason, bank_reference })
  await loadWpsBatches(selectedPeriod.value?.id)
}

function downloadWpsUrl(batch: WpsPayrollBatch) {
  return `${config.public.apiBaseUrl}/wps-payroll-batches/${batch.id}/download`
}

function label(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function apiErrorMessages(error: unknown, fallback: string) {
  if (error && typeof error === 'object' && 'errors' in error) {
    const errors = (error as { errors?: Record<string, string[]> }).errors
    const messages = errors ? Object.values(errors).flat() : []

    if (messages.length) {
      return messages
    }
  }

  return [apiErrorMessage(error, fallback)]
}
</script>

<style scoped>
.muted {
  margin: 6px 0 0;
  color: #5d6a72;
}

.panel-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(220px, 1fr));
  gap: 20px;
}

.payslip-items {
  display: grid;
  gap: 4px;
  min-width: 220px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.payslip-items li {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  color: #41505a;
}

.section-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.period-row-active td {
  padding-top: 24px;
  padding-bottom: 24px;
  border-bottom-color: transparent;
  background: #f5f9f7;
}

.period-detail-row > td {
  padding: 0 18px 24px;
  background: #f5f9f7;
}

.period-detail {
  border: 1px solid #cbdad3;
  border-radius: 10px;
  background: #ffffff;
  padding: 20px;
}

.period-detail-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.period-detail-heading h3 {
  margin: 0;
  font-size: 1.35rem;
}

.payslip-table {
  margin: 0;
}

.secondary-link {
  color: #176b54;
  font-weight: 700;
  text-decoration: none;
}

.wps-validation {
  margin: 10px 0;
  border: 1px solid #e0a7ac;
  border-radius: 8px;
  background: #fff5f5;
  padding: 14px 16px;
  color: #a52631;
}

.wps-validation p {
  margin: 4px 0 8px;
}

.wps-validation ul {
  margin: 0;
  padding-left: 20px;
}

.form-grid h2,
section h2 {
  margin: 0;
}

@media (max-width: 980px) {
  .panel-grid {
    grid-template-columns: 1fr;
  }
}
</style>
