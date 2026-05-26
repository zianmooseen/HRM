import type { ApiResponse, ApiSuccess } from '../../../shared/types/api'

export function useApiClient() {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBaseUrl

  function xsrfToken() {
    if (import.meta.server) {
      return null
    }

    const match = document.cookie
      .split('; ')
      .find((cookie) => cookie.startsWith('XSRF-TOKEN='))

    return match ? decodeURIComponent(match.split('=')[1]) : null
  }

  async function request<T>(path: string, options: RequestInit = {}): Promise<ApiSuccess<T>> {
    const token = xsrfToken()
    const isFormData = options.body instanceof FormData
    const response = await fetch(`${baseURL}${path}`, {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        ...(!isFormData ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        ...(options.headers || {}),
      },
      ...options,
    })

    const payload = (await response.json()) as ApiResponse<T>

    if (!response.ok || !payload.success) {
      throw payload
    }

    return payload
  }

  return {
    csrf: () => fetch(`${baseURL.replace(/\/api$/, '')}/sanctum/csrf-cookie`, { credentials: 'include' }),
    get: <T>(path: string) => request<T>(path),
    post: <T>(path: string, body: unknown) =>
      request<T>(path, {
        method: 'POST',
        body: body instanceof FormData ? body : JSON.stringify(body),
      }),
    put: <T>(path: string, body: unknown) =>
      request<T>(path, {
        method: 'PUT',
        body: JSON.stringify(body),
      }),
    delete: <T>(path: string) =>
      request<T>(path, {
        method: 'DELETE',
      }),
  }
}

export function apiErrorMessage(error: unknown, fallback = 'Something went wrong.') {
  if (error && typeof error === 'object' && 'errors' in error) {
    const errors = (error as { errors?: Record<string, string[]> }).errors
    const first = errors ? Object.values(errors).flat()[0] : null

    if (first) {
      return first
    }
  }

  if (error && typeof error === 'object' && 'message' in error) {
    const message = (error as { message?: string }).message

    if (message) {
      return message
    }
  }

  return fallback
}
