export interface EmployeeSelfProfile {
  id: number
  employee_code: string
  first_name: string
  middle_name: string | null
  last_name: string
  display_name: string
  personal_email: string | null
  work_email: string | null
  phone: string | null
  status: string
  hire_date: string | null
  probation_end_date: string | null
  contract_start_date: string | null
  contract_end_date: string | null
  employment_type: string | null
  contract_type: string | null
  branch: { name: string } | null
  department: { name: string } | null
  job_title: { title: string } | null
}

export interface Pagination { current_page: number, last_page: number, total: number }
export interface DirectoryEmployee {
  id: number
  name: string
  position: string | null
  department: string | null
  branch: string | null
  work_email: string | null
  office_phone: string | null
}
export interface SelfCompany {
  name: string
  legal_name: string | null
  country: string
  emirate: string | null
  timezone: string
  default_currency: string
  branch: { name: string, address_line_1: string | null, address_line_2: string | null, city: string | null, phone: string | null, email: string | null } | null
}
export interface EmployeePayStatement {
  id: number
  period_start: string
  period_end: string
  pay_date: string | null
  gross_pay: string
  total_deductions: string
  net_pay: string
  items: { id: number, label: string, type: string, amount: string }[]
}
export interface EmployeePayOverview {
  currency: string
  compensation: { basic_salary: string | null, monthly_salary: string | null }
  direct_deposit: { bank_name: string | null, masked_iban: string | null }
  ytd: { year: number, through_date: string, statements: number, gross_pay: string, total_deductions: string, net_pay: string }
  current_statement: EmployeePayStatement | null
}
