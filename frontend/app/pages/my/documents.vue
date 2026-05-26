<template>
  <section class="page">
    <header>
      <h1>My documents</h1>
    </header>

    <form v-if="employee" class="form-grid" @submit.prevent="uploadDocument">
      <label>
        Document type
        <select v-model="form.document_type" required>
          <option value="passport">Passport</option>
          <option value="visa">Visa</option>
          <option value="labor_card">Labor card</option>
          <option value="emirates_id">Emirates ID</option>
          <option value="medical_certificate">Medical certificate</option>
          <option value="contract">Contract</option>
          <option value="other">Other</option>
        </select>
      </label>
      <label>
        Title
        <input v-model="form.title" placeholder="Optional">
      </label>
      <label class="full">
        File
        <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required @change="selectFile">
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <button type="submit" :disabled="uploading">{{ uploading ? 'Uploading...' : 'Upload document' }}</button>
    </form>

    <p v-if="loading">Loading documents...</p>
    <table v-else>
      <thead>
        <tr>
          <th>Type</th>
          <th>Title</th>
          <th>File</th>
          <th>Expiry</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="document in documents" :key="document.id">
          <td>{{ label(document.document_type) }}</td>
          <td>{{ document.title }}</td>
          <td><a :href="downloadHref(document)" target="_blank">{{ document.original_file_name }}</a></td>
          <td>{{ document.expiry_date || '-' }}</td>
        </tr>
        <tr v-if="documents.length === 0">
          <td colspan="4">No documents uploaded yet.</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface EmployeeDocument {
  id: number
  document_type: string
  title: string
  original_file_name: string
  expiry_date: string | null
  download_url: string
}

const api = useApiClient()
const config = useRuntimeConfig()
const employee = ref<any>(null)
const documents = ref<EmployeeDocument[]>([])
const loading = ref(true)
const uploading = ref(false)
const error = ref('')
const selectedFile = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const form = reactive({
  document_type: 'passport',
  title: '',
})

onMounted(async () => {
  try {
    const profile = await api.get<{ employee: any }>('/self/profile')
    employee.value = profile.data.employee
    await loadDocuments()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to load documents.')
  } finally {
    loading.value = false
  }
})

async function loadDocuments() {
  const response = await api.get<{ documents: EmployeeDocument[] }>('/documents')
  documents.value = response.data.documents
}

function selectFile(event: Event) {
  selectedFile.value = (event.target as HTMLInputElement).files?.[0] || null
}

async function uploadDocument() {
  if (!employee.value || !selectedFile.value) {
    error.value = 'Choose a file to upload.'
    return
  }

  uploading.value = true
  error.value = ''
  const payload = new FormData()
  payload.append('employee_id', String(employee.value.id))
  payload.append('document_type', form.document_type)
  if (form.title) payload.append('title', form.title)
  payload.append('file', selectedFile.value)

  try {
    await api.post('/documents', payload)
    form.title = ''
    selectedFile.value = null
    if (fileInput.value) fileInput.value.value = ''
    await loadDocuments()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to upload document.')
  } finally {
    uploading.value = false
  }
}

function downloadHref(document: EmployeeDocument) {
  return `${config.public.apiBaseUrl.replace(/\/api$/, '')}${document.download_url}`
}

function label(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}
</script>
