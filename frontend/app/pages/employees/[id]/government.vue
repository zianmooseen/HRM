<template>
  <section class="page">
    <header>
      <div>
        <h1>Government identifiers</h1>
        <p class="muted">Maintain the employee identifiers used for MoHRE and WPS payroll validation.</p>
      </div>
      <NuxtLink :to="`/employees/${route.params.id}`">Back to employee</NuxtLink>
    </header>

    <p v-if="loading">Loading government profile...</p>
    <form v-else class="form-grid profile-form" @submit.prevent="save">
      <label>
        MoHRE establishment
        <select v-model.number="form.mohre_establishment_id">
          <option :value="0">Not assigned</option>
          <option v-for="item in establishments" :key="item.id" :value="item.id">{{ item.establishment_name }}</option>
        </select>
      </label>
      <label>Labour card number<input v-model="form.labour_card_number"></label>
      <label>Work permit number<input v-model="form.work_permit_number"></label>
      <label>MoHRE person code<input v-model="form.person_code"></label>
      <label>WPS employee identifier<input v-model="form.wps_employee_identifier"></label>
      <label>Emirates ID number<input v-model="form.emirates_id_number"></label>
      <label>Visa file number<input v-model="form.visa_file_number"></label>
      <label>Passport number<input v-model="form.passport_number"></label>
      <p class="notice">These identifiers are encrypted at rest. Access and changes are recorded in the audit log.</p>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="saved">Government profile saved.</p>
      <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : 'Save identifiers' }}</button>
    </form>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

interface Establishment { id: number, establishment_name: string }
interface GovernmentProfile {
  mohre_establishment_id: number | null
  labour_card_number: string | null
  work_permit_number: string | null
  person_code: string | null
  emirates_id_number: string | null
  visa_file_number: string | null
  passport_number: string | null
  wps_employee_identifier: string | null
}

const route = useRoute()
const api = useApiClient()
const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const error = ref('')
const establishments = ref<Establishment[]>([])
const form = reactive({
  mohre_establishment_id: 0,
  labour_card_number: '',
  work_permit_number: '',
  person_code: '',
  emirates_id_number: '',
  visa_file_number: '',
  passport_number: '',
  wps_employee_identifier: '',
})

onMounted(async () => {
  try {
    const [profileResponse, establishmentResponse] = await Promise.all([
      api.get<{ employee_government_profile: GovernmentProfile | null }>(`/employees/${route.params.id}/government-profile`),
      api.get<{ mohre_establishments: Establishment[] }>('/mohre-establishments'),
    ])
    establishments.value = establishmentResponse.data.mohre_establishments
    const profile = profileResponse.data.employee_government_profile
    if (profile) {
      Object.assign(form, profile, {
        mohre_establishment_id: profile.mohre_establishment_id || 0,
        labour_card_number: profile.labour_card_number || '',
        work_permit_number: profile.work_permit_number || '',
        person_code: profile.person_code || '',
        emirates_id_number: profile.emirates_id_number || '',
        visa_file_number: profile.visa_file_number || '',
        passport_number: profile.passport_number || '',
        wps_employee_identifier: profile.wps_employee_identifier || '',
      })
    }
  } catch (cause) {
    error.value = apiErrorMessage(cause, 'Unable to load government profile.')
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  saved.value = false
  error.value = ''
  try {
    await api.put(`/employees/${route.params.id}/government-profile`, {
      ...form,
      mohre_establishment_id: form.mohre_establishment_id || null,
    })
    saved.value = true
  } catch (cause) {
    error.value = apiErrorMessage(cause, 'Unable to save government profile.')
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.muted { color: #5d6a72; }
.profile-form { max-width: 760px; }
.notice { color: #5d6a72; }
</style>
