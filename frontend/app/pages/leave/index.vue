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
      <section v-if="dayCalculation" class="day-preview">
        <strong>{{ dayCalculation.working_days }} balance days</strong>
        <span>{{ dayCalculation.total_days }} calendar days</span>
        <span v-if="dayCalculation.public_holidays_count > 0">
          {{ dayCalculation.public_holidays_count }} public holiday{{ dayCalculation.public_holidays_count === 1 ? '' : 's' }} excluded
        </span>
      </section>
      <p v-if="dayCalculationError" class="error full">{{ dayCalculationError }}</p>
      <ul v-if="excludedPublicHolidays.length > 0" class="holiday-list full">
        <li v-for="holiday in excludedPublicHolidays" :key="holiday.date">
          {{ holiday.date }} · {{ holiday.name }}
        </li>
      </ul>
      <label v-if="selectedLeaveType?.code === 'sick_leave' || selectedLeaveType?.requires_document">
        Medical certificate
        <select v-model="form.medical_certificate_document_id">
          <option value="">Select uploaded certificate</option>
          <option v-for="document in medicalCertificates" :key="document.id" :value="String(document.id)">
            {{ document.title }} · {{ document.original_file_name }}
          </option>
        </select>
      </label>
      <label class="full">
        Reason
        <textarea v-model="form.reason" rows="3" />
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <button type="submit" :disabled="saving">{{ saving ? 'Submitting...' : 'Submit leave request' }}</button>
    </form>

    <section>
      <h2>Pending approvals</h2>
      <p v-if="pendingRequests.length === 0" class="muted">No leave requests are waiting for approval.</p>
      <table v-else>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Type</th>
            <th>Dates</th>
            <th>Requested</th>
            <th>Available before approval</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="request in pendingRequests" :key="`pending-${request.id}`">
            <td>{{ request.employee?.display_name || '-' }}</td>
            <td>{{ request.leave_type?.name || '-' }}</td>
            <td>{{ request.start_date }} to {{ request.end_date }}</td>
            <td>{{ request.working_days }} days</td>
            <td>{{ availableBeforeApproval(request) }}</td>
            <td class="table-actions">
              <button
                v-if="auth.hasPermission('leave.approve')"
                type="button"
                class="secondary"
                @click="approve(request)"
              >
                Approve
              </button>
              <button
                v-if="auth.hasPermission('leave.reject')"
                type="button"
                class="danger"
                @click="reject(request)"
              >
                Reject
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

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

      <p v-if="actionError" class="error">{{ actionError }}</p>
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
            <th>Latest note</th>
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
            <td>{{ latestNote(request) }}</td>
            <td class="table-actions">
              <button
                v-if="request.leave_type?.code === 'sick_leave'"
                type="button"
                class="secondary"
                @click="previewSickPay(request)"
              >
                Preview pay
              </button>
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
            <td colspan="7">No leave requests found.</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="sickPayPreview" class="pay-preview">
      <header>
        <div>
          <h2>Sick Pay Preview</h2>
          <p class="muted">
            Request #{{ sickPayPreview.requestId }} · {{ sickPayPreview.calculation.eligible ? 'Eligible' : sickPayPreview.calculation.reason }}
          </p>
        </div>
      </header>

      <table>
        <thead>
          <tr>
            <th>Tier</th>
            <th>Days</th>
            <th>Pay %</th>
            <th>Daily wage</th>
            <th>Gross pay</th>
            <th>Deduction</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in sickPayPreview.calculation.items" :key="`${sickPayPreview.requestId}-${item.pay_tier}`">
            <td>{{ label(item.pay_tier) }}</td>
            <td>{{ item.days }}</td>
            <td>{{ item.pay_percentage }}</td>
            <td>{{ money(item.daily_wage) }}</td>
            <td>{{ money(item.gross_pay_amount) }}</td>
            <td>{{ money(item.deduction_amount) }}</td>
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
  public_holidays_count: string
  day_calculation: DayCalculationSnapshot | null
  status: string
  reason: string | null
  approval_note: string | null
  rejection_reason: string | null
  medical_certificate_document_id: number | null
  employee?: EmployeeOption | null
  leave_type?: LeaveType | null
  pay_calculation_items?: LeavePayCalculationItem[]
  status_events?: LeaveStatusEvent[]
}

interface LeaveStatusEvent {
  id: number
  status: string
  actor_user_id: number | null
  note: string | null
  created_at: string
}

interface LeavePayCalculationItem {
  pay_tier: string
  days: string | number
  pay_percentage: string | number
  daily_wage: string | number
  gross_pay_amount: string | number
  deduction_amount: string | number
  calculation_basis?: string
}

interface DayCalculation {
  total_days: number
  working_days: number
  public_holidays_count: number
  day_calculation_json: DayCalculationSnapshot
}

interface DayCalculationSnapshot {
  weekend_days_excluded: boolean
  public_holidays_count_as_annual_leave: boolean | null
  excluded_public_holidays: Array<{ date: string, name: string }>
}

interface SickPayPreview {
  requestId: number
  calculation: {
    eligible: boolean
    reason: string
    previously_used_days: number
    items: LeavePayCalculationItem[]
  }
  stored_items: LeavePayCalculationItem[]
}

interface EmployeeDocument {
  id: number
  employee_id: number
  document_type: string
  title: string
  original_file_name: string
  expiry_date: string | null
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
const requests = ref<LeaveRequestRow[]>([])
const balances = ref<LeaveBalance[]>([])
const medicalCertificates = ref<EmployeeDocument[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const loadError = ref('')
const actionError = ref('')
const dayCalculationError = ref('')
const sickPayPreview = ref<SickPayPreview | null>(null)
const dayCalculation = ref<DayCalculation | null>(null)
const form = reactive({
  employee_id: 0,
  leave_type_id: 0,
  start_date: new Date().toISOString().slice(0, 10),
  end_date: new Date().toISOString().slice(0, 10),
  medical_certificate_document_id: '',
  reason: '',
})
const filters = reactive({
  employee_id: 0,
  status: '',
})
const selectedLeaveType = computed(() => leaveTypes.value.find((leaveType) => leaveType.id === form.leave_type_id) || null)
const pendingRequests = computed(() => requests.value.filter((request) => request.status === 'pending'))
const excludedPublicHolidays = computed(() => dayCalculation.value?.day_calculation_json.excluded_public_holidays || [])

onMounted(async () => {
  await Promise.all([loadSetup(), loadRequests(), loadBalances()])
})

watch(
  () => [form.employee_id, form.leave_type_id],
  async () => {
    form.medical_certificate_document_id = ''
    if ((selectedLeaveType.value?.code === 'sick_leave' || selectedLeaveType.value?.requires_document) && form.employee_id) {
      await loadMedicalCertificates()
    } else {
      medicalCertificates.value = []
    }
  },
)

watch(
  () => [form.employee_id, form.leave_type_id, form.start_date, form.end_date],
  () => {
    void loadDayCalculation()
  },
)

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

async function loadMedicalCertificates() {
  const query = new URLSearchParams({
    employee_id: String(form.employee_id),
    document_type: 'medical_certificate',
  })
  const response = await api.get<{ documents: EmployeeDocument[] }>(`/documents?${query.toString()}`)
  medicalCertificates.value = response.data.documents
}

async function loadDayCalculation() {
  dayCalculationError.value = ''
  dayCalculation.value = null

  if (!form.employee_id || !form.leave_type_id || !form.start_date || !form.end_date || form.end_date < form.start_date) {
    return
  }

  const query = new URLSearchParams({
    employee_id: String(form.employee_id),
    leave_type_id: String(form.leave_type_id),
    start_date: form.start_date,
    end_date: form.end_date,
  })

  try {
    const response = await api.get<{ calculation: DayCalculation }>(`/leave-requests/day-count?${query.toString()}`)
    dayCalculation.value = response.data.calculation
  } catch (err) {
    dayCalculationError.value = apiErrorMessage(err, 'Unable to calculate leave days.')
  }
}

async function submit() {
  saving.value = true
  error.value = ''

  try {
    await api.post('/leave-requests', {
      ...form,
      medical_certificate_document_id: form.medical_certificate_document_id
        ? Number(form.medical_certificate_document_id)
        : null,
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
  const approvalNote = window.prompt('Approval note (optional)') || ''
  actionError.value = ''

  try {
    await api.post(`/leave-requests/${request.id}/approve`, { approval_note: approvalNote || null })
    await Promise.all([loadRequests(), loadBalances()])
    if (request.leave_type?.code === 'sick_leave') {
      await previewSickPay(request)
    }
  } catch (err) {
    actionError.value = apiErrorMessage(err, 'Unable to approve leave request.')
  }
}

async function reject(request: LeaveRequestRow) {
  const reason = window.prompt('Rejection reason')
  if (!reason) {
    return
  }

  actionError.value = ''

  try {
    await api.post(`/leave-requests/${request.id}/reject`, { rejection_reason: reason })
    await Promise.all([loadRequests(), loadBalances()])
  } catch (err) {
    actionError.value = apiErrorMessage(err, 'Unable to reject leave request.')
  }
}

async function previewSickPay(request: LeaveRequestRow) {
  actionError.value = ''

  try {
    const response = await api.get<Omit<SickPayPreview, 'requestId'>>(`/leave-requests/${request.id}/sick-pay`)
    sickPayPreview.value = { requestId: request.id, ...response.data }
  } catch (err) {
    actionError.value = apiErrorMessage(err, 'Unable to calculate sick leave pay.')
  }
}

function resetForm() {
  form.employee_id = 0
  form.leave_type_id = 0
  form.start_date = new Date().toISOString().slice(0, 10)
  form.end_date = new Date().toISOString().slice(0, 10)
  form.medical_certificate_document_id = ''
  form.reason = ''
  dayCalculation.value = null
  dayCalculationError.value = ''
}

function label(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function money(value: string | number) {
  return Number(value).toFixed(2)
}

function balanceFor(request: LeaveRequestRow) {
  const year = new Date(request.start_date).getFullYear()

  return balances.value.find((balance) => (
    balance.employee_id === request.employee_id
    && balance.leave_type_id === request.leave_type_id
    && balance.leave_year === year
  ))
}

function availableBeforeApproval(request: LeaveRequestRow) {
  const balance = balanceFor(request)

  if (!balance) return 'No balance configured'

  const requestedDays = Number(request.working_days)
  const pendingDays = Math.max(0, Number(balance.pending_days) - requestedDays)
  const entitlement = Number(balance.opening_balance)
    + Number(balance.accrued_days)
    + Number(balance.carried_forward_days)
    + Number(balance.adjusted_days)

  if (entitlement <= 0) return 'Not configured'

  return (entitlement - Number(balance.used_days) - pendingDays - Number(balance.encashed_days)).toFixed(2)
}

function latestNote(request: LeaveRequestRow) {
  const eventNote = request.status_events?.slice().reverse().find((event) => event.note)?.note

  return eventNote || request.approval_note || request.rejection_reason || '-'
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

.day-preview {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  min-height: 40px;
  color: #35444c;
}

.day-preview strong {
  color: #102027;
}

.holiday-list {
  margin: 0;
  padding-left: 18px;
  color: #5d6a72;
}

@media (max-width: 760px) {
  .filters {
    grid-template-columns: 1fr;
  }
}
</style>
