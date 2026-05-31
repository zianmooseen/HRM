<template>
  <section class="page">
    <header>
      <div>
        <h1>Dashboard</h1>
        <p class="muted">Signed in as {{ auth.user?.name }}</p>
      </div>
    </header>

    <p v-if="loading">Loading dashboard...</p>
    <p v-else-if="error" class="error">{{ error }}</p>

    <template v-else-if="dashboard">
      <section class="dashboard-grid">
        <article class="summary-card">
          <span>Active employees</span>
          <strong>{{ dashboard.employee_counts.active }}</strong>
          <small>{{ dashboard.employee_counts.total }} total records</small>
        </article>
        <article class="summary-card">
          <span>Onboarding</span>
          <strong>{{ dashboard.employee_counts.onboarding }}</strong>
          <small>Employees in setup</small>
        </article>
        <article class="summary-card">
          <span>Pending leave</span>
          <strong>{{ dashboard.leave.pending_requests }}</strong>
          <small>Requests awaiting action</small>
        </article>
        <article class="summary-card">
          <span>Attendance today</span>
          <strong>{{ dashboard.attendance_today.recorded }}</strong>
          <small>{{ dashboard.attendance_today.present }} present, {{ dashboard.attendance_today.late }} late</small>
        </article>
        <article class="summary-card">
          <span>Payroll</span>
          <strong>{{ payrollStatus }}</strong>
          <small>{{ payrollPeriodLabel }}</small>
        </article>
        <article class="summary-card">
          <span>Emiratisation</span>
          <strong>{{ emiratisationStatus }}</strong>
          <small>{{ emiratisationDetail }}</small>
        </article>
      </section>

      <section class="quick-links">
        <NuxtLink to="/employees">Employees</NuxtLink>
        <NuxtLink to="/attendance">Attendance</NuxtLink>
        <NuxtLink to="/leave">Leave</NuxtLink>
        <NuxtLink to="/payroll">Payroll</NuxtLink>
        <NuxtLink to="/reports/compliance">Compliance reports</NuxtLink>
      </section>

      <section class="panel-grid">
        <section class="panel">
          <header>
            <div>
              <h2>Contract Expiry Reminders</h2>
              <p class="muted">Fixed-term contracts needing review in the next 60 days.</p>
            </div>
            <NuxtLink to="/employees">View employees</NuxtLink>
          </header>

          <table v-if="dashboard.alerts.contracts_expiring.length > 0">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Contract end</th>
                <th>Days left</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="employee in dashboard.alerts.contracts_expiring" :key="employee.id">
                <td>{{ employee.display_name }}</td>
                <td>{{ employee.contract_end_date }}</td>
                <td>{{ employee.days_remaining }}</td>
                <td><NuxtLink :to="`/employees/${employee.id}`">Review</NuxtLink></td>
              </tr>
            </tbody>
          </table>
          <p v-else class="muted">No contracts expiring in the next 60 days.</p>
        </section>

        <section class="panel">
          <header>
            <div>
              <h2>Document Expiry Reminders</h2>
              <p class="muted">Passports, visas, labor cards, and other tracked files expiring soon.</p>
            </div>
            <NuxtLink to="/employees">Employee files</NuxtLink>
          </header>

          <table v-if="dashboard.alerts.documents_expiring.length > 0">
            <thead>
              <tr>
                <th>Document</th>
                <th>Employee</th>
                <th>Expiry</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="document in dashboard.alerts.documents_expiring" :key="document.id">
                <td>{{ document.title }}</td>
                <td>{{ document.employee_name || 'Unassigned' }}</td>
                <td>{{ document.expiry_date }}</td>
                <td><NuxtLink :to="`/employees/${document.employee_id}`">Open</NuxtLink></td>
              </tr>
            </tbody>
          </table>
          <p v-else class="muted">No employee documents expiring in the next 60 days.</p>
        </section>
      </section>

      <section class="panel-grid">
        <section class="panel">
          <header>
            <div>
              <h2>Leave and Attendance</h2>
              <p class="muted">Current workflow pressure for HR and managers.</p>
            </div>
          </header>
          <dl class="details-grid">
            <div>
              <dt>Approved leave this month</dt>
              <dd>{{ dashboard.leave.approved_this_month }}</dd>
            </div>
            <div>
              <dt>Absent today</dt>
              <dd>{{ dashboard.attendance_today.absent }}</dd>
            </div>
            <div>
              <dt>On leave today</dt>
              <dd>{{ dashboard.attendance_today.on_leave }}</dd>
            </div>
            <div>
              <dt>Remote today</dt>
              <dd>{{ dashboard.attendance_today.remote }}</dd>
            </div>
          </dl>
        </section>

        <section class="panel">
          <header>
            <div>
              <h2>Recent Activity</h2>
              <p class="muted">Latest audit events for the current company.</p>
            </div>
            <NuxtLink to="/platform/audit-logs">Audit logs</NuxtLink>
          </header>
          <table v-if="dashboard.recent_audit_logs.length > 0">
            <thead>
              <tr>
                <th>Date</th>
                <th>Action</th>
                <th>Record</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in dashboard.recent_audit_logs" :key="`${log.action}-${log.auditable_id}-${log.created_at}`">
                <td>{{ log.created_at }}</td>
                <td>{{ actionLabel(log.action) }}</td>
                <td>{{ log.auditable_type }} #{{ log.auditable_id }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="muted">No audit events recorded yet.</p>
        </section>
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
</script>

<style scoped>
.muted {
  margin: 6px 0 0;
  color: #5d6a72;
}

.dashboard-grid,
.panel-grid,
.quick-links,
.details-grid {
  display: grid;
  gap: 16px;
}

.dashboard-grid {
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.panel-grid {
  margin-top: 20px;
  grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
}

.summary-card,
.panel {
  display: grid;
  gap: 10px;
  background: #ffffff;
  border: 1px solid #d8dee4;
  border-radius: 8px;
  padding: 16px;
}

.summary-card span,
.summary-card small,
.details-grid dt {
  color: #5d6a72;
}

.summary-card strong {
  color: #102027;
  font-size: 2rem;
}

.quick-links {
  margin-top: 18px;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
}

.quick-links a {
  min-height: 42px;
  display: grid;
  place-items: center;
  border: 1px solid #2f7d68;
  border-radius: 6px;
  color: #1f6f5b;
  font-weight: 700;
}

.panel > header {
  align-items: center;
}

.panel h2 {
  margin: 0;
}

.details-grid {
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
}

.details-grid dd {
  margin: 4px 0 0;
  color: #102027;
  font-size: 1.4rem;
  font-weight: 700;
}

@media (max-width: 760px) {
  .panel-grid {
    grid-template-columns: 1fr;
  }
}
</style>
