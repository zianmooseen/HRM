<template>
  <section class="page">
    <header>
      <div>
        <h1>Payroll</h1>
        <p class="muted">Configure recurring salary items, run periods, and review generated payslips.</p>
      </div>
    </header>

    <section class="panel-grid">
      <form class="form-grid" @submit.prevent="createComponent">
        <h2>Salary Component</h2>
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
            <option value="earning">Earning</option>
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
        <button type="submit" :disabled="savingComponent">{{ savingComponent ? 'Saving...' : 'Create component' }}</button>
      </form>

      <form class="form-grid" @submit.prevent="assignComponent">
        <h2>Employee Assignment</h2>
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
          Component
          <select v-model.number="assignmentForm.salary_component_id" required>
            <option disabled :value="0">Select component</option>
            <option v-for="component in components" :key="component.id" :value="component.id">
              {{ component.name }}
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
        <button type="submit" :disabled="savingAssignment">{{ savingAssignment ? 'Saving...' : 'Assign component' }}</button>
      </form>

      <form class="form-grid" @submit.prevent="createPeriod">
        <h2>Payroll Period</h2>
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
        </thead>
        <tbody>
          <tr v-for="period in periods" :key="period.id">
            <td>{{ period.period_start }} to {{ period.period_end }}</td>
            <td>{{ period.pay_date || '-' }}</td>
            <td>{{ label(period.status) }}</td>
            <td>{{ period.payslips_count ?? 0 }}</td>
            <td class="table-actions">
              <button type="button" class="secondary" @click="openPeriod(period)">Open</button>
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
          <tr v-if="periods.length === 0">
            <td colspan="5">No payroll periods found.</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="selectedPeriod">
      <h2>Payslips</h2>
      <p class="muted">{{ selectedPeriod.period_start }} to {{ selectedPeriod.period_end }}</p>
      <table>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Gross</th>
            <th>Deductions</th>
            <th>Net</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="payslip in payslips" :key="payslip.id">
            <td>{{ payslip.employee?.display_name || '-' }}</td>
            <td>{{ payslip.gross_pay }}</td>
            <td>{{ payslip.total_deductions }}</td>
            <td>{{ payslip.net_pay }}</td>
            <td>{{ label(payslip.status) }}</td>
          </tr>
          <tr v-if="payslips.length === 0">
            <td colspan="5">Run payroll to generate payslips.</td>
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
  status: string
  payslips_count?: number
}

interface Payslip {
  id: number
  gross_pay: string
  total_deductions: string
  net_pay: string
  status: string
  employee?: EmployeeOption | null
}

const api = useApiClient()
const employees = ref<EmployeeOption[]>([])
const components = ref<SalaryComponent[]>([])
const periods = ref<PayrollPeriod[]>([])
const payslips = ref<Payslip[]>([])
const selectedPeriod = ref<PayrollPeriod | null>(null)
const loading = ref(true)
const loadError = ref('')
const componentError = ref('')
const assignmentError = ref('')
const periodError = ref('')
const savingComponent = ref(false)
const savingAssignment = ref(false)
const savingPeriod = ref(false)
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
  period_start: today.slice(0, 8) + '01',
  period_end: today,
  pay_date: '',
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
    componentError.value = apiErrorMessage(err, 'Unable to create salary component.')
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
    assignmentError.value = apiErrorMessage(err, 'Unable to assign salary component.')
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
      pay_date: periodForm.pay_date || null,
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

function label(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
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
