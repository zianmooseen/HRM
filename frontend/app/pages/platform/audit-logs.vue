<template>
  <section class="page audit-page">
    <header>
      <div>
        <h1>Audit Logs</h1>
        <p class="muted">Search company activity and understand what changed.</p>
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
        <div v-else class="audit-table-scroll" role="region" aria-label="Audit activity" tabindex="0">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Module</th>
              <th>Action</th>
              <th>Record</th>
              <th>Actor</th>
              <th class="audit-action-column">Details</th>
            </tr>
            <tr class="column-filter-row">
              <th><TableColumnFilter v-model="columnFilters.date" label="Filter audit date" /></th>
              <th><TableColumnFilter v-model="columnFilters.module" label="Filter audit module" /></th>
              <th><TableColumnFilter v-model="columnFilters.action" label="Filter audit action" /></th>
              <th><TableColumnFilter v-model="columnFilters.record" label="Filter audit record" /></th>
              <th><TableColumnFilter v-model="columnFilters.actor" label="Filter audit actor" /></th>
              <th class="audit-action-column" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in filteredLogs" :key="log.id" :class="{ selected: selectedLog?.id === log.id }">
              <td>{{ formatDate(log.created_at) }}</td>
              <td>{{ moduleLabel(log.module) }}</td>
              <td>{{ actionLabel(log.action) }}</td>
              <td>{{ log.auditable_type }} #{{ log.auditable_id || '-' }}</td>
              <td>{{ log.actor_user_id || 'System' }}</td>
              <td class="audit-action-column"><button type="button" class="secondary" :aria-label="`Details for event ${log.id}`" @click="selectLog(log.id)">Details</button></td>
            </tr>
            <tr v-if="filteredLogs.length === 0">
              <td colspan="6">No audit logs match these filters.</td>
            </tr>
          </tbody>
        </table>
        </div>

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
            <p class="muted">Select an event to see what happened.</p>
          </div>
        </header>

        <p v-if="detailLoading">Loading event...</p>
        <p v-else-if="detailError" class="error">{{ detailError }}</p>
        <template v-else-if="selectedLog">
          <p class="event-explanation">{{ explanation.summary }}</p>
          <dl class="details-grid">
            <div>
              <dt>Action</dt>
              <dd>{{ actionLabel(selectedLog.action) }}</dd>
            </div>
            <div>
              <dt>Record</dt>
              <dd>{{ selectedLog.reference_labels?._record?.[String(selectedLog.auditable_id)] || `${selectedLog.auditable_type} #${selectedLog.auditable_id || '-'}` }}</dd>
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
            <div class="detail-heading">
              <h3>What was recorded</h3>
              <button type="button" class="secondary" :aria-expanded="showRaw" aria-controls="raw-audit-snapshots" @click="showRaw = !showRaw">{{ showRaw ? 'Hide raw' : 'Raw' }}</button>
            </div>
            <ul v-if="explanation.details.length" class="event-facts">
              <li v-for="(detail, index) in explanation.details" :key="index">{{ detail }}</li>
            </ul>
            <p v-else class="muted">No additional field changes were recorded for this event.</p>
            <p v-if="Object.keys(selectedLog.reference_labels || {}).length" class="muted">Names reflect current records. Raw preserves the recorded IDs.</p>
            <div v-if="showRaw" id="raw-audit-snapshots">
              <h3>Before</h3>
              <pre>{{ json(selectedLog.before) }}</pre>
              <h3>After</h3>
              <pre>{{ json(selectedLog.after) }}</pre>
            </div>
          </template>
        </template>
        <p v-else class="muted">No event selected.</p>
      </aside>
    </section>
  </section>
</template>

<script setup lang="ts">
import { explainAuditEvent, type AuditEmployeeContext } from '../../utils/audit-explanation'
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
  reference_labels?: Record<string, Record<string, string>>
  employees?: AuditEmployeeContext[]
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
const { filters: columnFilters, filteredRows: filteredLogs } = useTableColumnFilters(
  logs,
  [
    { key: 'date', value: log => formatDate(log.created_at) },
    { key: 'module', value: log => moduleLabel(log.module) },
    { key: 'action', value: log => actionLabel(log.action) },
    { key: 'record', value: log => `${log.auditable_type} #${log.auditable_id || '-'}` },
    { key: 'actor', value: log => log.actor_user_id || 'System' },
  ],
)
const selectedLog = ref<AuditLogRow | null>(null)
const showRaw = ref(false)
const explanation = computed(() => selectedLog.value ? explainAuditEvent(selectedLog.value) : { summary: '', details: [] })
let detailRequest = 0
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
  detailRequest++
  selectedLog.value = null
  detailLoading.value = false
  detailError.value = ''
  showRaw.value = false

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
  const request = ++detailRequest
  showRaw.value = false
  selectedLog.value = null
  detailLoading.value = true
  detailError.value = ''

  try {
    const response = await api.get<{ audit_log: AuditLogRow }>(`/audit-logs/${id}`)
    if (request === detailRequest) selectedLog.value = response.data.audit_log
  } catch (err) {
    if (request === detailRequest) detailError.value = apiErrorMessage(err, 'Unable to load audit log detail.')
  } finally {
    if (request === detailRequest) detailLoading.value = false
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
  color: var(--color-muted);
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
  grid-template-columns: minmax(0, 1fr) minmax(300px, 360px);
  align-items: start;
  gap: 20px;
}

.panel {
  min-width: 0;
  border: 1px solid var(--color-border);
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
  color: var(--color-muted);
  font-size: 13px;
}

.details-grid dd {
  margin: 4px 0 0;
  color: var(--color-text);
  font-weight: 700;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 16px;
}

pre {
  max-height: 280px;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  background: #f6f8fa;
  padding: 12px;
  font-size: 13px;
  white-space: pre-wrap;
  word-break: break-word;
}

@media (max-width: 1250px) {
  .audit-layout {
    grid-template-columns: 1fr;
  }
}
/* Keep wide columns inside Activity and pin its Details buttons while scrolling. */
.audit-table-scroll { max-width: 100%; overflow-x: auto; margin-top: 16px; border: 1px solid var(--color-border); border-radius: 12px; }
.audit-page .audit-table-scroll > table { display: table; min-width: 780px; border: 0; border-radius: 0; overflow: visible; }
.audit-page .audit-action-column { position: sticky; right: 0; z-index: 1; width: 110px; min-width: 110px; background: white; border-left: 1px solid var(--color-border); }
.audit-page th.audit-action-column { background: var(--color-surface-muted); }
.audit-page .selected td { background: var(--color-accent-soft); }
.details-grid > div { min-width: 0; }
.details-grid dd, .event-explanation, .event-facts { overflow-wrap: anywhere; }
.event-explanation { padding: 16px; background: var(--color-accent-soft); border-radius: 10px; font-size: 1rem; line-height: 1.6; }
.detail-heading { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
.event-facts { display: grid; gap: 12px; padding-left: 20px; line-height: 1.6; }
</style>
