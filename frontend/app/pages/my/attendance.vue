<template>
  <section class="page">
    <header>
      <h1>My attendance</h1>
    </header>
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

const api = useApiClient()
const records = ref<AttendanceRecord[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const response = await api.get<{ attendance_records: AttendanceRecord[] }>('/attendance-records')
    records.value = response.data.attendance_records
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to load attendance.')
  } finally {
    loading.value = false
  }
})

function label(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}
</script>
