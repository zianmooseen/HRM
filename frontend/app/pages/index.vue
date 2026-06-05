<template>
  <section class="page dashboard-page">
    <header class="dashboard-hero">
      <div>
        <h1>Dashboard</h1>
        <p class="muted">Plan, prioritize, and manage UAE HR operations with clarity.</p>
      </div>
      <div class="hero-actions">
        <NuxtLink class="primary-action" to="/employees/create">Add Employee</NuxtLink>
        <NuxtLink class="ghost-action" to="/reports/compliance">Compliance Reports</NuxtLink>
      </div>
    </header>

    <p v-if="loading" class="loading-state">Loading dashboard...</p>
    <p v-else-if="error" class="error">{{ error }}</p>

    <template v-else-if="dashboard">
      <section class="metric-grid">
        <article class="metric-card metric-card-feature">
          <div class="metric-topline">
            <span>Active Employees</span>
            <NuxtLink to="/employees" aria-label="Open employees">></NuxtLink>
          </div>
          <strong>{{ dashboard.employee_counts.active }}</strong>
          <small>{{ dashboard.employee_counts.total }} total employee records</small>
        </article>

        <article class="metric-card">
          <div class="metric-topline">
            <span>Onboarding</span>
            <NuxtLink to="/onboarding" aria-label="Open onboarding">></NuxtLink>
          </div>
          <strong>{{ dashboard.employee_counts.onboarding }}</strong>
          <small>Employees in setup</small>
        </article>

        <article class="metric-card">
          <div class="metric-topline">
            <span>Pending Leave</span>
            <NuxtLink to="/leave" aria-label="Open leave">></NuxtLink>
          </div>
          <strong>{{ dashboard.leave.pending_requests }}</strong>
          <small>Requests awaiting action</small>
        </article>

        <article class="metric-card">
          <div class="metric-topline">
            <span>Attendance Today</span>
            <NuxtLink to="/attendance" aria-label="Open attendance">></NuxtLink>
          </div>
          <strong>{{ dashboard.attendance_today.recorded }}</strong>
          <small>{{ dashboard.attendance_today.present }} present, {{ dashboard.attendance_today.late }} late</small>
        </article>
      </section>

      <section class="dashboard-layout">
        <div class="main-column">
          <section class="panel analytics-panel">
            <header>
              <div>
                <h2>HR Analytics</h2>
                <p class="muted">Today, leave, and lifecycle signals.</p>
              </div>
              <NuxtLink to="/attendance">View attendance</NuxtLink>
            </header>
            <div class="bar-chart" aria-label="HR activity chart">
              <div v-for="bar in analyticsBars" :key="bar.label" class="bar-column">
                <span class="bar" :class="{ striped: bar.striped }" :style="{ height: `${bar.height}%` }"></span>
                <small>{{ bar.label }}</small>
              </div>
            </div>
          </section>

          <section class="panel">
            <header>
              <div>
                <h2>Contract Reminders</h2>
                <p class="muted">Fixed-term contracts needing review in the next 60 days.</p>
              </div>
              <NuxtLink to="/employees">View employees</NuxtLink>
            </header>

            <div v-if="dashboard.alerts.contracts_expiring.length > 0" class="reminder-list">
              <NuxtLink
                v-for="employee in dashboard.alerts.contracts_expiring"
                :key="employee.id"
                class="reminder-row"
                :to="`/employees/${employee.id}`"
              >
                <span>
                  <strong>{{ employee.display_name }}</strong>
                  <small>Contract ends {{ employee.contract_end_date }}</small>
                </span>
                <em>{{ employee.days_remaining }} days</em>
              </NuxtLink>
            </div>
            <p v-else class="muted empty-state">No contracts expiring in the next 60 days.</p>
          </section>

          <section class="panel activity-panel">
            <header>
              <div>
                <h2>Recent Activity</h2>
                <p class="muted">Latest company audit events.</p>
              </div>
              <NuxtLink to="/platform/audit-logs">Audit logs</NuxtLink>
            </header>
            <div v-if="dashboard.recent_audit_logs.length > 0" class="activity-list">
              <article v-for="log in dashboard.recent_audit_logs" :key="`${log.action}-${log.auditable_id}-${log.created_at}`" class="activity-row">
                <span class="activity-dot"></span>
                <div>
                  <strong>{{ actionLabel(log.action) }}</strong>
                  <small>{{ log.auditable_type }} #{{ log.auditable_id }} - {{ log.created_at }}</small>
                </div>
              </article>
            </div>
            <p v-else class="muted empty-state">No audit events recorded yet.</p>
          </section>

          <section class="panel qa-panel">
            <header>
              <div>
                <h2>Q&A</h2>
                <p class="muted">Common dashboard questions and the next action to take.</p>
              </div>
            </header>

            <div class="qa-list">
              <details v-for="item in dashboardQuestions" :key="item.question">
                <summary>{{ item.question }}</summary>
                <p>{{ item.answer }}</p>
                <NuxtLink :to="item.to">{{ item.action }}</NuxtLink>
              </details>
            </div>
          </section>
        </div>

        <aside class="side-column">
          <section class="panel focus-panel">
            <h2>Payroll</h2>
            <strong>{{ payrollStatus }}</strong>
            <p class="muted">{{ payrollPeriodLabel }}</p>
            <NuxtLink to="/payroll">Open payroll</NuxtLink>
          </section>

          <section class="panel project-list">
            <header>
              <h2>Operations</h2>
              <NuxtLink to="/leave">Review</NuxtLink>
            </header>
            <div class="operation-row">
              <span class="operation-icon icon-leave"></span>
              <div>
                <strong>Approved Leave</strong>
                <small>{{ dashboard.leave.approved_this_month }} this month</small>
              </div>
            </div>
            <div class="operation-row">
              <span class="operation-icon icon-absent"></span>
              <div>
                <strong>Absent Today</strong>
                <small>{{ dashboard.attendance_today.absent }} employee records</small>
              </div>
            </div>
            <div class="operation-row">
              <span class="operation-icon icon-remote"></span>
              <div>
                <strong>Remote Today</strong>
                <small>{{ dashboard.attendance_today.remote }} employee records</small>
              </div>
            </div>
            <div class="operation-row">
              <span class="operation-icon icon-onleave"></span>
              <div>
                <strong>On Leave Today</strong>
                <small>{{ dashboard.attendance_today.on_leave }} employee records</small>
              </div>
            </div>
          </section>

          <section class="compliance-card">
            <h2>Emiratisation</h2>
            <strong>{{ emiratisationStatus }}</strong>
            <p>{{ emiratisationDetail }}</p>
            <NuxtLink to="/settings/emiratisation">Open compliance</NuxtLink>
          </section>

          <section class="panel document-panel">
            <header>
              <h2>Documents</h2>
              <NuxtLink to="/employees">Files</NuxtLink>
            </header>
            <div v-if="dashboard.alerts.documents_expiring.length > 0" class="document-list">
              <NuxtLink
                v-for="document in dashboard.alerts.documents_expiring.slice(0, 5)"
                :key="document.id"
                class="document-row"
                :to="`/employees/${document.employee_id}`"
              >
                <span>{{ document.title }}</span>
                <small>{{ document.expiry_date }}</small>
              </NuxtLink>
            </div>
            <p v-else class="muted empty-state">No documents expiring soon.</p>
          </section>
        </aside>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface DashboardSummary {
  employee_counts: {
    active: number
    onboarding: number
    terminated: number
    draft: number
    total: number
  }
  attendance_today: {
    date: string
    recorded: number
    present: number
    late: number
    absent: number
    on_leave: number
    remote: number
  }
  leave: {
    pending_requests: number
    approved_this_month: number
  }
  payroll: {
    latest_period: {
      id: number
      period_start: string
      period_end: string
      pay_date: string
      status: string
    } | null
  }
  compliance: {
    latest_emiratisation_snapshot: {
      snapshot_date: string
      compliance_status: string
      required_uae_citizens: number
      missing_uae_citizens: number
      estimated_contribution_amount: string | number
    } | null
  }
  alerts: {
    contracts_expiring: Array<{
      id: number
      display_name: string
      contract_end_date: string
      days_remaining: number
    }>
    documents_expiring: Array<{
      id: number
      employee_id: number
      employee_name: string | null
      title: string
      document_type: string
      expiry_date: string
      days_remaining: number
    }>
  }
  recent_audit_logs: Array<{
    created_at: string
    action: string
    auditable_type: string
    auditable_id: number | null
    actor_user_id: number | null
  }>
}

const auth = useAuthStore()
const api = useApiClient()
const dashboard = ref<DashboardSummary | null>(null)
const loading = ref(true)
const error = ref('')

const payrollStatus = computed(() => {
  const status = dashboard.value?.payroll.latest_period?.status
  return status ? label(status) : 'Not run'
})
const payrollPeriodLabel = computed(() => {
  const period = dashboard.value?.payroll.latest_period

  return period ? `${period.period_start} to ${period.period_end}` : 'No payroll period yet'
})
const emiratisationStatus = computed(() => {
  const status = dashboard.value?.compliance.latest_emiratisation_snapshot?.compliance_status
  return status ? label(status) : 'Needs review'
})
const emiratisationDetail = computed(() => {
  const snapshot = dashboard.value?.compliance.latest_emiratisation_snapshot

  if (!snapshot) {
    return 'No saved snapshot'
  }

  return `${snapshot.missing_uae_citizens} missing of ${snapshot.required_uae_citizens} required`
})
const analyticsBars = computed(() => {
  const attendance = dashboard.value?.attendance_today
  const leave = dashboard.value?.leave

  return [
    { label: 'P', height: percent(attendance?.present, attendance?.recorded), striped: false },
    { label: 'L', height: percent(attendance?.late, attendance?.recorded), striped: false },
    { label: 'A', height: percent(attendance?.absent, attendance?.recorded), striped: true },
    { label: 'R', height: percent(attendance?.remote, attendance?.recorded), striped: false },
    { label: 'O', height: percent(attendance?.on_leave, attendance?.recorded), striped: true },
    { label: 'V', height: Math.min((leave?.approved_this_month ?? 0) * 8 + 26, 86), striped: false },
    { label: 'Q', height: Math.min((leave?.pending_requests ?? 0) * 10 + 22, 90), striped: true },
  ]
})
const dashboardQuestions = computed(() => [
  {
    question: 'How do I handle pending leave?',
    answer: `${dashboard.value?.leave.pending_requests ?? 0} leave request(s) are waiting for review. Open Leave to approve, reject, or inspect balances before deciding.`,
    action: 'Review leave requests',
    to: '/leave',
  },
  {
    question: 'How do I check attendance issues?',
    answer: `${dashboard.value?.attendance_today.absent ?? 0} absent, ${dashboard.value?.attendance_today.late ?? 0} late, and ${dashboard.value?.attendance_today.remote ?? 0} remote record(s) are visible today. Open Attendance to filter or correct records.`,
    action: 'Open attendance',
    to: '/attendance',
  },
  {
    question: 'What should I do with expiring contracts?',
    answer: `${dashboard.value?.alerts.contracts_expiring.length ?? 0} contract(s) need review in the next 60 days. Open the employee profile to extend the contract or prepare termination.`,
    action: 'View employees',
    to: '/employees',
  },
  {
    question: 'How do I follow up expiring documents?',
    answer: `${dashboard.value?.alerts.documents_expiring.length ?? 0} document(s) are expiring soon. Open the employee file and upload a replacement document when available.`,
    action: 'Open employee files',
    to: '/employees',
  },
  {
    question: 'How do I continue payroll work?',
    answer: `The latest payroll status is ${payrollStatus.value}. Open Payroll to run, review, approve, or prepare WPS output.`,
    action: 'Open payroll',
    to: '/payroll',
  },
  {
    question: 'How do I review Emiratisation risk?',
    answer: `Current Emiratisation status is ${emiratisationStatus.value}. Open Emiratisation to review missing UAE citizen counts and update snapshots.`,
    action: 'Open Emiratisation',
    to: '/settings/emiratisation',
  },
])

onMounted(loadDashboard)

async function loadDashboard() {
  loading.value = true
  error.value = ''

  try {
    // Feature flow step 1: the dashboard consumes a single backend summary so module counts stay consistent.
    const response = await api.get<{ dashboard: DashboardSummary }>('/dashboard')
    dashboard.value = response.data.dashboard
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to load dashboard.')
  } finally {
    loading.value = false
  }
}

function actionLabel(value: string) {
  return label(value.replace(/\./g, ' '))
}

function label(value: string) {
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

function percent(value = 0, total = 0) {
  if (!total) {
    return 34
  }

  return Math.max(24, Math.min(88, Math.round((value / total) * 88)))
}
</script>

<style scoped>
.dashboard-page {
  gap: 24px;
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.muted {
  margin: 8px 0 0;
  color: #7d8782;
  font-size: 1.05rem;
  font-weight: 400;
  line-height: 1.35;
}

.dashboard-hero {
  align-items: flex-start;
}

.dashboard-hero h1 {
  color: #050908;
  font-size: clamp(2.25rem, 3.4vw, 3rem);
  font-weight: 500;
  letter-spacing: 0;
  line-height: 0.98;
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
}

.primary-action,
.ghost-action {
  min-height: 52px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 0 28px;
  font-size: 1rem;
  font-weight: 500;
}

.primary-action {
  background: linear-gradient(135deg, #063b24, #238457);
  color: #ffffff;
}

.ghost-action {
  border: 1px solid #064027;
  color: #063b24;
}

.metric-grid {
  display: grid;
  grid-template-columns: 1.25fr repeat(3, 1fr);
  gap: 16px;
}

.metric-card,
.panel {
  display: grid;
  gap: 14px;
  border: 1px solid rgba(14, 50, 35, 0.05);
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 18px 50px rgba(20, 33, 27, 0.06);
  padding: 26px;
}

.metric-card-feature {
  background:
    radial-gradient(circle at 72% 76%, rgba(110, 226, 154, 0.2), transparent 34%),
    linear-gradient(135deg, #08391f, #20804f);
  color: #ffffff;
}

.metric-topline {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  color: inherit;
  font-size: 1.05rem;
  font-weight: 500;
}

.metric-topline a {
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  border: 1px solid currentColor;
  border-radius: 999px;
  background: #ffffff;
  color: #050908;
  font-weight: 800;
}

.metric-card strong {
  color: #060b09;
  font-size: clamp(3rem, 4.8vw, 4.15rem);
  font-weight: 500;
  letter-spacing: 0;
  line-height: 0.95;
}

.metric-card-feature strong,
.metric-card-feature small {
  color: #ffffff;
}

.metric-card small {
  color: #2d7a50;
  font-size: 0.94rem;
  font-weight: 400;
}

.dashboard-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(300px, 360px);
  gap: 18px;
  align-items: start;
}

.main-column,
.side-column {
  display: grid;
  gap: 18px;
}

.panel > header {
  align-items: center;
}

.panel h2,
.compliance-card h2 {
  margin: 0;
  color: #050908;
  font-size: 1.35rem;
  font-weight: 500;
  line-height: 1.15;
}

.panel header a,
.focus-panel a,
.compliance-card a {
  color: #064027;
  font-size: 0.96rem;
  font-weight: 500;
}

.analytics-panel {
  min-height: 250px;
}

.bar-chart {
  min-height: 160px;
  display: grid;
  grid-template-columns: repeat(7, minmax(32px, 1fr));
  align-items: end;
  gap: 24px;
  padding-top: 12px;
}

.bar-column {
  display: grid;
  gap: 8px;
  justify-items: center;
}

.bar {
  width: min(62px, 100%);
  min-height: 42px;
  display: block;
  border-radius: 999px;
  background: linear-gradient(180deg, #0c4a2d, #2b9660);
}

.bar.striped {
  background:
    repeating-linear-gradient(135deg, #8fa59a 0 4px, transparent 4px 10px),
    #f5f7f4;
}

.bar-column small {
  color: #7d8782;
  font-size: 0.95rem;
  font-weight: 400;
}

.reminder-list,
.activity-list,
.document-list,
.project-list,
.qa-list {
  display: grid;
  gap: 12px;
}

.qa-list details {
  border-bottom: 1px solid #edf1ee;
  padding-bottom: 12px;
}

.qa-list summary {
  color: #050908;
  cursor: pointer;
  font-size: 1rem;
  font-weight: 500;
  line-height: 1.25;
}

.qa-list p {
  margin: 8px 0 10px;
  color: #6f7b75;
  font-size: 0.9rem;
  line-height: 1.45;
}

.qa-list a {
  color: #064027;
  font-size: 0.9rem;
  font-weight: 500;
}

.reminder-row,
.activity-row,
.operation-row,
.document-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.reminder-row,
.document-row {
  justify-content: space-between;
  border-bottom: 1px solid #edf1ee;
  padding-bottom: 12px;
}

.reminder-row span,
.document-row {
  min-width: 0;
}

.reminder-row strong,
.activity-row strong,
.operation-row strong,
.document-row span {
  display: block;
  color: #050908;
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.25;
}

.reminder-row small,
.activity-row small,
.operation-row small,
.document-row small {
  color: #8a918d;
  font-size: 0.86rem;
  font-weight: 400;
}

.reminder-row em {
  border-radius: 999px;
  background: #eef8f1;
  color: #226f47;
  font-size: 0.86rem;
  font-weight: 400;
  font-style: normal;
  padding: 5px 10px;
  white-space: nowrap;
}

.activity-dot,
.operation-icon {
  width: 34px;
  height: 34px;
  flex: 0 0 auto;
  border-radius: 12px;
  background: #0f6d42;
}

.activity-dot {
  width: 10px;
  height: 10px;
  border-radius: 999px;
}

.operation-icon {
  background: conic-gradient(from 45deg, #0f6d42, #6fceb0, #0f6d42);
}

.icon-absent {
  background: conic-gradient(from 90deg, #d9a229, #f5d17a, #d9a229);
}

.icon-remote {
  background: conic-gradient(from 90deg, #227b93, #6cd4d6, #227b93);
}

.icon-onleave {
  background: conic-gradient(from 90deg, #5b3f9b, #b9a8ee, #5b3f9b);
}

.focus-panel strong,
.compliance-card strong {
  color: #064027;
  font-size: 1.85rem;
  font-weight: 500;
  line-height: 1.1;
}

.focus-panel {
  background: #ffffff;
}

.compliance-card {
  display: grid;
  gap: 10px;
  border-radius: 8px;
  background:
    radial-gradient(circle at 90% 20%, rgba(97, 180, 122, 0.4), transparent 30%),
    linear-gradient(135deg, #052414, #0e633b);
  color: #ffffff;
  padding: 26px;
  box-shadow: 0 18px 50px rgba(20, 33, 27, 0.08);
}

.compliance-card h2,
.compliance-card strong,
.compliance-card p,
.compliance-card a {
  color: #ffffff;
}

.empty-state,
.loading-state {
  border-radius: 8px;
  background: #ffffff;
  padding: 20px;
}

@media (max-width: 1180px) {
  .metric-grid,
  .dashboard-layout {
    grid-template-columns: 1fr 1fr;
  }

  .metric-card-feature,
  .main-column {
    grid-column: 1 / -1;
  }
}

@media (max-width: 760px) {
  .dashboard-hero {
    display: grid;
  }

  .hero-actions,
  .metric-grid,
  .dashboard-layout {
    grid-template-columns: 1fr;
  }

  .hero-actions {
    display: grid;
  }

  .bar-chart {
    gap: 10px;
  }
}
</style>
