<template>
  <section class="page">
    <header>
      <div>
        <h1>Audit Logs</h1>
        <p class="muted">Search company activity and inspect before/after snapshots for authorized records.</p>
      </div>
    </header>

    <form class="filter-grid" @submit.prevent="loadLogs(1)">
      <label>
        Module
        <select v-model="filters.module">
          <option value="">All modules</option>
          <option v-for="module in modules" :key="module" :value="module">{{ moduleLabel(module) }}</option>
        </select>
      </label>
      <label>
        Action contains
        <input v-model="filters.action" placeholder="created, approved, payroll">
      </label>
      <label>
        Employee ID
        <input v-model="filters.employee_id" inputmode="numeric">
      </label>
      <label>
        Actor user ID
        <input v-model="filters.actor_user_id" inputmode="numeric">
      </label>
      <label>
        From
        <input v-model="filters.date_from" type="date">
      </label>
      <label>
        To
        <input v-model="filters.date_to" type="date">
      </label>
      <div class="button-row">
        <button type="submit" :disabled="loading">{{ loading ? 'Searching...' : 'Search' }}</button>
        <button type="button" class="secondary" @click="resetFilters">Reset</button>
      </div>
    </form>

    <p v-if="error" class="error">{{ error }}</p>

    <section class="audit-layout">
      <section class="panel">
        <header>
          <div>
            <h2>Activity</h2>
            <p class="muted">{{ meta.total }} event{{ meta.total === 1 ? '' : 's' }}</p>
          </div>
        </header>

        <p v-if="loading">Loading audit logs...</p>
        <table v-else>
          <thead>
            <tr>
              <th>Date</th>
              <th>Module</th>
              <th>Action</th>
              <th>Record</th>
              <th>Actor</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs" :key="log.id">
              <td>{{ formatDate(log.created_at) }}</td>
              <td>{{ moduleLabel(log.module) }}</td>
              <td>{{ actionLabel(log.action) }}</td>
              <td>{{ log.auditable_type }} #{{ log.auditable_id || '-' }}</td>
              <td>{{ log.actor_user_id || 'System' }}</td>
              <td><button type="button" class="secondary" @click="selectLog(log.id)">Details</button></td>
            </tr>
            <tr v-if="logs.length === 0">
              <td colspan="6">No audit logs match these filters.</td>
            </tr>
          </tbody>
        </table>

        <div class="pagination">
          <button type="button" class="secondary" :disabled="meta.current_page <= 1 || loading" @click="loadLogs(meta.current_page - 1)">
            Previous
          </button>
          <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <button type="button" class="secondary" :disabled="meta.current_page >= meta.last_page || loading" @click="loadLogs(meta.current_page + 1)">
            Next
          </button>
        </div>
      </section>

      <aside class="panel detail-panel">
        <header>
          <div>
            <h2>Event Detail</h2>
            <p class="muted">Select an event to view its audit snapshot.</p>
          </div>
        </header>

        <p v-if="detailLoading">Loading event...</p>
        <p v-else-if="detailError" class="error">{{ detailError }}</p>
        <template v-else-if="selectedLog">
          <dl class="details-grid">
            <div>
              <dt>Action</dt>
              <dd>{{ actionLabel(selectedLog.action) }}</dd>
            </div>
            <div>
              <dt>Record</dt>
              <dd>{{ selectedLog.auditable_type }} #{{ selectedLog.auditable_id || '-' }}</dd>
            </div>
            <div>
              <dt>IP address</dt>
              <dd>{{ selectedLog.ip_address || '-' }}</dd>
            </div>
            <div>
              <dt>Created</dt>
              <dd>{{ formatDate(selectedLog.created_at) }}</dd>
            </div>
          </dl>

          <p v-if="!selectedLog.snapshots_visible" class="muted">
            Snapshot details are hidden because this event contains sensitive data outside your permissions.
          </p>
          <template v-else>
            <h3>Before</h3>
            <pre>{{ json(selectedLog.before) }}</pre>
            <h3>After</h3>
            <pre>{{ json(selectedLog.after) }}</pre>
          </template>
        </template>
        <p v-else class="muted">No event selected.</p>
      </aside>
    </section>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface AuditLogRow {
  id: number
  company_id: number
  actor_user_id: number | null
  action: string
  module: string
  auditable_type: string
  auditable_id: number | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
  snapshots_visible: boolean
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
}

interface AuditMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

const api = useApiClient()
const logs = ref<AuditLogRow[]>([])
const selectedLog = ref<AuditLogRow | null>(null)
const modules = ref<string[]>([])
const loading = ref(true)
const detailLoading = ref(false)
const error = ref('')
const detailError = ref('')
const meta = reactive<AuditMeta>({
  current_page: 1,
  per_page: 25,
  total: 0,
  last_page: 1,
})
const filters = reactive({
  module: '',
  action: '',
  employee_id: '',
  actor_user_id: '',
  date_from: '',
  date_to: '',
})

onMounted(() => loadLogs())

async function loadLogs(page = 1) {
  loading.value = true
  error.value = ''

  try {
    const query = new URLSearchParams({ page: String(page), per_page: String(meta.per_page) })
    Object.entries(filters).forEach(([key, value]) => {
      if (value) query.set(key, value)
    })

    const response = await api.get<{
      audit_logs: AuditLogRow[]
      meta: AuditMeta
      filters: { modules: string[] }
    }>(`/audit-logs?${query.toString()}`)

    logs.value = response.data.audit_logs
    Object.assign(meta, response.data.meta)
    modules.value = response.data.filters.modules
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to load audit logs.')
  } finally {
    loading.value = false
  }
}

async function selectLog(id: number) {
  detailLoading.value = true
  detailError.value = ''

  try {
    const response = await api.get<{ audit_log: AuditLogRow }>(`/audit-logs/${id}`)
    selectedLog.value = response.data.audit_log
  } catch (err) {
    detailError.value = apiErrorMessage(err, 'Unable to load audit log detail.')
  } finally {
    detailLoading.value = false
  }
}

function resetFilters() {
  Object.assign(filters, {
    module: '',
    action: '',
    employee_id: '',
    actor_user_id: '',
    date_from: '',
    date_to: '',
  })
  selectedLog.value = null
  loadLogs(1)
}

function moduleLabel(value: string) {
  return label(value)
}

function actionLabel(value: string) {
  return label(value.replace(/\./g, ' '))
}

function label(value: string) {
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

function formatDate(value: string) {
  return value ? new Date(value).toLocaleString() : '-'
}

function json(value: unknown) {
  return JSON.stringify(value, null, 2)
}
</script>

<style scoped>
.muted {
  margin: 6px 0 0;
  color: #5d6a72;
}

.filter-grid {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  align-items: end;
  margin-bottom: 20px;
}

.audit-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(320px, 0.8fr);
  gap: 20px;
}

.panel {
  min-width: 0;
  border: 1px solid #d8dee4;
  border-radius: 8px;
  background: #ffffff;
  padding: 16px;
}

.panel h2,
.detail-panel h3 {
  margin: 0;
}

.details-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  margin-bottom: 16px;
}

.details-grid dt {
  color: #5d6a72;
  font-size: 13px;
}

.details-grid dd {
  margin: 4px 0 0;
  color: #102027;
  font-weight: 700;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 16px;
}

pre {
  max-height: 280px;
  overflow: auto;
  border: 1px solid #d8dee4;
  border-radius: 6px;
  background: #f6f8fa;
  padding: 12px;
  font-size: 13px;
  white-space: pre-wrap;
  word-break: break-word;
}

@media (max-width: 980px) {
  .audit-layout {
    grid-template-columns: 1fr;
  }
}
</style>
