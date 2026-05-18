<template>
  <section class="page">
    <h1>Companies</h1>
    <table>
      <thead><tr><th>Name</th><th>Emirate</th><th>Status</th></tr></thead>
      <tbody>
        <tr v-for="company in companies" :key="company.id">
          <td>{{ company.name }}</td>
          <td>{{ company.emirate || '-' }}</td>
          <td>{{ company.status }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const api = useApiClient()
const companies = ref<any[]>([])

onMounted(async () => {
  const response = await api.get<{ companies: any[] }>('/companies')
  companies.value = response.data.companies
})
</script>
