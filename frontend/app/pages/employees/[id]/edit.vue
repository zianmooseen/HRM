<template>
  <section class="page">
    <h1>Edit employee</h1>
    <form v-if="loaded" class="form-grid" @submit.prevent="submit">
      <label>
        Employee code
        <input v-model="form.employee_code" required>
      </label>
      <label>
        First name
        <input v-model="form.first_name" required>
      </label>
      <label>
        Last name
        <input v-model="form.last_name" required>
      </label>
      <label>
        Work email
        <input v-model="form.work_email" type="email">
      </label>
      <label>
        Branch
        <select v-model="form.branch_id">
          <option :value="null">No branch</option>
          <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
        </select>
      </label>
      <label>
        Department
        <select v-model="form.department_id">
          <option :value="null">No department</option>
          <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
        </select>
      </label>
      <label>
        Job title
        <select v-model="form.job_title_id">
          <option :value="null">No job title</option>
          <option v-for="jobTitle in jobTitles" :key="jobTitle.id" :value="jobTitle.id">{{ jobTitle.title }}</option>
        </select>
      </label>
      <label>
        Status
        <select v-model="form.status">
          <option value="draft">Draft</option>
          <option value="onboarding">Onboarding</option>
          <option value="active">Active</option>
          <option value="on_leave">On leave</option>
          <option value="suspended">Suspended</option>
          <option value="terminated">Terminated</option>
          <option value="archived">Archived</option>
        </select>
      </label>
      <label>
        Basic salary
        <input v-model.number="form.basic_salary" type="number" min="0">
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : 'Save employee' }}</button>
    </form>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const route = useRoute()
const router = useRouter()
const api = useApiClient()
const saving = ref(false)
const loaded = ref(false)
const error = ref('')
const branches = ref<Array<{ id: number, name: string }>>([])
const departments = ref<Array<{ id: number, name: string }>>([])
const jobTitles = ref<Array<{ id: number, title: string }>>([])
const form = reactive<any>({})

onMounted(async () => {
  const [employeeResponse, branchResponse, departmentResponse, jobTitleResponse] = await Promise.all([
    api.get<{ employee: any }>(`/employees/${route.params.id}`),
    api.get<{ branches: Array<{ id: number, name: string }> }>('/branches'),
    api.get<{ departments: Array<{ id: number, name: string }> }>('/departments'),
    api.get<{ job_titles: Array<{ id: number, title: string }> }>('/job-titles'),
  ])
  Object.assign(form, employeeResponse.data.employee)
  branches.value = branchResponse.data.branches
  departments.value = departmentResponse.data.departments
  jobTitles.value = jobTitleResponse.data.job_titles
  loaded.value = true
})

async function submit() {
  saving.value = true
  error.value = ''

  try {
    await api.put(`/employees/${route.params.id}`, form)
    await router.push(`/employees/${route.params.id}`)
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to save employee.')
  } finally {
    saving.value = false
  }
}
</script>
