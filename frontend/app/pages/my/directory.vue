<template>
  <section class="page">
    <h1>Employee Directory</h1>
    <p>Find colleagues in your company using their name or work email.</p>
    <form class="directory-search" @submit.prevent="search">
      <label>Search<input v-model="query" type="search" maxlength="100" placeholder="Name or work email"></label>
      <button type="submit" :disabled="loading">Search</button>
    </form>
    <p v-if="loading" role="status">Loading colleagues…</p>
    <p v-else-if="error" class="error" role="alert">{{ error }}</p>
    <template v-else>
      <div class="directory-table">
        <table>
          <thead><tr><th>Name</th><th>Position</th><th>Department</th><th>Branch</th><th>Work email</th><th>Office telephone</th></tr></thead>
          <tbody>
            <tr v-for="person in people" :key="person.id">
              <td>{{ person.name }}</td><td>{{ person.position || '—' }}</td><td>{{ person.department || '—' }}</td><td>{{ person.branch || '—' }}</td>
              <td><a v-if="person.work_email" :href="`mailto:${person.work_email}`">{{ person.work_email }}</a><span v-else>—</span></td>
              <td>{{ person.office_phone || '—' }}</td>
            </tr>
            <tr v-if="!people.length"><td colspan="6">No colleagues match your search.</td></tr>
          </tbody>
        </table>
      </div>
      <div v-if="pagination.total" class="directory-pagination">
        <button type="button" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">Previous</button>
        <span>Page {{ pagination.current_page }} of {{ pagination.last_page }} · {{ pagination.total }} employees</span>
        <button type="button" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Next</button>
      </div>
    </template>
  </section>
</template>
<script setup lang="ts">
import type { DirectoryEmployee, Pagination } from '../../../../shared/types/self-service'
definePageMeta({ middleware: ['auth'] })
const api = useApiClient()
const query = ref('')
const appliedSearch = ref('')
const people = ref<DirectoryEmployee[]>([])
const pagination = ref<Pagination>({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(true)
const error = ref('')
async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams({ search: appliedSearch.value, page: String(page) })
    const response = await api.get<{ employees: DirectoryEmployee[], pagination: Pagination }>(`/self/directory?${params}`)
    people.value = response.data.employees
    pagination.value = response.data.pagination
  } catch (err) { error.value = apiErrorMessage(err, 'Unable to load the employee directory.') }
  finally { loading.value = false }
}
function search() { appliedSearch.value = query.value.trim(); void load() }
onMounted(() => load())
</script>
<style scoped>
.directory-search, .directory-pagination { display: flex; gap: 16px; align-items: end; flex-wrap: wrap; margin: 20px 0; }
.directory-search label { display: grid; gap: 6px; flex: 1; max-width: 420px; }
.directory-search input { min-height: 40px; padding: 8px; border: 1px solid var(--color-input-border); border-radius: 6px; }
.directory-table { overflow-x: auto; }
</style>
