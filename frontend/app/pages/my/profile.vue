<template>
  <section class="page">
    <header>
      <h1>My profile</h1>
    </header>
    <p v-if="loading">Loading profile...</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <dl v-else-if="employee" class="detail-list">
      <dt>Code</dt>
      <dd>{{ employee.employee_code }}</dd>
      <dt>Name</dt>
      <dd>{{ employee.display_name }}</dd>
      <dt>Status</dt>
      <dd>{{ employee.status }}</dd>
      <dt>Work email</dt>
      <dd>{{ employee.work_email || '-' }}</dd>
      <dt>Branch</dt>
      <dd>{{ employee.branch?.name || '-' }}</dd>
      <dt>Department</dt>
      <dd>{{ employee.department?.name || '-' }}</dd>
      <dt>Job title</dt>
      <dd>{{ employee.job_title?.title || '-' }}</dd>
      <dt>Hire date</dt>
      <dd>{{ employee.hire_date || '-' }}</dd>
    </dl>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const api = useApiClient()
const loading = ref(true)
const error = ref('')
const employee = ref<any>(null)

onMounted(async () => {
  try {
    const response = await api.get<{ employee: any }>('/self/profile')
    employee.value = response.data.employee
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to load profile.')
  } finally {
    loading.value = false
  }
})
</script>
