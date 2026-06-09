<template>
  <section class="page">
    <header>
      <div>
        <h1>Leave Policies</h1>
        <p class="muted">Configure employee annual entitlement, accrual, carry-forward, and adjustments.</p>
      </div>
    </header>

    <form v-if="auth.hasPermission('settings.update')" class="form-grid" @submit.prevent="save">
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
        Leave type
        <select v-model.number="form.leave_type_id" required>
          <option disabled :value="0">Select leave type</option>
          <option v-for="leaveType in leaveTypes" :key="leaveType.id" :value="leaveType.id">
            {{ leaveType.name }}
          </option>
        </select>
      </label>
      <label>
        Leave year
        <input v-model.number="form.leave_year" type="number" min="2020" max="2100" required>
      </label>
      <label>
        Opening balance
        <input v-model.number="form.opening_balance" type="number" min="0" step="0.5" required>
      </label>
      <label>
        Accrued days
        <input v-model.number="form.accrued_days" type="number" min="0" step="0.5" required>
      </label>
      <label>
        Carry-forward days
        <input v-model.number="form.carried_forward_days" type="number" min="0" step="0.5" required>
      </label>
      <label>
        Adjustment days
        <input v-model.number="form.adjusted_days" type="number" min="0" step="0.5" required>
      </label>
      <label>
        Encashed days
        <input v-model.number="form.encashed_days" type="number" min="0" step="0.5">
      </label>
      <label class="full">
        Note
        <textarea v-model="form.note" rows="3" />
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : 'Save leave balance' }}</button>
    </form>

    <section v-if="auth.hasPermission('settings.update')">
      <h2>Annual Accrual</h2>
      <form class="form-grid" @submit.prevent="runAccrual">
        <label>
          Employee
          <select v-model.number="accrual.employee_id">
            <option :value="0">All active employees</option>
            <option v-for="employee in employees" :key="employee.id" :value="employee.id">
              {{ employee.display_name }}
            </option>
          </select>
        </label>
        <label>
          Leave year
          <input v-model.number="accrual.leave_year" type="number" min="2020" max="2100" required>
        </label>
        <label>
          Accrual date
          <input v-model="accrual.accrual_date" type="date">
        </label>
        <p v-if="accrualError" class="error">{{ accrualError }}</p>
        <p v-if="accrualResult" class="muted">
          Accrued {{ accrualResult.processed_count }} employee balance records as of {{ accrualResult.accrual_date }}.
        </p>
        <button type="submit" :disabled="accruing">{{ accruing ? 'Running...' : 'Run annual accrual' }}</button>
      </form>
    </section>

    <section>
      <h2>Configured Balances</h2>
      <section class="filters">
        <label>
          Employee
          <select v-model.number="filters.employee_id" @change="loadBalances">
            <option :value="0">All employees</option>
            <option v-for="employee in employees" :key="employee.id" :value="employee.id">
              {{ employee.display_name }}
            </option>
          </select>
        </label>
        <label>
          Year
          <input v-model.number="filters.leave_year" type="number" min="2020" max="2100" @change="loadBalances">
        </label>
      </section>

      <p v-if="loading">Loading leave balances...</p>
      <p v-else-if="loadError" class="error">{{ loadError }}</p>
      <table v-else>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Type</th>
            <th>Year</th>
            <th>Opening</th>
            <th>Accrued</th>
            <th>Carry-forward</th>
            <th>Used</th>
            <th>Pending</th>
            <th>Closing</th>
            <th></th>
          </tr>
          <tr class="column-filter-row">
            <th><TableColumnFilter v-model="columnFilters.employee" label="Filter balance employee" /></th>
            <th><TableColumnFilter v-model="columnFilters.type" label="Filter balance leave type" /></th>
            <th><TableColumnFilter v-model="columnFilters.year" label="Filter balance year" /></th>
            <th><TableColumnFilter v-model="columnFilters.opening" label="Filter opening balance" /></th>
            <th><TableColumnFilter v-model="columnFilters.accrued" label="Filter accrued balance" /></th>
            <th><TableColumnFilter v-model="columnFilters.carry" label="Filter carry-forward balance" /></th>
            <th><TableColumnFilter v-model="columnFilters.used" label="Filter used balance" /></th>
            <th><TableColumnFilter v-model="columnFilters.pending" label="Filter pending balance" /></th>
            <th><TableColumnFilter v-model="columnFilters.closing" label="Filter closing balance" /></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="balance in filteredBalances" :key="balance.id">
            <td>{{ balance.employee?.display_name || '-' }}</td>
            <td>{{ balance.leave_type?.name || '-' }}</td>
            <td>{{ balance.leave_year }}</td>
            <td>{{ balance.opening_balance }}</td>
            <td>{{ balance.accrued_days }}</td>
            <td>{{ balance.carried_forward_days }}</td>
            <td>{{ balance.used_days }}</td>
            <td>{{ balance.pending_days }}</td>
            <td>{{ balance.closing_balance }}</td>
            <td>
              <button type="button" class="secondary" @click="edit(balance)">Edit</button>
            </td>
          </tr>
          <tr v-if="filteredBalances.length === 0">
            <td colspan="10">No leave balances configured yet.</td>
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

interface LeaveType {
  id: number
  name: string
}

interface LeaveBalance {
  id: number
  employee_id: number
  leave_type_id: number
  leave_year: number
  opening_balance: string
  accrued_days: string
  used_days: string
  pending_days: string
  carried_forward_days: string
  encashed_days: string
  adjusted_days: string
  closing_balance: string
  employee?: EmployeeOption | null
  leave_type?: LeaveType | null
}

const auth = useAuthStore()
const api = useApiClient()
const employees = ref<EmployeeOption[]>([])
const leaveTypes = ref<LeaveType[]>([])
const balances = ref<LeaveBalance[]>([])
const { filters: columnFilters, filteredRows: filteredBalances } = useTableColumnFilters(
  balances,
  [
    { key: 'employee', value: balance => balance.employee?.display_name },
    { key: 'type', value: balance => balance.leave_type?.name },
    { key: 'year', value: balance => balance.leave_year },
    { key: 'opening', value: balance => balance.opening_balance },
    { key: 'accrued', value: balance => balance.accrued_days },
    { key: 'carry', value: balance => balance.carried_forward_days },
    { key: 'used', value: balance => balance.used_days },
    { key: 'pending', value: balance => balance.pending_days },
    { key: 'closing', value: balance => balance.closing_balance },
  ],
)
const saving = ref(false)
const accruing = ref(false)
const loading = ref(true)
const error = ref('')
const accrualError = ref('')
const loadError = ref('')
const accrualResult = ref<{ processed_count: number, accrual_date: string } | null>(null)
const currentYear = new Date().getFullYear()
const form = reactive({
  employee_id: 0,
  leave_type_id: 0,
  leave_year: currentYear,
  opening_balance: 0,
  accrued_days: 30,
  carried_forward_days: 0,
  adjusted_days: 0,
  encashed_days: 0,
  note: '',
})
const filters = reactive({
  employee_id: 0,
  leave_year: currentYear,
})
const accrual = reactive({
  employee_id: 0,
  leave_year: currentYear,
  accrual_date: '',
})

onMounted(async () => {
  await Promise.all([loadSetup(), loadBalances()])
})

async function loadSetup() {
  const [employeeResponse, leaveTypeResponse] = await Promise.all([
    api.get<{ employees: EmployeeOption[] }>('/employees'),
    api.get<{ leave_types: LeaveType[] }>('/leave-types'),
  ])
  employees.value = employeeResponse.data.employees
  leaveTypes.value = leaveTypeResponse.data.leave_types
}

async function loadBalances() {
  loading.value = true
  loadError.value = ''

  const query = new URLSearchParams()
  if (filters.employee_id) query.set('employee_id', String(filters.employee_id))
  if (filters.leave_year) query.set('leave_year', String(filters.leave_year))

  try {
    const suffix = query.toString() ? `?${query.toString()}` : ''
    const response = await api.get<{ leave_balances: LeaveBalance[] }>(`/leave-balances${suffix}`)
    balances.value = response.data.leave_balances
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Unable to load leave balances.')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = ''

  try {
    await api.post('/leave-balances', {
      ...form,
      note: form.note || null,
    })
    resetForm()
    await loadBalances()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to save leave balance.')
  } finally {
    saving.value = false
  }
}

async function runAccrual() {
  accruing.value = true
  accrualError.value = ''
  accrualResult.value = null

  try {
    const response = await api.post<{ processed_count: number, accrual_date: string }>('/leave-balances/accrue-annual', {
      leave_year: accrual.leave_year,
      employee_id: accrual.employee_id || null,
      accrual_date: accrual.accrual_date || null,
    })
    accrualResult.value = response.data
    filters.leave_year = accrual.leave_year
    if (accrual.employee_id) filters.employee_id = accrual.employee_id
    await loadBalances()
  } catch (err) {
    accrualError.value = apiErrorMessage(err, 'Unable to run annual leave accrual.')
  } finally {
    accruing.value = false
  }
}

function edit(balance: LeaveBalance) {
  form.employee_id = balance.employee_id
  form.leave_type_id = balance.leave_type_id
  form.leave_year = balance.leave_year
  form.opening_balance = Number(balance.opening_balance)
  form.accrued_days = Number(balance.accrued_days)
  form.carried_forward_days = Number(balance.carried_forward_days)
  form.adjusted_days = Number(balance.adjusted_days)
  form.encashed_days = Number(balance.encashed_days)
  form.note = ''
}

function resetForm() {
  form.employee_id = 0
  form.leave_type_id = 0
  form.leave_year = currentYear
  form.opening_balance = 0
  form.accrued_days = 30
  form.carried_forward_days = 0
  form.adjusted_days = 0
  form.encashed_days = 0
  form.note = ''
}
</script>

<style scoped>
.muted {
  margin: 6px 0 0;
  color: #5d6a72;
}

.full {
  grid-column: 1 / -1;
}

.filters {
  display: grid;
  grid-template-columns: repeat(2, minmax(160px, 1fr));
  gap: 12px;
  max-width: 520px;
  margin-bottom: 16px;
}

.filters label {
  display: grid;
  gap: 6px;
}

.filters input,
.filters select {
  min-height: 40px;
  border: 1px solid #b8c1c8;
  border-radius: 6px;
  padding: 8px 10px;
}

section h2 {
  margin: 0 0 12px;
}

@media (max-width: 760px) {
  .filters {
    grid-template-columns: 1fr;
  }
}
</style>
