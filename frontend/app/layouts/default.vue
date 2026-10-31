<template>
  <div class="app-shell" :class="{ 'navigation-open': navigationOpen }" @keydown.esc="closeNavigation">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="app-topbar">
      <button ref="menuButton" type="button" class="icon-button" aria-label="Toggle navigation" aria-controls="app-navigation" :aria-expanded="navigationOpen" @click="toggleNavigation">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
      </button>
      <NuxtLink to="/" class="app-brand" data-tour="app-brand">UAE <strong>HRM</strong></NuxtLink>
      <span class="topbar-title">{{ pageTitle }}</span>
      <div class="topbar-actions">
        <button type="button" class="icon-button" :disabled="!hasTour" aria-label="Open page guide" title="Page guide" @click="startTour">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M9.5 9a2.5 2.5 0 0 1 5 .5c0 1.5-2.5 2-2.5 3M12 16h.01" /></svg>
        </button>
        <details v-if="auth.user" ref="accountMenu" class="account-menu">
          <summary aria-label="Account menu" title="Account menu"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><circle cx="12" cy="9" r="3" /><path d="M5.5 18c1-5 12-5 13 0" /></svg></summary>
          <div class="account-dropdown"><strong>{{ auth.user.name }}</strong><small>{{ auth.user.email }}</small>
            <NuxtLink v-if="auth.hasRole('employee')" to="/my/profile">My profile</NuxtLink>
            <button type="button" :disabled="loggingOut" @click="logout">{{ loggingOut ? 'Signing out…' : 'Sign out' }}</button>
          </div>
        </details>
      </div>
    </header>
    <button v-if="navigationOpen" class="navigation-backdrop" type="button" aria-label="Close navigation" tabindex="-1" @click="closeNavigation" />
    <aside v-show="navigationOpen" id="app-navigation" class="sidebar" data-tour="sidebar">
      <label class="navigation-search">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 5 5" /></svg>
        <input v-model="navigationSearch" type="search" placeholder="Search menu" aria-label="Search navigation">
      </label>
      <nav aria-label="Main navigation" data-tour="main-nav">
        <template v-for="group in filteredNavigationGroups" :key="group.label">
          <span class="nav-group">{{ group.label === 'Self service' ? 'Myself' : group.label }}</span>
          <section
            v-for="section in group.sections"
            :key="sectionKey(group.label, section.label)"
            :class="['nav-section', { active: isSectionActive(section) }]"
          >
            <button
              type="button"
              class="nav-section-trigger"
              :aria-expanded="Boolean(navigationSearch.trim()) || isSectionExpanded(group.label, section.label)"
              @click="toggleSection(group.label, section.label)"
            >
              <span>{{ section.label }}</span>
              <span class="nav-chevron">›</span>
            </button>

            <div v-if="navigationSearch.trim() || isSectionExpanded(group.label, section.label)" class="nav-section-items">
              <NuxtLink v-for="item in section.items" :key="item.to" :to="item.to" :data-tour="navTourKey(item.to)">
                {{ item.label }}
              </NuxtLink>
            </div>
          </section>
        </template>
        <p v-if="!filteredNavigationGroups.length" class="navigation-empty" role="status">No matching pages.</p>
      </nav>
      <div v-if="auth.user" class="user-panel">
        <div>
          <span>{{ auth.user.name }}</span>
          <small>{{ auth.user.email }}</small>
        </div>
        <button type="button" class="guide-button" :disabled="!hasTour" data-tour="tour-launcher" @click="startTour">
          Guide
        </button>
        <button type="button" :disabled="loggingOut" @click="logout">
          {{ loggingOut ? 'Signing out...' : 'Sign out' }}
        </button>
      </div>
    </aside>
    <main id="main-content" class="content" tabindex="-1" data-tour="page-content" :inert="isMobile && navigationOpen">
      <OrganizationScopeFilter v-if="route.path !== '/platform/companies'" />
      <slot v-if="isPlatformAdministration || !scope.enabled || (scope.ready && scope.companyId)" />
      <p v-else-if="!scope.error" role="status">{{ scope.ready ? 'No company is assigned to this account.' : 'Loading company filters…' }}</p>
    </main>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const scope = useOrganizationScopeStore()
const { startTour, hasTour } = useFeatureTour()
const route = useRoute()
const isPlatformAdministration = computed(() => auth.hasRole('super_admin') && !auth.user?.client_id && route.path.startsWith('/platform/'))
const loggingOut = ref(false)
const expandedSections = ref<Record<string, boolean>>({})
const navigationOpen = ref(true)
const navigationSearch = ref('')
const isMobile = ref(false)
const menuButton = ref<HTMLButtonElement | null>(null)
const accountMenu = ref<HTMLDetailsElement | null>(null)
let mobileQuery: MediaQueryList | undefined
function syncViewport() {
  isMobile.value = Boolean(mobileQuery?.matches)
  navigationOpen.value = !isMobile.value
}
onMounted(() => {
  mobileQuery = window.matchMedia('(max-width: 900px)')
  syncViewport()
  mobileQuery.addEventListener('change', syncViewport)
})
onBeforeUnmount(() => mobileQuery?.removeEventListener('change', syncViewport))
async function toggleNavigation() {
  navigationOpen.value = !navigationOpen.value
  if (navigationOpen.value && isMobile.value) {
    await nextTick()
    document.querySelector<HTMLInputElement>('#app-navigation input')?.focus()
  }
}
function closeNavigation() {
  if (accountMenu.value?.open) {
    accountMenu.value.open = false
    accountMenu.value.querySelector('summary')?.focus()
    return
  }
  navigationOpen.value = false
  menuButton.value?.focus()
}
watch(() => route.path, () => {
  navigationSearch.value = ''
  if (accountMenu.value) accountMenu.value.open = false
  if (isMobile.value) {
    navigationOpen.value = false
    menuButton.value?.focus()
  }
})


const navigationGroups = [
  {
    label: 'Platform',
    roles: ['super_admin'],
    sections: [
      {
        label: 'Dashboard',
        defaultOpen: true,
        items: [
          { label: 'Platform dashboard', to: '/', permission: null },
        ],
      },
      {
        label: 'Administration',
        items: [
          { label: 'Clients & companies', to: '/platform/companies', permission: 'companies.view' },
          { label: 'WPS providers & risk', to: '/platform/wps', permission: null },
          { label: 'Platform settings', to: '/platform/settings', permission: 'settings.view' },
        ],
      },
      {
        label: 'Commercial',
        items: [
          { label: 'Billing', to: '/platform/billing', permission: 'companies.view' },
        ],
      },
      {
        label: 'Governance',
        items: [
          { label: 'Audit logs', to: '/platform/audit-logs', permission: 'audit_logs.view' },
        ],
      },
    ],
  },
  {
    label: 'Company',
    roles: ['super_admin', 'company_admin', 'hr_manager', 'payroll_manager', 'department_manager'],
    sections: [
      {
        label: 'Dashboard',
        defaultOpen: true,
        items: [
          { label: 'Company dashboard', to: '/', permission: null },
        ],
      },
      {
        label: 'Organization',
        items: [
          { label: 'Company settings', to: '/settings/company', permission: 'companies.view' },
          { label: 'Branches', to: '/settings/branches', permission: 'companies.view' },
          { label: 'Departments', to: '/settings/departments', permission: 'companies.view' },
          { label: 'Job titles', to: '/settings/job-titles', permission: 'companies.view' },
        ],
      },
      {
        label: 'People',
        items: [
          { label: 'Employees', to: '/employees', permission: 'employees.view' },
          { label: 'Temporary Workers', to: '/temporary-workers', permission: 'temporary_workers.view' },
          { label: 'Onboarding', to: '/onboarding', permission: 'employees.view' },
        ],
      },
      {
        label: 'Time & Leave',
        items: [
          { label: 'Attendance', to: '/attendance', permission: 'attendance.view' },
          { label: 'Leave', to: '/leave', permission: 'leave.view' },
          { label: 'Leave calendar', to: '/leave/calendar', permission: 'leave.view' },
        ],
      },
      {
        label: 'Payroll',
        items: [
          { label: 'Payroll', to: '/payroll', permission: 'payroll.view' },
          { label: 'WPS salary transfers', to: '/payroll/wps', permission: 'salary_transfers.view' },
          { label: 'Payroll policies', to: '/settings/payroll-policies', permission: 'settings.view' },
        ],
      },
      {
        label: 'Compliance',
        items: [
          { label: 'Compliance', to: '/settings/compliance', permission: 'settings.view' },
          { label: 'MoHRE & WPS setup', to: '/settings/mohre-wps', permission: 'wps_settings.view' },
          { label: 'Leave policies', to: '/settings/leave-policies', permission: 'settings.view' },
          { label: 'Public holidays', to: '/settings/public-holidays', permission: 'settings.view' },
          { label: 'Emiratisation', to: '/settings/emiratisation', permission: 'settings.view' },
          { label: 'Compliance reports', to: '/reports/compliance', permission: 'settings.view' },
        ],
      },
      {
        label: 'Governance',
        items: [
          { label: 'Audit logs', to: '/platform/audit-logs', permission: 'audit_logs.view' },
        ],
      },
    ],
  },
  {
    label: 'Self service',
    roles: ['employee'],
    sections: [
      {
        label: 'Home',
        defaultOpen: true,
        items: [{ label: 'Home', to: '/', permission: null }],
      },
      {
        label: 'Personal',
        defaultOpen: true,
        items: [
          { label: 'Employee Summary', to: '/my/profile', permission: null },
          { label: 'Name and Contact Details', to: '/my/personal/contact', permission: null },
          { label: 'Status / Key Dates', to: '/my/personal/key-dates', permission: null },
          { label: 'Identification Documents', to: '/my/identification', permission: 'documents.view' },
        ],
      },
      {
        label: 'My Company',
        items: [
          { label: 'Company Details', to: '/my/company', permission: null },
          { label: 'Employee Directory', to: '/my/directory', permission: null },
        ],
      },
      {
        label: 'Job',
        items: [
          { label: 'Job Summary', to: '/my/personal/job', permission: null },
          { label: 'Compensation', to: '/my/pay/compensation', permission: null },
        ],
      },
      {
        label: 'Pay',
        items: [
          { label: 'Current Pay Statement', to: '/my/pay/current', permission: null },
          { label: 'Pay History', to: '/my/pay/history', permission: null },
          { label: 'YTD Summary', to: '/my/pay/ytd', permission: null },
          { label: 'Direct Deposit', to: '/my/pay/direct-deposit', permission: null },
        ],
      },
      {
        label: 'Workforce Management',
        items: [
          { label: 'My Attendance', to: '/my/attendance', permission: 'attendance.view' },
          { label: 'My Leave', to: '/my/leave', permission: 'leave.view' },
          { label: 'Leave Calendar', to: '/leave/calendar', permission: 'leave.view' },
        ],
      },
      {
        label: 'Documents',
        items: [{ label: 'Employee Documents', to: '/my/documents', permission: 'documents.view' }],
      },
    ],
  },
] as const

const visibleNavigationGroups = computed(() =>
  navigationGroups
    .filter((group) => group.roles.some((role) => auth.hasRole(role)))
    .map((group) => ({
      ...group,
      sections: group.sections
        .map((section) => ({
          ...section,
          items: section.items.filter((item) => !item.permission || auth.hasPermission(item.permission)),
        }))
        .filter((section) => section.items.length > 0),
    }))
    .filter((group) => group.sections.length > 0),
)

// Search only within the navigation already allowed by the user's roles and permissions.
const filteredNavigationGroups = computed(() => {
  const term = navigationSearch.value.trim().toLowerCase()
  return visibleNavigationGroups.value.map(group => ({
    ...group,
    sections: group.sections.map(section => ({
      ...section,
      items: section.items.filter(item => `${group.label} ${section.label} ${item.label}`.toLowerCase().includes(term)),
    })).filter(section => section.items.length),
  })).filter(group => group.sections.length)
})
const pageTitle = computed(() => {
  const items = visibleNavigationGroups.value.flatMap(group => group.sections.flatMap(section => section.items))
  const match = items.filter(item => isRouteActive(item.to)).sort((a, b) => b.to.length - a.to.length)[0]
  return match?.label || 'UAE HRM'
})

watch(
  visibleNavigationGroups,
  (groups) => {
    const next = { ...expandedSections.value }

    for (const group of groups) {
      for (const section of group.sections) {
        const key = sectionKey(group.label, section.label)

        if (next[key] === undefined) {
          next[key] = Boolean(section.defaultOpen || isSectionActive(section))
        }

        if (isSectionActive(section)) {
          next[key] = true
        }
      }
    }

    expandedSections.value = next
  },
  { immediate: true },
)

watch(
  () => route.path,
  () => {
    const next = { ...expandedSections.value }

    for (const group of visibleNavigationGroups.value) {
      for (const section of group.sections) {
        if (isSectionActive(section)) {
          next[sectionKey(group.label, section.label)] = true
        }
      }
    }

    expandedSections.value = next
  },
)

function sectionKey(groupLabel: string, sectionLabel: string) {
  return `${groupLabel}:${sectionLabel}`
}

function isSectionExpanded(groupLabel: string, sectionLabel: string) {
  return Boolean(expandedSections.value[sectionKey(groupLabel, sectionLabel)])
}

function toggleSection(groupLabel: string, sectionLabel: string) {
  const key = sectionKey(groupLabel, sectionLabel)
  expandedSections.value = {
    ...expandedSections.value,
    [key]: !expandedSections.value[key],
  }
}

function isSectionActive(section: { items: Array<{ to: string }> }) {
  return section.items.some((item) => isRouteActive(item.to))
}

function isRouteActive(to: string) {
  if (to === '/') {
    return route.path === '/'
  }

  return route.path === to || route.path.startsWith(`${to}/`)
}

function navTourKey(to: string) {
  return `nav-${to.replaceAll('/', '-').replace(/^-/, '') || 'dashboard'}`
}

async function logout() {
  loggingOut.value = true

  try {
    await auth.logout()
  } finally {
    loggingOut.value = false
    await navigateTo('/login')
  }
}
</script>

<style scoped>
.app-shell { --header-height: 72px; --sidebar-width: 280px; min-height: 100vh; background: var(--color-canvas); }
.app-topbar { position: sticky; top: 0; z-index: 40; height: var(--header-height); display: flex; align-items: center; gap: 22px; padding: 0 28px; background: var(--color-navy); color: white; }
.app-topbar :focus-visible { outline-color: white; }
.app-brand { white-space: nowrap; font-size: 1.4rem; letter-spacing: .02em; color: white; }
.app-brand strong { font-weight: 700; }
.topbar-title { border-left: 1px solid #ffffff40; padding-left: 24px; font-size: 1.2rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 14px; }
.icon-button, .account-menu summary { width: 42px; height: 42px; display: grid; place-items: center; padding: 8px; border: 0; border-radius: 50%; color: white; background: transparent; cursor: pointer; }
.icon-button:hover, .account-menu summary:hover { background: #ffffff18; }
.icon-button:disabled { opacity: .45; cursor: default; }
svg { width: 26px; height: 26px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.account-menu { position: relative; }
.account-menu summary { list-style: none; }
.account-menu summary::-webkit-details-marker { display: none; }
.account-dropdown { position: absolute; top: 54px; right: 0; width: min(300px, calc(100vw - 32px)); display: grid; gap: 12px; padding: 22px; background: white; color: var(--color-text); border: 1px solid var(--color-border); border-radius: 12px; box-shadow: 0 10px 30px #142c4820; overflow-wrap: anywhere; }
.account-dropdown small { color: var(--color-muted); }
.account-dropdown a { color: var(--color-accent); }
.account-dropdown button { border: 1px solid var(--color-accent); border-radius: 999px; padding: 9px 14px; background: white; color: var(--color-accent); cursor: pointer; }
.sidebar { position: fixed; top: var(--header-height); bottom: 0; left: 0; z-index: 30; width: var(--sidebar-width); display: flex; flex-direction: column; padding: 24px 16px 18px; border-right: 1px solid var(--color-border); background: white; }
.navigation-search { display: flex; align-items: center; gap: 10px; border: 1px solid var(--color-input-border); border-radius: 8px; padding: 10px 12px; color: var(--color-muted); }
.navigation-search svg { width: 21px; height: 21px; }
.navigation-search input { width: 100%; min-width: 0; border: 0; padding: 0; background: transparent; font: inherit; color: var(--color-text); }
.navigation-search:focus-within { outline: 2px solid var(--color-accent); outline-offset: 2px; }
.navigation-search input:focus-visible { outline: none; }
.sidebar nav { overflow-y: auto; min-height: 0; flex: 1; margin: 18px -8px 0; padding: 0 8px 20px; scrollbar-width: thin; }
.nav-group { display: block; margin: 18px 12px 12px; color: var(--color-text); font-size: 1.3rem; font-weight: 700; }
.nav-section { margin-bottom: 4px; }
.nav-section-trigger { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 44px; padding: 10px 12px; border: 0; border-radius: 8px; background: transparent; color: var(--color-text); cursor: pointer; font: inherit; font-weight: 600; text-align: left; }
.nav-section-trigger:hover { background: var(--color-surface-muted); }
.nav-chevron { color: var(--color-muted); font-size: 1.4rem; line-height: 1; transform: rotate(90deg); }
.nav-section-trigger[aria-expanded="true"] .nav-chevron { transform: rotate(-90deg); }
.nav-section-items { display: grid; margin-left: 16px; border-left: 1px solid var(--color-border); }
.sidebar a { display: block; border-radius: 0 999px 999px 0; padding: 10px 14px; color: var(--color-text); font-size: .95rem; line-height: 1.4; }
.sidebar a:hover { background: var(--color-surface-muted); }
.sidebar a.router-link-active { background: #ededed; color: #202020; font-weight: 700; }
.navigation-empty { color: var(--color-muted); padding: 12px; }
.user-panel { display: grid; gap: 10px; flex-shrink: 0; border-top: 1px solid var(--color-border); padding: 16px 10px 0; }
.user-panel span, .user-panel small { display: block; overflow-wrap: anywhere; }
.user-panel span { font-weight: 600; }
.user-panel small { color: var(--color-muted); margin-top: 3px; }
.user-panel button { min-height: 38px; border: 1px solid var(--color-accent); border-radius: 999px; background: white; color: var(--color-accent); cursor: pointer; font: inherit; font-weight: 600; }
.user-panel .guide-button { background: var(--color-accent-soft); border-color: transparent; }
.user-panel button:disabled { opacity: .5; cursor: default; }
.content { min-width: 0; padding: 32px; }
.navigation-open .content { margin-left: var(--sidebar-width); }
.navigation-backdrop { display: none; }
.skip-link { position: fixed; top: -100px; left: 16px; z-index: 60; padding: 12px 20px; background: white; color: var(--color-accent); border-radius: 6px; }
.skip-link:focus { top: 12px; }
@media (max-width: 1100px) { .content { padding: 24px; } }
@media (max-width: 900px) {
  .app-shell { --header-height: 64px; }
  .app-topbar { padding: 0 16px; gap: 12px; }
  .topbar-title { font-size: 1rem; padding-left: 12px; }
  .navigation-open .content { margin-left: 0; }
  .sidebar { width: min(320px, 88vw); box-shadow: 8px 0 24px #152d4820; }
  .navigation-backdrop { display: block; position: fixed; inset: var(--header-height) 0 0; border: 0; background: #10233855; z-index: 25; }
}
@media (max-width: 540px) {
  .topbar-title { display: none; }
  .content { padding: 20px 16px; }
  .topbar-actions { gap: 4px; }
}
@media print { .app-topbar, .sidebar, .navigation-backdrop { display: none; } .navigation-open .content { margin: 0; padding: 0; } }
</style>
