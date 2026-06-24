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
        Work permit number
        <input v-model="form.work_permit_number">
      </label>
      <label>
        Labor card number
        <input v-model="form.labor_card_number">
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
      <label>
        Monthly package estimate
        <input v-model.number="form.monthly_salary" type="number" min="0">
      </label>
      <label>
        Bank name
        <input v-model="form.bank_name">
      </label>
      <label>
        UAE IBAN
        <input
          v-model="form.bank_iban"
          maxlength="34"
          placeholder="AE07 0331 2345 6789 0123 456"
          aria-describedby="bank-iban-help"
          @blur="normalizeIban"
        >
        <small id="bank-iban-help" class="field-hint">
          23 characters starting with AE. Copy the exact IBAN from the bank statement or banking app.
        </small>
      </label>
      <label>
        Bank routing code
        <input v-model="form.bank_routing_code">
      </label>
      <label>
        WPS person ID
        <input v-model="form.wps_person_id">
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
const managers = ref<Array<{ id: number, display_name: string }>>([])
const form = reactive<any>({})

function normalizeIban() {
  form.bank_iban = String(form.bank_iban || '').replace(/\s+/g, '').toUpperCase()
}

onMounted(async () => {
  const [employeeResponse, branchResponse, departmentResponse, jobTitleResponse, employeeListResponse] = await Promise.all([
    api.get<{ employee: any }>(`/employees/${route.params.id}`),
    api.get<{ branches: Array<{ id: number, name: string }> }>('/branches'),
    api.get<{ departments: Array<{ id: number, name: string }> }>('/departments'),
    api.get<{ job_titles: Array<{ id: number, title: string }> }>('/job-titles'),
    api.get<{ employees: Array<{ id: number, display_name: string }> }>('/employees'),
  ])
  Object.assign(form, employeeResponse.data.employee)
  branches.value = branchResponse.data.branches
  departments.value = departmentResponse.data.departments
  jobTitles.value = jobTitleResponse.data.job_titles
  managers.value = employeeListResponse.data.employees.filter((employee) => employee.id !== Number(route.params.id))
  loaded.value = true
})

async function submit() {
  saving.value = true
  error.value = ''
  normalizeIban()

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
