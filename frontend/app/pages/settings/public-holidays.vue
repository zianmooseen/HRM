<template>
  <section class="page">
    <header>
      <div>
        <h1>Public Holidays</h1>
        <p class="muted">Maintain company holidays used by leave calculations, payroll checks, and compliance reporting.</p>
      </div>
    </header>

    <form v-if="auth.hasPermission('settings.update')" class="form-grid" @submit.prevent="saveHoliday">
      <label>
        Holiday name
        <input v-model="form.name" required>
      </label>
      <label>
        Date
        <input v-model="form.holiday_date" type="date" required>
      </label>
      <label>
        Country code
        <input v-model="form.country_code" maxlength="2" required>
      </label>
      <label>
        Emirate
        <input v-model="form.emirate" placeholder="All UAE or specific emirate">
      </label>
      <label>
        Source
        <select v-model="form.source">
          <option value="company">Company</option>
          <option value="government">Government</option>
          <option value="imported">Imported</option>
          <option value="manual">Manual</option>
        </select>
      </label>
      <label>
        Status
        <select v-model="form.status">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </label>
      <label class="checkbox-label">
        <input v-model="form.paid" type="checkbox">
        Paid public holiday
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <div class="button-row">
        <button type="submit" :disabled="saving">
          {{ saving ? 'Saving...' : editingId ? 'Save holiday' : 'Create holiday' }}
        </button>
        <button v-if="editingId" type="button" class="secondary" @click="resetForm">Cancel</button>
      </div>
    </form>

    <section v-if="auth.hasPermission('settings.update')" class="import-panel">
      <header>
        <div>
          <h2>Import Holidays</h2>
          <p class="muted">Paste one holiday per line using: date, name, emirate. Use All or leave emirate blank for company-wide holidays.</p>
        </div>
      </header>
      <form class="form-grid" @submit.prevent="importHolidays">
        <label class="full">
          Holiday rows
          <textarea v-model="importText" rows="6" placeholder="2026-01-01, New Year, All&#10;2026-12-02, UAE National Day, All" />
        </label>
        <label>
          Country code
          <input v-model="importDefaults.country_code" maxlength="2" required>
        </label>
        <label>
          Source
          <select v-model="importDefaults.source">
            <option value="government">Government</option>
            <option value="company">Company</option>
            <option value="imported">Imported</option>
            <option value="manual">Manual</option>
          </select>
        </label>
        <label>
          Status
          <select v-model="importDefaults.status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </label>
        <label class="checkbox-label">
          <input v-model="importDefaults.paid" type="checkbox">
          Paid public holidays
        </label>
        <p v-if="importError" class="error">{{ importError }}</p>
        <p v-if="importSummary" class="muted">
          Imported {{ importSummary.created_count }} holiday{{ importSummary.created_count === 1 ? '' : 's' }};
          skipped {{ importSummary.skipped_count }} duplicate{{ importSummary.skipped_count === 1 ? '' : 's' }}.
        </p>
        <div class="button-row">
          <button type="submit" :disabled="importing">{{ importing ? 'Importing...' : 'Import holidays' }}</button>
          <button type="button" class="secondary" @click="loadTemplate">Use template</button>
        </div>
      </form>
    </section>

    <section>
      <h2>Holiday Calendar</h2>
      <p v-if="loading">Loading public holidays...</p>
      <p v-else-if="loadError" class="error">{{ loadError }}</p>
      <table v-else>
        <thead>
          <tr>
            <th>Date</th>
            <th>Name</th>
            <th>Country</th>
            <th>Emirate</th>
            <th>Paid</th>
            <th>Source</th>
            <th>Status</th>
            <th v-if="auth.hasPermission('settings.update')"></th>
          </tr>
          <tr class="column-filter-row">
            <th><TableColumnFilter v-model="columnFilters.date" label="Filter holiday date" type="date" /></th>
            <th><TableColumnFilter v-model="columnFilters.name" label="Filter holiday name" /></th>
            <th><TableColumnFilter v-model="columnFilters.country" label="Filter holiday country" /></th>
            <th><TableColumnFilter v-model="columnFilters.emirate" label="Filter holiday emirate" /></th>
            <th><TableColumnFilter v-model="columnFilters.paid" label="Filter paid status" /></th>
            <th><TableColumnFilter v-model="columnFilters.source" label="Filter holiday source" /></th>
            <th><TableColumnFilter v-model="columnFilters.status" label="Filter holiday status" /></th>
            <th v-if="auth.hasPermission('settings.update')"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="holiday in filteredHolidays" :key="holiday.id">
            <td>{{ holiday.holiday_date }}</td>
            <td>{{ holiday.name }}</td>
            <td>{{ holiday.country_code }}</td>
            <td>{{ holiday.emirate || 'All' }}</td>
            <td>{{ holiday.paid ? 'Yes' : 'No' }}</td>
            <td>{{ sourceLabel(holiday.source) }}</td>
            <td>{{ holiday.status }}</td>
            <td v-if="auth.hasPermission('settings.update')" class="table-actions">
              <button type="button" class="secondary" @click="editHoliday(holiday)">Edit</button>
              <button type="button" class="danger" @click="deleteHoliday(holiday.id)">Delete</button>
            </td>
          </tr>
          <tr v-if="filteredHolidays.length === 0">
            <td :colspan="auth.hasPermission('settings.update') ? 8 : 7">No public holidays configured yet.</td>
          </tr>
        </tbody>
      </table>
    </section>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

type HolidaySource = 'company' | 'government' | 'imported' | 'manual'
type HolidayStatus = 'active' | 'inactive'

interface PublicHoliday {
  id: number
  name: string
  holiday_date: string
  country_code: string
  emirate: string | null
  paid: boolean
  source: HolidaySource
  status: HolidayStatus
}

interface ImportSummary {
  created_count: number
  skipped_count: number
  skipped: Array<{ row: number, name: string, holiday_date: string, reason: string }>
}

const auth = useAuthStore()
const api = useApiClient()
const holidays = ref<PublicHoliday[]>([])
const { filters: columnFilters, filteredRows: filteredHolidays } = useTableColumnFilters(
  holidays,
  [
    { key: 'date', value: holiday => holiday.holiday_date },
    { key: 'name', value: holiday => holiday.name },
    { key: 'country', value: holiday => holiday.country_code },
    { key: 'emirate', value: holiday => holiday.emirate || 'All' },
    { key: 'paid', value: holiday => holiday.paid ? 'Yes' : 'No' },
    { key: 'source', value: holiday => sourceLabel(holiday.source) },
    { key: 'status', value: holiday => holiday.status },
  ],
)
const loading = ref(true)
const saving = ref(false)
const importing = ref(false)
const error = ref('')
const importError = ref('')
const loadError = ref('')
const importText = ref('')
const importSummary = ref<ImportSummary | null>(null)
const editingId = ref<number | null>(null)
const form = reactive({
  name: '',
  holiday_date: '',
  country_code: 'AE',
  emirate: '',
  paid: true,
  source: 'government' as HolidaySource,
  status: 'active' as HolidayStatus,
})
const importDefaults = reactive({
  country_code: 'AE',
  paid: true,
  source: 'government' as HolidaySource,
  status: 'active' as HolidayStatus,
})

onMounted(loadHolidays)

async function loadHolidays() {
  loading.value = true
  loadError.value = ''

  try {
    const response = await api.get<{ public_holidays: PublicHoliday[] }>('/public-holidays')
    holidays.value = response.data.public_holidays
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Unable to load public holidays.')
  } finally {
    loading.value = false
  }
}

function resetForm() {
  Object.assign(form, {
    name: '',
    holiday_date: '',
    country_code: 'AE',
    emirate: '',
    paid: true,
    source: 'government',
    status: 'active',
  })
  editingId.value = null
  error.value = ''
}

function editHoliday(holiday: PublicHoliday) {
  Object.assign(form, {
    name: holiday.name,
    holiday_date: holiday.holiday_date,
    country_code: holiday.country_code,
    emirate: holiday.emirate || '',
    paid: holiday.paid,
    source: holiday.source,
    status: holiday.status,
  })
  editingId.value = holiday.id
}

async function saveHoliday() {
  saving.value = true
  error.value = ''

  const payload = {
    ...form,
    country_code: form.country_code.toUpperCase(),
    emirate: form.emirate || null,
  }

  try {
    // Feature flow step 2: after saving the holiday, reload the calendar so downstream leave/payroll users see current dates.
    if (editingId.value) {
      await api.put(`/public-holidays/${editingId.value}`, payload)
    } else {
      await api.post('/public-holidays', payload)
    }
    resetForm()
    await loadHolidays()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to save public holiday.')
  } finally {
    saving.value = false
  }
}

async function deleteHoliday(id: number) {
  await api.delete(`/public-holidays/${id}`)
  await loadHolidays()
}

async function importHolidays() {
  importing.value = true
  importError.value = ''
  importSummary.value = null

  const holidaysToImport = parseImportRows()

  if (holidaysToImport.length === 0) {
    importing.value = false
    importError.value = 'Add at least one holiday row before importing.'
    return
  }

  try {
    const response = await api.post<{ import_summary: ImportSummary }>('/public-holidays/import', {
      holidays: holidaysToImport,
    })
    importSummary.value = response.data.import_summary
    importText.value = ''
    await loadHolidays()
  } catch (err) {
    importError.value = apiErrorMessage(err, 'Unable to import public holidays.')
  } finally {
    importing.value = false
  }
}

function parseImportRows() {
  return importText.value
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const [holiday_date = '', name = '', emirate = ''] = line.split(',').map((part) => part.trim())

      return {
        holiday_date,
        name,
        emirate: emirate && emirate.toLowerCase() !== 'all' ? emirate : null,
        country_code: importDefaults.country_code.toUpperCase(),
        paid: importDefaults.paid,
        source: importDefaults.source,
        status: importDefaults.status,
      }
    })
}

function loadTemplate() {
  importText.value = [
    '2026-01-01, New Year, All',
    '2026-12-02, UAE National Day, All',
    '2026-12-03, UAE National Day Holiday, All',
  ].join('\n')
  importSummary.value = null
  importError.value = ''
}

function sourceLabel(source: HolidaySource) {
  return {
    company: 'Company',
    government: 'Government',
    imported: 'Imported',
    manual: 'Manual',
  }[source]
}
</script>
