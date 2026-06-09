<template>
  <select
    v-if="options?.length"
    class="column-filter"
    :aria-label="label"
    :value="modelValue"
    @change="emitValue"
  >
    <option value="">All</option>
    <option v-for="option in options" :key="option.value" :value="option.value">
      {{ option.label }}
    </option>
  </select>
  <input
    v-else
    class="column-filter"
    :aria-label="label"
    :placeholder="placeholder || 'Filter'"
    :type="type"
    :value="modelValue"
    @input="emitValue"
  >
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  modelValue: string
  label: string
  placeholder?: string
  type?: 'text' | 'date' | 'number'
  options?: Array<{ label: string, value: string }>
}>(), {
  placeholder: '',
  type: 'text',
  options: () => [],
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

function emitValue(event: Event) {
  emit('update:modelValue', (event.target as HTMLInputElement | HTMLSelectElement).value)
}
</script>
