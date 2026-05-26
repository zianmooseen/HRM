import type { Permission } from '../constants/permissions'
import type { RoleSlug } from '../constants/roles'

export interface ApiSuccess<T> {
  success: true
  message: string
  data: T
}

export interface ApiFailure {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export type ApiResponse<T> = ApiSuccess<T> | ApiFailure

export interface AuthUser {
  id: number
  name: string
  username: string | null
  email: string
  roles: RoleSlug[]
  permissions: Permission[]
}

export interface AuthPayload {
  user: AuthUser
}
