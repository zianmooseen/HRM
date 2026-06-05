<template>
  <div class="app-shell">
    <aside class="sidebar" data-tour="sidebar">
      <strong data-tour="app-brand">UAE HRM</strong>
      <nav aria-label="Main navigation" data-tour="main-nav">
        <template v-for="group in visibleNavigationGroups" :key="group.label">
          <span class="nav-group">{{ group.label }}</span>
          <section
            v-for="section in group.sections"
            :key="sectionKey(group.label, section.label)"
            :class="['nav-section', { active: isSectionActive(section) }]"
          >
            <button
              type="button"
              class="nav-section-trigger"
              :aria-expanded="isSectionExpanded(group.label, section.label)"
              @click="toggleSection(group.label, section.label)"
            >
              <span>{{ section.label }}</span>
              <span class="nav-chevron">›</span>
            </button>

            <div v-if="isSectionExpanded(group.label, section.label)" class="nav-section-items">
              <NuxtLink v-for="item in section.items" :key="item.to" :to="item.to" :data-tour="navTourKey(item.to)">
                {{ item.label }}
              </NuxtLink>
            </div>
          </section>
        </template>
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
    <main class="content" data-tour="page-content">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const { startTour, hasTour } = useFeatureTour()
const route = useRoute()
const loggingOut = ref(false)
const expandedSections = ref<Record<string, boolean>>({})

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
          { label: 'Companies', to: '/platform/companies', permission: 'companies.view' },
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
    roles: ['company_admin', 'hr_manager', 'payroll_manager', 'department_manager'],
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
          { label: 'Onboarding', to: '/onboarding', permission: 'employees.view' },
        ],
      },
      {
        label: 'Time & Leave',
        items: [
          { label: 'Attendance', to: '/attendance', permission: 'attendance.view' },
          { label: 'Leave', to: '/leave', permission: 'leave.view' },
        ],
      },
      {
        label: 'Payroll',
        items: [
          { label: 'Payroll', to: '/payroll', permission: 'payroll.view' },
          { label: 'Payroll policies', to: '/settings/payroll-policies', permission: 'settings.view' },
        ],
      },
      {
        label: 'Compliance',
        items: [
          { label: 'Compliance', to: '/settings/compliance', permission: 'settings.view' },
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
        label: 'My Workspace',
        defaultOpen: true,
        items: [
          { label: 'My profile', to: '/my/profile', permission: null },
          { label: 'My documents', to: '/my/documents', permission: 'documents.view' },
          { label: 'My leave', to: '/my/leave', permission: 'leave.view' },
          { label: 'My attendance', to: '/my/attendance', permission: 'attendance.view' },
        ],
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
.app-shell {
  display: grid;
  grid-template-columns: 260px minmax(0, 1fr);
  min-height: 100vh;
  background: #f3f5f2;
}

.sidebar {
  position: sticky;
  top: 0;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #edf0ec;
  background: #fbfcfb;
  color: #0a120f;
  padding: 28px 22px;
  box-shadow: 16px 0 50px rgba(17, 35, 27, 0.04);
}

.sidebar > strong {
  color: #073d25;
  font-size: 1.05rem;
  font-weight: 500;
  letter-spacing: 0;
}

.sidebar nav {
  display: grid;
  gap: 6px;
  margin-top: 30px;
}

.nav-group {
  margin: 18px 10px 8px;
  color: #8a918d;
  font-size: 0.82rem;
  font-weight: 400;
  text-transform: uppercase;
}

.nav-section {
  display: grid;
  gap: 4px;
}

.nav-section-trigger {
  min-height: 42px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #617069;
  cursor: pointer;
  font: inherit;
  font-size: 0.98rem;
  font-weight: 500;
  padding: 10px 14px;
  text-align: left;
}

.nav-section.active > .nav-section-trigger,
.nav-section-trigger:hover {
  background: #ffffff;
  color: #063b24;
}

.nav-chevron {
  display: inline-flex;
  color: #8a918d;
  font-size: 1.15rem;
  line-height: 1;
  transition: transform 0.16s ease;
}

.nav-section-trigger[aria-expanded="true"] .nav-chevron {
  transform: rotate(90deg);
}

.nav-section-items {
  display: grid;
  gap: 3px;
  padding-left: 10px;
}

.sidebar a {
  position: relative;
  border-radius: 8px;
  color: #83908a;
  padding: 10px 14px;
  font-size: 0.96rem;
  font-weight: 400;
}

.sidebar a.router-link-active {
  background: #ffffff;
  color: #06100c;
  font-weight: 500;
  box-shadow: 0 12px 28px rgba(18, 34, 27, 0.08);
}

.sidebar a.router-link-active::before {
  content: "";
  position: absolute;
  left: -32px;
  top: 9px;
  width: 8px;
  height: calc(100% - 18px);
  border-radius: 0 999px 999px 0;
  background: linear-gradient(180deg, #0e7044, #2ba264);
}

.user-panel {
  display: grid;
  gap: 12px;
  margin-top: auto;
  border-top: 1px solid #edf0ec;
  padding-top: 18px;
}

.user-panel span,
.user-panel small {
  display: block;
}

.user-panel small {
  margin-top: 4px;
  color: #7d8782;
}

.user-panel button {
  min-height: 40px;
  border: 1px solid #064027;
  border-radius: 999px;
  background: #ffffff;
  color: #064027;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
}

.user-panel .guide-button {
  background: #064027;
  color: #ffffff;
}

.user-panel button:disabled {
  cursor: wait;
  opacity: 0.7;
}

.content {
  width: 100%;
  max-width: 1480px;
  padding: 34px;
}

@media (max-width: 760px) {
  .app-shell {
    grid-template-columns: 1fr;
  }

  .sidebar {
    position: static;
    min-height: auto;
  }
}
</style>
