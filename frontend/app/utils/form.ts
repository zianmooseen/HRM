import { toRaw } from 'vue'

export function createFormReset<T extends object>(form: T) {
  const initial = structuredClone(toRaw(form))
  return () => Object.assign(form, structuredClone(initial))
}
