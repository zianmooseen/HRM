<template>
  <div class="app-shell">
    <aside class="sidebar">
      <strong>UAE HRM</strong>
      <nav aria-label="Main navigation">
        <template v-for="group in visibleNavigationGroups" :key="group.label">
          <span class="nav-group">{{ group.label }}</span>
          <NuxtLink v-for="item in group.items" :key="item.to" :to="item.to">
            {{ item.label }}
          </NuxtLink>
        </template>
      </nav>
      <div v-if="auth.user" class="user-panel">
        <div>
          <span>{{ auth.user.name }}</span>
          <small>{{ auth.user.email }}</small>
        </div>
        <button type="button" :disabled="loggingOut" @click="logout">
          {{ loggingOut ? 'Signing out...' : 'Sign out' }}
        </button>
      </div>
    </aside>
    <main class="content">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const loggingOut = ref(false)

const navigationGroups = [
  {
    label: 'Platform',
    roles: ['super_admin'],
    items: [
      { label: 'Platform dashboard', to: '/', permission: null },
      { label: 'Companies', to: '/platform/companies', permission: 'companies.view' },
      { label: 'Billing', to: '/platform/billing', permission: 'companies.view' },
      { label: 'Platform settings', to: '/platform/settings', permission: 'settings.view' },
      { label: 'Audit logs', to: '/platform/audit-logs', permission: 'audit_logs.view' },
    ],
  },
  {
    label: 'Company',
    roles: ['company_admin', 'hr_manager', 'payroll_manager', 'department_manager'],
    items: [
      { label: 'Company dashboard', to: '/', permission: null },
      { label: 'Company settings', to: '/settings/company', permission: 'companies.view' },
      { label: 'Branches', to: '/settings/branches', permission: 'companies.view' },
      { label: 'Departments', to: '/settings/departments', permission: 'companies.view' },
      { label: 'Job titles', to: '/settings/job-titles', permission: 'companies.view' },
      { label: 'Employees', to: '/employees', permission: 'employees.view' },
      { label: 'Onboarding', to: '/onboarding', permission: 'employees.view' },
      { label: 'Leave', to: '/leave', permission: 'leave.view' },
      { label: 'Attendance', to: '/attendance', permission: 'attendance.view' },
      { label: 'Payroll', to: '/payroll', permission: 'payroll.view' },
      { label: 'Compliance reports', to: '/reports/compliance', permission: 'settings.view' },
      { label: 'Audit logs', to: '/platform/audit-logs', permission: 'audit_logs.view' },
      { label: 'Compliance', to: '/settings/compliance', permission: 'settings.view' },
      { label: 'Leave policies', to: '/settings/leave-policies', permission: 'settings.view' },
      { label: 'Payroll policies', to: '/settings/payroll-policies', permission: 'settings.view' },
      { label: 'Public holidays', to: '/settings/public-holidays', permission: 'settings.view' },
      { label: 'Emiratisation', to: '/settings/emiratisation', permission: 'settings.view' },
    ],
  },
  {
    label: 'Self service',
    roles: ['employee'],
    items: [
      { label: 'My profile', to: '/my/profile', permission: null },
      { label: 'My documents', to: '/my/documents', permission: 'documents.view' },
      { label: 'My leave', to: '/my/leave', permission: 'leave.view' },
      { label: 'My attendance', to: '/my/attendance', permission: 'attendance.view' },
    ],
  },
] as const

const visibleNavigationGroups = computed(() =>
  navigationGroups
    .filter((group) => group.roles.some((role) => auth.hasRole(role)))
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => !item.permission || auth.hasPermission(item.permission)),
    }))
    .filter((group) => group.items.length > 0),
)

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
  grid-template-columns: 248px 1fr;
  min-height: 100vh;
}

.sidebar {
  display: flex;
  flex-direction: column;
  background: #102027;
  color: #ffffff;
  padding: 24px;
}

.sidebar nav {
  display: grid;
  gap: 8px;
  margin-top: 24px;
}

.nav-group {
  margin-top: 12px;
  color: #8fb3bd;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
}

.sidebar a {
  border-radius: 6px;
  padding: 10px 12px;
}

.sidebar a.router-link-active {
  background: #1f3b44;
}

.user-panel {
  display: grid;
  gap: 12px;
  margin-top: auto;
  border-top: 1px solid rgba(255, 255, 255, 0.18);
  padding-top: 18px;
}

.user-panel span,
.user-panel small {
  display: block;
}

.user-panel small {
  margin-top: 4px;
  color: #bed0d6;
}

.user-panel button {
  min-height: 40px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 6px;
  background: #ffffff;
  color: #102027;
  cursor: pointer;
}

.user-panel button:disabled {
  cursor: wait;
  opacity: 0.7;
}

.content {
  padding: 32px;
}

@media (max-width: 760px) {
  .app-shell {
    grid-template-columns: 1fr;
  }

  .sidebar {
    position: static;
  }
}
</style>
