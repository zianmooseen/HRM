<template>
  <section class="action-form-panel">
    <div class="action-form-heading">
      <h2 v-if="open" :id="`${panelId}-title`">{{ title || actionLabel }}</h2>
      <button
        ref="trigger"
        type="button"
        :class="{ secondary: open }"
        :aria-expanded="open"
        :aria-controls="panelId"
        :disabled="busy || (!open && disabled)"
        @click="toggle"
      >{{ open ? 'Cancel' : actionLabel }}</button>
    </div>
    <div v-if="open" :id="panelId" ref="panel" role="region" :aria-labelledby="`${panelId}-title`" :aria-busy="busy">
      <slot />
      <div class="action-form-footer">
        <button type="button" class="secondary" :disabled="busy" @click="toggle">Cancel</button>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { nextTick, ref, useId, watch } from 'vue'

withDefaults(defineProps<{ actionLabel: string, title?: string, busy?: boolean, disabled?: boolean }>(), {
  title: '', busy: false, disabled: false,
})
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ create: [], cancel: [] }>()
const panelId = useId()
const panel = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)

function toggle() {
  if (open.value) {
    open.value = false
    emit('cancel')
  } else {
    emit('create')
    open.value = true
  }
}

watch(open, async (visible) => {
  await nextTick()
  if (!visible) {
    trigger.value?.focus()
    return
  }
  panel.value?.scrollIntoView({ block: 'nearest' })
  panel.value?.querySelector<HTMLElement>('input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled)')?.focus({ preventScroll: true })
})
</script>

<style scoped>
.action-form-panel { display: grid; gap: 16px; align-content: start; min-width: 0; }
.action-form-heading { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; justify-content: space-between; }
.action-form-heading h2 { margin: 0; }
.action-form-footer { display: flex; margin-top: 12px; }
</style>
