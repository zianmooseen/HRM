<template>
  <section class="page">
    <header>
      <div>
        <h1>Leave</h1>
        <p class="muted">Submit requests, review approvals, and monitor employee leave balances.</p>
      </div>
    </header>

    <form v-if="auth.hasPermission('leave.create')" class="form-grid" @submit.prevent="submit">
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
        Start date
        <input v-model="form.start_date" type="date" required>
      </label>
      <label>
        End date
        <input v-model="form.end_date" type="date" required>
      </label>
      <label class="full">
        Reason
        <textarea v-model="form.reason" rows="3" />
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <button type="submit" :disabled="saving">{{ saving ? 'Submitting...' : 'Submit leave request' }}</button>
    </form>

    <section>
      <h2>Requests</h2>
      <section class="filters">
        <label>
          Employee
          <select v-model.number="filters.employee_id" @change="loadRequests">
            <option :value="0">All employees</option>
            <option v-for="employee in employees" :key="employee.id" :value="employee.id">
              {{ employee.display_name }}
            </option>
          </select>
        </label>
        <label>
          Status
          <select v-model="filters.status" @change="loadRequests">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </label>
      </section>

      <p v-if="loading">Loading leave requests...</p>
      <p v-else-if="loadError" class="error">{{ loadError }}</p>
      <table v-else>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Type</th>
            <th>Dates</th>
            <th>Days</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="request in requests" :key="request.id">
            <td>{{ request.employee?.display_name || '-' }}</td>
            <td>{{ request.leave_type?.name || '-' }}</td>
            <td>{{ request.start_date }} to {{ request.end_date }}</td>
            <td>{{ request.working_days }} working / {{ request.total_days }} total</td>
            <td>{{ label(request.status) }}</td>
            <td class="table-actions">
              <button
                v-if="request.status === 'pending' && auth.hasPermission('leave.approve')"
                type="button"
                class="secondary"
                @click="approve(request)"
              >
                Approve
              </button>
              <button
                v-if="request.status === 'pending' && auth.hasPermission('leave.reject')"
                type="button"
                class="danger"
                @click="reject(request)"
              >
                Reject
              </button>
            </td>
          </tr>
          <tr v-if="requests.length === 0">
            <td colspan="6">No leave requests found.</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section>
      <h2>Balances</h2>
      <p v-if="balances.length === 0" class="muted">Balances appear after leave requests are submitted.</p>
      <table v-else>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Type</th>
            <th>Year</th>
            <th>Used</th>
            <th>Pending</th>
            <th>Closing</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="balance in balances" :key="balance.id">
            <td>{{ balance.employee?.display_name || '-' }}</td>
            <td>{{ balance.leave_type?.name || '-' }}</td>
            <td>{{ balance.leave_year }}</td>
            <td>{{ balance.used_days }}</td>
            <td>{{ balance.pending_days }}</td>
            <td>{{ balance.closing_balance }}</td>
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
  code: string
  requires_document: boolean
}

interface LeaveRequestRow {
  id: number
  employee_id: number
  leave_type_id: number
  start_date: string
  end_date: string
  total_days: string
  working_days: string
  status: string
  reason: string | null
  employee?: EmployeeOption | null
  leave_type?: LeaveType | null
}

interface LeaveBalance {
  id: number
  employee_id: number
  leave_type_id: number
  leave_year: number
  used_days: string
  pending_days: string
  closing_balance: string
  employee?: EmployeeOption | null
  leave_type?: LeaveType | null
}

const auth = useAuthStore()
const api = useApiClient()
const employees = ref<EmployeeOption[]>([])
const leaveTypes = ref<LeaveType[]>([])
const requests = ref<LeaveRequestRow[]>([])
const balances = ref<LeaveBalance[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const loadError = ref('')
const form = reactive({
  employee_id: 0,
  leave_type_id: 0,
  start_date: new Date().toISOString().slice(0, 10),
  end_date: new Date().toISOString().slice(0, 10),
  reason: '',
})
const filters = reactive({
  employee_id: 0,
  status: '',
})

onMounted(async () => {
  await Promise.all([loadSetup(), loadRequests(), loadBalances()])
})

async function loadSetup() {
  const [employeeResponse, leaveTypeResponse] = await Promise.all([
    api.get<{ employees: EmployeeOption[] }>('/employees'),
    api.get<{ leave_types: LeaveType[] }>('/leave-types'),
  ])
  employees.value = employeeResponse.data.employees
  leaveTypes.value = leaveTypeResponse.data.leave_types
}

async function loadRequests() {
  loading.value = true
  loadError.value = ''

  const query = new URLSearchParams()
  if (filters.employee_id) query.set('employee_id', String(filters.employee_id))
  if (filters.status) query.set('status', filters.status)

  try {
    const suffix = query.toString() ? `?${query.toString()}` : ''
    const response = await api.get<{ leave_requests: LeaveRequestRow[] }>(`/leave-requests${suffix}`)
    requests.value = response.data.leave_requests
  } catch {
    loadError.value = 'Unable to load leave requests.'
  } finally {
    loading.value = false
  }
}

async function loadBalances() {
  const response = await api.get<{ leave_balances: LeaveBalance[] }>('/leave-balances')
  balances.value = response.data.leave_balances
}

async function submit() {
  saving.value = true
  error.value = ''

  try {
    await api.post('/leave-requests', {
      ...form,
      reason: form.reason || null,
    })
    resetForm()
    await Promise.all([loadRequests(), loadBalances()])
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to submit leave request.')
  } finally {
    saving.value = false
  }
}

async function approve(request: LeaveRequestRow) {
  await api.post(`/leave-requests/${request.id}/approve`, {})
  await Promise.all([loadRequests(), loadBalances()])
}

async function reject(request: LeaveRequestRow) {
  const reason = window.prompt('Rejection reason')
  if (!reason) {
    return
  }

  await api.post(`/leave-requests/${request.id}/reject`, { rejection_reason: reason })
  await Promise.all([loadRequests(), loadBalances()])
}

function resetForm() {
  form.employee_id = 0
  form.leave_type_id = 0
  form.start_date = new Date().toISOString().slice(0, 10)
  form.end_date = new Date().toISOString().slice(0, 10)
  form.reason = ''
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
