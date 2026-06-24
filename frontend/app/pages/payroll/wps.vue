<template>
  <section class="page">
    <header>
      <div>
        <h1>WPS salary transfers</h1>
        <p class="muted">Track SIF generation, provider submission, payment confirmation, and compliance evidence.</p>
      </div>
      <NuxtLink to="/payroll">Back to payroll</NuxtLink>
    </header>

    <p v-if="loading">Loading WPS operations...</p>
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <template v-else>
      <section class="summary-grid">
        <article><span>Current risk</span><strong>{{ label(summary?.risk_status || 'not_scheduled') }}</strong></article>
        <article><span>Open batches</span><strong>{{ summary?.open_alerts || 0 }}</strong></article>
        <article><span>Tracked periods</span><strong>{{ summary?.periods.length || 0 }}</strong></article>
      </section>

      <section>
        <h2>Salary transfer batches</h2>
        <table>
          <thead>
            <tr><th>Batch</th><th>Establishment</th><th>Provider</th><th>Due</th><th>Total</th><th>Status</th><th>Proof</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="batch in batches" :key="batch.id">
              <td>{{ batch.batch_number }}</td>
              <td>{{ batch.mohre_establishment?.establishment_name || '-' }}</td>
              <td>{{ batch.wps_provider?.name || batch.provider }}</td>
              <td>{{ batch.payroll_due_date || '-' }}</td>
              <td>{{ batch.total_amount }}</td>
              <td>{{ label(batch.status) }}</td>
              <td>{{ label(batch.proof_status || 'missing') }}</td>
              <td class="table-actions">
                <button type="button" class="secondary" @click="selectBatch(batch)">Manage</button>
                <a class="secondary-link" :href="downloadUrl(batch)" target="_blank">SIF</a>
              </td>
            </tr>
            <tr v-if="batches.length === 0"><td colspan="8">No WPS salary transfer batches generated.</td></tr>
          </tbody>
        </table>
      </section>

      <section v-if="selectedBatch" class="operations-grid">
        <form class="form-grid" @submit.prevent="updateStatus">
          <h2>Update {{ selectedBatch.batch_number }}</h2>
          <label>
            Next status
            <select v-model="statusForm.status" required>
              <option v-for="status in availableStatuses(selectedBatch.status)" :key="status" :value="status">{{ label(status) }}</option>
            </select>
          </label>
          <label>Provider reference<input v-model="statusForm.provider_reference"></label>
          <label>Bank reference<input v-model="statusForm.bank_reference"></label>
          <label v-if="statusForm.status === 'rejected'">Rejection reason<textarea v-model="statusForm.rejection_reason" required rows="3" /></label>
          <label v-if="statusForm.status === 'failed'">Failure reason<textarea v-model="statusForm.failure_reason" required rows="3" /></label>
          <label v-if="statusForm.status === 'manual_override'">Override reason<textarea v-model="statusForm.manual_override_reason" required rows="3" /></label>
          <p v-if="statusError" class="error">{{ statusError }}</p>
          <button type="submit" :disabled="updatingStatus || !statusForm.status">
            {{ updatingStatus ? 'Updating...' : 'Update batch status' }}
          </button>
        </form>

        <form class="form-grid" @submit.prevent="uploadProof">
          <h2>Transfer proof</h2>
          <label>
            Proof type
            <select v-model="proofForm.proof_type">
              <option value="provider_receipt">Provider receipt</option>
              <option value="bank_confirmation">Bank confirmation</option>
              <option value="exchange_house_receipt">Exchange house receipt</option>
              <option value="wps_report">WPS report</option>
              <option value="manual_reference">Manual reference</option>
              <option value="api_confirmation">API confirmation</option>
            </select>
          </label>
          <label>Provider reference<input v-model="proofForm.provider_reference"></label>
          <label>Transaction reference<input v-model="proofForm.transaction_reference"></label>
          <label>Evidence file<input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.csv,.xls,.xlsx" @change="setProofFile"></label>
          <label>Notes<textarea v-model="proofForm.notes" rows="3" /></label>
          <p v-if="proofError" class="error">{{ proofError }}</p>
          <button type="submit" :disabled="uploadingProof">{{ uploadingProof ? 'Uploading...' : 'Save transfer proof' }}</button>
        </form>

        <section>
          <h2>Evidence history</h2>
          <ul class="proof-list">
            <li v-for="proof in selectedBatch.proofs" :key="proof.id">
              <div>
                <strong>{{ label(proof.proof_type) }}</strong>
                <span>{{ proof.provider_reference || proof.transaction_reference || proof.original_file_name || 'Reference only' }}</span>
                <small>{{ label(proof.status) }} · {{ proof.created_at }}</small>
              </div>
              <div class="table-actions">
                <a v-if="proof.original_file_name" class="secondary-link" :href="proofDownloadUrl(proof)" target="_blank">Download</a>
                <button v-if="proof.status === 'uploaded'" type="button" class="secondary" @click="verifyProof(proof, 'verified')">Verify</button>
                <button v-if="proof.status === 'uploaded'" type="button" class="secondary" @click="verifyProof(proof, 'rejected')">Reject</button>
              </div>
            </li>
            <li v-if="selectedBatch.proofs.length === 0">No transfer proof uploaded.</li>
          </ul>
        </section>
      </section>

      <section>
        <h2>Compliance timeline</h2>
        <table>
          <thead><tr><th>Period</th><th>Due date</th><th>WPS status</th><th>Compliance</th><th>Days after due</th></tr></thead>
          <tbody>
            <tr v-for="period in summary?.periods || []" :key="period.payroll_period_id">
              <td>{{ period.period_start }} to {{ period.period_end }}</td>
              <td>{{ period.due_date || '-' }}</td>
              <td>{{ label(period.wps_status || 'not_started') }}</td>
              <td>{{ label(period.status) }}</td>
              <td>{{ period.days_after_due ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface Proof {
  id: number
  proof_type: string
  provider_reference: string | null
  transaction_reference: string | null
  original_file_name: string | null
  status: string
  created_at: string
}
interface Batch {
  id: number
  batch_number: string
  provider: string
  payroll_due_date: string | null
  total_amount: string
  status: string
  proof_status: string | null
  bank_reference: string | null
  provider_reference: string | null
  mohre_establishment?: { establishment_name: string } | null
  wps_provider?: { name: string } | null
  proofs: Proof[]
}
interface Summary {
  risk_status: string
  open_alerts: number
  periods: Array<{
    payroll_period_id: number
    period_start: string
    period_end: string
    due_date: string | null
    wps_status: string | null
    status: string
    days_after_due: number | null
  }>
}

const api = useApiClient()
const config = useRuntimeConfig()
const batches = ref<Batch[]>([])
const summary = ref<Summary | null>(null)
const selectedBatch = ref<Batch | null>(null)
const loading = ref(true)
const loadError = ref('')
const statusError = ref('')
const proofError = ref('')
const updatingStatus = ref(false)
const uploadingProof = ref(false)
const statusForm = reactive({
  status: '', provider_reference: '', bank_reference: '', rejection_reason: '', failure_reason: '', manual_override_reason: '',
})
const proofForm = reactive({
  proof_type: 'provider_receipt', provider_reference: '', transaction_reference: '', notes: '', file: null as File | null,
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const [batchResponse, summaryResponse] = await Promise.all([
      api.get<{ wps_payroll_batches: Batch[] }>('/wps-payroll-batches'),
      api.get<{ wps_compliance: Summary }>('/wps-compliance'),
    ])
    batches.value = batchResponse.data.wps_payroll_batches
    summary.value = summaryResponse.data.wps_compliance
    if (selectedBatch.value) selectedBatch.value = batches.value.find(item => item.id === selectedBatch.value?.id) || null
  } catch (cause) {
    loadError.value = apiErrorMessage(cause, 'Unable to load WPS operations.')
  } finally {
    loading.value = false
  }
}
function selectBatch(batch: Batch) {
  selectedBatch.value = batch
  Object.assign(statusForm, {
    status: availableStatuses(batch.status)[0] || '',
    provider_reference: batch.provider_reference || '',
    bank_reference: batch.bank_reference || '',
    rejection_reason: '', failure_reason: '', manual_override_reason: '',
  })
}
async function updateStatus() {
  if (!selectedBatch.value) return
  updatingStatus.value = true
  statusError.value = ''
  try {
    await api.post(`/wps-payroll-batches/${selectedBatch.value.id}/status`, {
      ...statusForm,
      provider_reference: statusForm.provider_reference || null,
      bank_reference: statusForm.bank_reference || null,
      rejection_reason: statusForm.rejection_reason || null,
      failure_reason: statusForm.failure_reason || null,
      manual_override_reason: statusForm.manual_override_reason || null,
    })
    await load()
  } catch (cause) {
    statusError.value = apiErrorMessage(cause, 'Unable to update WPS status.')
  } finally {
    updatingStatus.value = false
  }
}
async function uploadProof() {
  if (!selectedBatch.value) return
  uploadingProof.value = true
  proofError.value = ''
  const body = new FormData()
  body.append('proof_type', proofForm.proof_type)
  if (proofForm.provider_reference) body.append('provider_reference', proofForm.provider_reference)
  if (proofForm.transaction_reference) body.append('transaction_reference', proofForm.transaction_reference)
  if (proofForm.notes) body.append('notes', proofForm.notes)
  if (proofForm.file) body.append('file', proofForm.file)
  try {
    await api.post(`/wps-payroll-batches/${selectedBatch.value.id}/proofs`, body)
    Object.assign(proofForm, { proof_type: 'provider_receipt', provider_reference: '', transaction_reference: '', notes: '', file: null })
    await load()
  } catch (cause) {
    proofError.value = apiErrorMessage(cause, 'Unable to save transfer proof.')
  } finally {
    uploadingProof.value = false
  }
}
async function verifyProof(proof: Proof, status: string) {
  await api.post(`/wps-transfer-proofs/${proof.id}/verify`, { status })
  await load()
}
function setProofFile(event: Event) {
  proofForm.file = (event.target as HTMLInputElement).files?.[0] || null
}
function availableStatuses(status: string) {
  const transitions: Record<string, string[]> = {
    generated: ['submitted', 'cancelled'],
    submitted: ['processing', 'accepted', 'partially_accepted', 'rejected', 'failed'],
    processing: ['accepted', 'partially_accepted', 'rejected', 'paid', 'failed'],
    accepted: ['paid', 'partially_paid', 'needs_review'],
    partially_accepted: ['processing', 'partially_paid', 'failed', 'needs_review'],
    rejected: ['corrected', 'cancelled'],
    corrected: ['generated', 'submitted', 'cancelled'],
    partially_paid: ['paid', 'failed', 'needs_review'],
    failed: ['corrected', 'needs_review', 'cancelled'],
    needs_review: ['processing', 'paid', 'failed', 'manual_override'],
  }
  return transitions[status] || []
}
function downloadUrl(batch: Batch) {
  return `${config.public.apiBaseUrl}/wps-payroll-batches/${batch.id}/download`
}
function proofDownloadUrl(proof: Proof) {
  return `${config.public.apiBaseUrl}/wps-transfer-proofs/${proof.id}/download`
}
function label(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())
}
</script>

<style scoped>
.muted { color: #5d6a72; }
.summary-grid { display: grid; grid-template-columns: repeat(3, minmax(160px, 1fr)); gap: 16px; }
.summary-grid article { display: grid; gap: 8px; padding: 18px; border: 1px solid #d8dee4; border-radius: 8px; background: #fff; }
.summary-grid span { color: #5d6a72; }
.summary-grid strong { font-size: 1.4rem; }
.operations-grid { display: grid; grid-template-columns: repeat(2, minmax(280px, 1fr)); gap: 20px; }
.operations-grid > section { grid-column: 1 / -1; }
.proof-list { display: grid; gap: 10px; padding: 0; list-style: none; }
.proof-list li { display: flex; justify-content: space-between; gap: 16px; padding: 12px; border: 1px solid #d8dee4; border-radius: 8px; }
.proof-list li div:first-child { display: grid; gap: 4px; }
.secondary-link { color: #176b54; font-weight: 700; text-decoration: none; }
@media (max-width: 900px) {
  .summary-grid, .operations-grid { grid-template-columns: 1fr; }
}
</style>
