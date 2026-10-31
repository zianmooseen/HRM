<template>
  <section class="page profile-page">
    <h1>{{ titles[section] }}</h1>
    <p v-if="loading" role="status">Loading your details…</p>
    <p v-else-if="error" role="alert" class="error">{{ error }}</p>
    <template v-else-if="employee">
      <section class="profile-banner" aria-label="Employee overview">
        <div class="profile-avatar" aria-hidden="true"><svg viewBox="0 0 48 48"><circle cx="24" cy="17" r="8" /><path d="M9 41c0-16 30-16 30 0" /></svg></div>
        <div class="profile-identity">
          <h2>{{ employee.display_name }}</h2>
          <p>{{ employee.job_title?.title || 'Employee' }}<span v-if="employee.department?.name"> · {{ employee.department.name }}</span></p>
          <a v-if="employee.work_email" :href="`mailto:${employee.work_email}`">{{ employee.work_email }}</a>
        </div>
      </section>
      <div class="profile-layout">
        <section class="profile-details">
          <h2>{{ section === 'summary' ? 'Personal information' : titles[section] }}</h2>
          <dl class="profile-fields">
            <div v-for="field in fields" :key="field.label">
              <dt>{{ field.label }}</dt><dd>{{ field.value || 'Not recorded' }}</dd>
            </div>
          </dl>
        </section>
        <aside class="profile-actions" aria-label="Related employee pages">
          <h2>Things I can do</h2>
          <NuxtLink to="/my/personal/contact">View contact details</NuxtLink>
          <NuxtLink to="/my/personal/key-dates">View status and key dates</NuxtLink>
          <NuxtLink to="/my/personal/job">View job summary</NuxtLink>
          <NuxtLink to="/my/company">View my company</NuxtLink>
          <p>Contact HR if your details need to be updated.</p>
        </aside>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { EmployeeSelfProfile } from '../../../shared/types/self-service'
const props = withDefaults(defineProps<{ section?: 'summary' | 'contact' | 'key-dates' | 'job' }>(), { section: 'summary' })
const titles = { summary: 'Employee Summary', contact: 'Name and Contact Details', 'key-dates': 'Status / Key Dates', job: 'Job Summary' }
const api = useApiClient()
const employee = ref<EmployeeSelfProfile | null>(null)
const loading = ref(true)
const error = ref('')
const fields = computed(() => {
  const e = employee.value
  if (!e) return []
  switch (props.section) {
    case 'contact': return [
      { label: 'First name', value: e.first_name }, { label: 'Middle name', value: e.middle_name }, { label: 'Last name', value: e.last_name },
      { label: 'Work email', value: e.work_email }, { label: 'Personal email', value: e.personal_email }, { label: 'Telephone', value: e.phone },
    ]
    case 'key-dates': return [
      { label: 'Status', value: label(e.status) }, { label: 'Hire date', value: e.hire_date }, { label: 'Probation end', value: e.probation_end_date },
      { label: 'Contract start', value: e.contract_start_date }, { label: 'Contract end', value: e.contract_end_date },
    ]
    case 'job': return [
      { label: 'Position', value: e.job_title?.title }, { label: 'Department', value: e.department?.name }, { label: 'Branch', value: e.branch?.name },
      { label: 'Employment type', value: label(e.employment_type) }, { label: 'Contract type', value: label(e.contract_type) }, { label: 'Hire date', value: e.hire_date },
    ]
    default: return [
      { label: 'Employee code', value: e.employee_code }, { label: 'Name', value: e.display_name }, { label: 'Status', value: label(e.status) },
      { label: 'Work email', value: e.work_email }, { label: 'Position', value: e.job_title?.title }, { label: 'Department', value: e.department?.name },
      { label: 'Branch', value: e.branch?.name }, { label: 'Hire date', value: e.hire_date },
    ]
  }
})
function label(value: string | null) { return value?.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase()) }
onMounted(async () => {
  try { employee.value = (await api.get<{ employee: EmployeeSelfProfile }>('/self/profile')).data.employee }
  catch (err) { error.value = apiErrorMessage(err, 'Unable to load your employee details.') }
  finally { loading.value = false }
})
</script>

<style scoped>
.profile-banner { display: flex; align-items: center; gap: 24px; padding: 28px; background: white; border: 1px solid var(--color-border); border-radius: var(--radius-card); }
.profile-avatar { width: 92px; height: 112px; flex-shrink: 0; display: grid; place-items: center; border-radius: 8px; background: #eeeeee; color: #acacac; }
.profile-avatar svg { width: 64px; height: 64px; fill: none; stroke: currentColor; stroke-width: 3; }
.profile-identity { min-width: 0; }
.profile-identity h2 { margin: 0 0 8px; }
.profile-identity p { margin: 0 0 6px; color: var(--color-muted); }
.profile-identity a, .profile-actions a { color: var(--color-accent-hover); text-decoration: underline; text-underline-offset: 3px; overflow-wrap: anywhere; }
.profile-layout { display: grid; grid-template-columns: minmax(0, 1fr) 260px; gap: 24px; align-items: start; }
.profile-details { padding: 28px; background: white; border: 1px solid var(--color-border); border-radius: var(--radius-card); }
.profile-details h2, .profile-actions h2 { margin: 0 0 28px; }
.profile-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 28px 36px; margin: 0; }
.profile-fields dt { color: var(--color-muted); margin-bottom: 5px; }
.profile-fields dd { margin: 0; font-weight: 400; overflow-wrap: anywhere; }
.profile-actions { padding: 28px 20px; }
.profile-actions a { display: block; margin-bottom: 16px; }
.profile-actions p { margin-top: 32px; color: var(--color-muted); }
@media (max-width: 1100px) { .profile-layout { grid-template-columns: 1fr; } .profile-actions { padding: 20px; } }
@media (max-width: 600px) { .profile-banner { padding: 20px; gap: 16px; align-items: start; } .profile-avatar { width: 60px; height: 76px; } .profile-avatar svg { width: 42px; height: 42px; } .profile-fields { grid-template-columns: 1fr; } .profile-details { padding: 22px; } }
</style>
