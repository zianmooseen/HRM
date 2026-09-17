import type { LeaveCalendarEntry, LeaveCalendarPayload } from '../../../shared/types/leave-calendar'

export function useLeaveCalendar(month: Ref<string>) {
  const api = useApiClient()
  const entries = ref<LeaveCalendarEntry[]>([])
  const loading = ref(false)
  const error = ref('')
  let requestId = 0

  async function reload() {
    const currentRequest = ++requestId
    loading.value = true
    error.value = ''
    entries.value = []

    try {
      const response = await api.get<LeaveCalendarPayload>(`/leave-calendar?month=${encodeURIComponent(month.value)}`)
      // Keep rapid month changes from displaying an older response.
      if (currentRequest === requestId) entries.value = response.data.entries
    } catch (err) {
      if (currentRequest === requestId) error.value = apiErrorMessage(err, 'Unable to load the leave calendar.')
    } finally {
      if (currentRequest === requestId) loading.value = false
    }
  }

  onMounted(reload)
  watch(month, reload)
  onBeforeUnmount(() => { requestId++ })

  return { entries, loading, error, reload }
}
