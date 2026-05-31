<template>
  <section class="page">
    <header>
      <h1>My attendance</h1>
    </header>

    <section class="panel">
      <header>
        <div>
          <h2>Request Correction</h2>
          <p class="muted">Submit missed punch or attendance status corrections for approval.</p>
        </div>
      </header>
      <form class="form-grid" @submit.prevent="submitCorrection">
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
          Check in
          <input v-model="correctionForm.requested_check_in" type="time">
        </label>
        <label>
          Check out
          <input v-model="correctionForm.requested_check_out" type="time">
        </label>
        <label>
          Status
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
        <button type="submit" :disabled="savingCorrection">{{ savingCorrection ? 'Submitting...' : 'Submit correction' }}</button>
      </form>
    </section>

    <p v-if="loading">Loading attendance...</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <table v-else>
      <thead>
        <tr>
          <th>Date</th>
          <th>Check in</th>
          <th>Check out</th>
          <th>Status</th>
          <th>Source</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="record in records" :key="record.id">
          <td>{{ record.date }}</td>
          <td>{{ record.check_in || '-' }}</td>
          <td>{{ record.check_out || '-' }}</td>
          <td>{{ label(record.status) }}</td>
          <td>{{ label(record.source) }}</td>
        </tr>
        <tr v-if="records.length === 0">
          <td colspan="5">No attendance records yet.</td>
        </tr>
      </tbody>
    </table>

    <section class="panel">
      <h2>My correction requests</h2>
      <p v-if="loadingCorrections">Loading correction requests...</p>
      <p v-else-if="correctionLoadError" class="error">{{ correctionLoadError }}</p>
      <table v-else>
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Requested</th>
            <th>Status</th>
            <th>Rejection reason</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="correction in corrections" :key="correction.id">
            <td>{{ correction.date }}</td>
            <td>{{ label(correction.correction_type) }}</td>
            <td>{{ correction.requested_check_in || '-' }} - {{ correction.requested_check_out || '-' }}</td>
            <td>{{ label(correction.status) }}</td>
            <td>{{ correction.rejection_reason || '-' }}</td>
          </tr>
          <tr v-if="corrections.length === 0">
            <td colspan="5">No correction requests yet.</td>
          </tr>
        </tbody>
      </table>
    </section>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface AttendanceRecord {
  id: number
  date: string
  check_in: string | null
  check_out: string | null
  status: string
  source: string
}

interface AttendanceCorrectionRequest {
  id: number
  date: string
  correction_type: string
  requested_check_in: string | null
  requested_check_out: string | null
  requested_status: string
  status: string
  rejection_reason: string | null
}

const api = useApiClient()
const statuses = ['present', 'absent', 'late', 'half_day', 'on_leave', 'holiday', 'remote']
const records = ref<AttendanceRecord[]>([])
const corrections = ref<AttendanceCorrectionRequest[]>([])
const loading = ref(true)
const loadingCorrections = ref(true)
const savingCorrection = ref(false)
const error = ref('')
const correctionError = ref('')
const correctionLoadError = ref('')
const correctionSaved = ref(false)
const correctionForm = reactive({
  date: new Date().toISOString().slice(0, 10),
  correction_type: 'wrong_time',
  requested_check_in: '',
  requested_check_out: '',
  requested_break_minutes: 0,
  requested_status: 'present',
  reason: '',
})

onMounted(async () => {
  await Promise.all([loadAttendance(), loadCorrections()])
})

async function loadAttendance() {
  try {
    const response = await api.get<{ attendance_records: AttendanceRecord[] }>('/attendance-records')
    records.value = response.data.attendance_records
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to load attendance.')
  } finally {
    loading.value = false
  }
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
    correctionForm.date = new Date().toISOString().slice(0, 10)
    correctionForm.correction_type = 'wrong_time'
    correctionForm.requested_check_in = ''
    correctionForm.requested_check_out = ''
    correctionForm.requested_status = 'present'
    correctionForm.reason = ''
    correctionSaved.value = true
    await loadCorrections()
  } catch (err) {
    correctionError.value = apiErrorMessage(err, 'Unable to submit correction request.')
  } finally {
    savingCorrection.value = false
  }
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

.panel {
  display: grid;
  gap: 14px;
  margin: 20px 0;
  border: 1px solid #d8dee4;
  border-radius: 8px;
  background: #ffffff;
  padding: 16px;
}

.panel h2 {
  margin: 0;
}

.full {
  grid-column: 1 / -1;
}
</style>
