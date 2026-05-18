export const ROLE_SLUGS = [
  'super_admin',
  'company_admin',
  'hr_manager',
  'payroll_manager',
  'branch_manager',
  'department_manager',
  'employee',
] as const

export type RoleSlug = (typeof ROLE_SLUGS)[number]
