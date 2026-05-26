<template>
  <section class="page">
    <h1>Create employee</h1>
    <form class="form-grid" @submit.prevent="submit">
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
        Personal email
        <input v-model="form.personal_email" type="email">
      </label>
      <label>
        Phone
        <input v-model="form.phone">
      </label>
      <label>
        Gender
        <select v-model="form.gender">
          <option value="">Not specified</option>
          <option value="female">Female</option>
          <option value="male">Male</option>
          <option value="other">Other</option>
        </select>
      </label>
      <label>
        Date of birth
        <input v-model="form.date_of_birth" type="date">
      </label>
      <label>
        Nationality
        <input v-model="form.nationality">
      </label>
      <label class="checkbox-label">
        <input v-model="form.is_uae_citizen" type="checkbox">
        UAE citizen
      </label>
      <label>
        Skill level
        <input v-model="form.skill_level">
      </label>
      <label class="checkbox-label">
        <input v-model="form.is_skilled_worker" type="checkbox">
        Skilled worker
      </label>
      <label>
        Work permit type
        <input v-model="form.work_permit_type">
      </label>
      <label>
        Hire date
        <input v-model="form.hire_date" type="date">
      </label>
      <label>
        Probation end date
        <input v-model="form.probation_end_date" type="date">
      </label>
      <label>
        Contract start date
        <input v-model="form.contract_start_date" type="date">
      </label>
      <label>
        Contract end date
        <input v-model="form.contract_end_date" type="date">
      </label>
      <label>
        Employment type
        <input v-model="form.employment_type">
      </label>
      <label>
        Contract type
        <input v-model="form.contract_type">
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
        Manager
        <select v-model="form.manager_employee_id">
          <option :value="null">No manager</option>
          <option v-for="employee in managers" :key="employee.id" :value="employee.id">
            {{ employee.display_name }}
          </option>
        </select>
      </label>
      <label>
        Status
        <select v-model="form.status">
          <option value="draft">Draft</option>
          <option value="onboarding">Onboarding</option>
          <option value="active">Active</option>
        </select>
      </label>
      <label>
        Basic salary
        <input v-model.number="form.basic_salary" type="number" min="0">
      </label>
      <label>
        Monthly package estimate
        <input v-model.number="form.monthly_salary" type="number" min="0">
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : 'Create employee' }}</button>
    </form>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const api = useApiClient()
const router = useRouter()
const saving = ref(false)
const error = ref('')
const branches = ref<Array<{ id: number, name: string }>>([])
const departments = ref<Array<{ id: number, name: string }>>([])
const jobTitles = ref<Array<{ id: number, title: string }>>([])
const managers = ref<Array<{ id: number, display_name: string }>>([])
const form = reactive({
  employee_code: '',
  first_name: '',
  last_name: '',
  work_email: '',
  personal_email: '',
  phone: '',
  gender: '',
  date_of_birth: '',
  nationality: '',
  is_uae_citizen: false,
  skill_level: '',
  is_skilled_worker: false,
  work_permit_type: '',
  hire_date: '',
  probation_end_date: '',
  contract_start_date: '',
  contract_end_date: '',
  employment_type: '',
  contract_type: '',
  branch_id: null as number | null,
  department_id: null as number | null,
  job_title_id: null as number | null,
  manager_employee_id: null as number | null,
  status: 'draft',
  basic_salary: null as number | null,
  monthly_salary: null as number | null,
})

onMounted(async () => {
  const [branchResponse, departmentResponse, jobTitleResponse, employeeResponse] = await Promise.all([
    api.get<{ branches: Array<{ id: number, name: string }> }>('/branches'),
    api.get<{ departments: Array<{ id: number, name: string }> }>('/departments'),
    api.get<{ job_titles: Array<{ id: number, title: string }> }>('/job-titles'),
    api.get<{ employees: Array<{ id: number, display_name: string }> }>('/employees'),
  ])
  branches.value = branchResponse.data.branches
  departments.value = departmentResponse.data.departments
  jobTitles.value = jobTitleResponse.data.job_titles
  managers.value = employeeResponse.data.employees
})

async function submit() {
  saving.value = true
  error.value = ''

  try {
    const response = await api.post<{ employee: { id: number } }>('/employees', form)
    await router.push(`/employees/${response.data.employee.id}`)
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to create employee. Check required fields and unique employee code.')
  } finally {
    saving.value = false
  }
}
</script>
