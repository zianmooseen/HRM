<template>
  <NuxtLayout>
    <NuxtPage :key="scope.key" />
  </NuxtLayout>
</template>

<script setup lang="ts">
const scope = useOrganizationScopeStore()
</script>
