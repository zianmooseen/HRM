<template>
  <section class="page">
    <header>
      <div>
        <h1>Platform Billing</h1>
        <p class="muted">Manage SaaS plans, company subscriptions, and client invoices.</p>
      </div>
    </header>

    <p v-if="loadError" class="error">{{ loadError }}</p>

    <section class="panel">
      <header>
        <div>
          <h2>Subscription Plans</h2>
          <p class="muted">Internal plan catalog used when assigning companies to billing tiers.</p>
        </div>
      </header>
      <form class="form-grid" @submit.prevent="createPlan">
        <label>
          Name
          <input v-model="planForm.name" required>
        </label>
        <label>
          Code
          <input v-model="planForm.code" required>
        </label>
        <label>
          Cycle
          <select v-model="planForm.billing_cycle">
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
          </select>
        </label>
        <label>
          Price
          <input v-model.number="planForm.price" type="number" min="0" step="0.01" required>
        </label>
        <label>
          Currency
          <input v-model="planForm.currency" maxlength="3" required>
        </label>
        <label>
          Max employees
          <input v-model.number="planForm.max_employees" type="number" min="1">
        </label>
        <label>
          Status
          <select v-model="planForm.status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </label>
        <label class="full">
          Features
          <input v-model="planFeaturesText" placeholder="Comma separated features">
        </label>
        <p v-if="planError" class="error">{{ planError }}</p>
        <button type="submit" :disabled="savingPlan">{{ savingPlan ? 'Creating...' : 'Create plan' }}</button>
      </form>

      <table>
        <thead>
          <tr>
            <th>Plan</th>
            <th>Cycle</th>
            <th>Price</th>
            <th>Limit</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="plan in plans" :key="plan.id">
            <td>{{ plan.name }} <small>{{ plan.code }}</small></td>
            <td>{{ label(plan.billing_cycle) }}</td>
            <td>{{ money(plan.price, plan.currency) }}</td>
            <td>{{ plan.max_employees || 'Unlimited' }}</td>
            <td>{{ label(plan.status) }}</td>
          </tr>
          <tr v-if="plans.length === 0">
            <td colspan="5">No plans configured yet.</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="panel">
      <header>
        <div>
          <h2>Assign Subscription</h2>
          <p class="muted">Create a subscription record for a company.</p>
        </div>
      </header>
      <form class="form-grid" @submit.prevent="assignSubscription">
        <label>
          Company
          <select v-model.number="subscriptionForm.company_id" required>
            <option disabled :value="0">Select company</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
          </select>
        </label>
        <label>
          Plan
          <select v-model.number="subscriptionForm.subscription_plan_id" required>
            <option disabled :value="0">Select plan</option>
            <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
          </select>
        </label>
        <label>
          Status
          <select v-model="subscriptionForm.status">
            <option value="trialing">Trialing</option>
            <option value="active">Active</option>
            <option value="past_due">Past due</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </label>
        <label>
          Starts on
          <input v-model="subscriptionForm.starts_on" type="date" required>
        </label>
        <label>
          Trial ends
          <input v-model="subscriptionForm.trial_ends_on" type="date">
        </label>
        <label>
          Period starts
          <input v-model="subscriptionForm.current_period_starts_on" type="date">
        </label>
        <label>
          Period ends
          <input v-model="subscriptionForm.current_period_ends_on" type="date">
        </label>
        <label class="full">
          Notes
          <textarea v-model="subscriptionForm.notes" rows="2" />
        </label>
        <p v-if="subscriptionError" class="error">{{ subscriptionError }}</p>
        <button type="submit" :disabled="savingSubscription">{{ savingSubscription ? 'Assigning...' : 'Assign subscription' }}</button>
      </form>

      <table>
        <thead>
          <tr>
            <th>Company</th>
            <th>Plan</th>
            <th>Status</th>
            <th>Current period</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="subscription in subscriptions" :key="subscription.id">
            <td>{{ subscription.company?.name || subscription.company_id }}</td>
            <td>{{ subscription.plan?.name || subscription.subscription_plan_id }}</td>
            <td>{{ label(subscription.status) }}</td>
            <td>{{ subscription.current_period_starts_on || '-' }} to {{ subscription.current_period_ends_on || '-' }}</td>
          </tr>
          <tr v-if="subscriptions.length === 0">
            <td colspan="4">No company subscriptions yet.</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="panel">
      <header>
        <div>
          <h2>Invoices</h2>
          <p class="muted">Create and mark client invoices paid.</p>
        </div>
      </header>
      <form class="form-grid" @submit.prevent="createInvoice">
        <label>
          Company
          <select v-model.number="invoiceForm.company_id" required>
            <option disabled :value="0">Select company</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
          </select>
        </label>
        <label>
          Subscription
          <select v-model.number="invoiceForm.company_subscription_id">
            <option :value="null">No subscription link</option>
            <option v-for="subscription in subscriptionsForInvoice" :key="subscription.id" :value="subscription.id">
              {{ subscription.plan?.name || subscription.id }} ({{ label(subscription.status) }})
            </option>
          </select>
        </label>
        <label>
          Invoice number
          <input v-model="invoiceForm.invoice_number" required>
        </label>
        <label>
          Issue date
          <input v-model="invoiceForm.issue_date" type="date" required>
        </label>
        <label>
          Due date
          <input v-model="invoiceForm.due_date" type="date" required>
        </label>
        <label>
          Subtotal
          <input v-model.number="invoiceForm.subtotal" type="number" min="0" step="0.01" required>
        </label>
        <label>
          Tax
          <input v-model.number="invoiceForm.tax_amount" type="number" min="0" step="0.01">
        </label>
        <label>
          Total
          <input v-model.number="invoiceForm.total_amount" type="number" min="0" step="0.01" required>
        </label>
        <label>
          Currency
          <input v-model="invoiceForm.currency" maxlength="3" required>
        </label>
        <label>
          Status
          <select v-model="invoiceForm.status">
            <option value="draft">Draft</option>
            <option value="open">Open</option>
            <option value="paid">Paid</option>
            <option value="void">Void</option>
            <option value="uncollectible">Uncollectible</option>
          </select>
        </label>
        <p v-if="invoiceError" class="error">{{ invoiceError }}</p>
        <button type="submit" :disabled="savingInvoice">{{ savingInvoice ? 'Creating...' : 'Create invoice' }}</button>
      </form>

      <table>
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Company</th>
            <th>Due</th>
            <th>Total</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="invoice in invoices" :key="invoice.id">
            <td>{{ invoice.invoice_number }}</td>
            <td>{{ invoice.company?.name || invoice.company_id }}</td>
            <td>{{ invoice.due_date }}</td>
            <td>{{ money(invoice.total_amount, invoice.currency) }}</td>
            <td>{{ label(invoice.status) }}</td>
            <td>
              <button v-if="invoice.status !== 'paid'" type="button" class="secondary" @click="markPaid(invoice)">Mark paid</button>
            </td>
          </tr>
          <tr v-if="invoices.length === 0">
            <td colspan="6">No invoices yet.</td>
          </tr>
        </tbody>
      </table>
    </section>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface Company {
  id: number
  name: string
}

interface Plan {
  id: number
  name: string
  code: string
  billing_cycle: string
  price: string | number
  currency: string
  max_employees: number | null
  status: string
}

interface Subscription {
  id: number
  company_id: number
  subscription_plan_id: number
  status: string
  starts_on: string
  current_period_starts_on: string | null
  current_period_ends_on: string | null
  company?: Company
  plan?: Plan
}

interface Invoice {
  id: number
  company_id: number
  invoice_number: string
  due_date: string
  total_amount: string | number
  currency: string
  status: string
  company?: Company
}

const api = useApiClient()
const companies = ref<Company[]>([])
const plans = ref<Plan[]>([])
const subscriptions = ref<Subscription[]>([])
const invoices = ref<Invoice[]>([])
const loadError = ref('')
const planError = ref('')
const subscriptionError = ref('')
const invoiceError = ref('')
const savingPlan = ref(false)
const savingSubscription = ref(false)
const savingInvoice = ref(false)
const today = new Date().toISOString().slice(0, 10)
const planFeaturesText = ref('')

const planForm = reactive({
  name: '',
  code: '',
  billing_cycle: 'monthly',
  price: 0,
  currency: 'AED',
  max_employees: null as number | null,
  status: 'active',
})
const subscriptionForm = reactive({
  company_id: 0,
  subscription_plan_id: 0,
  status: 'trialing',
  starts_on: today,
  trial_ends_on: '',
  current_period_starts_on: today,
  current_period_ends_on: '',
  notes: '',
})
const invoiceForm = reactive({
  company_id: 0,
  company_subscription_id: null as number | null,
  invoice_number: '',
  issue_date: today,
  due_date: today,
  subtotal: 0,
  tax_amount: 0,
  total_amount: 0,
  currency: 'AED',
  status: 'open',
})
const subscriptionsForInvoice = computed(() =>
  subscriptions.value.filter((subscription) => subscription.company_id === invoiceForm.company_id),
)

onMounted(loadAll)

async function loadAll() {
  loadError.value = ''

  try {
    const [companyResponse, planResponse, subscriptionResponse, invoiceResponse] = await Promise.all([
      api.get<{ companies: Company[] }>('/companies'),
      api.get<{ subscription_plans: Plan[] }>('/platform/billing/plans'),
      api.get<{ company_subscriptions: Subscription[] }>('/platform/billing/subscriptions'),
      api.get<{ billing_invoices: Invoice[] }>('/platform/billing/invoices'),
    ])
    companies.value = companyResponse.data.companies
    plans.value = planResponse.data.subscription_plans
    subscriptions.value = subscriptionResponse.data.company_subscriptions
    invoices.value = invoiceResponse.data.billing_invoices
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Unable to load platform billing.')
  }
}

async function createPlan() {
  savingPlan.value = true
  planError.value = ''

  try {
    await api.post('/platform/billing/plans', {
      ...planForm,
      code: planForm.code.trim().toLowerCase(),
      currency: planForm.currency.toUpperCase(),
      features: planFeaturesText.value.split(',').map((item) => item.trim()).filter(Boolean),
    })
    Object.assign(planForm, { name: '', code: '', billing_cycle: 'monthly', price: 0, currency: 'AED', max_employees: null, status: 'active' })
    planFeaturesText.value = ''
    await loadAll()
  } catch (err) {
    planError.value = apiErrorMessage(err, 'Unable to create subscription plan.')
  } finally {
    savingPlan.value = false
  }
}

async function assignSubscription() {
  savingSubscription.value = true
  subscriptionError.value = ''

  try {
    await api.post(`/platform/billing/companies/${subscriptionForm.company_id}/subscription`, {
      ...subscriptionForm,
      trial_ends_on: subscriptionForm.trial_ends_on || null,
      current_period_starts_on: subscriptionForm.current_period_starts_on || null,
      current_period_ends_on: subscriptionForm.current_period_ends_on || null,
      notes: subscriptionForm.notes || null,
    })
    await loadAll()
  } catch (err) {
    subscriptionError.value = apiErrorMessage(err, 'Unable to assign subscription.')
  } finally {
    savingSubscription.value = false
  }
}

async function createInvoice() {
  savingInvoice.value = true
  invoiceError.value = ''

  try {
    await api.post(`/platform/billing/companies/${invoiceForm.company_id}/invoices`, {
      ...invoiceForm,
      currency: invoiceForm.currency.toUpperCase(),
      company_subscription_id: invoiceForm.company_subscription_id || null,
    })
    invoiceForm.invoice_number = ''
    await loadAll()
  } catch (err) {
    invoiceError.value = apiErrorMessage(err, 'Unable to create invoice.')
  } finally {
    savingInvoice.value = false
  }
}

async function markPaid(invoice: Invoice) {
  await api.post(`/platform/billing/invoices/${invoice.id}/mark-paid`, {})
  await loadAll()
}

function money(value: string | number, currency: string) {
  return new Intl.NumberFormat('en-AE', { style: 'currency', currency }).format(Number(value || 0))
}

function label(value: string) {
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}
</script>

<style scoped>
.muted {
  margin: 6px 0 0;
  color: #5d6a72;
}

.panel {
  display: grid;
  gap: 16px;
  margin-top: 20px;
  border: 1px solid #d8dee4;
  border-radius: 8px;
  background: #ffffff;
  padding: 16px;
}

.panel h2 {
  margin: 0;
}

.full {
  grid-column: 1 / -1;
}

small {
  display: block;
  color: #5d6a72;
}
</style>
