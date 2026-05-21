<template>
  <section class="page">
    <header>
      <div>
        <h1>Compliance</h1>
        <p class="muted">UAE legal rule defaults and end-of-service gratuity estimates.</p>
      </div>
    </header>

    <section>
      <h2>Gratuity Calculator</h2>
      <form class="form-grid" @submit.prevent="calculate">
        <label>
          Employee
          <select v-model.number="form.employee_id" required>
            <option disabled :value="0">Select employee</option>
            <option v-for="employee in employees" :key="employee.id" :value="employee.id">
              {{ employee.display_name }} ({{ employee.employee_code }})
            </option>
          </select>
        </label>
        <label>
          Termination date
          <input v-model="form.termination_date" type="date" required>
        </label>
        <label>
          Basic salary override
          <input v-model.number="form.basic_salary" type="number" min="0">
        </label>
        <label>
          Unpaid leave days
          <input v-model.number="form.unpaid_leave_days" type="number" min="0">
        </label>
        <p v-if="error" class="error">{{ error }}</p>
        <button type="submit" :disabled="calculating">{{ calculating ? 'Calculating...' : 'Calculate gratuity' }}</button>
      </form>

      <dl v-if="result" class="detail-list">
        <dt>Employee</dt>
        <dd>{{ result.employee.display_name }}</dd>
        <dt>Service</dt>
        <dd>{{ result.gratuity.service_years }} years ({{ result.gratuity.service_days }} days)</dd>
        <dt>Daily wage</dt>
        <dd>{{ result.gratuity.currency }} {{ result.gratuity.daily_wage }}</dd>
        <dt>Gratuity days</dt>
        <dd>{{ result.gratuity.gratuity_days }}</dd>
        <dt>Estimated gratuity</dt>
        <dd>{{ result.gratuity.currency }} {{ result.gratuity.gratuity_amount }}</dd>
        <dt>Maximum cap</dt>
        <dd>{{ result.gratuity.currency }} {{ result.gratuity.maximum_amount }}</dd>
      </dl>
    </section>

    <section>
      <h2>Active Legal Rules</h2>
      <p v-if="loadingRules">Loading legal rules...</p>
      <p v-else-if="!legalRuleSet" class="muted">No active legal rule set found.</p>
      <table v-else>
        <thead>
          <tr>
            <th>Rule set</th>
            <th>Rule key</th>
            <th>Value</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in legalRuleSet.items" :key="item.rule_key">
            <td>{{ legalRuleSet.name }} {{ legalRuleSet.version }}</td>
            <td>{{ item.rule_key }}</td>
            <td>{{ item.value }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface EmployeeOption {
  id: number
  employee_code: string
  display_name: string
}

interface LegalRuleItem {
  rule_key: string
  value: string | number | boolean
}

interface LegalRuleSet {
  name: string
  version: string
  items: LegalRuleItem[]
}

interface GratuityResult {
  employee: EmployeeOption
  gratuity: {
    service_days: number
    service_years: number
    daily_wage: number
    gratuity_days: number
    gratuity_amount: number
    maximum_amount: number
    currency: string
  }
}

const api = useApiClient()
const employees = ref<EmployeeOption[]>([])
const legalRuleSet = ref<LegalRuleSet | null>(null)
const result = ref<GratuityResult | null>(null)
const loadingRules = ref(true)
const calculating = ref(false)
const error = ref('')
const form = reactive({
  employee_id: 0,
  termination_date: new Date().toISOString().slice(0, 10),
  basic_salary: null as number | null,
  unpaid_leave_days: 0,
})

onMounted(async () => {
  const [employeeResponse, rulesResponse] = await Promise.all([
    api.get<{ employees: EmployeeOption[] }>('/employees'),
    api.get<{ legal_rule_set: LegalRuleSet | null }>('/compliance/legal-rules'),
  ])
  employees.value = employeeResponse.data.employees
  legalRuleSet.value = rulesResponse.data.legal_rule_set
  loadingRules.value = false
})

async function calculate() {
  calculating.value = true
  error.value = ''
  result.value = null

  try {
    const response = await api.post<GratuityResult>('/compliance/gratuity', {
      ...form,
      basic_salary: form.basic_salary || null,
    })
    result.value = response.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to calculate gratuity.')
  } finally {
    calculating.value = false
  }
}
</script>

<style scoped>
.muted {
  margin: 6px 0 0;
  color: #5d6a72;
}

section h2 {
  margin: 0 0 12px;
}
</style>
