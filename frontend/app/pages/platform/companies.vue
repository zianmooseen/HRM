<template>
  <section class="page">
    <div class="client-heading">
      <h1>Clients and companies</h1>
      <button v-if="auth.hasRole('super_admin')" type="button" @click="showForm = !showForm">{{ showForm ? 'Close form' : 'Add client' }}</button>
    </div>
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <p v-if="notice" role="status">{{ notice }}</p>
    <form v-if="showForm" class="client-form" @submit.prevent="createClient">
      <h2>Add client</h2>
      <p>The client will be ready to sign in after setup completes.</p>
      <div class="client-fields">
        <label>Company name<input v-model="form.company_name" required maxlength="255"></label>
        <label>Client code<input v-model="form.client_code" required pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="50" placeholder="example-company"><small>Used when signing in. Lowercase letters, numbers and hyphens.</small></label>
        <label>Admin name<input v-model="form.admin_name" required maxlength="255" autocomplete="name"></label>
        <label>Admin email<input v-model="form.admin_email" type="email" required maxlength="255" autocomplete="email"></label>
        <label>Admin username<input v-model="form.admin_username" required maxlength="100" pattern="[a-zA-Z0-9._-]+" autocomplete="off"></label>
        <label>Initial password<input v-model="form.admin_password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"><small>At least 12 characters. Share securely with the client admin.</small></label>
        <label>Emirate<input v-model="form.emirate" maxlength="100"></label>
        <label>Subscription plan<select v-model="form.subscription_plan_id"><option :value="null">Assign later</option><option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option></select></label>
      </div>
      <button type="submit" :disabled="saving">{{ saving ? 'Setting up client…' : 'Create client' }}</button>
    </form>
    <p v-if="loading" role="status">Loading clients…</p>
    <table v-if="clients.length">
      <caption>Client setup</caption>
      <thead><tr><th>Client</th><th>Sign-in code</th><th>Setup status</th><th>Action</th></tr></thead>
      <tbody>
        <tr v-for="client in clients" :key="client.id">
          <td>{{ client.company_name }}</td>
          <td>{{ client.client_code }}</td>
          <td>{{ client.maintenance ? 'Paused for maintenance' : label(client.provisioning_status) }}<p v-if="client.latest_run?.error_message" class="error">{{ client.latest_run.error_message }}</p></td>
          <td><button v-if="client.provisioning_status !== 'ready'" type="button" :disabled="retrying !== null" @click="retryClient(client)">{{ retrying === client.id ? 'Retrying…' : 'Retry setup' }}</button><span v-else>{{ client.maintenance ? 'Sign-in paused' : 'Ready to sign in' }}</span></td>
        </tr>
      </tbody>
    </table>
    <p v-if="!loading && !companies.length">No companies yet. Add your first client to get started.</p>
    <table v-else-if="companies.length">
      <caption>Companies</caption>
      <thead>
        <tr><th>Name</th><th>Emirate</th><th>Status</th><th>Billing</th></tr>
        <tr class="column-filter-row">
          <th><TableColumnFilter v-model="columnFilters.name" label="Filter company name" /></th>
          <th><TableColumnFilter v-model="columnFilters.emirate" label="Filter company emirate" /></th>
          <th><TableColumnFilter v-model="columnFilters.status" label="Filter company status" /></th>
          <th><TableColumnFilter v-model="columnFilters.billing" label="Filter company billing" /></th>
        </tr>
      </thead>
      <tbody><tr v-for="company in filteredCompanies" :key="company.id"><td>{{ company.name }}</td><td>{{ company.emirate || '-' }}</td><td>{{ company.status }}</td><td>{{ billingStatus(company.id) }}</td></tr></tbody>
    </table>
  </section>
</template>

<script setup lang="ts">
import type { OrganizationScopePayload } from '../../../../shared/types/organization-scope'

definePageMeta({ middleware: ['auth'] })

interface Client {
  id: number
  company_name: string
  client_code: string
  provisioning_status: 'pending' | 'provisioning' | 'failed' | 'ready'
  maintenance: boolean
  latest_run: { error_message: string | null } | null
}
interface Company { id: number, name: string, emirate: string | null, status: string }
interface Subscription { company_id: number, status: string, plan: { name: string } | null }
interface Plan { id: number, name: string, status: string }

const api = useApiClient()
const auth = useAuthStore()
const scope = useOrganizationScopeStore()
const companies = ref<Company[]>([])
const clients = ref<Client[]>([])
const subscriptions = ref<Subscription[]>([])
const plans = ref<Plan[]>([])
const loading = ref(true)
const saving = ref(false)
const retrying = ref<number | null>(null)
const showForm = ref(false)
const error = ref('')
const notice = ref('')
const initialForm = () => ({ company_name: '', client_code: '', admin_name: '', admin_email: '', admin_username: '', admin_password: '', emirate: '', subscription_plan_id: null as number | null })
const form = reactive(initialForm())
const { filters: columnFilters, filteredRows: filteredCompanies } = useTableColumnFilters(companies, [
  { key: 'name', value: company => company.name },
  { key: 'emirate', value: company => company.emirate },
  { key: 'status', value: company => company.status },
  { key: 'billing', value: company => billingStatus(company.id) },
])

async function load() {
  const [companyResponse, subscriptionResponse, clientResponse, planResponse] = await Promise.all([
    api.get<{ companies: Company[] }>('/companies'),
    api.get<{ company_subscriptions: Subscription[] }>('/platform/billing/subscriptions'),
    api.get<{ clients: Client[] }>('/platform/clients'),
    api.get<{ subscription_plans: Plan[] }>('/platform/billing/plans'),
  ])
  companies.value = companyResponse.data.companies
  subscriptions.value = subscriptionResponse.data.company_subscriptions
  clients.value = clientResponse.data.clients
  plans.value = planResponse.data.subscription_plans.filter(plan => plan.status === 'active')
}

async function refreshScope() {
  const response = await api.get<OrganizationScopePayload>('/organization-scope')
  scope.refreshOptions(response.data.companies)
}

async function createClient() {
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    // 1. Reserve the client and complete setup before allowing sign-in.
    const response = await api.post<{ client: Client }>('/platform/clients', form)
    const client = response.data.client
    notice.value = client.provisioning_status === 'ready'
      ? `${client.company_name} is ready. The admin can sign in with client code ${client.client_code}.`
      : 'Client saved, but setup needs attention. Use Retry setup after resolving the reported issue.'
    Object.assign(form, initialForm())
    showForm.value = false
    // 2. Refresh the directory and available company selection.
    await load()
    await refreshScope()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not complete client creation. Refresh the list before trying again.')
  } finally {
    saving.value = false
  }
}

async function retryClient(client: Client) {
  retrying.value = client.id
  error.value = ''
  try {
    await api.post(`/platform/clients/${client.id}/retry`, {})
    await load()
    await refreshScope()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not retry setup.')
  } finally {
    retrying.value = null
  }
}

onMounted(async () => {
  try { await load() } catch (err) { error.value = apiErrorMessage(err, 'Unable to load clients.') } finally { loading.value = false }
})

function billingStatus(companyId: number) {
  const subscription = subscriptions.value.find(item => item.company_id === companyId)
  return subscription ? `${label(subscription.status)} - ${subscription.plan?.name || 'Plan'}` : 'No subscription'
}
function label(value: string) { return value.replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase()) }
</script>

<style scoped>
.client-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.client-form { padding: 20px; border: 1px solid #d8dee4; border-radius: 8px; margin: 16px 0; }
.client-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; margin-bottom: 20px; }
.client-fields label { display: grid; gap: 6px; align-content: start; }
.client-fields input, .client-fields select { width: 100%; min-height: 40px; padding: 8px; border: 1px solid #b8c1c8; border-radius: 6px; }
small { color: #52616b; }
.error { color: #9f1d35; }
caption { text-align: left; font-weight: 600; padding: 16px 0 8px; }
</style>
