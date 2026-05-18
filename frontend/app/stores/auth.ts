import type { AuthPayload, AuthUser } from '../../../shared/types/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const loaded = ref(false)
  const api = useApiClient()

  async function fetchCurrentUser() {
    try {
      const response = await api.get<AuthPayload>('/me')
      user.value = response.data.user
    } catch {
      user.value = null
    } finally {
      loaded.value = true
    }
  }

  async function login(email: string, password: string) {
    // Feature flow step 1: request Sanctum CSRF cookie before posting credentials.
    await api.csrf()

    // Feature flow step 2: credentials are exchanged for a secure cookie-backed session.
    const response = await api.post<AuthPayload>('/login', { email, password })
    user.value = response.data.user
    loaded.value = true
  }

  async function logout() {
    try {
      // Feature flow step 1: ask Laravel to invalidate the cookie-backed session.
      await api.post('/logout', {})
    } finally {
      // Feature flow step 2: clear the client state even if the session already expired server-side.
      user.value = null
      loaded.value = true
    }
  }

  function hasPermission(permission: string) {
    return Boolean(user.value?.permissions.includes(permission as never))
  }

  function hasRole(role: string) {
    return Boolean(user.value?.roles.includes(role as never))
  }

  return { user, loaded, fetchCurrentUser, login, logout, hasPermission, hasRole }
})
