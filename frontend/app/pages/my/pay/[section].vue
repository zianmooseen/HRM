<template>
  <section class="page">
    <h1>{{ titles[section] }}</h1>
    <label v-if="section === 'ytd'" class="year-field">Year<input v-model.number="year" type="number" min="1900" max="2100"></label>
    <p v-if="loading" role="status">Loading your pay details…</p>
    <p v-else-if="error" role="alert" class="error">{{ error }}</p>
    <template v-else>
      <template v-if="section === 'compensation' && overview">
        <dl class="detail-list"><dt>Monthly basic salary</dt><dd>{{ money(overview.compensation.basic_salary) }}</dd><dt>Monthly salary on record</dt><dd>{{ money(overview.compensation.monthly_salary) }}</dd></dl>
        <p>These are your current salary records. Your approved pay statements show the earnings and deductions for each payroll period.</p>
      </template>
      <template v-else-if="section === 'direct-deposit' && overview">
        <dl class="detail-list"><dt>Bank</dt><dd>{{ overview.direct_deposit.bank_name || 'Not recorded' }}</dd><dt>IBAN (last four characters)</dt><dd>{{ overview.direct_deposit.masked_iban || 'Not recorded' }}</dd></dl>
        <p>Contact HR or payroll to request a change to your bank details.</p>
      </template>
      <template v-else-if="section === 'ytd' && overview">
        <p>Approved statements with scheduled pay dates in {{ overview.ytd.year }}, through {{ overview.ytd.through_date }}. These totals do not confirm bank transfers.</p>
        <dl class="detail-list"><dt>Statements</dt><dd>{{ overview.ytd.statements }}</dd><dt>Gross pay</dt><dd>{{ money(overview.ytd.gross_pay) }}</dd><dt>Deductions</dt><dd>{{ money(overview.ytd.total_deductions) }}</dd><dt>Net pay</dt><dd>{{ money(overview.ytd.net_pay) }}</dd></dl>
      </template>
      <template v-else>
        <p>{{ section === 'history' ? 'Your approved pay statements, newest period first.' : 'Your latest approved pay statement.' }}</p>
        <p v-if="!statements.length">No approved pay statements are available yet.</p>
        <article v-for="statement in statements" :key="statement.id" class="pay-statement">
          <h2>{{ statement.period_start }} – {{ statement.period_end }}</h2>
          <p>Scheduled pay date: {{ statement.pay_date || 'Not recorded' }}</p>
          <dl class="detail-list"><dt>Gross pay</dt><dd>{{ money(statement.gross_pay) }}</dd><dt>Deductions</dt><dd>{{ money(statement.total_deductions) }}</dd><dt>Net pay</dt><dd>{{ money(statement.net_pay) }}</dd></dl>
          <details v-if="statement.items.length" :open="section === 'current'">
            <summary>Earnings and deductions</summary>
            <table><thead><tr><th>Description</th><th>Type</th><th>Amount</th></tr></thead><tbody><tr v-for="item in statement.items" :key="item.id"><td>{{ item.label }}</td><td>{{ item.type }}</td><td>{{ money(item.amount) }}</td></tr></tbody></table>
          </details>
        </article>
        <div v-if="section === 'history' && pagination.total" class="pay-pagination">
          <button type="button" :disabled="page <= 1" @click="page--">Previous</button>
          <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button type="button" :disabled="page >= pagination.last_page" @click="page++">Next</button>
        </div>
      </template>
    </template>
  </section>
</template>
<script setup lang="ts">
import type { EmployeePayOverview, EmployeePayStatement, Pagination } from '../../../../../shared/types/self-service'
definePageMeta({ middleware: ['auth'], validate: route => ['current', 'history', 'ytd', 'compensation', 'direct-deposit'].includes(String(route.params.section)) })
const titles = { current: 'Current Pay Statement', history: 'Pay History', ytd: 'YTD Summary', compensation: 'Compensation', 'direct-deposit': 'Direct Deposit' }
const route = useRoute()
const section = computed(() => route.params.section as keyof typeof titles)
const api = useApiClient()
const loading = ref(true)
const error = ref('')
const year = ref(new Date().getFullYear())
const page = ref(1)
const overview = ref<EmployeePayOverview | null>(null)
const history = ref<EmployeePayStatement[]>([])
const currency = ref('AED')
const pagination = ref<Pagination>({ current_page: 1, last_page: 1, total: 0 })
const statements = computed(() => section.value === 'history' ? history.value : overview.value?.current_statement ? [overview.value.current_statement] : [])
let requestId = 0
async function load() {
  const id = ++requestId
  loading.value = true
  error.value = ''
  try {
    if (section.value === 'history') {
      const response = await api.get<{ currency: string, statements: EmployeePayStatement[], pagination: Pagination }>(`/self/pay/history?page=${page.value}`)
      if (id !== requestId) return
      history.value = response.data.statements
      pagination.value = response.data.pagination
      currency.value = response.data.currency
    } else {
      const response = await api.get<EmployeePayOverview>(`/self/pay?year=${year.value}`)
      if (id !== requestId) return
      overview.value = response.data
      currency.value = response.data.currency
    }
  } catch (err) { if (id === requestId) error.value = apiErrorMessage(err, 'Unable to load your pay details.') }
  finally { if (id === requestId) loading.value = false }
}
function money(value: string | null) { return value === null ? 'Not recorded' : new Intl.NumberFormat(undefined, { style: 'currency', currency: currency.value }).format(Number(value)) }
onMounted(load)
watch([section, year, page], load)
onBeforeUnmount(() => { requestId++ })
</script>
<style scoped>
.pay-statement { padding: 20px; margin: 20px 0; background: white; border: 1px solid var(--color-border); border-radius: 8px; }
.pay-statement h2 { margin-top: 0; }
.pay-statement summary { cursor: pointer; margin: 16px 0; }
.pay-pagination { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
.year-field { display: grid; gap: 6px; max-width: 160px; margin: 16px 0; }
.year-field input { min-height: 40px; padding: 8px; border: 1px solid var(--color-input-border); border-radius: 6px; }
</style>
