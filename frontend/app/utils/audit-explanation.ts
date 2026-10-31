export interface AuditEmployeeContext {
  id: number
  name: string
  employee_code: string
  branch_name: string | null
}

export interface AuditEvent {
  action: string
  auditable_type: string
  auditable_id: number | null
  actor_user_id: number | null
  reference_labels?: Record<string, Record<string, string>>
  employees?: AuditEmployeeContext[]
  snapshots_visible: boolean
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
}

function words(value: string) {
  return value.replace(/([a-z0-9])([A-Z])/g, '$1 $2').replace(/[_.]/g, ' ').toLowerCase()
}

function fieldLabel(key: string) {
  const value = words(key).replace(/\bid\b/g, 'ID').replace(/\biban\b/g, 'IBAN')
  return value.charAt(0).toUpperCase() + value.slice(1)
}

function employeeLabel(employee: AuditEmployeeContext): string {
  return `${employee.name} (${employee.employee_code}; ID #${employee.id}), current branch: ${employee.branch_name || 'not available'}`
}

function display(value: unknown, key: string, employees: AuditEmployeeContext[] = [], references: Record<string, Record<string, string>> = {}): string {
  if (key === 'employee_id') {
    const employee = employees.find(item => String(item.id) === String(value))
    if (employee) return employeeLabel(employee)
  }
  if (value === null || value === undefined || value === '') return 'not set'
  if (references[key]) {
    const resolve = (item: unknown) => {
      const id = item && typeof item === 'object' && 'id' in item ? item.id : item
      return references[key]?.[String(id)] || `unavailable record (ID #${String(id)})`
    }
    return Array.isArray(value) ? (value.length ? value.map(resolve).join(', ') : 'none') : resolve(value)
  }
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'object') return Array.isArray(value) ? `${value.length} items` : 'structured details'
  const text = String(value)
  // Date-only HR values must not shift a day when viewed in another timezone.
  if (/(?:date|_at)$/.test(key) && /^\d{4}-\d{2}-\d{2}(?:T.*)?$/.test(text)) return text.slice(0, 10)
  if (/(?:status|type|source|role)$/.test(key)) return words(text)
  return text
}

export function explainAuditEvent(log: AuditEvent): { summary: string, details: string[] } {
  const references = log.snapshots_visible ? (log.reference_labels || {}) : {}
  const actor = log.actor_user_id === null ? 'The system' : references.actor_user_id?.[String(log.actor_user_id)] || `User #${log.actor_user_id}`
  const recordName = references._record?.[String(log.auditable_id)]
  const record = recordName ? `${words(log.auditable_type)} “${recordName}”` : `${words(log.auditable_type)}${log.auditable_id === null ? '' : ` #${log.auditable_id}`}`
  const action = log.action.split('.').at(-1) || ''
  const verbs: Record<string, string> = {
    created: 'created', updated: 'updated', deleted: 'deleted', approved: 'approved',
    rejected: 'rejected', cancelled: 'cancelled', canceled: 'cancelled', uploaded: 'uploaded',
    downloaded: 'downloaded', restored: 'restored', submitted: 'submitted', processed: 'processed',
    completed: 'completed', activated: 'activated', suspended: 'suspended', viewed: 'viewed',
  }
  let summary = verbs[action]
    ? `${actor} ${verbs[action]} ${record}.`
    : `${actor} recorded “${words(log.action)}” for ${record}.`
  // Explanations and raw data use the same server-authorized snapshots.
  if (!log.snapshots_visible) return { summary, details: [] }
  const employees = log.employees || []
  if (employees.length) summary += ` ${employees.map(employee => `Employee: ${employeeLabel(employee)}.`).join(' ')}`
  function flatten(snapshot: Record<string, unknown>, prefix = ''): Record<string, unknown> {
    return Object.fromEntries(Object.entries(snapshot).flatMap(([key, value]) => {
      const path = prefix ? `${prefix}.${key}` : key
      if (value && typeof value === 'object' && !Array.isArray(value) && !references[key]) {
        return Object.entries(flatten(value as Record<string, unknown>, path))
      }
      return [[path, value]]
    }))
  }
  const before = flatten(log.before || {})
  const after = flatten(log.after || {})
  const details: string[] = []
  for (const path of new Set([...Object.keys(before), ...Object.keys(after)])) {
    const key = path.split('.').at(-1) || path
    if (['created_at', 'updated_at'].includes(key) || path === 'id') continue
    const previous = before[path]
    const next = after[path]
    if (JSON.stringify(previous) === JSON.stringify(next)) continue
    const label = fieldLabel(references[key] ? path.replace(/_ids$/, 's').replace(/_id$/, '') : path)
    if (log.before === null) details.push(`${label}: ${display(next, key, employees, references)}.`)
    else if (log.after === null) details.push(`${label} was ${display(previous, key, employees, references)}.`)
    else if (!references[key] && ((previous !== null && typeof previous === 'object') || (next !== null && typeof next === 'object'))) {
      details.push(`${label} details changed. Select Raw to inspect the recorded values.`)
    } else {
      details.push(`${label} changed from “${display(previous, key, employees, references)}” to “${display(next, key, employees, references)}”.`)
    }
  }
  return { summary, details }
}
