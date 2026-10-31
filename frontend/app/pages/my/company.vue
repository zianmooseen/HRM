<template>
  <section class="page">
    <h1>My Company</h1>
    <p v-if="loading" role="status">Loading company details…</p>
    <p v-else-if="error" class="error" role="alert">{{ error }}</p>
    <template v-else-if="company">
      <dl class="detail-list">
        <dt>Company</dt><dd>{{ company.name }}</dd>
        <dt>Legal name</dt><dd>{{ company.legal_name || 'Not recorded' }}</dd>
        <dt>Country</dt><dd>{{ company.country }}</dd>
        <dt>Emirate</dt><dd>{{ company.emirate || 'Not recorded' }}</dd>
        <dt>Time zone</dt><dd>{{ company.timezone }}</dd>
        <dt>Currency</dt><dd>{{ company.default_currency }}</dd>
      </dl>
      <h2>My branch</h2>
      <dl v-if="company.branch" class="detail-list">
        <dt>Branch</dt><dd>{{ company.branch.name }}</dd>
        <dt>Address</dt><dd>{{ [company.branch.address_line_1, company.branch.address_line_2, company.branch.city].filter(Boolean).join(', ') || 'Not recorded' }}</dd>
        <dt>Office telephone</dt><dd>{{ company.branch.phone || 'Not recorded' }}</dd>
        <dt>Office email</dt><dd>{{ company.branch.email || 'Not recorded' }}</dd>
      </dl>
      <p v-else>No branch has been assigned.</p>
    </template>
  </section>
</template>
<script setup lang="ts">
import type { SelfCompany } from '../../../../shared/types/self-service'
definePageMeta({ middleware: ['auth'] })
const api = useApiClient()
const company = ref<SelfCompany | null>(null)
const loading = ref(true)
const error = ref('')
onMounted(async () => {
  try { company.value = (await api.get<{ company: SelfCompany }>('/self/company')).data.company }
  catch (err) { error.value = apiErrorMessage(err, 'Unable to load your company.') }
  finally { loading.value = false }
})
</script>
