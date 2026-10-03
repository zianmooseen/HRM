export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()
  const scope = useOrganizationScopeStore()
  const api = useApiClient()

  // Feature flow step 1: hydrate the browser session before protected pages render.
  if (!auth.loaded) {
    await auth.fetchCurrentUser()
  }

  // Feature flow step 2: send anonymous users to login; backend authorization still remains authoritative.
  if (!auth.user) {
    return navigateTo('/login')
  }

  await scope.initialize(auth.user, () => api.get<import('../../../shared/types/organization-scope').OrganizationScopePayload>('/organization-scope'))
})
