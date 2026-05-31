<template>
  <section class="page">
    <header>
      <div>
        <h1>Attendance</h1>
        <p class="muted">Manual daily records for employees in the current company.</p>
      </div>
      <button type="button" class="secondary" @click="resetForm">New record</button>
    </header>

    <form class="form-grid" @submit.prevent="submit">
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
        Date
        <input v-model="form.date" type="date" required>
      </label>
      <label>
        Check in
        <input v-model="form.check_in" type="time">
      </label>
      <label>
        Check out
        <input v-model="form.check_out" type="time">
      </label>
      <label>
        Break minutes
        <input v-model.number="form.break_minutes" type="number" min="0">
      </label>
      <label>
        Status
        <select v-model="form.status" required>
          <option v-for="status in statuses" :key="status" :value="status">{{ label(status) }}</option>
        </select>
      </label>
      <label>
        Source
        <select v-model="form.source" required>
          <option value="manual">Manual</option>
          <option value="web">Web</option>
          <option value="mobile">Mobile</option>
          <option value="biometric">Biometric</option>
          <option value="import">Import</option>
        </select>
      </label>
      <label class="full">
        Notes
        <textarea v-model="form.notes" rows="3" />
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <div class="button-row">
        <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : submitLabel }}</button>
        <button v-if="editingId" type="button" class="secondary" @click="resetForm">Cancel edit</button>
      </div>
    </form>

    <section class="panel">
      <header>
        <div>
          <h2>Correction Request</h2>
          <p class="muted">Submit a missed punch or time correction for manager/HR approval.</p>
        </div>
      </header>
      <form class="form-grid" @submit.prevent="submitCorrection">
        <label>
          Employee
          <select v-model.number="correctionForm.employee_id" required>
            <option disabled :value="0">Select employee</option>
            <option v-for="employee in employees" :key="employee.id" :value="employee.id">
              {{ employee.display_name }} ({{ employee.employee_code }})
            </option>
          </select>
        </label>
        <label>
          Date
          <input v-model="correctionForm.date" type="date" required>
        </label>
        <label>
          Type
          <select v-model="correctionForm.correction_type" required>
            <option value="missed_check_in">Missed check in</option>
            <option value="missed_check_out">Missed check out</option>
            <option value="wrong_time">Wrong time</option>
            <option value="absence_status">Absence status</option>
            <option value="other">Other</option>
          </select>
        </label>
        <label>
          Requested check in
          <input v-model="correctionForm.requested_check_in" type="time">
        </label>
        <label>
          Requested check out
          <input v-model="correctionForm.requested_check_out" type="time">
        </label>
        <label>
          Break minutes
          <input v-model.number="correctionForm.requested_break_minutes" type="number" min="0">
        </label>
        <label>
          Requested status
          <select v-model="correctionForm.requested_status" required>
            <option v-for="status in statuses" :key="status" :value="status">{{ label(status) }}</option>
          </select>
        </label>
        <label class="full">
          Reason
          <textarea v-model="correctionForm.reason" rows="3" required />
        </label>
        <p v-if="correctionError" class="error">{{ correctionError }}</p>
        <p v-if="correctionSaved" class="muted">Correction request submitted.</p>
        <div class="button-row">
          <button type="submit" :disabled="savingCorrection">{{ savingCorrection ? 'Submitting...' : 'Submit correction' }}</button>
        </div>
      </form>
    </section>

    <section class="filters">
      <label>
        Filter employee
        <select v-model.number="filters.employee_id" @change="loadRecords">
          <option :value="0">All employees</option>
          <option v-for="employee in employees" :key="employee.id" :value="employee.id">
            {{ employee.display_name }}
          </option>
        </select>
      </label>
      <label>
        From
        <input v-model="filters.date_from" type="date" @change="loadRecords">
      </label>
      <label>
        To
        <input v-model="filters.date_to" type="date" @change="loadRecords">
      </label>
    </section>

    <p v-if="loading">Loading attendance...</p>
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <table v-else>
      <thead>
        <tr>
          <th>Date</th>
          <th>Employee</th>
          <th>Status</th>
          <th>Time</th>
          <th>Source</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="record in records" :key="record.id">
          <td>{{ record.date }}</td>
          <td>{{ record.employee?.display_name || '-' }}</td>
          <td>{{ label(record.status) }}</td>
          <td>{{ timeRange(record) }}</td>
          <td>{{ label(record.source) }}</td>
          <td class="table-actions">
            <button type="button" class="secondary" @click="edit(record)">Edit</button>
            <button type="button" class="danger" @click="remove(record)">Delete</button>
          </td>
        </tr>
        <tr v-if="records.length === 0">
          <td colspan="6">No attendance records found.</td>
        </tr>
      </tbody>
    </table>

    <section class="panel">
      <header>
        <div>
          <h2>Correction Approval Queue</h2>
          <p class="muted">Pending correction requests are applied to attendance only after approval.</p>
        </div>
        <button type="button" class="secondary" @click="loadCorrections">Refresh</button>
      </header>
      <p v-if="loadingCorrections">Loading correction requests...</p>
      <p v-else-if="correctionLoadError" class="error">{{ correctionLoadError }}</p>
      <table v-else>
        <thead>
          <tr>
            <th>Date</th>
            <th>Employee</th>
            <th>Type</th>
            <th>Requested</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="correction in corrections" :key="correction.id">
            <td>{{ correction.date }}</td>
            <td>{{ correction.employee?.display_name || '-' }}</td>
            <td>{{ label(correction.correction_type) }}</td>
            <td>{{ correctionSummary(correction) }}</td>
            <td>{{ label(correction.status) }}</td>
            <td class="table-actions">
              <button v-if="correction.status === 'pending'" type="button" class="secondary" @click="approveCorrection(correction)">Approve</button>
              <button v-if="correction.status === 'pending'" type="button" class="danger" @click="rejectCorrection(correction)">Reject</button>
            </td>
          </tr>
          <tr v-if="corrections.length === 0">
            <td colspan="6">No correction requests found.</td>
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

interface AttendanceRecord {
  id: number
  employee_id: number
  date: string
  check_in: string | null
  check_out: string | null
  break_minutes: number
  status: string
  source: string
  notes: string | null
  employee?: EmployeeOption | null
}

interface AttendanceCorrectionRequest {
  id: number
  employee_id: number
  date: string
  correction_type: string
  requested_check_in: string | null
  requested_check_out: string | null
  requested_break_minutes: number
  requested_status: string
  reason: string | null
  status: string
  employee?: EmployeeOption | null
}

const api = useApiClient()
const statuses = ['present', 'absent', 'late', 'half_day', 'on_leave', 'holiday', 'remote']
const employees = ref<EmployeeOption[]>([])
const records = ref<AttendanceRecord[]>([])
const corrections = ref<AttendanceCorrectionRequest[]>([])
const loading = ref(true)
const loadingCorrections = ref(true)
const saving = ref(false)
const savingCorrection = ref(false)
const error = ref('')
const loadError = ref('')
const correctionError = ref('')
const correctionLoadError = ref('')
const correctionSaved = ref(false)
const editingId = ref<number | null>(null)
const form = reactive({
  employee_id: 0,
  date: new Date().toISOString().slice(0, 10),
  check_in: '',
  check_out: '',
  break_minutes: 0,
  status: 'present',
  source: 'manual',
  notes: '',
})
const filters = reactive({
  employee_id: 0,
  date_from: '',
  date_to: '',
})
const correctionForm = reactive({
  employee_id: 0,
  date: new Date().toISOString().slice(0, 10),
  correction_type: 'wrong_time',
  requested_check_in: '',
  requested_check_out: '',
  requested_break_minutes: 0,
  requested_status: 'present',
  reason: '',
})
const submitLabel = computed(() => editingId.value ? 'Update attendance' : 'Create attendance')

onMounted(async () => {
  await Promise.all([loadEmployees(), loadRecords(), loadCorrections()])
})

async function loadEmployees() {
  const response = await api.get<{ employees: EmployeeOption[] }>('/employees')
  employees.value = response.data.employees
}

async function loadCorrections() {
  loadingCorrections.value = true
  correctionLoadError.value = ''

  try {
    const response = await api.get<{ attendance_correction_requests: AttendanceCorrectionRequest[] }>('/attendance-correction-requests')
    corrections.value = response.data.attendance_correction_requests
  } catch (err) {
    correctionLoadError.value = apiErrorMessage(err, 'Unable to load correction requests.')
  } finally {
    loadingCorrections.value = false
  }
}

async function loadRecords() {
  loading.value = true
  loadError.value = ''

  const query = new URLSearchParams()
  if (filters.employee_id) query.set('employee_id', String(filters.employee_id))
  if (filters.date_from) query.set('date_from', filters.date_from)
  if (filters.date_to) query.set('date_to', filters.date_to)

  try {
    const suffix = query.toString() ? `?${query.toString()}` : ''
    const response = await api.get<{ attendance_records: AttendanceRecord[] }>(`/attendance-records${suffix}`)
    records.value = response.data.attendance_records
  } catch {
    loadError.value = 'Unable to load attendance records.'
  } finally {
    loading.value = false
  }
}

async function submitCorrection() {
  savingCorrection.value = true
  correctionError.value = ''
  correctionSaved.value = false

  try {
    await api.post('/attendance-correction-requests', {
      ...correctionForm,
      requested_check_in: correctionForm.requested_check_in || null,
      requested_check_out: correctionForm.requested_check_out || null,
      reason: correctionForm.reason || null,
    })
    resetCorrectionForm()
    correctionSaved.value = true
    await loadCorrections()
  } catch (err) {
    correctionError.value = apiErrorMessage(err, 'Unable to submit correction request.')
  } finally {
    savingCorrection.value = false
  }
}

async function approveCorrection(correction: AttendanceCorrectionRequest) {
  correctionError.value = ''

  try {
    await api.post(`/attendance-correction-requests/${correction.id}/approve`, {})
    await Promise.all([loadRecords(), loadCorrections()])
  } catch (err) {
    correctionError.value = apiErrorMessage(err, 'Unable to approve correction request.')
  }
}

async function rejectCorrection(correction: AttendanceCorrectionRequest) {
  const rejection_reason = window.prompt('Rejection reason')

  if (!rejection_reason) return

  correctionError.value = ''

  try {
    await api.post(`/attendance-correction-requests/${correction.id}/reject`, { rejection_reason })
    await loadCorrections()
  } catch (err) {
    correctionError.value = apiErrorMessage(err, 'Unable to reject correction request.')
  }
}

async function submit() {
  saving.value = true
  error.value = ''

  try {
    const payload = {
      ...form,
      check_in: form.check_in || null,
      check_out: form.check_out || null,
      notes: form.notes || null,
    }

    if (editingId.value) {
      await api.put(`/attendance-records/${editingId.value}`, payload)
    } else {
      await api.post('/attendance-records', payload)
    }

    resetForm()
    await loadRecords()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to save attendance record.')
  } finally {
    saving.value = false
  }
}

function resetCorrectionForm() {
  correctionForm.employee_id = 0
  correctionForm.date = new Date().toISOString().slice(0, 10)
  correctionForm.correction_type = 'wrong_time'
  correctionForm.requested_check_in = ''
  correctionForm.requested_check_out = ''
  correctionForm.requested_break_minutes = 0
  correctionForm.requested_status = 'present'
  correctionForm.reason = ''
}

function edit(record: AttendanceRecord) {
  editingId.value = record.id
  form.employee_id = record.employee_id
  form.date = record.date
  form.check_in = record.check_in || ''
  form.check_out = record.check_out || ''
  form.break_minutes = record.break_minutes
  form.status = record.status
  form.source = record.source
  form.notes = record.notes || ''
  error.value = ''
}

async function remove(record: AttendanceRecord) {
  await api.delete(`/attendance-records/${record.id}`)
  if (editingId.value === record.id) {
    resetForm()
  }
  await loadRecords()
}

function resetForm() {
  editingId.value = null
  form.employee_id = 0
  form.date = new Date().toISOString().slice(0, 10)
  form.check_in = ''
  form.check_out = ''
  form.break_minutes = 0
  form.status = 'present'
  form.source = 'manual'
  form.notes = ''
  error.value = ''
}

function label(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function timeRange(record: AttendanceRecord) {
  if (!record.check_in && !record.check_out) {
    return '-'
  }

  return `${record.check_in || '-'} - ${record.check_out || '-'}`
}

function correctionSummary(correction: AttendanceCorrectionRequest) {
  return `${correction.requested_check_in || '-'} - ${correction.requested_check_out || '-'}, ${label(correction.requested_status)}`
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
  grid-template-columns: repeat(3, minmax(160px, 1fr));
  gap: 12px;
  max-width: 760px;
}

.panel {
  display: grid;
  gap: 14px;
  margin-top: 20px;
  border: 1px solid #d8dee4;
  border-radius: 8px;
  background: #ffffff;
  padding: 16px;
}

.panel h2 {
  margin: 0;
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

@media (max-width: 760px) {
  .filters {
    grid-template-columns: 1fr;
  }
}
</style>
