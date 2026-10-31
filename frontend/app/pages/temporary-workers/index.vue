<template>
  <section class="page">
    <header>
      <div><h1>Temporary Workers</h1><p class="muted">Daily and hourly work, earnings, and payments.</p></div>
      <button v-if="auth.hasPermission('temporary_workers.manage')" @click="startNew">Add worker</button>
    </header>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="notice" role="status">{{ notice }}</p>
    <div class="summary-grid">
      <article><span>Total workers</span><h2>{{ counts.total }}</h2></article>
      <article><span>Active workers</span><h2>{{ counts.active }}</h2></article>
      <article><span>Inactive workers</span><h2>{{ counts.total - counts.active }}</h2></article>
    </div>
    <form v-if="showForm" class="form-grid" @submit.prevent="save">
      <h2 class="wide">{{ editingId ? 'Edit worker' : 'Add temporary worker' }}</h2>
      <label>Name<input v-model="form.name" required maxlength="255"></label>
      <label>Branch<select v-model="form.branch_id" required><option value="" disabled>Select branch</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option></select></label>
      <label>Phone (optional)<input v-model="form.phone" maxlength="50"></label>
      <label>Work / role (optional)<input v-model="form.job_description" maxlength="255"></label>
      <label>Wage basis<select v-model="form.wage_basis"><option value="daily">Daily</option><option value="hourly">Hourly</option></select></label>
      <label>Rate ({{ editingCurrency || currency }})<input v-model="form.rate" type="number" required min="0.01" max="9999999.99" step="0.01"></label>
      <label>Status<select v-model="form.status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
      <p class="muted wide">Only a basic profile is needed. No onboarding or employee login is created. Rate changes apply to new work entries.</p>
      <div class="button-row wide"><button :disabled="saving || !branches.length">{{ saving ? 'Saving…' : 'Save worker' }}</button><button type="button" class="secondary" :disabled="saving" @click="showForm = false">Cancel</button></div>
      <p v-if="!branches.length" class="wide">Create a company branch before adding workers.</p>
    </form>
    <section class="panel">
      <form class="filters" @submit.prevent="page = 1; load()">
        <label>Worker name<input v-model="search" placeholder="Search workers"></label>
        <label>Status<select v-model="status"><option value="">All workers</option><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
        <button :disabled="loading">Search</button>
      </form>
      <p v-if="loading" role="status">Loading workers…</p>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Worker / branch</th><th>Rate</th><th>Time worked</th><th>Earned</th><th>Paid</th><th>Balance</th><th>Actions</th></tr></thead>
          <tbody>
            <tr v-for="worker in workers" :key="worker.id">
              <td><NuxtLink :to="`/temporary-workers/${worker.id}`">{{ worker.name }}</NuxtLink><small>{{ worker.branch?.name }} · {{ worker.status }}</small><small v-if="worker.job_description">{{ worker.job_description }}</small></td>
              <td>{{ worker.currency }} {{ worker.rate }} / {{ worker.wage_basis === 'daily' ? 'day' : 'hour' }}</td>
              <td>{{ worker.totals.days }} days · {{ worker.totals.hours }} hours<small>{{ worker.totals.dates_worked }} dates worked</small></td>
              <td>{{ worker.currency }} {{ worker.totals.earned }}</td><td>{{ worker.currency }} {{ worker.totals.paid }}</td><td>{{ worker.currency }} {{ worker.totals.balance }}</td>
              <td><NuxtLink :to="`/temporary-workers/${worker.id}`">Work &amp; payments</NuxtLink><button v-if="auth.hasPermission('temporary_workers.manage')" class="secondary" @click="edit(worker)">Edit</button></td>
            </tr>
            <tr v-if="!loading && !workers.length"><td colspan="7">No temporary workers found.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="button-row"><button class="secondary" :disabled="page <= 1 || loading" @click="page--; load()">Previous</button><span>Page {{ page }} of {{ lastPage }}</span><button class="secondary" :disabled="page >= lastPage || loading" @click="page++; load()">Next</button></div>
    </section>
  </section>
</template>

<script setup lang="ts">
import type { TemporaryWorker } from '../../../../shared/types/temporary-workers'
definePageMeta({ middleware: ['auth'] })
const api = useApiClient()
const auth = useAuthStore()
const workers = ref<TemporaryWorker[]>([])
const branches = ref<{ id: number, name: string }[]>([])
const counts = ref({ total: 0, active: 0 })
const currency = ref('AED')
const editingCurrency = ref('')
const search = ref('')
const status = ref('')
const page = ref(1)
const lastPage = ref(1)
const error = ref('')
const notice = ref('')
const loading = ref(false)
const saving = ref(false)
const showForm = ref(false)
const editingId = ref<number | null>(null)
const blank = () => ({ name: '', branch_id: '' as number | string, phone: '', job_description: '', wage_basis: 'daily', rate: '', status: 'active' })
const form = reactive(blank())
async function load() {
  loading.value = true
  error.value = ''
  try {
    const result = await api.get<{ workers: TemporaryWorker[], branches: typeof branches.value, currency: string, counts: typeof counts.value, meta: { last_page: number } }>(`/temporary-workers?${new URLSearchParams({ search: search.value, status: status.value, page: String(page.value) })}`)
    workers.value = result.data.workers
    branches.value = result.data.branches
    currency.value = result.data.currency || 'AED'
    counts.value = result.data.counts
    lastPage.value = result.data.meta.last_page
  } catch (cause) { error.value = apiErrorMessage(cause, 'Unable to load temporary workers.') }
  finally { loading.value = false }
}
function startNew() {
  Object.assign(form, blank(), { branch_id: branches.value.length === 1 ? branches.value[0]!.id : '' })
  editingId.value = null
  editingCurrency.value = ''
  showForm.value = true
}
function edit(worker: TemporaryWorker) {
  Object.assign(form, { name: worker.name, branch_id: worker.branch_id, phone: worker.phone || '', job_description: worker.job_description || '', wage_basis: worker.wage_basis, rate: worker.rate, status: worker.status })
  editingId.value = worker.id
  editingCurrency.value = worker.currency
  showForm.value = true
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
async function save() {
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    if (editingId.value) await api.put(`/temporary-workers/${editingId.value}`, form)
    else await api.post('/temporary-workers', form)
    showForm.value = false
    notice.value = 'Worker saved.'
    await load()
  } catch (cause) { error.value = apiErrorMessage(cause, 'Unable to save worker.') }
  finally { saving.value = false }
}
await load()
</script>

<style scoped>
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; }
.panel { padding: 24px; min-width: 0; }
.filters, .button-row { display: flex; align-items: end; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
.filters label { display: grid; gap: 6px; }
.wide { grid-column: 1 / -1; }
.table-wrap { overflow-x: auto; max-width: 100%; margin-bottom: 20px; }
table { min-width: 980px; width: 100%; }
small { display: block; margin-top: 6px; color: var(--color-muted); }
td:last-child { min-width: 160px; } td button { display: block; margin-top: 8px; }
</style>
