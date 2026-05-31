<template>
  <section class="page">
    <header>
      <div>
        <h1>Compliance Reports</h1>
        <p class="muted">Review company compliance posture and export supporting records for audit review.</p>
      </div>
    </header>

    <p v-if="loading">Loading compliance report...</p>
    <p v-else-if="error" class="error">{{ error }}</p>

    <template v-else-if="summary">
      <section class="metric-grid">
        <article>
          <span>Company</span>
          <strong>{{ summary.company.name }}</strong>
          <small>{{ categoryLabel(summary.company.emiratisation_category) }}</small>
        </article>
        <article>
          <span>Public holidays</span>
          <strong>{{ summary.public_holiday_count }}</strong>
          <small>Active holidays</small>
        </article>
        <article>
          <span>Emiratisation</span>
          <strong>{{ statusLabel(summary.latest_emiratisation_snapshot?.compliance_status || 'needs_review') }}</strong>
          <small>Latest saved snapshot</small>
        </article>
        <article>
          <span>Payroll basis</span>
          <strong>{{ payrollDivisorLabel(summary.settings?.payroll_day_divisor) }}</strong>
          <small>Daily wage divisor</small>
        </article>
      </section>

      <section class="report-panel">
        <header>
          <div>
            <h2>CSV Exports</h2>
            <p class="muted">Exports are scoped to the current company and require your signed-in session.</p>
          </div>
        </header>
        <div class="export-grid">
          <a v-for="exportType in summary.exports" :key="exportType" :href="downloadUrl(exportType)" download>
            {{ exportLabel(exportType) }}
          </a>
        </div>
      </section>

      <section class="report-panel">
        <h2>Policy Snapshot</h2>
        <dl v-if="summary.settings" class="details-grid">
          <div>
            <dt>Annual leave accrual</dt>
            <dd>{{ label(summary.settings.annual_leave_accrual_method) }}</dd>
          </div>
          <div>
            <dt>Carry forward</dt>
            <dd>{{ summary.settings.annual_leave_carry_forward_allowed === 'yes' ? 'Allowed' : 'Not allowed' }}</dd>
          </div>
          <div>
            <dt>Carry forward limit</dt>
            <dd>{{ summary.settings.annual_leave_max_carry_forward_days || 'Not set' }}</dd>
          </div>
          <div>
            <dt>Public holidays count as annual leave</dt>
            <dd>{{ summary.settings.public_holidays_count_as_annual_leave === 'yes' ? 'Yes' : 'No' }}</dd>
          </div>
          <div>
            <dt>Sick leave medical certificate</dt>
            <dd>{{ summary.settings.sick_leave_requires_medical_certificate === 'yes' ? 'Required' : 'Not required' }}</dd>
          </div>
          <div>
            <dt>Sick leave notification days</dt>
            <dd>{{ summary.settings.sick_leave_notification_days }}</dd>
          </div>
          <div>
            <dt>Emiratisation monitoring</dt>
            <dd>{{ summary.settings.emiratisation_monitoring_enabled === 'yes' ? 'Enabled' : 'Disabled' }}</dd>
          </div>
        </dl>
        <p v-else class="muted">Compliance settings have not been configured yet.</p>
      </section>

      <section class="report-panel">
        <h2>Latest Emiratisation Snapshot</h2>
        <dl v-if="summary.latest_emiratisation_snapshot" class="details-grid">
          <div>
            <dt>Snapshot date</dt>
            <dd>{{ summary.latest_emiratisation_snapshot.snapshot_date }}</dd>
          </div>
          <div>
            <dt>Active workers</dt>
            <dd>{{ summary.latest_emiratisation_snapshot.total_active_workers }}</dd>
          </div>
          <div>
            <dt>Skilled workers</dt>
            <dd>{{ summary.latest_emiratisation_snapshot.total_skilled_workers }}</dd>
          </div>
          <div>
            <dt>Skilled UAE citizens</dt>
            <dd>{{ summary.latest_emiratisation_snapshot.total_skilled_uae_citizens }}</dd>
          </div>
          <div>
            <dt>Required UAE citizens</dt>
            <dd>{{ summary.latest_emiratisation_snapshot.required_uae_citizens }}</dd>
          </div>
          <div>
            <dt>Missing UAE citizens</dt>
            <dd>{{ summary.latest_emiratisation_snapshot.missing_uae_citizens }}</dd>
          </div>
          <div>
            <dt>Estimated exposure</dt>
            <dd>{{ money(summary.latest_emiratisation_snapshot.estimated_contribution_amount) }}</dd>
          </div>
        </dl>
        <p v-else class="muted">No Emiratisation snapshot has been saved yet.</p>
      </section>

      <section class="report-panel">
        <h2>Recent Compliance Audit History</h2>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Action</th>
              <th>Record</th>
              <th>Actor</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in summary.recent_audit_logs" :key="`${log.action}-${log.auditable_id}-${log.created_at}`">
              <td>{{ log.created_at }}</td>
              <td>{{ actionLabel(log.action) }}</td>
              <td>{{ log.auditable_type }} #{{ log.auditable_id }}</td>
              <td>{{ log.actor_user_id || 'System' }}</td>
            </tr>
            <tr v-if="summary.recent_audit_logs.length === 0">
              <td colspan="4">No compliance audit events recorded yet.</td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface ReportSettings {
  payroll_day_divisor: string
  annual_leave_accrual_method: string
  annual_leave_carry_forward_allowed: string
  annual_leave_max_carry_forward_days: string | null
  public_holidays_count_as_annual_leave: string
  sick_leave_requires_medical_certificate: string
  sick_leave_notification_days: number
  emiratisation_monitoring_enabled: string
  updated_at: string
}

interface EmiratisationSnapshot {
  snapshot_date: string
  total_active_workers: number
  total_skilled_workers: number
  total_active_uae_citizens: number
  total_skilled_uae_citizens: number
  required_uae_citizens: number
  missing_uae_citizens: number
  estimated_contribution_amount: string | number
  compliance_status: string
}

interface AuditRow {
  created_at: string
  action: string
  auditable_type: string
  auditable_id: number | null
  actor_user_id: number | null
}

interface ComplianceReportSummary {
  company: {
    id: number
    name: string
    emiratisation_applicable: boolean
    emiratisation_category: string
    economic_sector_code: string | null
    mohre_establishment_number: string | null
  }
  settings: ReportSettings | null
  public_holiday_count: number
  latest_emiratisation_snapshot: EmiratisationSnapshot | null
  recent_audit_logs: AuditRow[]
  exports: string[]
}

const api = useApiClient()
const config = useRuntimeConfig()
const loading = ref(true)
const error = ref('')
const summary = ref<ComplianceReportSummary | null>(null)

onMounted(loadReport)

async function loadReport() {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get<{ summary: ComplianceReportSummary }>('/compliance/reports')
    summary.value = response.data.summary
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to load compliance reports.')
  } finally {
    loading.value = false
  }
}

function downloadUrl(type: string) {
  return `${config.public.apiBaseUrl}/compliance/reports/export?type=${encodeURIComponent(type)}`
}

function exportLabel(type: string) {
  const labels: Record<string, string> = {
    settings: 'Compliance settings',
    public_holidays: 'Public holidays',
    emiratisation: 'Emiratisation snapshots',
    audit: 'Audit history',
  }

  return labels[type] || label(type)
}

function payrollDivisorLabel(value?: string) {
  const labels: Record<string, string> = {
    calendar_30: '30-day calendar',
    actual_calendar_days: 'Actual calendar days',
    working_days: 'Working days',
  }

  return value ? labels[value] || label(value) : 'Not configured'
}

function categoryLabel(value: string) {
  const labels: Record<string, string> = {
    large_50_plus: 'Large company, 50+ employees',
    selected_20_to_49: 'Selected 20-49 employee sector',
    not_applicable: 'Not applicable',
  }

  return labels[value] || label(value)
}

function statusLabel(value: string) {
  const labels: Record<string, string> = {
    compliant: 'Compliant',
    non_compliant: 'Non-compliant',
    at_risk: 'At risk',
    needs_review: 'Needs review',
    not_applicable: 'Not applicable',
  }

  return labels[value] || label(value)
}

function actionLabel(value: string) {
  return label(value.replace(/\./g, ' '))
}

function label(value: string) {
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

function money(value: string | number) {
  return new Intl.NumberFormat('en-AE', {
    style: 'currency',
    currency: 'AED',
  }).format(Number(value || 0))
}
</script>

<style scoped>
.muted {
  margin: 6px 0 0;
  color: #5d6a72;
}

.metric-grid,
.export-grid,
.details-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
}

.metric-grid article,
.report-panel {
  border: 1px solid #d8e0e4;
  border-radius: 8px;
  background: #ffffff;
  padding: 18px;
}

.metric-grid span,
.details-grid dt {
  color: #5d6a72;
  font-size: 13px;
}

.metric-grid strong,
.details-grid dd {
  display: block;
  margin: 4px 0 0;
  color: #102027;
  font-size: 20px;
  font-weight: 700;
}

.metric-grid small {
  display: block;
  margin-top: 4px;
  color: #5d6a72;
}

.report-panel {
  margin-top: 20px;
}

.report-panel header {
  margin-bottom: 16px;
}

.export-grid a {
  display: grid;
  min-height: 44px;
  place-items: center;
  border: 1px solid #2f7d68;
  border-radius: 6px;
  color: #1f6f5b;
  font-weight: 700;
}

.details-grid div {
  min-width: 0;
}

.details-grid dd {
  font-size: 16px;
}
</style>
