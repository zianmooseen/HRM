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
</script>
