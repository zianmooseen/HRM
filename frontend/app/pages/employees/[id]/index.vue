<template>
  <section class="page">
    <header>
      <h1>{{ employee?.display_name || 'Employee' }}</h1>
      <NuxtLink :to="`/employees/${route.params.id}/edit`">Edit</NuxtLink>
    </header>
    <p v-if="loading">Loading employee...</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <dl v-else-if="employee" class="detail-list">
      <dt>Code</dt>
      <dd>{{ employee.employee_code }}</dd>
      <dt>Status</dt>
      <dd>{{ employee.status }}</dd>
      <dt>Work email</dt>
      <dd>{{ employee.work_email || '-' }}</dd>
      <dt>Personal email</dt>
      <dd>{{ employee.personal_email || '-' }}</dd>
      <dt>Phone</dt>
      <dd>{{ employee.phone || '-' }}</dd>
      <dt>Gender</dt>
      <dd>{{ employee.gender ? label(employee.gender) : '-' }}</dd>
      <dt>Date of birth</dt>
      <dd>{{ employee.date_of_birth || '-' }}</dd>
      <dt>Nationality</dt>
      <dd>{{ employee.nationality || '-' }}</dd>
      <dt>UAE citizen</dt>
      <dd>{{ employee.is_uae_citizen ? 'Yes' : 'No' }}</dd>
      <dt>Skilled worker</dt>
      <dd>{{ employee.is_skilled_worker ? 'Yes' : 'No' }}</dd>
      <dt>Skill level</dt>
      <dd>{{ employee.skill_level || '-' }}</dd>
      <dt>Work permit type</dt>
      <dd>{{ employee.work_permit_type || '-' }}</dd>
      <template v-if="employee.basic_salary !== undefined">
        <dt>Work permit number</dt>
        <dd>{{ employee.work_permit_number || '-' }}</dd>
        <dt>Labor card number</dt>
        <dd>{{ employee.labor_card_number || '-' }}</dd>
      </template>
      <dt>Branch</dt>
      <dd>{{ employee.branch?.name || '-' }}</dd>
      <dt>Department</dt>
      <dd>{{ employee.department?.name || '-' }}</dd>
      <dt>Job title</dt>
      <dd>{{ employee.job_title?.title || '-' }}</dd>
      <dt>Manager</dt>
      <dd>{{ employee.manager?.display_name || '-' }}</dd>
      <dt>Hire date</dt>
      <dd>{{ employee.hire_date || '-' }}</dd>
      <dt>Contract</dt>
      <dd>{{ employee.contract_start_date || '-' }} to {{ employee.contract_end_date || 'Open-ended' }}</dd>
      <template v-if="employee.basic_salary !== undefined">
        <dt>Basic salary</dt>
        <dd>{{ employee.basic_salary || '-' }}</dd>
        <dt>Monthly package estimate</dt>
        <dd>{{ employee.monthly_salary || '-' }}</dd>
        <dt>Bank name</dt>
        <dd>{{ employee.bank_name || '-' }}</dd>
        <dt>Bank IBAN</dt>
        <dd>{{ employee.bank_iban || '-' }}</dd>
        <dt>Bank routing code</dt>
        <dd>{{ employee.bank_routing_code || '-' }}</dd>
        <dt>WPS person ID</dt>
        <dd>{{ employee.wps_person_id || '-' }}</dd>
      </template>
      <dt>Login account</dt>
      <dd>{{ employee.user_id ? `User #${employee.user_id}` : 'Not created' }}</dd>
    </dl>

    <section v-if="employee && auth.hasPermission('employees.update') && !employee.user_id" class="account-panel">
      <h2>Employee login</h2>
      <form class="form-grid" @submit.prevent="createEmployeeAccount">
        <label>
          Username
          <input v-model="accountForm.username" required>
        </label>
        <label>
          Email
          <input v-model="accountForm.email" type="email" required>
        </label>
        <label>
          Password
          <input v-model="accountForm.password" type="password" required minlength="6">
        </label>
        <p v-if="accountError" class="error">{{ accountError }}</p>
        <button type="submit" :disabled="creatingAccount">{{ creatingAccount ? 'Creating...' : 'Create employee login' }}</button>
      </form>
    </section>

    <section v-if="employee && auth.hasPermission('employees.view')" class="service-period-panel">
      <header>
        <div>
          <h2>Service Periods</h2>
          <p class="muted">{{ servicePeriods.length }} contract records</p>
        </div>
      </header>

      <form v-if="employee.status !== 'terminated' && activeServicePeriod && auth.hasPermission('employees.update')" class="form-grid" @submit.prevent="extendContract">
        <label>
          New contract end date
          <input v-model="extensionForm.end_date" type="date" required>
        </label>
        <label class="full">
          Extension reason
          <textarea v-model="extensionForm.change_reason" rows="3" />
        </label>
        <p v-if="servicePeriodError" class="error">{{ servicePeriodError }}</p>
        <button type="submit" :disabled="extendingContract">{{ extendingContract ? 'Extending...' : 'Extend contract' }}</button>
      </form>

      <form v-if="employee.status === 'terminated' && auth.hasPermission('employees.update')" class="form-grid" @submit.prevent="rehireEmployee">
        <label>
          Rehire start date
          <input v-model="rehireForm.start_date" type="date" required>
        </label>
        <label>
          Contract end date
          <input v-model="rehireForm.end_date" type="date">
        </label>
        <label>
          Employment type
          <input v-model="rehireForm.employment_type">
        </label>
        <label>
          Contract type
          <input v-model="rehireForm.contract_type">
        </label>
        <label>
          Basic salary
          <input v-model.number="rehireForm.basic_salary" type="number" min="0">
        </label>
        <label class="full">
          Rehire reason
          <textarea v-model="rehireForm.change_reason" rows="3" />
        </label>
        <p v-if="servicePeriodError" class="error">{{ servicePeriodError }}</p>
        <button type="submit" :disabled="rehiring">{{ rehiring ? 'Rehiring...' : 'Rehire employee' }}</button>
      </form>

      <p v-if="servicePeriodsLoading">Loading service periods...</p>
      <table v-else-if="servicePeriods.length > 0">
        <thead>
          <tr>
            <th>Start</th>
            <th>End</th>
            <th>Employment</th>
            <th>Contract</th>
            <th>Status</th>
            <th>Reason</th>
          </tr>
          <tr class="column-filter-row">
            <th><TableColumnFilter v-model="periodColumnFilters.start" label="Filter service start" type="date" /></th>
            <th><TableColumnFilter v-model="periodColumnFilters.end" label="Filter service end" /></th>
            <th><TableColumnFilter v-model="periodColumnFilters.employment" label="Filter employment type" /></th>
            <th><TableColumnFilter v-model="periodColumnFilters.contract" label="Filter contract type" /></th>
            <th><TableColumnFilter v-model="periodColumnFilters.status" label="Filter service status" /></th>
            <th><TableColumnFilter v-model="periodColumnFilters.reason" label="Filter service reason" /></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="period in filteredServicePeriods" :key="period.id">
            <td>{{ period.start_date }}</td>
            <td>{{ period.end_date || '-' }}</td>
            <td>{{ period.employment_type || '-' }}</td>
            <td>{{ period.contract_type || '-' }}</td>
            <td>{{ label(period.status) }}</td>
            <td>{{ period.change_reason || '-' }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="employee && auth.hasPermission('employees.update')" class="termination-panel">
      <header>
        <div>
          <h2>Termination and Final Settlement</h2>
          <p class="muted">{{ terminations.length }} termination records</p>
        </div>
      </header>

      <form v-if="employee.status !== 'terminated'" class="form-grid" @submit.prevent="createTermination">
        <label>
          Termination date
          <input v-model="terminationForm.termination_date" type="date" required>
        </label>
        <label>
          Last working date
          <input v-model="terminationForm.last_working_date" type="date">
        </label>
        <label>
          Type
          <select v-model="terminationForm.termination_type" required>
            <option value="company_initiated">Company initiated</option>
            <option value="employee_resignation">Employee resignation</option>
            <option value="mutual">Mutual</option>
            <option value="contract_end">Contract end</option>
            <option value="other">Other</option>
          </select>
        </label>
        <label>
          Basic salary override
          <input v-model.number="terminationForm.basic_salary" type="number" min="0">
        </label>
        <label>
          Unpaid leave days
          <input v-model.number="terminationForm.unpaid_leave_days" type="number" min="0" step="0.5">
        </label>
        <label>
          Leave encashment
          <input v-model.number="terminationForm.leave_encashment_amount" type="number" min="0" step="0.01">
        </label>
        <label>
          Notice paid
          <input v-model.number="terminationForm.notice_paid_amount" type="number" min="0" step="0.01">
        </label>
        <label>
          Other earnings
          <input v-model.number="terminationForm.other_earnings_amount" type="number" min="0" step="0.01">
        </label>
        <label>
          Deductions
          <input v-model.number="terminationForm.deductions_amount" type="number" min="0" step="0.01">
        </label>
        <label class="full">
          Reason
          <textarea v-model="terminationForm.termination_reason" rows="3" />
        </label>
        <p v-if="terminationError" class="error">{{ terminationError }}</p>
        <button type="submit" :disabled="terminating">{{ terminating ? 'Creating...' : 'Create termination settlement' }}</button>
      </form>

      <p v-if="terminationsLoading">Loading termination records...</p>
      <table v-else-if="terminations.length > 0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Gratuity</th>
            <th>Final settlement</th>
            <th>Status</th>
            <th></th>
          </tr>
          <tr class="column-filter-row">
            <th><TableColumnFilter v-model="terminationColumnFilters.date" label="Filter termination date" type="date" /></th>
            <th><TableColumnFilter v-model="terminationColumnFilters.type" label="Filter termination type" /></th>
            <th><TableColumnFilter v-model="terminationColumnFilters.gratuity" label="Filter gratuity amount" /></th>
            <th><TableColumnFilter v-model="terminationColumnFilters.settlement" label="Filter settlement amount" /></th>
            <th><TableColumnFilter v-model="terminationColumnFilters.status" label="Filter termination status" /></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="termination in filteredTerminations" :key="termination.id">
            <td>{{ termination.termination_date }}</td>
            <td>{{ label(termination.termination_type) }}</td>
            <td>{{ termination.gratuity_amount }}</td>
            <td>{{ termination.final_settlement_amount }}</td>
            <td>{{ label(termination.status) }}</td>
            <td>
              <button
                v-if="auth.hasPermission('payroll.approve') && termination.status !== 'paid'"
                type="button"
                class="secondary"
                @click="markTerminationPaid(termination)"
              >
                Mark paid
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="employee && auth.hasPermission('employees.view')" class="onboarding-panel">
      <header>
        <div>
          <h2>Onboarding</h2>
          <p class="muted">{{ activeOnboardingCase ? label(activeOnboardingCase.status) : 'No active onboarding case' }}</p>
        </div>
      </header>

      <form v-if="!activeOnboardingCase && auth.hasPermission('employees.update')" class="start-onboarding" @submit.prevent="startOnboarding">
        <label>
          Template
          <select v-model.number="selectedTemplateId" required>
            <option :value="0">Select template</option>
            <option v-for="template in onboardingTemplates" :key="template.id" :value="template.id">
              {{ template.name }}
            </option>
          </select>
        </label>
        <button type="submit" :disabled="startingOnboarding">
          {{ startingOnboarding ? 'Starting...' : 'Start onboarding' }}
        </button>
      </form>

      <p v-if="onboardingError" class="error">{{ onboardingError }}</p>
      <p v-if="onboardingLoading">Loading onboarding...</p>
      <div v-else-if="activeOnboardingCase" class="onboarding-case">
        <div class="progress-row">
          <span>{{ activeOnboardingCase.progress?.completed_tasks || 0 }} / {{ activeOnboardingCase.progress?.total_tasks || 0 }} tasks</span>
          <span>{{ activeOnboardingCase.progress?.percent || 0 }}%</span>
        </div>
        <div class="progress-track">
          <span :style="{ width: `${activeOnboardingCase.progress?.percent || 0}%` }" />
        </div>

        <ul class="task-list">
          <li v-for="task in activeOnboardingCase.tasks" :key="task.id">
            <div>
              <strong>{{ task.title }}</strong>
              <small>{{ label(task.task_type) }} · {{ task.due_date || 'No due date' }}</small>
            </div>
            <select
              v-if="auth.hasPermission('employees.update') && activeOnboardingCase.status !== 'completed'"
              :value="task.status"
              @change="updateTask(task, ($event.target as HTMLSelectElement).value)"
            >
              <option value="pending">Pending</option>
              <option value="in_progress">In progress</option>
              <option value="blocked">Blocked</option>
              <option value="completed">Completed</option>
              <option value="skipped">Skipped</option>
              <option value="cancelled">Cancelled</option>
            </select>
            <span v-else>{{ label(task.status) }}</span>
          </li>
        </ul>

        <button
          v-if="auth.hasPermission('employees.update') && activeOnboardingCase.status !== 'completed'"
          type="button"
          :disabled="completingOnboarding"
          @click="completeOnboarding"
        >
          {{ completingOnboarding ? 'Completing...' : 'Complete onboarding and activate' }}
        </button>
      </div>
    </section>

    <section v-if="employee && auth.hasPermission('documents.view')" class="documents">
      <header>
        <div>
          <h2>Documents</h2>
          <p class="muted">{{ documents.length }} files · {{ expiringDocuments.length }} need attention</p>
        </div>
      </header>

      <form v-if="auth.hasPermission('documents.upload')" class="form-grid" @submit.prevent="uploadDocument">
        <label>
          Document type
          <select v-model="documentForm.document_type" required>
            <option value="passport">Passport</option>
            <option value="visa">Visa</option>
            <option value="labor_card">Labor card</option>
            <option value="emirates_id">Emirates ID</option>
            <option value="medical_certificate">Medical certificate</option>
            <option value="contract">Contract</option>
            <option value="other">Other</option>
          </select>
        </label>
        <label>
          Title
          <input v-model="documentForm.title" placeholder="Optional">
        </label>
        <label>
          Issue date
          <input v-model="documentForm.issue_date" type="date">
        </label>
        <label>
          Expiry date
          <input v-model="documentForm.expiry_date" type="date">
        </label>
        <label class="full">
          File
          <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required @change="selectFile">
        </label>
        <p v-if="documentError" class="error">{{ documentError }}</p>
        <button type="submit" :disabled="uploading">{{ uploading ? 'Uploading...' : 'Upload document' }}</button>
      </form>

      <p v-if="documentsLoading">Loading documents...</p>
      <p v-else-if="documents.length === 0" class="muted">No documents uploaded yet.</p>
      <div v-else class="document-groups">
        <section v-for="group in groupedDocuments" :key="group.type" class="document-group">
          <header>
            <h3>{{ label(group.type) }}</h3>
            <span>{{ group.items.length }}</span>
          </header>

          <div class="document-grid">
            <article v-for="document in group.items" :key="document.id" class="document-card">
              <a class="document-preview" :href="downloadHref(document)" target="_blank">
                <img
                  v-if="document.is_previewable && document.preview_url"
                  :src="previewHref(document)"
                  :alt="document.title"
                >
                <span v-else>{{ fileBadge(document) }}</span>
              </a>

              <div class="document-body">
                <div>
                  <h4>{{ document.title }}</h4>
                  <p class="muted">{{ document.original_file_name }}</p>
                </div>
                <span :class="['status-pill', document.expiry_status]">{{ expiryLabel(document) }}</span>
              </div>

              <footer>
                <a :href="downloadHref(document)" target="_blank">Open</a>
                <button
                  v-if="auth.hasPermission('documents.delete')"
                  type="button"
                  class="danger"
                  @click="deleteDocument(document)"
                >
                  Delete
                </button>
              </footer>
            </article>
          </div>
        </section>
      </div>
    </section>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const route = useRoute()
const api = useApiClient()
const auth = useAuthStore()
const config = useRuntimeConfig()
const loading = ref(true)
const error = ref('')
const employee = ref<any>(null)
const documents = ref<EmployeeDocument[]>([])
const documentsLoading = ref(false)
const documentError = ref('')
const uploading = ref(false)
const selectedFile = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const documentForm = reactive({
  document_type: 'medical_certificate',
  title: '',
  issue_date: '',
  expiry_date: '',
})
const creatingAccount = ref(false)
const accountError = ref('')
const accountForm = reactive({
  username: '',
  email: '',
  password: '',
})
const terminations = ref<EmployeeTermination[]>([])
const servicePeriods = ref<EmployeeServicePeriod[]>([])
const { filters: periodColumnFilters, filteredRows: filteredServicePeriods } = useTableColumnFilters(
  servicePeriods,
  [
    { key: 'start', value: period => period.start_date },
    { key: 'end', value: period => period.end_date },
    { key: 'employment', value: period => period.employment_type },
    { key: 'contract', value: period => period.contract_type },
    { key: 'status', value: period => label(period.status) },
    { key: 'reason', value: period => period.change_reason },
  ],
)
const { filters: terminationColumnFilters, filteredRows: filteredTerminations } = useTableColumnFilters(
  terminations,
  [
    { key: 'date', value: termination => termination.termination_date },
    { key: 'type', value: termination => label(termination.termination_type) },
    { key: 'gratuity', value: termination => termination.gratuity_amount },
    { key: 'settlement', value: termination => termination.final_settlement_amount },
    { key: 'status', value: termination => label(termination.status) },
  ],
)
const terminationsLoading = ref(false)
const servicePeriodsLoading = ref(false)
const terminationError = ref('')
const servicePeriodError = ref('')
const terminating = ref(false)
const extendingContract = ref(false)
const rehiring = ref(false)
const terminationForm = reactive({
  termination_date: new Date().toISOString().slice(0, 10),
  last_working_date: '',
  termination_type: 'company_initiated',
  termination_reason: '',
  basic_salary: null as number | null,
  unpaid_leave_days: 0,
  leave_encashment_amount: 0,
  notice_paid_amount: 0,
  other_earnings_amount: 0,
  deductions_amount: 0,
})
const extensionForm = reactive({
  end_date: '',
  change_reason: '',
})
const rehireForm = reactive({
  start_date: new Date().toISOString().slice(0, 10),
  end_date: '',
  employment_type: '',
  contract_type: '',
  basic_salary: null as number | null,
  change_reason: '',
})

interface EmployeeDocument {
  id: number
  document_type: string
  title: string
  original_file_name: string
  mime_type: string
  size_bytes: number
  expiry_date: string | null
  days_until_expiry: number | null
  expiry_status: 'not_tracked' | 'expired' | 'expiring_soon' | 'valid'
  download_url: string
  preview_url: string | null
  is_previewable: boolean
}

interface OnboardingTemplate {
  id: number
  name: string
  is_default: boolean
}

interface OnboardingTask {
  id: number
  title: string
  task_type: string
  status: string
  due_date: string | null
}

interface OnboardingCase {
  id: number
  status: string
  progress: { total_tasks: number, completed_tasks: number, percent: number } | null
  tasks: OnboardingTask[]
}

interface EmployeeTermination {
  id: number
  termination_date: string
  last_working_date: string
  termination_type: string
  gratuity_amount: string
  final_settlement_amount: string
  paid_amount: string
  status: string
}

interface EmployeeServicePeriod {
  id: number
  start_date: string
  end_date: string | null
  employment_type: string | null
  contract_type: string | null
  status: string
  change_reason: string | null
}

const groupedDocuments = computed(() => {
  const groups = new Map<string, EmployeeDocument[]>()

  for (const document of documents.value) {
    groups.set(document.document_type, [...(groups.get(document.document_type) || []), document])
  }

  return Array.from(groups.entries()).map(([type, items]) => ({ type, items }))
})
const expiringDocuments = computed(() => documents.value.filter((document) => ['expired', 'expiring_soon'].includes(document.expiry_status)))
const onboardingTemplates = ref<OnboardingTemplate[]>([])
const onboardingCases = ref<OnboardingCase[]>([])
const onboardingLoading = ref(false)
const onboardingError = ref('')
const selectedTemplateId = ref(0)
const startingOnboarding = ref(false)
const completingOnboarding = ref(false)
const activeOnboardingCase = computed(() => onboardingCases.value.find((onboardingCase) => onboardingCase.status !== 'completed' && onboardingCase.status !== 'cancelled') || onboardingCases.value[0] || null)
const activeServicePeriod = computed(() => servicePeriods.value.find((period) => period.status === 'active') || null)

onMounted(async () => {
  try {
    const response = await api.get<{ employee: any }>(`/employees/${route.params.id}`)
    employee.value = response.data.employee
    accountForm.username = employee.value?.employee_code?.toLowerCase().replace(/[^a-z0-9._-]/g, '.') || ''
    accountForm.email = employee.value?.work_email || employee.value?.personal_email || ''
    if (auth.hasPermission('documents.view')) {
      await loadDocuments()
    }
    if (auth.hasPermission('employees.view')) {
      await Promise.all([loadOnboardingTemplates(), loadOnboardingCases(), loadTerminations(), loadServicePeriods()])
    }
  } catch {
    error.value = 'Unable to load employee.'
  } finally {
    loading.value = false
  }
})

async function createEmployeeAccount() {
  creatingAccount.value = true
  accountError.value = ''

  try {
    const response = await api.post<{ employee: any }>(`/employees/${route.params.id}/account`, {
      username: accountForm.username,
      email: accountForm.email,
      password: accountForm.password,
    })
    employee.value = response.data.employee
    accountForm.password = ''
  } catch (err) {
    accountError.value = apiErrorMessage(err, 'Unable to create employee login.')
  } finally {
    creatingAccount.value = false
  }
}

async function loadServicePeriods() {
  servicePeriodsLoading.value = true
  servicePeriodError.value = ''

  try {
    const response = await api.get<{ service_periods: EmployeeServicePeriod[] }>(`/employees/${route.params.id}/service-periods`)
    servicePeriods.value = response.data.service_periods
    extensionForm.end_date = activeServicePeriod.value?.end_date || ''
  } catch (err) {
    servicePeriodError.value = apiErrorMessage(err, 'Unable to load service periods.')
  } finally {
    servicePeriodsLoading.value = false
  }
}

async function extendContract() {
  extendingContract.value = true
  servicePeriodError.value = ''

  try {
    const response = await api.post<{ employee: any }>(`/employees/${route.params.id}/service-periods/extend`, {
      end_date: extensionForm.end_date,
      change_reason: extensionForm.change_reason || null,
    })
    employee.value = response.data.employee
    extensionForm.change_reason = ''
    await loadServicePeriods()
  } catch (err) {
    servicePeriodError.value = apiErrorMessage(err, 'Unable to extend contract.')
  } finally {
    extendingContract.value = false
  }
}

async function rehireEmployee() {
  rehiring.value = true
  servicePeriodError.value = ''

  try {
    const response = await api.post<{ employee: any }>(`/employees/${route.params.id}/service-periods/rehire`, {
      start_date: rehireForm.start_date,
      end_date: rehireForm.end_date || null,
      employment_type: rehireForm.employment_type || null,
      contract_type: rehireForm.contract_type || null,
      basic_salary: rehireForm.basic_salary || null,
      change_reason: rehireForm.change_reason || null,
    })
    employee.value = response.data.employee
    await loadServicePeriods()
  } catch (err) {
    servicePeriodError.value = apiErrorMessage(err, 'Unable to rehire employee.')
  } finally {
    rehiring.value = false
  }
}

async function loadTerminations() {
  terminationsLoading.value = true
  terminationError.value = ''

  try {
    const response = await api.get<{ employee_terminations: EmployeeTermination[] }>(`/employee-terminations?employee_id=${route.params.id}`)
    terminations.value = response.data.employee_terminations
  } catch (err) {
    terminationError.value = apiErrorMessage(err, 'Unable to load termination records.')
  } finally {
    terminationsLoading.value = false
  }
}

async function createTermination() {
  terminating.value = true
  terminationError.value = ''

  try {
    await api.post(`/employees/${route.params.id}/termination`, {
      ...terminationForm,
      last_working_date: terminationForm.last_working_date || null,
      termination_reason: terminationForm.termination_reason || null,
      basic_salary: terminationForm.basic_salary || null,
    })
    const employeeResponse = await api.get<{ employee: any }>(`/employees/${route.params.id}`)
    employee.value = employeeResponse.data.employee
    await loadTerminations()
    await loadServicePeriods()
  } catch (err) {
    terminationError.value = apiErrorMessage(err, 'Unable to create termination settlement.')
  } finally {
    terminating.value = false
  }
}

async function markTerminationPaid(termination: EmployeeTermination) {
  const reference = window.prompt('Payment reference') || ''
  terminationError.value = ''

  try {
    await api.post(`/employee-terminations/${termination.id}/mark-paid`, {
      paid_amount: Number(termination.final_settlement_amount),
      payment_reference: reference || null,
    })
    await loadTerminations()
  } catch (err) {
    terminationError.value = apiErrorMessage(err, 'Unable to mark final settlement as paid.')
  }
}

async function loadDocuments() {
  documentsLoading.value = true
  documentError.value = ''

  try {
    const response = await api.get<{ documents: EmployeeDocument[] }>(`/documents?employee_id=${route.params.id}`)
    documents.value = response.data.documents
  } catch (err) {
    documentError.value = apiErrorMessage(err, 'Unable to load documents.')
  } finally {
    documentsLoading.value = false
  }
}

async function loadOnboardingTemplates() {
  const response = await api.get<{ onboarding_templates: OnboardingTemplate[] }>('/onboarding-templates')
  onboardingTemplates.value = response.data.onboarding_templates
  selectedTemplateId.value = onboardingTemplates.value.find((template) => template.is_default)?.id || onboardingTemplates.value[0]?.id || 0
}

async function loadOnboardingCases() {
  onboardingLoading.value = true
  onboardingError.value = ''

  try {
    const response = await api.get<{ onboarding_cases: OnboardingCase[] }>(`/onboarding-cases?employee_id=${route.params.id}`)
    onboardingCases.value = response.data.onboarding_cases
  } catch (err) {
    onboardingError.value = apiErrorMessage(err, 'Unable to load onboarding.')
  } finally {
    onboardingLoading.value = false
  }
}

async function startOnboarding() {
  if (!selectedTemplateId.value) {
    onboardingError.value = 'Create or select an onboarding template first.'
    return
  }

  startingOnboarding.value = true
  onboardingError.value = ''

  try {
    await api.post(`/employees/${route.params.id}/onboarding/start`, {
      onboarding_template_id: selectedTemplateId.value,
    })
    await loadOnboardingCases()
    const response = await api.get<{ employee: any }>(`/employees/${route.params.id}`)
    employee.value = response.data.employee
  } catch (err) {
    onboardingError.value = apiErrorMessage(err, 'Unable to start onboarding.')
  } finally {
    startingOnboarding.value = false
  }
}

async function updateTask(task: OnboardingTask, status: string) {
  onboardingError.value = ''

  try {
    const response = await api.post<{ onboarding_case: OnboardingCase }>(`/onboarding-tasks/${task.id}`, { status })
    onboardingCases.value = [response.data.onboarding_case, ...onboardingCases.value.filter((onboardingCase) => onboardingCase.id !== response.data.onboarding_case.id)]
  } catch (err) {
    onboardingError.value = apiErrorMessage(err, 'Unable to update onboarding task.')
  }
}

async function completeOnboarding() {
  if (!activeOnboardingCase.value) {
    return
  }

  completingOnboarding.value = true
  onboardingError.value = ''

  try {
    const response = await api.post<{ onboarding_case: OnboardingCase }>(`/onboarding-cases/${activeOnboardingCase.value.id}/complete`, {})
    onboardingCases.value = [response.data.onboarding_case, ...onboardingCases.value.filter((onboardingCase) => onboardingCase.id !== response.data.onboarding_case.id)]
    const employeeResponse = await api.get<{ employee: any }>(`/employees/${route.params.id}`)
    employee.value = employeeResponse.data.employee
  } catch (err) {
    onboardingError.value = apiErrorMessage(err, 'Unable to complete onboarding.')
  } finally {
    completingOnboarding.value = false
  }
}

function selectFile(event: Event) {
  selectedFile.value = (event.target as HTMLInputElement).files?.[0] || null
}

async function uploadDocument() {
  if (!selectedFile.value) {
    documentError.value = 'Choose a file to upload.'
    return
  }

  uploading.value = true
  documentError.value = ''

  const payload = new FormData()
  payload.append('employee_id', String(route.params.id))
  payload.append('document_type', documentForm.document_type)
  if (documentForm.title) payload.append('title', documentForm.title)
  if (documentForm.issue_date) payload.append('issue_date', documentForm.issue_date)
  if (documentForm.expiry_date) payload.append('expiry_date', documentForm.expiry_date)
  payload.append('file', selectedFile.value)

  try {
    await api.post('/documents', payload)
    documentForm.title = ''
    documentForm.issue_date = ''
    documentForm.expiry_date = ''
    selectedFile.value = null
    if (fileInput.value) fileInput.value.value = ''
    await loadDocuments()
  } catch (err) {
    documentError.value = apiErrorMessage(err, 'Unable to upload document.')
  } finally {
    uploading.value = false
  }
}

async function deleteDocument(document: EmployeeDocument) {
  if (!window.confirm(`Delete ${document.title}?`)) {
    return
  }

  try {
    await api.delete(`/documents/${document.id}`)
    await loadDocuments()
  } catch (err) {
    documentError.value = apiErrorMessage(err, 'Unable to delete document.')
  }
}

function downloadHref(document: EmployeeDocument) {
  return `${config.public.apiBaseUrl.replace(/\/api$/, '')}${document.download_url}`
}

function previewHref(document: EmployeeDocument) {
  return document.preview_url ? `${config.public.apiBaseUrl.replace(/\/api$/, '')}${document.preview_url}` : ''
}

function label(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function fileBadge(document: EmployeeDocument) {
  if (document.mime_type === 'application/pdf') {
    return 'PDF'
  }

  return document.original_file_name.split('.').pop()?.toUpperCase() || 'FILE'
}

function expiryLabel(document: EmployeeDocument) {
  if (document.expiry_status === 'not_tracked') {
    return 'No expiry'
  }

  if (document.expiry_status === 'expired') {
    return 'Expired'
  }

  if (document.expiry_status === 'expiring_soon') {
    return `${document.days_until_expiry} days left`
  }

  return document.expiry_date || 'Valid'
}
</script>

<style scoped>
.documents {
  display: grid;
  gap: 18px;
}

.onboarding-panel,
.onboarding-case,
.account-panel,
.service-period-panel,
.termination-panel {
  display: grid;
  gap: 14px;
}

.onboarding-panel h2,
.account-panel h2,
.service-period-panel h2,
.termination-panel h2 {
  margin: 0;
}

.start-onboarding {
  display: flex;
  align-items: end;
  gap: 12px;
  max-width: 620px;
}

.start-onboarding label {
  display: grid;
  flex: 1;
  gap: 6px;
}

.start-onboarding select,
.task-list select {
  min-height: 40px;
  border: 1px solid #b8c1c8;
  border-radius: 6px;
  padding: 8px 10px;
}

.progress-row {
  display: flex;
  justify-content: space-between;
  max-width: 760px;
  color: #5d6a72;
  font-weight: 700;
}

.progress-track {
  overflow: hidden;
  max-width: 760px;
  height: 8px;
  background: #d8dee4;
  border-radius: 999px;
}

.progress-track span {
  display: block;
  height: 100%;
  background: #16765f;
}

.task-list {
  display: grid;
  gap: 8px;
  max-width: 760px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.task-list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: #ffffff;
  border: 1px solid #d8dee4;
  border-radius: 8px;
  padding: 12px;
}

.task-list small {
  display: block;
  margin-top: 4px;
  color: #5d6a72;
}

.documents h2,
.documents h3,
.documents h4 {
  margin: 0;
}

.document-groups {
  display: grid;
  gap: 18px;
}

.document-group {
  display: grid;
  gap: 12px;
}

.document-group > header {
  align-items: center;
}

.document-group > header span {
  color: #5d6a72;
  font-weight: 700;
}

.document-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}

.document-card {
  display: grid;
  grid-template-rows: 132px 1fr auto;
  overflow: hidden;
  background: #ffffff;
  border: 1px solid #d8dee4;
  border-radius: 8px;
}

.document-preview {
  display: grid;
  place-items: center;
  min-height: 132px;
  background: #eef2f4;
  color: #41505a;
  font-weight: 800;
}

.document-preview img {
  width: 100%;
  height: 132px;
  object-fit: cover;
}

.document-body {
  display: grid;
  gap: 10px;
  padding: 12px;
}

.document-body h4,
.document-body p {
  overflow-wrap: anywhere;
}

.status-pill {
  width: fit-content;
  border-radius: 999px;
  padding: 4px 8px;
  background: #e8eef1;
  color: #172026;
  font-size: 0.85rem;
  font-weight: 700;
}

.status-pill.valid {
  background: #dcefe8;
  color: #155b49;
}

.status-pill.expiring_soon {
  background: #fff1c7;
  color: #6b4c00;
}

.status-pill.expired {
  background: #f8d9df;
  color: #8b1830;
}

.document-card footer {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding: 12px;
  border-top: 1px solid #e5e9ed;
}

.document-card footer a,
.document-card footer button {
  display: inline-flex;
  align-items: center;
  min-height: 34px;
  border-radius: 6px;
  padding: 7px 10px;
}

@media (max-width: 760px) {
  .start-onboarding,
  .task-list li {
    display: grid;
  }

  .document-grid {
    grid-template-columns: 1fr;
  }
}
</style>
