import assert from 'node:assert/strict'
import test from 'node:test'
import { calendarDays } from '../app/utils/leave-calendar.ts'

test('months align to Monday, include leap days, and fill complete weeks', () => {
  const leap = calendarDays('2028-02', [])
  assert.equal(leap[0], null)
  assert.equal(leap[1]?.date, '2028-02-01')
  assert.equal(leap.filter(Boolean).length, 29)
  assert.equal(leap.length, 35)
  assert.equal(calendarDays('2026-02', []).filter(Boolean).length, 28)
  assert.equal(calendarDays('2026-03', []).length, 42)
  assert.equal(calendarDays('2027-02', []).length, 28)
})

test('leave is inclusive across years and duplicate employees appear once per day', () => {
  const base = { id: 1, employee_id: 1, employee_name: 'Colleague', is_self: false, start_date: '2025-12-30', end_date: '2026-01-02' }
  const days = calendarDays('2026-01', [
    base,
    { ...base, id: 2 },
    { ...base, id: 3, employee_id: 2, employee_name: 'Self', is_self: true, start_date: '2026-01-02', end_date: '2026-01-02' },
  ]).filter((day) => day !== null)
  assert.deepEqual(days[0]?.people.map((person) => person.employee_id), [1])
  assert.deepEqual(days[1]?.people.map((person) => person.employee_id), [2, 1])
  assert.deepEqual(days[2]?.people, [])
})

test('busy days retain every colleague for scrolling', () => {
  const entries = Array.from({ length: 80 }, (_, id) => ({
    id, employee_id: id, employee_name: `Employee ${id}`, is_self: false,
    start_date: '2026-09-01', end_date: '2026-09-30',
  }))
  const days = calendarDays('2026-09', entries).filter((day) => day !== null)
  assert.equal(days.length, 30)
  assert.ok(days.every((day) => day.people.length === 80))
})
