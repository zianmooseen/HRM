<template>
  <section class="login-page">
    <form class="login-form" @submit.prevent="submit">
      <h1>Sign in</h1>
      <label>
        Email
        <input v-model="email" type="email" autocomplete="email" required>
      </label>
      <label>
        Password
        <input v-model="password" type="password" autocomplete="current-password" required>
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <button type="submit" :disabled="loading">{{ loading ? 'Signing in...' : 'Sign in' }}</button>
    </form>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const auth = useAuthStore()
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''

  try {
    await auth.login(email.value, password.value)
    await navigateTo('/')
  } catch {
    error.value = 'Invalid email or password.'
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
button {
  min-height: 42px;
  border-radius: 6px;
  border: 1px solid #b8c1c8;
  padding: 8px 10px;
}

button {
  background: #16765f;
  color: #ffffff;
  cursor: pointer;
}

.error {
  color: #9f1d35;
  margin: 0;
}
</style>
