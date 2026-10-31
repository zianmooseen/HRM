import assert from 'node:assert/strict'
import test from 'node:test'
import { explainAuditEvent } from '../app/utils/audit-explanation.ts'
const base = { action: 'leave_request.created', auditable_type: 'LeaveRequest', auditable_id: 41, actor_user_id: 7, snapshots_visible: true, before: null, after: null }
test('explains creation with readable snapshot fields and date-only values', () => {
  const result = explainAuditEvent({ ...base, after: { employee_id: 9, start_date: '2026-10-04T00:00:00.000000Z', reason: 'personal', approved: false, total_days: 0 } })
  assert.equal(result.summary, 'User #7 created leave request #41.')
  assert.deepEqual(result.details, ['Employee ID: 9.', 'Start date: 2026-10-04.', 'Reason: personal.', 'Approved: No.', 'Total days: 0.'])
})
test('describes changed fields without repeating unchanged values', () => {
  const result = explainAuditEvent({ ...base, action: 'leave_request.approved', before: { status: 'pending', employee_id: 9, reason: 'personal' }, after: { status: 'approved', employee_id: 9, reason: null } })
  assert.match(result.summary, /approved leave request/)
  assert.deepEqual(result.details, ['Status changed from “pending” to “approved”.', 'Reason changed from “personal” to “not set”.'])
})
test('does not use unauthorized snapshot data', () => {
  const result = explainAuditEvent({ ...base, snapshots_visible: false, after: { basic_salary: '50000', employee_id: 999 } })
  assert.deepEqual(result.details, [])
  assert.doesNotMatch(result.summary, /50000|999/)
})
test('handles system events, missing snapshots, and unfamiliar actions without inventing details', () => {
  const result = explainAuditEvent({ ...base, actor_user_id: null, action: 'payroll.recalculation_requested' })
  assert.equal(result.summary, 'The system recorded “payroll recalculation requested” for leave request #41.')
  assert.deepEqual(result.details, [])
})
test('deletion retains former values and nested changes direct users to Raw', () => {
  assert.deepEqual(explainAuditEvent({ ...base, action: 'leave_request.deleted', before: { reason: 'personal' } }).details, ['Reason was personal.'])
  assert.deepEqual(explainAuditEvent({ ...base, before: { settings: { amount: 1 } }, after: { settings: { amount: 2 } } }).details, ['Settings amount changed from “1” to “2”.'])
})

const employeeContext = { id: 9, name: 'Alex Morgan', employee_code: 'EMP-009', branch_name: 'Dubai' }
test('includes employee name, employee number and current branch in explanations', () => {
  const result = explainAuditEvent({ ...base, employees: [employeeContext], after: { employee_id: 9 } })
  assert.match(result.summary, /Alex Morgan \(EMP-009; ID #9\), current branch: Dubai/)
  assert.deepEqual(result.details, ['Employee ID: Alex Morgan (EMP-009; ID #9), current branch: Dubai.'])
})
test('handles missing branches, unresolved employees, and unauthorized enrichment', () => {
  const result = explainAuditEvent({ ...base, employees: [{ ...employeeContext, branch_name: null }], before: { employee_id: 10 }, after: { employee_id: '9' } })
  assert.match(result.summary, /current branch: not available/)
  assert.match(result.details[0], /from “10” to “Alex Morgan/)
  const hidden = explainAuditEvent({ ...base, employees: [employeeContext], snapshots_visible: false })
  assert.doesNotMatch(hidden.summary, /Alex|Dubai|EMP-009/)
  assert.deepEqual(hidden.details, [])
})

test('uses role names for scalar changes and lists, while preserving quantities', () => {
  const result = explainAuditEvent({ ...base, action: 'employee.updated', reference_labels: { role_id: { 1: 'Employee', 2: 'HR Manager' }, role_ids: { 1: 'Employee', 2: 'HR Manager' }, actor_user_id: { 7: 'Sam Admin' } }, before: { role_id: 1, role_ids: [1], amount: 1 }, after: { role_id: 2, role_ids: [1, 2], amount: 2 } })
  assert.match(result.summary, /^Sam Admin/)
  assert.deepEqual(result.details, ['Role changed from “Employee” to “HR Manager”.', 'Roles changed from “Employee” to “Employee, HR Manager”.', 'Amount changed from “1” to “2”.'])
})
test('resolves nested references and marks unavailable names without inventing them', () => {
  const result = explainAuditEvent({ ...base, reference_labels: { department_id: { 1: 'Finance' }, role_id: {} }, before: { assignment: { department_id: 1 }, role_id: null }, after: { assignment: { department_id: 99 }, role_id: 8 } })
  assert.deepEqual(result.details, ['Assignment department changed from “Finance” to “unavailable record (ID #99)”.', 'Role changed from “not set” to “unavailable record (ID #8)”.'])
})
test('reference names stay hidden when snapshots are restricted', () => {
  const result = explainAuditEvent({ ...base, snapshots_visible: false, reference_labels: { actor_user_id: { 7: 'Secret actor' }, _record: { 41: 'Secret record' } } })
  assert.doesNotMatch(result.summary, /Secret/)
  assert.deepEqual(result.details, [])
})
