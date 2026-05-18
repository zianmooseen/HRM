<template>
  <section>
    <h1>Dashboard</h1>
    <p>Signed in as {{ auth.user?.name }}</p>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const auth = useAuthStore()
</script>
