<template>
  <section class="page">
    <h1>Companies</h1>
    <table>
      <thead><tr><th>Name</th><th>Emirate</th><th>Status</th><th>Billing</th></tr></thead>
      <tbody>
        <tr v-for="company in companies" :key="company.id">
          <td>{{ company.name }}</td>
          <td>{{ company.emirate || '-' }}</td>
          <td>{{ company.status }}</td>
          <td>{{ billingStatus(company.id) }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const api = useApiClient()
const companies = ref<any[]>([])
const subscriptions = ref<any[]>([])

onMounted(async () => {
  const [companyResponse, subscriptionResponse] = await Promise.all([
    api.get<{ companies: any[] }>('/companies'),
    api.get<{ company_subscriptions: any[] }>('/platform/billing/subscriptions'),
  ])
  companies.value = companyResponse.data.companies
  subscriptions.value = subscriptionResponse.data.company_subscriptions
})

function billingStatus(companyId: number) {
  const subscription = subscriptions.value.find((item) => item.company_id === companyId)

  return subscription ? `${label(subscription.status)} - ${subscription.plan?.name || 'Plan'}` : 'No subscription'
}

function label(value: string) {
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}
</script>
