<template><EmployeeProfilePanel :section="section" /></template>
<script setup lang="ts">
definePageMeta({ middleware: ['auth'], validate: route => ['contact', 'key-dates', 'job'].includes(String(route.params.section)) })
const route = useRoute()
const section = computed(() => route.params.section as 'contact' | 'key-dates' | 'job')
</script>
