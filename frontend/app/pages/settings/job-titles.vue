<template>
  <section class="page">
    <h1>Job titles</h1>
    <form class="form-grid" @submit.prevent="saveJobTitle">
      <label>
        Title
        <input v-model="form.title" required>
      </label>
      <label>
        Code
        <input v-model="form.code" required>
      </label>
      <label>
        Description
        <textarea v-model="form.description" />
      </label>
      <label>
        Status
        <select v-model="form.status">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <div class="button-row">
        <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : editingId ? 'Save job title' : 'Create job title' }}</button>
        <button v-if="editingId" type="button" class="secondary" @click="resetForm">Cancel</button>
      </div>
    </form>
    <table>
      <thead>
        <tr><th>Title</th><th>Code</th><th>Status</th><th></th></tr>
        <tr class="column-filter-row">
          <th><TableColumnFilter v-model="columnFilters.title" label="Filter job title" /></th>
          <th><TableColumnFilter v-model="columnFilters.code" label="Filter job title code" /></th>
          <th><TableColumnFilter v-model="columnFilters.status" label="Filter job title status" /></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="jobTitle in filteredJobTitles" :key="jobTitle.id">
          <td>{{ jobTitle.title }}</td>
          <td>{{ jobTitle.code }}</td>
          <td>{{ jobTitle.status }}</td>
          <td class="table-actions">
            <button type="button" class="secondary" @click="editJobTitle(jobTitle)">Edit</button>
            <button type="button" class="danger" @click="deleteJobTitle(jobTitle.id)">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const api = useApiClient()
const jobTitles = ref<any[]>([])
const { filters: columnFilters, filteredRows: filteredJobTitles } = useTableColumnFilters(
  jobTitles,
  [
    { key: 'title', value: jobTitle => jobTitle.title },
    { key: 'code', value: jobTitle => jobTitle.code },
    { key: 'status', value: jobTitle => jobTitle.status },
  ],
)
const form = reactive({ title: '', code: '', description: '', status: 'active' })
const editingId = ref<number | null>(null)
const saving = ref(false)
const error = ref('')

async function load() {
  const response = await api.get<{ job_titles: any[] }>('/job-titles')
  jobTitles.value = response.data.job_titles
}

function resetForm() {
  Object.assign(form, { title: '', code: '', description: '', status: 'active' })
  editingId.value = null
  error.value = ''
}

function editJobTitle(jobTitle: any) {
  Object.assign(form, {
    title: jobTitle.title,
    code: jobTitle.code,
    description: jobTitle.description || '',
    status: jobTitle.status,
  })
  editingId.value = jobTitle.id
}

async function saveJobTitle() {
  saving.value = true
  error.value = ''

  try {
    if (editingId.value) {
      await api.put(`/job-titles/${editingId.value}`, form)
    } else {
      await api.post('/job-titles', form)
    }
    resetForm()
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to save job title.')
  } finally {
    saving.value = false
  }
}

async function deleteJobTitle(id: number) {
  await api.delete(`/job-titles/${id}`)
  await load()
}

onMounted(load)
</script>
