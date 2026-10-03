import assert from 'node:assert/strict'
import test from 'node:test'
import { ref, computed } from 'vue'
import { createPinia, defineStore, setActivePinia } from 'pinia'
import { scopedApiUrl } from '../app/utils/organization-scope.ts'

const cookie = ref(null)
Object.assign(globalThis, { ref, computed, defineStore, useCookie: () => cookie, apiErrorMessage: (_error, fallback) => fallback })
const { useOrganizationScopeStore } = await import('../app/stores/organizationScope.ts')
const user = { id: 4, roles: ['company_admin'] }
const companies = [
  { id: 1, name: 'One', branches: [{ id: 10, name: 'Dubai', code: 'DXB' }, { id: 11, name: 'Abu Dhabi', code: 'AUH' }] },
  { id: 2, name: 'Two', branches: [{ id: 20, name: 'Sharjah', code: 'SHJ' }] },
]
const options = async () => ({ success: true, data: { companies } })

test('selection persists, company changes clear branch, and invalid choices cannot be applied', async () => {
  setActivePinia(createPinia())
  cookie.value = null
  const scope = useOrganizationScopeStore()
  await scope.initialize(user, options)
  assert.equal(scope.companyId, 1)
  scope.selectBranch(10)
  const previousKey = scope.key
  assert.equal(cookie.value.branchId, 10)
  scope.selectCompany(2)
  assert.equal(scope.branchId, null)
  assert.notEqual(scope.key, previousKey)
  scope.selectBranch(10)
  assert.equal(scope.branchId, null)
  scope.selectBranch(20)
  setActivePinia(createPinia())
  const restored = useOrganizationScopeStore()
  await restored.initialize(user, options)
  assert.equal(restored.companyId, 2)
  assert.equal(restored.branchId, 20)
  restored.refreshOptions([{ ...companies[1], branches: [] }])
  assert.equal(restored.branchId, null)
})

test('stale saved scope and another account cannot reuse an unauthorized company', async () => {
  setActivePinia(createPinia())
  cookie.value = { userId: 4, companyId: 999, branchId: 20 }
  const scope = useOrganizationScopeStore()
  await scope.initialize(user, options)
  assert.equal(scope.companyId, 1)
  assert.equal(scope.branchId, null)
  await scope.initialize({ id: 5, roles: ['employee'] }, () => { throw new Error('Must not fetch') })
  assert.equal(scope.enabled, false)
  assert.equal(scope.companyId, null)
  scope.reset()
  assert.equal(cookie.value, null)
})

test('failed scope loading fails closed and can be retried', async () => {
  setActivePinia(createPinia())
  const scope = useOrganizationScopeStore()
  await scope.initialize(user, async () => { throw new Error('Network failed') })
  assert.equal(scope.ready, false)
  assert.equal(scope.companyId, null)
  assert.ok(scope.error)
  await scope.initialize(user, options)
  assert.equal(scope.ready, true)
  assert.equal(scope.error, '')
})

test('API requests and download URLs share scope while authentication stays independent', () => {
  const selection = { companyId: 2, branchId: 20 }
  assert.equal(scopedApiUrl('/api', '/employees', selection), '/api/employees?scope_company_id=2&scope_branch_id=20')
  const url = new URL(scopedApiUrl('http://localhost:8000/api', '/compliance/reports/export?type=audit', selection))
  assert.equal(url.searchParams.get('type'), 'audit')
  assert.equal(url.searchParams.get('scope_branch_id'), '20')
  assert.equal(scopedApiUrl('/api', '/me', selection), '/api/me')
  assert.equal(scopedApiUrl('/api', '/organization-scope', selection), '/api/organization-scope')
  assert.equal(scopedApiUrl('/api', '/employees?scope_branch_id=10', { companyId: 2, branchId: null }), '/api/employees?scope_company_id=2')
})
