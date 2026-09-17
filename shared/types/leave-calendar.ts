export interface LeaveCalendarEntry {
  id: number
  employee_id: number
  employee_name: string
  is_self: boolean
  start_date: string
  end_date: string
}

export interface LeaveCalendarPayload {
  month: string
  entries: LeaveCalendarEntry[]
}
