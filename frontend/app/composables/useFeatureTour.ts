import type { DriveStep } from 'driver.js'

type TourDefinition = {
  label: string
  match: RegExp
  steps: DriveStep[]
}

const sharedShellSteps: DriveStep[] = [
  {
    element: '[data-tour="tour-launcher"]',
    popover: {
      title: 'Start a Page Guide',
      description: 'Use this button whenever you want a guided walkthrough for the current feature page.',
      side: 'right',
      align: 'start',
    },
  },
  {
    element: '[data-tour="main-nav"]',
    popover: {
      title: 'Feature Navigation',
      description: 'Use this menu to move between HR, payroll, compliance, platform, and self-service features.',
      side: 'right',
      align: 'center',
    },
  },
]

const pageIntroSteps: DriveStep[] = [
  {
    element: '.page h1, .dashboard-hero h1',
    popover: {
      title: 'Feature Page',
      description: 'This heading identifies the workflow you are currently using.',
      side: 'bottom',
      align: 'start',
    },
  },
]

const formSteps: DriveStep[] = [
  {
    element: '.form-grid',
    popover: {
      title: 'Data Entry',
      description: 'Complete the fields in this area, then submit the form action at the bottom.',
      side: 'right',
      align: 'start',
    },
  },
]

const tableSteps: DriveStep[] = [
  {
    element: 'table',
    popover: {
      title: 'Records Table',
      description: 'Review existing records here. Row actions usually open, edit, approve, or delete a record.',
      side: 'top',
      align: 'start',
    },
  },
]

const panelSteps: DriveStep[] = [
  {
    element: '.panel',
    popover: {
      title: 'Workflow Panel',
      description: 'Panels group the main actions and summaries for this feature.',
      side: 'top',
      align: 'start',
    },
  },
]

const tourDefinitions: TourDefinition[] = [
  {
    label: 'Dashboard',
    match: /^\/$/,
    steps: [
      {
        element: '.dashboard-hero',
        popover: {
          title: 'Dashboard Header',
          description: 'Start here for the main HRM overview and quick actions.',
          side: 'bottom',
          align: 'start',
        },
      },
      {
        element: '.metric-grid',
        popover: {
          title: 'Operational Metrics',
          description: 'These cards summarize employees, onboarding, leave, and attendance for the company.',
          side: 'bottom',
          align: 'start',
        },
      },
      {
        element: '.analytics-panel',
        popover: {
          title: 'HR Analytics',
          description: 'This chart gives a compact view of today’s attendance and leave signals.',
          side: 'top',
          align: 'start',
        },
      },
      {
        element: '.side-column',
        popover: {
          title: 'Priority Modules',
          description: 'Use this column to jump into payroll, operations, compliance, and document follow-up.',
          side: 'left',
          align: 'start',
        },
      },
      {
        element: '.qa-panel',
        popover: {
          title: 'Dashboard Q&A',
          description: 'Use these questions when you know the problem but need the next screen or action.',
          side: 'top',
          align: 'start',
        },
      },
    ],
  },
  {
    label: 'Employees',
    match: /^\/employees$/,
    steps: [
      ...pageIntroSteps,
      {
        element: 'header a[href="/employees/create"]',
        popover: {
          title: 'Create Employee',
          description: 'Start a new employee profile from this action.',
          side: 'left',
          align: 'center',
        },
      },
      ...tableSteps,
    ],
  },
  {
    label: 'Create Employee',
    match: /^\/employees\/create$/,
    steps: [
      ...pageIntroSteps,
      {
        element: '.form-grid label:nth-of-type(1)',
        popover: {
          title: 'Employee Code',
          description: 'Enter the unique internal employee code first.',
          side: 'right',
          align: 'center',
        },
      },
      {
        element: '.form-grid label:nth-of-type(14)',
        popover: {
          title: 'Hire Date',
          description: 'This date drives service period, leave accrual, probation, and gratuity calculations.',
          side: 'right',
          align: 'center',
        },
      },
      {
        element: '.form-grid label:nth-of-type(25)',
        popover: {
          title: 'Compensation',
          description: 'Set basic salary and package estimate for payroll and final settlement workflows.',
          side: 'right',
          align: 'center',
        },
      },
      ...formSteps,
    ],
  },
  {
    label: 'Employee Detail',
    match: /^\/employees\/\d+$/,
    steps: [...pageIntroSteps, { element: '.detail-list', popover: { title: 'Employee Details', description: 'Review the employee profile, employment dates, salary visibility, and lifecycle status.', side: 'right', align: 'start' } }, ...panelSteps],
  },
  {
    label: 'Edit Employee',
    match: /^\/employees\/\d+\/edit$/,
    steps: [...pageIntroSteps, ...formSteps],
  },
  {
    label: 'Attendance',
    match: /^\/attendance$/,
    steps: [
      ...pageIntroSteps,
      { element: '.form-grid', popover: { title: 'Manual Attendance Entry', description: 'Create or edit daily attendance records for employees.', side: 'right', align: 'start' } },
      { element: '.filters', popover: { title: 'Attendance Filters', description: 'Filter records by employee and date range.', side: 'top', align: 'start' } },
      ...tableSteps,
      ...panelSteps,
    ],
  },
  {
    label: 'Leave',
    match: /^\/leave$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps, ...panelSteps],
  },
  {
    label: 'Onboarding',
    match: /^\/onboarding$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps, ...panelSteps],
  },
  {
    label: 'Payroll',
    match: /^\/payroll$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps, ...panelSteps],
  },
  {
    label: 'Compliance Reports',
    match: /^\/reports\/compliance$/,
    steps: [...pageIntroSteps, ...panelSteps, ...tableSteps],
  },
  {
    label: 'Audit Logs',
    match: /^\/platform\/audit-logs$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps],
  },
  {
    label: 'Platform Companies',
    match: /^\/platform\/companies$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps],
  },
  {
    label: 'Platform Billing',
    match: /^\/platform\/billing$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps, ...panelSteps],
  },
  {
    label: 'Platform Settings',
    match: /^\/platform\/settings$/,
    steps: [...pageIntroSteps, ...panelSteps],
  },
  {
    label: 'Company Settings',
    match: /^\/settings\/company$/,
    steps: [...pageIntroSteps, ...formSteps, ...panelSteps],
  },
  {
    label: 'Branches',
    match: /^\/settings\/branches$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps],
  },
  {
    label: 'Departments',
    match: /^\/settings\/departments$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps],
  },
  {
    label: 'Job Titles',
    match: /^\/settings\/job-titles$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps],
  },
  {
    label: 'Compliance Settings',
    match: /^\/settings\/compliance$/,
    steps: [...pageIntroSteps, ...formSteps, ...panelSteps],
  },
  {
    label: 'Leave Policies',
    match: /^\/settings\/leave-policies$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps, ...panelSteps],
  },
  {
    label: 'Payroll Policies',
    match: /^\/settings\/payroll-policies$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps, ...panelSteps],
  },
  {
    label: 'Public Holidays',
    match: /^\/settings\/public-holidays$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps],
  },
  {
    label: 'Emiratisation',
    match: /^\/settings\/emiratisation$/,
    steps: [...pageIntroSteps, ...formSteps, ...panelSteps, ...tableSteps],
  },
  {
    label: 'Self-Service Profile',
    match: /^\/my\/profile$/,
    steps: [...pageIntroSteps, { element: '.detail-list', popover: { title: 'My Profile', description: 'Employees can review their own HR profile information here.', side: 'right', align: 'start' } }],
  },
  {
    label: 'Self-Service Documents',
    match: /^\/my\/documents$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps],
  },
  {
    label: 'Self-Service Leave',
    match: /^\/my\/leave$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps, ...panelSteps],
  },
  {
    label: 'Self-Service Attendance',
    match: /^\/my\/attendance$/,
    steps: [...pageIntroSteps, ...formSteps, ...tableSteps, ...panelSteps],
  },
]

export function useFeatureTour() {
  const route = useRoute()
  const activeDefinition = computed(() => tourDefinitions.find((tour) => tour.match.test(route.path)) ?? null)
  const totalFeatureTours = tourDefinitions.length

  const availableSteps = computed(() => {
    if (!activeDefinition.value || !import.meta.client) {
      return []
    }

    return [...sharedShellSteps, ...activeDefinition.value.steps].filter((step) => {
      if (!step.element || typeof step.element !== 'string') {
        return true
      }

      return Boolean(document.querySelector(step.element))
    })
  })

  async function startTour() {
    if (!import.meta.client || availableSteps.value.length === 0) {
      return
    }

    await nextTick()

    const { driver } = await import('driver.js')
    const featureName = activeDefinition.value?.label ?? 'This Page'

    driver({
      steps: availableSteps.value,
      animate: true,
      allowClose: true,
      overlayOpacity: 0.58,
      stagePadding: 8,
      stageRadius: 8,
      showProgress: true,
      nextBtnText: 'Next',
      prevBtnText: 'Back',
      doneBtnText: 'Done',
      popoverClass: 'hrm-driver-popover',
      progressText: `${featureName} {{current}} of {{total}}`,
    }).drive()
  }

  return {
    startTour,
    totalFeatureTours,
    currentFeatureLabel: computed(() => activeDefinition.value?.label ?? 'No guide available'),
    hasTour: computed(() => Boolean(activeDefinition.value)),
  }
}
