<template>
  <section class="page">
    <header>
      <h1>Employees</h1>
      <NuxtLink to="/employees/create">Create employee</NuxtLink>
    </header>
    <p v-if="loading">Loading employees...</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <table v-else>
      <thead>
        <tr>
          <th>Code</th>
          <th>Name</th>
          <th>Status</th>
          <th>Contract</th>
          <th>Branch</th>
          <th>Department</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="employee in employees" :key="employee.id">
          <td>{{ employee.employee_code }}</td>
          <td>{{ employee.display_name }}</td>
          <td>{{ employee.status }}</td>
          <td>
            <span :class="['status-pill', employee.contract_expiry_status]">
              {{ contractLabel(employee) }}
            </span>
          </td>
          <td>{{ employee.branch?.name || '-' }}</td>
          <td>{{ employee.department?.name || '-' }}</td>
          <td><NuxtLink :to="`/employees/${employee.id}`">Open</NuxtLink></td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface EmployeeRow {
  id: number
  employee_code: string
  display_name: string
  status: string
  contract_end_date: string | null
  contract_days_remaining: number | null
  contract_expiry_status: string
  branch?: { name: string } | null
  department?: { name: string } | null
}

const api = useApiClient()
const employees = ref<EmployeeRow[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const response = await api.get<{ employees: EmployeeRow[] }>('/employees')
    employees.value = response.data.employees
  } catch {
    error.value = 'Unable to load employees.'
  } finally {
    loading.value = false
  }
})

function contractLabel(employee: EmployeeRow) {
  if (employee.contract_expiry_status === 'not_tracked') {
    return 'No end date'
  }

  if (employee.contract_expiry_status === 'expired') {
    return 'Expired'
  }

  if (employee.contract_days_remaining === 0) {
    return 'Ends today'
  }

  return `${employee.contract_end_date} · ${employee.contract_days_remaining} days`
}
</script>

<style scoped>
.status-pill {
  display: inline-flex;
  border-radius: 999px;
  padding: 4px 8px;
  background: #e8eef1;
  color: #172026;
  font-size: 0.85rem;
  font-weight: 700;
  white-space: nowrap;
}

.status-pill.critical,
.status-pill.expired {
  background: #f8d9df;
  color: #8b1830;
}

.status-pill.warning {
  background: #fff1c7;
  color: #6b4c00;
}

.status-pill.valid {
  background: #dcefe8;
  color: #155b49;
}
</style>
