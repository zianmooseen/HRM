<template>
  <section class="page">
    <header>
      <h1>My leave</h1>
    </header>

    <form v-if="employee" class="form-grid" @submit.prevent="submit">
      <label>
        Leave type
        <select v-model.number="form.leave_type_id" required>
          <option :value="0">Select leave type</option>
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
      <button type="submit" :disabled="saving">{{ saving ? 'Submitting...' : 'Submit request' }}</button>
    </form>

    <table>
      <thead>
        <tr>
          <th>Type</th>
          <th>Dates</th>
          <th>Days</th>
          <th>Status</th>
          <th>Latest note</th>
        </tr>
        <tr class="column-filter-row">
          <th><TableColumnFilter v-model="columnFilters.type" label="Filter my leave type" /></th>
          <th><TableColumnFilter v-model="columnFilters.dates" label="Filter my leave dates" /></th>
          <th><TableColumnFilter v-model="columnFilters.days" label="Filter my leave days" /></th>
          <th><TableColumnFilter v-model="columnFilters.status" label="Filter my leave status" /></th>
          <th><TableColumnFilter v-model="columnFilters.note" label="Filter my leave note" /></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="request in filteredRequests" :key="request.id">
          <td>{{ request.leave_type?.name || '-' }}</td>
          <td>{{ request.start_date }} to {{ request.end_date }}</td>
          <td>{{ request.working_days }}</td>
          <td>{{ label(request.status) }}</td>
          <td>{{ latestNote(request) }}</td>
        </tr>
        <tr v-if="filteredRequests.length === 0">
          <td colspan="5">No leave requests yet.</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface LeaveType {
  id: number
  name: string
  code: string
  requires_document: boolean
}

interface LeaveRequestRow {
  id: number
  start_date: string
  end_date: string
  working_days: string
  status: string
  approval_note: string | null
  rejection_reason: string | null
  leave_type?: LeaveType | null
  status_events?: LeaveStatusEvent[]
}

interface LeaveStatusEvent {
  id: number
  status: string
  actor_user_id: number | null
  note: string | null
  created_at: string
}

interface EmployeeDocument {
  id: number
  document_type: string
  title: string
  original_file_name: string
}

const api = useApiClient()
const employee = ref<any>(null)
const leaveTypes = ref<LeaveType[]>([])
const requests = ref<LeaveRequestRow[]>([])
const { filters: columnFilters, filteredRows: filteredRequests } = useTableColumnFilters(
  requests,
  [
    { key: 'type', value: request => request.leave_type?.name },
    { key: 'dates', value: request => `${request.start_date} to ${request.end_date}` },
    { key: 'days', value: request => request.working_days },
    { key: 'status', value: request => label(request.status) },
    { key: 'note', value: request => latestNote(request) },
  ],
)
const medicalCertificates = ref<EmployeeDocument[]>([])
const saving = ref(false)
const error = ref('')
const form = reactive({
  leave_type_id: 0,
  start_date: new Date().toISOString().slice(0, 10),
  end_date: new Date().toISOString().slice(0, 10),
  medical_certificate_document_id: '',
  reason: '',
})
const selectedLeaveType = computed(() => leaveTypes.value.find((leaveType) => leaveType.id === form.leave_type_id) || null)

onMounted(async () => {
  const [profile, types] = await Promise.all([
    api.get<{ employee: any }>('/self/profile'),
    api.get<{ leave_types: LeaveType[] }>('/leave-types'),
  ])
  employee.value = profile.data.employee
  leaveTypes.value = types.data.leave_types
  await loadRequests()
})

watch(
  () => form.leave_type_id,
  async () => {
    form.medical_certificate_document_id = ''
    if (selectedLeaveType.value?.code === 'sick_leave' || selectedLeaveType.value?.requires_document) {
      await loadMedicalCertificates()
    }
  },
)

async function loadRequests() {
  const response = await api.get<{ leave_requests: LeaveRequestRow[] }>('/leave-requests')
  requests.value = response.data.leave_requests
}

async function loadMedicalCertificates() {
  const response = await api.get<{ documents: EmployeeDocument[] }>('/documents?document_type=medical_certificate')
  medicalCertificates.value = response.data.documents
}

async function submit() {
  if (!employee.value) return
  saving.value = true
  error.value = ''

  try {
    await api.post('/leave-requests', {
      employee_id: employee.value.id,
      leave_type_id: form.leave_type_id,
      start_date: form.start_date,
      end_date: form.end_date,
      medical_certificate_document_id: form.medical_certificate_document_id
        ? Number(form.medical_certificate_document_id)
        : null,
      reason: form.reason || null,
    })
    form.reason = ''
    form.medical_certificate_document_id = ''
    await loadRequests()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to submit leave request.')
  } finally {
    saving.value = false
  }
}

function label(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function latestNote(request: LeaveRequestRow) {
  const eventNote = request.status_events?.slice().reverse().find((event) => event.note)?.note

  return eventNote || request.approval_note || request.rejection_reason || '-'
}
</script>
