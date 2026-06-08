<template>
  <section class="page">
    <header>
      <div>
        <h1>Onboarding</h1>
        <p class="muted">Create reusable onboarding templates and track employee onboarding progress.</p>
      </div>
    </header>

    <section v-if="auth.hasPermission('employees.update')" class="template-builder">
      <h2>New template</h2>
      <form class="form-grid" @submit.prevent="createTemplate">
        <label>
          Name
          <input v-model="templateForm.name" required>
        </label>
        <label>
          Employment type
          <input v-model="templateForm.employment_type" placeholder="Optional">
        </label>
        <label class="full">
          Description
          <textarea v-model="templateForm.description" rows="2" />
        </label>
        <label class="checkbox-label">
          <span>Default template</span>
          <input v-model="templateForm.is_default" type="checkbox">
        </label>

        <section class="full task-editor">
          <header>
            <h3>Tasks</h3>
            <button type="button" class="secondary" @click="addTask">Add task</button>
          </header>
          <div v-for="(task, index) in templateForm.tasks" :key="index" class="task-row">
            <label>
              Title
              <input v-model="task.title" required>
            </label>
            <label>
              Type
              <select v-model="task.task_type" required>
                <option value="document_upload">Document upload</option>
                <option value="hr_review">HR review</option>
                <option value="payroll_setup">Payroll setup</option>
                <option value="account_creation">Account creation</option>
                <option value="policy_acknowledgement">Policy acknowledgement</option>
                <option value="asset_assignment">Asset assignment</option>
                <option value="training">Training</option>
                <option value="custom">Custom</option>
              </select>
            </label>
            <label class="task-due-label">
              Due days
              <input v-model.number="task.due_days_after_start" type="number" min="0">
            </label>
            <label class="checkbox-label task-required-label">
              <span>Required</span>
              <input v-model="task.required" type="checkbox">
            </label>
            <button v-if="templateForm.tasks.length > 1" type="button" class="danger" @click="removeTask(index)">
              Remove
            </button>
          </div>
        </section>

        <p v-if="templateError" class="error">{{ templateError }}</p>
        <button type="submit" :disabled="savingTemplate">{{ savingTemplate ? 'Creating...' : 'Create template' }}</button>
      </form>
    </section>

    <section>
      <h2>Templates</h2>
      <p v-if="templatesLoading">Loading templates...</p>
      <p v-else-if="templates.length === 0" class="muted">No onboarding templates yet.</p>
      <table v-else>
        <thead>
          <tr>
            <th>Name</th>
            <th>Tasks</th>
            <th>Status</th>
            <th>Default</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="template in templates" :key="template.id">
            <td>{{ template.name }}</td>
            <td>{{ template.tasks?.length || 0 }}</td>
            <td>{{ label(template.status) }}</td>
            <td>{{ template.is_default ? 'Yes' : 'No' }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section>
      <h2>Active cases</h2>
      <p v-if="casesLoading">Loading onboarding cases...</p>
      <p v-else-if="cases.length === 0" class="muted">No onboarding cases yet.</p>
      <table v-else>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Template</th>
            <th>Status</th>
            <th>Progress</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="onboardingCase in cases" :key="onboardingCase.id">
            <td>
              <NuxtLink :to="`/employees/${onboardingCase.employee_id}`">
                {{ onboardingCase.employee?.display_name || `Employee #${onboardingCase.employee_id}` }}
              </NuxtLink>
            </td>
            <td>{{ onboardingCase.template?.name || '-' }}</td>
            <td>{{ label(onboardingCase.status) }}</td>
            <td>{{ onboardingCase.progress?.completed_tasks || 0 }} / {{ onboardingCase.progress?.total_tasks || 0 }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface OnboardingTemplate {
  id: number
  name: string
  status: string
  is_default: boolean
  tasks?: Array<{ id: number }>
}

interface OnboardingCase {
  id: number
  employee_id: number
  status: string
  progress: { total_tasks: number, completed_tasks: number, percent: number } | null
  employee?: { display_name: string } | null
  template?: { name: string } | null
}

interface TemplateTaskForm {
  title: string
  task_type: string
  due_days_after_start: number | null
  required: boolean
}

const auth = useAuthStore()
const api = useApiClient()
const templates = ref<OnboardingTemplate[]>([])
const cases = ref<OnboardingCase[]>([])
const templatesLoading = ref(true)
const casesLoading = ref(true)
const savingTemplate = ref(false)
const templateError = ref('')
const templateForm = reactive({
  name: '',
  description: '',
  employment_type: '',
  is_default: false,
  tasks: [
    { title: 'Upload required documents', task_type: 'document_upload', due_days_after_start: 2, required: true },
    { title: 'HR profile review', task_type: 'hr_review', due_days_after_start: 3, required: true },
    { title: 'Payroll setup', task_type: 'payroll_setup', due_days_after_start: 5, required: true },
  ] as TemplateTaskForm[],
})

onMounted(async () => {
  await Promise.all([loadTemplates(), loadCases()])
})

async function loadTemplates() {
  templatesLoading.value = true
  try {
    const response = await api.get<{ onboarding_templates: OnboardingTemplate[] }>('/onboarding-templates')
    templates.value = response.data.onboarding_templates
  } finally {
    templatesLoading.value = false
  }
}

async function loadCases() {
  casesLoading.value = true
  try {
    const response = await api.get<{ onboarding_cases: OnboardingCase[] }>('/onboarding-cases')
    cases.value = response.data.onboarding_cases
  } finally {
    casesLoading.value = false
  }
}

async function createTemplate() {
  savingTemplate.value = true
  templateError.value = ''

  try {
    await api.post('/onboarding-templates', {
      name: templateForm.name,
      description: templateForm.description || null,
      employment_type: templateForm.employment_type || null,
      is_default: templateForm.is_default,
      tasks: templateForm.tasks.map((task, index) => ({
        ...task,
        sort_order: index,
      })),
    })
    templateForm.name = ''
    templateForm.description = ''
    templateForm.employment_type = ''
    templateForm.is_default = false
    await loadTemplates()
  } catch (err) {
    templateError.value = apiErrorMessage(err, 'Unable to create onboarding template.')
  } finally {
    savingTemplate.value = false
  }
}

function addTask() {
  templateForm.tasks.push({ title: '', task_type: 'custom', due_days_after_start: null, required: true })
}

function removeTask(index: number) {
  templateForm.tasks.splice(index, 1)
}

function label(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}
</script>

<style scoped>
.template-builder,
.task-editor {
  display: grid;
  gap: 14px;
}

.template-builder h2,
.task-editor h3 {
  margin: 0;
}

.task-row {
  display: grid;
  grid-template-columns: minmax(220px, 1.35fr) minmax(190px, 1fr) 140px 124px auto;
  column-gap: 16px;
  row-gap: 12px;
  align-items: end;
}

.task-row > label {
  min-width: 0;
}

.task-row > label:not(.task-required-label) input,
.task-row > label:not(.task-required-label) select {
  box-sizing: border-box;
  width: 100%;
}

.task-due-label {
  width: 140px;
}

.task-required-label {
  align-self: end;
  box-sizing: border-box;
  width: 124px;
  min-height: 40px;
  justify-content: flex-start;
  white-space: nowrap;
}

.task-required-label input[type="checkbox"] {
  flex: 0 0 auto;
  width: 16px;
  height: 16px;
}

.task-row .danger {
  min-width: 96px;
  justify-self: end;
}

@media (max-width: 900px) {
  .task-row {
    grid-template-columns: 1fr;
  }
}
</style>
