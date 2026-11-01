<template>
  <section class="login-page">
    <form class="login-form" @submit.prevent="submit">
      <h1>Sign in</h1>
      <label>
        Username or email
        <input v-model="login" autocomplete="username" required>
      </label>
      <label>
        Password
        <input v-model="password" type="password" autocomplete="current-password" required>
      </label>
      <label v-if="organizations.length">
        Company
        <select v-model="organizationId" required>
          <option value="" disabled>Choose your company</option>
          <option v-for="organization in organizations" :key="organization.id" :value="organization.id">{{ organization.name }}</option>
        </select>
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <button type="submit" :disabled="loading">{{ loading ? 'Signing in...' : organizations.length ? 'Continue' : 'Sign in' }}</button>
    </form>
  </section>
</template>

<script setup lang="ts">
import type { OrganizationOption } from '../../../shared/types/api'

definePageMeta({ layout: false })

const auth = useAuthStore()
const login = ref('')
const organizations = ref<OrganizationOption[]>([])
const organizationId = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

watch([login, password], () => {
  organizations.value = []
  organizationId.value = ''
  error.value = ''
})

async function submit() {
  loading.value = true
  error.value = ''

  try {
    const options = await auth.login(login.value.trim(), password.value, organizationId.value)
    if (options) {
      organizations.value = options
      organizationId.value = ''
      return
    }
    await navigateTo(auth.hasRole('super_admin') && !auth.user?.client_id ? '/platform/companies' : '/')
  } catch (cause: unknown) {
    error.value = apiErrorMessage(cause, 'Check your username or email and password.')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  display: grid;
  min-height: 100vh;
  place-items: center;
  padding: 24px;
}

.login-form {
  display: grid;
  gap: 16px;
  width: min(100%, 380px);
  background: #ffffff;
  border: 1px solid #d8dee4;
  border-radius: 8px;
  padding: 24px;
}

label {
  display: grid;
  gap: 6px;
}

input,
select,
button {
  min-height: 42px;
  border-radius: 6px;
  border: 1px solid #b8c1c8;
  padding: 8px 10px;
}

button {
  background: var(--color-accent);
  border-color: var(--color-accent);
  color: #ffffff;
  cursor: pointer;
}

button:hover:not(:disabled) {
  filter: brightness(.95);
}

.error {
  color: #9f1d35;
  margin: 0;
}
</style>
