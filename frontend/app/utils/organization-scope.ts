export function scopedApiUrl(baseUrl: string, path: string, selection: { companyId: number | null, branchId: number | null } | null) {
  const [pathname, query = ''] = `${baseUrl}${path}`.split('?')
  const params = new URLSearchParams(query)
  if (!['/login', '/logout', '/me', '/organization-scope'].includes(path.split('?')[0] || '') && selection?.companyId) {
    params.set('scope_company_id', String(selection.companyId))
    params.delete('scope_branch_id')
    if (selection.branchId) params.set('scope_branch_id', String(selection.branchId))
  }
  return `${pathname}${params.size ? `?${params.toString()}` : ''}`
}
