<template>
  <section class="page">
    <header>
      <div>
        <h1>Dashboard</h1>
        <p class="muted">Signed in as {{ auth.user?.name }}</p>
      </div>
    </header>

    <section class="dashboard-grid">
      <article class="summary-card">
        <span>Contracts expiring</span>
        <strong>{{ expiringContracts.length }}</strong>
        <small>Next 60 days</small>
      </article>
    </section>

    <section class="panel">
      <header>
        <div>
          <h2>Contract Expiry Reminders</h2>
          <p class="muted">Employees whose fixed-term contracts need review soon.</p>
        </div>
        <NuxtLink to="/employees">Employees</NuxtLink>
      </header>

      <p v-if="loading">Loading reminders...</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <table v-else-if="expiringContracts.length > 0">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Contract end</th>
            <th>Days left</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="employee in expiringContracts" :key="employee.id">
            <td>{{ employee.display_name }}</td>
            <td>{{ employee.contract_end_date }}</td>
            <td>{{ employee.contract_days_remaining }}</td>
            <td><NuxtLink :to="`/employees/${employee.id}`">Review</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else class="muted">No contracts expiring in the next 60 days.</p>
    </section>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const auth = useAuthStore()
const api = useApiClient()
const expiringContracts = ref<EmployeeContractReminder[]>([])
const loading = ref(true)
const error = ref('')

interface EmployeeContractReminder {
  id: number
  display_name: string
  contract_end_date: string
  contract_days_remaining: number
}

onMounted(async () => {
  try {
    // Feature flow step 2: dashboard consumes the backend expiry filter and only renders actionable reminders.
    const response = await api.get<{ employees: EmployeeContractReminder[] }>('/employees?contract_expiring_days=60')
    expiringContracts.value = response.data.employees
  } catch {
    error.value = 'Unable to load contract reminders.'
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.summary-card,
.panel {
  display: grid;
  gap: 10px;
  background: #ffffff;
  border: 1px solid #d8dee4;
  border-radius: 8px;
  padding: 16px;
}

.summary-card span,
.summary-card small {
  color: #5d6a72;
}

.summary-card strong {
  font-size: 2rem;
}

.panel > header {
  align-items: center;
}

.panel h2 {
  margin: 0;
}
</style>
