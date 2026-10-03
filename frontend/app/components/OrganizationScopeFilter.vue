<template>
  <section v-if="scope.enabled" class="organization-filter" aria-label="Company and branch filters">
    <div class="filter-fields">
      <label>
        Company
        <select :value="scope.companyId ?? ''" :disabled="!scope.companies.length" @change="changeCompany">
          <option v-if="!scope.companies.length" value="">No company available</option>
          <option v-for="company in scope.companies" :key="company.id" :value="company.id">{{ company.name }}</option>
        </select>
      </label>
      <span aria-hidden="true" class="filter-arrow">→</span>
      <label>
        Branch
        <select :value="scope.branchId ?? ''" :disabled="!scope.companyId" @change="changeBranch">
          <option value="">All branches</option>
          <option v-for="branch in scope.branches" :key="branch.id" :value="branch.id">{{ branch.name }} ({{ branch.code }})</option>
        </select>
      </label>
    </div>
    <p v-if="scope.error" role="alert" class="error">{{ scope.error }} <button type="button" @click="retry">Retry</button></p>
    <p v-else class="filter-summary" aria-live="polite">
      Applies across all pages. {{ scope.branchId ? 'Employee records are filtered by branch. Shared settings and billing apply to the whole company.' : 'Showing the selected company across all branches.' }}
    </p>
    <p v-if="scope.branchId && isPayrollPage" class="filter-summary">Select All branches to run or approve payroll, update WPS batches, or download bank files and proofs.</p>
    <p v-if="isCompanyWidePage" class="filter-summary"><strong>Company-wide:</strong> these settings and totals apply to every branch in {{ scope.company?.name }}.</p>
    <p v-if="scope.branchId && route.path === '/settings/emiratisation'" class="filter-summary">Select All branches to save a company compliance snapshot.</p>
    <p v-if="scope.branchId && route.path === '/settings/branches'" class="filter-summary">Select All branches to create a new branch.</p>
  </section>
</template>

<script setup lang="ts">
import type { OrganizationScopePayload } from '../../../shared/types/organization-scope'

const scope = useOrganizationScopeStore()
const auth = useAuthStore()
const route = useRoute()
const api = useApiClient()
const isPayrollPage = computed(() => route.path.startsWith('/payroll') || route.path === '/platform/wps')
const isCompanyWidePage = computed(() => ['/settings/company', '/settings/compliance', '/settings/leave-policies', '/settings/payroll-policies', '/settings/emiratisation', '/settings/public-holidays', '/settings/job-titles', '/platform/billing'].includes(route.path))

async function returnToList() {
  // A detail from the old scope must not remain open after switching organization.
  if (/^\/employees\/\d+(\/.*)?$/.test(route.path)) await navigateTo('/employees')
}

async function changeCompany(event: Event) {
  scope.selectCompany(Number((event.target as HTMLSelectElement).value))
  await returnToList()
}

async function changeBranch(event: Event) {
  const value = (event.target as HTMLSelectElement).value
  scope.selectBranch(value ? Number(value) : null)
  await returnToList()
}

async function retry() {
  if (auth.user) await scope.initialize(auth.user, () => api.get<OrganizationScopePayload>('/organization-scope'))
}
</script>

<style scoped>
.organization-filter { margin-bottom: 24px; padding: 16px 20px; border: 1px solid #dce3e0; border-radius: 10px; background: white; }
.filter-fields { display: flex; align-items: end; flex-wrap: wrap; gap: 14px; }
label { display: grid; gap: 6px; font-size: 0.8rem; font-weight: 600; flex: 1; max-width: 360px; min-width: 180px; }
select { width: 100%; min-height: 40px; border: 1px solid #cbd5d0; border-radius: 6px; padding: 8px; background: white; color: inherit; font: inherit; }
.filter-arrow { padding-bottom: 12px; color: #65766e; }
.filter-summary { margin: 10px 0 0; color: #5f6c72; font-size: 0.8rem; line-height: 1.5; }
@media (max-width: 600px) { .filter-arrow { display: none; } label { max-width: none; } }
</style>
