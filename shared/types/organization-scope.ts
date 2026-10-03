export interface ScopeBranch {
  id: number
  name: string
  code: string
}

export interface ScopeCompany {
  id: number
  name: string
  branches: ScopeBranch[]
}

export interface OrganizationScopePayload {
  companies: ScopeCompany[]
}
