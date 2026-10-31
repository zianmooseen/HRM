export interface TemporaryWorkerTotals {
  earned: string
  paid: string
  balance: string
  days: string
  hours: string
  dates_worked: number
  first_worked: string | null
  last_worked: string | null
}
export interface TemporaryWorker {
  id: number
  name: string
  branch_id: number
  branch: { id: number, name: string } | null
  phone: string | null
  job_description: string | null
  wage_basis: 'daily' | 'hourly'
  rate: string
  currency: string
  status: 'active' | 'inactive'
  totals: TemporaryWorkerTotals
}
export interface TemporaryWorkerEntry {
  id: number
  kind: 'work' | 'payment'
  date: string
  wage_basis: 'daily' | 'hourly' | null
  quantity: string | null
  rate: string | null
  amount: string
  method: string | null
  reference: string | null
  notes: string | null
  voided_at: string | null
  void_reason: string | null
}
