import type { LeaveCalendarEntry } from '../../../shared/types/leave-calendar'

export function calendarDays(month: string, entries: LeaveCalendarEntry[]) {
  const [year, monthNumber] = month.split('-').map(Number) as [number, number]
  const first = new Date(Date.UTC(year, monthNumber - 1, 1))
  const count = new Date(Date.UTC(year, monthNumber, 0)).getUTCDate()
  const offset = (first.getUTCDay() + 6) % 7
  const cells = Math.ceil((offset + count) / 7) * 7

  return Array.from({ length: cells }, (_, index) => {
    const day = index - offset + 1
    if (day < 1 || day > count) return null
    const date = `${month}-${String(day).padStart(2, '0')}`
    const people = new Map<number, LeaveCalendarEntry>()
    for (const entry of entries) {
      if (entry.start_date <= date && entry.end_date >= date) people.set(entry.employee_id, entry)
    }
    return {
      date,
      day,
      people: [...people.values()].sort((a, b) => Number(b.is_self) - Number(a.is_self) || a.employee_name.localeCompare(b.employee_name)),
    }
  })
}
