<template>
  <section class="page">
    <header>
      <h1>Branches</h1>
    </header>
    <form class="form-grid" @submit.prevent="saveBranch">
      <label>
        Name
        <input v-model="form.name" required>
      </label>
      <label>
        Code
        <input v-model="form.code" required>
      </label>
      <label>
        Emirate
        <input v-model="form.emirate">
      </label>
      <label>
        City
        <input v-model="form.city">
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
        <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : editingId ? 'Save branch' : 'Create branch' }}</button>
        <button v-if="editingId" type="button" class="secondary" @click="resetForm">Cancel</button>
      </div>
    </form>
    <table>
      <thead>
        <tr><th>Name</th><th>Code</th><th>Emirate</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <tr v-for="branch in branches" :key="branch.id">
          <td>{{ branch.name }}</td>
          <td>{{ branch.code }}</td>
          <td>{{ branch.emirate || '-' }}</td>
          <td>{{ branch.status }}</td>
          <td class="table-actions">
            <button type="button" class="secondary" @click="editBranch(branch)">Edit</button>
            <button type="button" class="danger" @click="deleteBranch(branch.id)">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const api = useApiClient()
const branches = ref<any[]>([])
const form = reactive({ name: '', code: '', emirate: '', city: '', status: 'active' })
const editingId = ref<number | null>(null)
const saving = ref(false)
const error = ref('')

async function load() {
  const response = await api.get<{ branches: any[] }>('/branches')
  branches.value = response.data.branches
}

function resetForm() {
  Object.assign(form, { name: '', code: '', emirate: '', city: '', status: 'active' })
  editingId.value = null
  error.value = ''
}

function editBranch(branch: any) {
  Object.assign(form, {
    name: branch.name,
    code: branch.code,
    emirate: branch.emirate || '',
    city: branch.city || '',
    status: branch.status,
  })
  editingId.value = branch.id
}

async function saveBranch() {
  saving.value = true
  error.value = ''

  try {
    if (editingId.value) {
      await api.put(`/branches/${editingId.value}`, form)
    } else {
      await api.post('/branches', form)
    }
    resetForm()
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to save branch.')
  } finally {
    saving.value = false
  }
}

async function deleteBranch(id: number) {
  await api.delete(`/branches/${id}`)
  await load()
}

onMounted(load)
</script>
