<template>
  <section class="page">
    <header><div><h1>{{ worker?.name || 'Temporary worker' }}</h1><p v-if="worker" class="muted">{{ worker.branch?.name }} · {{ worker.job_description || 'Temporary worker' }} · {{ worker.status }}</p></div><NuxtLink to="/temporary-workers">All temporary workers</NuxtLink></header>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="notice" role="status">{{ notice }}</p>
    <template v-if="worker && totals">
      <div class="summary-grid">
        <article><span>Time worked</span><h2>{{ totals.days }} days</h2><p>{{ totals.hours }} hours · {{ totals.dates_worked }} dates worked</p><small v-if="totals.first_worked">{{ totals.first_worked }} – {{ totals.last_worked }}</small></article>
        <article><span>Earned</span><h2>{{ money(totals.earned) }}</h2><p>Current rate: {{ money(worker.rate) }} / {{ worker.wage_basis === 'daily' ? 'day' : 'hour' }}</p></article>
        <article><span>Paid</span><h2>{{ money(totals.paid) }}</h2><p>Balance: {{ money(totals.balance) }}</p></article>
      </div>
      <div class="panel-grid">
        <ActionFormPanel v-if="auth.hasPermission('temporary_workers.manage')" v-model:open="forms.work" action-label="Record work" :busy="saving">
          <form class="form-grid" @submit.prevent="record('work')">
            <p v-if="worker.status === 'inactive'" class="wide">This worker is inactive. Reactivate their profile to record new work.</p>
            <label>Date worked<input v-model="work.date" type="date" :max="today" required></label>
            <label>{{ worker.wage_basis === 'daily' ? 'Days (up to 1)' : 'Hours (up to 24)' }}<input v-model="work.quantity" type="number" min="0.01" :max="worker.wage_basis === 'daily' ? 1 : 24" step="0.01" required></label>
            <label class="wide">Notes (optional)<textarea v-model="work.notes" maxlength="2000" /></label>
            <p class="muted wide">Uses the current {{ worker.wage_basis }} rate of {{ money(worker.rate) }}. One work entry per date; partial days are supported.</p>
            <button class="wide" :disabled="saving || worker.status !== 'active'">Record work</button>
          </form>
        </ActionFormPanel>
        <ActionFormPanel v-if="auth.hasPermission('temporary_workers.pay')" v-model:open="forms.payment" action-label="Record payment" :busy="saving">
          <form class="form-grid" @submit.prevent="record('payment')">
            <label>Payment date<input v-model="payment.date" type="date" :max="today" required></label>
            <label>Amount ({{ worker.currency }})<input v-model="payment.amount" type="number" min="0.01" :max="totals.balance" step="0.01" required></label>
            <label>Method<select v-model="payment.method"><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="cheque">Cheque</option><option value="other">Other</option></select></label>
            <label>Reference (optional)<input v-model="payment.reference" maxlength="255"></label>
            <label class="wide">Notes (optional)<textarea v-model="payment.notes" maxlength="2000" /></label>
            <p class="muted wide">Record money already paid. Maximum {{ money(totals.balance) }}. This does not initiate a bank transfer or recurring payroll.</p>
            <button class="wide" :disabled="saving || Number(totals.balance) <= 0">Record payment</button>
          </form>
        </ActionFormPanel>
      </div>
      <section class="panel">
        <h2>Work &amp; payment history</h2>
        <p class="muted">Totals exclude voided entries. Days and hours remain separate when the wage basis changes.</p>
        <div class="table-wrap"><table>
          <thead><tr><th>Date</th><th>Entry</th><th>Details</th><th>Amount</th><th>Notes / reference</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody><tr v-for="entry in entries" :key="entry.id">
            <td>{{ entry.date }}</td><td>{{ entry.kind === 'work' ? 'Work' : 'Payment' }}</td>
            <td v-if="entry.kind === 'work'">{{ entry.quantity }} {{ entry.wage_basis === 'daily' ? 'days' : 'hours' }} × {{ money(entry.rate || '0') }}</td><td v-else>{{ entry.method?.replaceAll('_', ' ') }}</td>
            <td>{{ money(entry.amount) }}</td><td>{{ entry.notes || '—' }}<small v-if="entry.reference">{{ entry.reference }}</small></td><td>{{ entry.voided_at ? 'Voided' : 'Recorded' }}<small v-if="entry.void_reason">{{ entry.void_reason }}</small></td>
            <td><button v-if="!entry.voided_at && auth.hasPermission(entry.kind === 'payment' ? 'temporary_workers.pay' : 'temporary_workers.manage')" class="secondary" :disabled="saving" @click="voidEntry = entry; voidReason = ''">Void</button></td>
          </tr><tr v-if="!entries.length"><td colspan="7">No work or payments recorded yet.</td></tr></tbody>
        </table></div>
        <div class="button-row"><button class="secondary" :disabled="page <= 1 || saving" @click="page--; load()">Previous</button><span>Page {{ page }} of {{ lastPage }}</span><button class="secondary" :disabled="page >= lastPage || saving" @click="page++; load()">Next</button></div>
        <form v-if="voidEntry" class="void-form" @submit.prevent="confirmVoid">
          <h3>Void {{ voidEntry.kind }} from {{ voidEntry.date }} ({{ money(voidEntry.amount) }})</h3>
          <p>Use this only to correct an incorrect record. The original entry and reason will remain in the history.</p>
          <label>Reason<textarea v-model="voidReason" required minlength="3" maxlength="2000" /></label>
          <div class="button-row"><button class="danger" :disabled="saving">Confirm void</button><button type="button" class="secondary" :disabled="saving" @click="voidEntry = null">Cancel</button></div>
        </form>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { TemporaryWorker, TemporaryWorkerEntry, TemporaryWorkerTotals } from '../../../../shared/types/temporary-workers'
definePageMeta({ middleware: ['auth'] })
const api = useApiClient()
const forms = reactive<Record<string, boolean>>({})
const auth = useAuthStore()
const route = useRoute()
const worker = ref<TemporaryWorker | null>(null)
const totals = ref<TemporaryWorkerTotals | null>(null)
const entries = ref<TemporaryWorkerEntry[]>([])
const page = ref(1)
const lastPage = ref(1)
const today = ref('')
const error = ref('')
const notice = ref('')
const saving = ref(false)
const voidEntry = ref<TemporaryWorkerEntry | null>(null)
const voidReason = ref('')
const work = reactive({ date: '', quantity: '', notes: '', entry_key: '' })
const payment = reactive({ date: '', amount: '', method: 'cash', reference: '', notes: '', entry_key: '' })
const path = `/temporary-workers/${route.params.id}`
const money = (value: string) => `${worker.value?.currency || ''} ${value}`
async function load() {
  try {
    const result = await api.get<{ worker: TemporaryWorker, totals: TemporaryWorkerTotals, entries: TemporaryWorkerEntry[], today: string, meta: { last_page: number } }>(`${path}?page=${page.value}`)
    worker.value = result.data.worker
    totals.value = result.data.totals
    entries.value = result.data.entries
    today.value = result.data.today
    work.date ||= today.value
    payment.date ||= today.value
    lastPage.value = result.data.meta.last_page
  } catch (cause) { error.value = apiErrorMessage(cause, 'Unable to load worker history.') }
}
async function record(kind: 'work' | 'payment') {
  saving.value = true
  error.value = ''
  notice.value = ''
  const form = kind === 'work' ? work : payment
  // Keep the submission key on failure so retrying cannot create a duplicate payment.
  form.entry_key ||= crypto.randomUUID()
  try {
    await api.post(`${path}/entries`, { ...form, kind })
    form.entry_key = ''
    if (kind === 'work') Object.assign(work, { quantity: '', notes: '' })
    else Object.assign(payment, { amount: '', reference: '', notes: '' })
    notice.value = kind === 'work' ? 'Work recorded.' : 'Payment recorded.'
    page.value = 1
    await load()
    forms[kind] = false
  } catch (cause) { error.value = apiErrorMessage(cause, 'Unable to record entry.') }
  finally { saving.value = false }
}
async function confirmVoid() {
  if (!voidEntry.value) return
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    await api.post(`${path}/entries/${voidEntry.value.id}/void`, { reason: voidReason.value })
    voidEntry.value = null
    notice.value = 'Entry voided. Totals updated.'
    await load()
  } catch (cause) { error.value = apiErrorMessage(cause, 'Unable to void entry.') }
  finally { saving.value = false }
}
await load()
</script>

<style scoped>
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
.panel-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
.panel { padding: 24px; min-width: 0; }
.wide { grid-column: 1 / -1; }
.table-wrap { max-width: 100%; overflow-x: auto; margin: 20px 0; }
table { width: 100%; min-width: 860px; }
small { display: block; margin-top: 6px; color: var(--color-muted); }
.button-row { display: flex; align-items: center; gap: 12px; margin-top: 16px; }
.void-form { border-top: 1px solid var(--color-border); margin-top: 24px; padding-top: 20px; }
.void-form label { display: grid; gap: 8px; }
@media (max-width: 1000px) { .panel-grid { grid-template-columns: 1fr; } }
</style>
