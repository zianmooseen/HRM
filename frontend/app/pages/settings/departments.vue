<template>
  <section class="page">
    <h1>Departments</h1>
    <form class="form-grid" @submit.prevent="saveDepartment">
      <label>
        Name
        <input v-model="form.name" required>
      </label>
      <label>
        Code
        <input v-model="form.code" required>
      </label>
      <label>
        Branch
        <select v-model="form.branch_id">
          <option :value="null">No branch</option>
          <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
        </select>
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
        <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : editingId ? 'Save department' : 'Create department' }}</button>
        <button v-if="editingId" type="button" class="secondary" @click="resetForm">Cancel</button>
      </div>
    </form>
    <table>
      <thead>
        <tr><th>Name</th><th>Code</th><th>Status</th><th></th></tr>
        <tr class="column-filter-row">
          <th><TableColumnFilter v-model="columnFilters.name" label="Filter department name" /></th>
          <th><TableColumnFilter v-model="columnFilters.code" label="Filter department code" /></th>
          <th><TableColumnFilter v-model="columnFilters.status" label="Filter department status" /></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="department in filteredDepartments" :key="department.id">
          <td>{{ department.name }}</td>
          <td>{{ department.code }}</td>
          <td>{{ department.status }}</td>
          <td class="table-actions">
            <button type="button" class="secondary" @click="editDepartment(department)">Edit</button>
            <button type="button" class="danger" @click="deleteDepartment(department.id)">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const api = useApiClient()
const departments = ref<any[]>([])
const { filters: columnFilters, filteredRows: filteredDepartments } = useTableColumnFilters(
  departments,
  [
    { key: 'name', value: department => department.name },
    { key: 'code', value: department => department.code },
    { key: 'status', value: department => department.status },
  ],
)
const branches = ref<any[]>([])
const form = reactive({ name: '', code: '', branch_id: null as number | null, status: 'active' })
const editingId = ref<number | null>(null)
const saving = ref(false)
const error = ref('')

async function load() {
  const [departmentResponse, branchResponse] = await Promise.all([
    api.get<{ departments: any[] }>('/departments'),
    api.get<{ branches: any[] }>('/branches'),
  ])
  departments.value = departmentResponse.data.departments
  branches.value = branchResponse.data.branches
}

function resetForm() {
  Object.assign(form, { name: '', code: '', branch_id: null, status: 'active' })
  editingId.value = null
  error.value = ''
}

function editDepartment(department: any) {
  Object.assign(form, {
    name: department.name,
    code: department.code,
    branch_id: department.branch_id,
    status: department.status,
  })
  editingId.value = department.id
}

async function saveDepartment() {
  saving.value = true
  error.value = ''

  try {
    if (editingId.value) {
      await api.put(`/departments/${editingId.value}`, form)
    } else {
      await api.post('/departments', form)
    }
    resetForm()
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to save department.')
  } finally {
    saving.value = false
  }
}

async function deleteDepartment(id: number) {
  await api.delete(`/departments/${id}`)
  await load()
}

onMounted(load)
</script>
