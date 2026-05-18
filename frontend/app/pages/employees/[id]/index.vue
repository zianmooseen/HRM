<template>
  <section class="page">
    <header>
      <h1>{{ employee?.display_name || 'Employee' }}</h1>
      <NuxtLink :to="`/employees/${route.params.id}/edit`">Edit</NuxtLink>
    </header>
    <p v-if="loading">Loading employee...</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <dl v-else-if="employee" class="detail-list">
      <dt>Code</dt>
      <dd>{{ employee.employee_code }}</dd>
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
    </dl>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const route = useRoute()
const api = useApiClient()
const loading = ref(true)
const error = ref('')
const employee = ref<any>(null)

onMounted(async () => {
  try {
    const response = await api.get<{ employee: any }>(`/employees/${route.params.id}`)
    employee.value = response.data.employee
  } catch {
    error.value = 'Unable to load employee.'
  } finally {
    loading.value = false
  }
})
</script>
