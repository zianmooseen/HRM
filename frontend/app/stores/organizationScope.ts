import type { ApiSuccess, AuthUser } from '../../../shared/types/api'
import type { OrganizationScopePayload, ScopeCompany } from '../../../shared/types/organization-scope'

export const useOrganizationScopeStore = defineStore('organizationScope', () => {
  const saved = useCookie<{ userId: number, companyId: number, branchId: number | null } | null>('hrm-organization-scope', { sameSite: 'lax', default: () => null })
  const companies = ref<ScopeCompany[]>([])
  const companyId = ref<number | null>(null)
  const branchId = ref<number | null>(null)
  const userId = ref<number | null>(null)
  const enabled = ref(false)
  const ready = ref(false)
  const error = ref('')
  const company = computed(() => companies.value.find((item) => item.id === companyId.value))
  const branches = computed(() => company.value?.branches || [])
  const key = computed(() => `${userId.value}:${companyId.value}:${branchId.value}`)

  async function initialize(user: AuthUser, getOptions: () => Promise<ApiSuccess<OrganizationScopePayload>>) {
    if (ready.value && userId.value === user.id) return
    ready.value = false
    companies.value = []
    companyId.value = null
    branchId.value = null
    enabled.value = user.roles.some((role) => role === 'super_admin' || role === 'company_admin')
    userId.value = user.id
    error.value = ''
    if (!enabled.value) {
      companies.value = []
      companyId.value = null
      branchId.value = null
      ready.value = true
      return
    }
    try {
      const response = await getOptions()
      companies.value = response.data.companies
      const previous = saved.value?.userId === user.id ? saved.value : null
      companyId.value = companies.value.find((item) => item.id === previous?.companyId)?.id || companies.value[0]?.id || null
      branchId.value = branches.value.find((item) => item.id === previous?.branchId)?.id || null
      ready.value = true
      persist()
    } catch (err) {
      error.value = apiErrorMessage(err, 'Unable to load company filters.')
      ready.value = false
    }
  }

  function persist() {
    saved.value = userId.value && companyId.value ? { userId: userId.value, companyId: companyId.value, branchId: branchId.value } : null
  }

  function selectCompany(id: number) {
    if (!companies.value.some((item) => item.id === id)) return
    companyId.value = id
    branchId.value = null
    persist()
  }

  function selectBranch(id: number | null) {
    if (id !== null && !branches.value.some((item) => item.id === id)) return
    branchId.value = id
    persist()
  }

  function reset() {
    companies.value = []
    companyId.value = null
    branchId.value = null
    userId.value = null
    enabled.value = false
    ready.value = false
    error.value = ''
    saved.value = null
  }

  function refreshOptions(options: ScopeCompany[]) {
    companies.value = options
    companyId.value = options.find((item) => item.id === companyId.value)?.id || options[0]?.id || null
    branchId.value = branches.value.find((item) => item.id === branchId.value)?.id || null
    persist()
  }

  return { companies, companyId, branchId, branches, company, enabled, ready, error, key, initialize, selectCompany, selectBranch, reset, refreshOptions }
})
