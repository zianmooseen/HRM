<template>
  <section class="page leave-calendar">
    <header>
      <div>
        <h1>Leave calendar</h1>
        <p class="muted">See when you and your colleagues are on approved leave.</p>
      </div>
      <span class="self-key">Your leave is highlighted</span>
    </header>

    <div class="calendar-toolbar">
      <h2 id="calendar-month" aria-live="polite">{{ monthLabel }}</h2>
      <div class="calendar-controls">
        <button type="button" class="secondary" aria-label="Previous month" :disabled="month === '1000-01'" @click="moveMonth(-1)">‹</button>
        <label class="month-picker">
          <span class="sr-only">Choose month</span>
          <input type="month" :value="month" min="1000-01" max="9999-12" @change="selectMonth">
        </label>
        <button type="button" class="secondary" aria-label="Next month" :disabled="month === '9999-12'" @click="moveMonth(1)">›</button>
        <button type="button" class="secondary" @click="month = today.slice(0, 7)">This month</button>
      </div>
    </div>

    <p v-if="loading" role="status" class="muted">Loading approved leave…</p>
    <div v-else-if="error" role="alert" class="calendar-error">
      <p class="error">{{ error }}</p>
      <button type="button" @click="reload">Try again</button>
    </div>
    <p v-else-if="!entries.length" role="status" class="muted">No approved leave this month.</p>
    <p v-else class="muted calendar-hint">{{ peopleCount }} {{ peopleCount === 1 ? 'person has' : 'people have' }} approved leave this month. Scroll inside a day to see everyone.</p>

    <div class="calendar-scroll" :aria-busy="loading">
      <div class="calendar-grid" role="table" aria-labelledby="calendar-month">
        <div class="calendar-week" role="row">
          <div v-for="weekday in weekdays" :key="weekday" class="weekday" role="columnheader">{{ weekday }}</div>
        </div>
        <div v-for="(week, weekIndex) in weeks" :key="weekIndex" class="calendar-week" role="row">
          <div v-for="(cell, dayIndex) in week" :key="cell?.date || `empty-${dayIndex}`" class="calendar-day" :class="{ 'empty-day': !cell, 'today': cell?.date === today }" role="cell">
            <template v-if="cell">
              <div class="day-heading">
                <time :datetime="cell.date" :aria-current="cell.date === today ? 'date' : undefined">{{ cell.day }}</time>
                <span v-if="!loading && !error && cell.people.length" class="day-count">{{ cell.people.length }} away</span>
              </div>
              <ul v-if="!loading && !error && cell.people.length" class="day-people" tabindex="0" :aria-label="`People on leave on ${cell.date}`">
                <li v-for="person in cell.people" :key="person.employee_id" :class="{ 'own-leave': person.is_self }">
                  <span>{{ person.employee_name }}</span>
                  <strong v-if="person.is_self">You</strong>
                </li>
              </ul>
            </template>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { calendarDays } from '../../utils/leave-calendar'

definePageMeta({ middleware: 'auth' })
useHead({ title: 'Leave calendar · UAE HRM' })

const currentDate = new Date()
const today = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(currentDate.getDate()).padStart(2, '0')}`
const month = ref(today.slice(0, 7))
const { entries, loading, error, reload } = useLeaveCalendar(month)
const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const monthLabel = computed(() => new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${month.value}-01T00:00:00Z`)))
const days = computed(() => calendarDays(month.value, entries.value))
const weeks = computed(() => Array.from({ length: days.value.length / 7 }, (_, index) => days.value.slice(index * 7, index * 7 + 7)))
const peopleCount = computed(() => new Set(entries.value.map((entry) => entry.employee_id)).size)

function moveMonth(offset: number) {
  const date = new Date(`${month.value}-01T00:00:00Z`)
  date.setUTCMonth(date.getUTCMonth() + offset)
  month.value = date.toISOString().slice(0, 7)
}

function selectMonth(event: Event) {
  const input = event.target as HTMLInputElement
  if (/^[1-9]\d{3}-(0[1-9]|1[0-2])$/.test(input.value)) month.value = input.value
  else input.value = month.value
}
</script>

<style scoped>
.leave-calendar { min-width: 0; }
.calendar-toolbar, .calendar-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.calendar-toolbar { justify-content: space-between; }
.calendar-toolbar h2 { margin: 0; font-size: 1.3rem; }
.month-picker input { padding: 9px; border: 1px solid #cbd5dc; border-radius: 6px; font: inherit; background: white; color: inherit; }
.self-key { background: #d9f2e8; color: #125b49; padding: 8px 12px; border-radius: 6px; font-size: 0.85rem; }
.calendar-hint { margin: 0; }
.calendar-scroll { overflow-x: auto; border: 1px solid #dce3e8; border-radius: 10px; background: white; }
.calendar-grid { min-width: 770px; }
.calendar-week { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
.weekday { padding: 12px; color: #5f6c72; font-size: 0.85rem; font-weight: 600; background: #f8fafb; }
.calendar-day { height: 170px; min-width: 0; padding: 8px; border-top: 1px solid #dce3e8; border-right: 1px solid #dce3e8; display: flex; flex-direction: column; gap: 6px; }
.calendar-day:last-child { border-right: 0; }
.empty-day { background: #f8fafb; }
.today { box-shadow: inset 0 0 0 2px #16765f; }
.day-heading { display: flex; align-items: center; justify-content: space-between; gap: 4px; min-height: 26px; }
.day-heading time { font-weight: 600; }
.today time { color: #125b49; }
.day-count { font-size: 0.7rem; color: #5f6c72; }
.day-people { list-style: none; margin: 0; padding: 0 3px 0 0; min-height: 0; overflow-y: auto; overscroll-behavior: contain; scrollbar-gutter: stable; }
.day-people:focus-visible { outline: 2px solid #16765f; outline-offset: 1px; }
.day-people li { display: flex; align-items: baseline; gap: 5px; margin-bottom: 5px; padding: 6px; border-radius: 5px; background: #edf1f5; font-size: 0.78rem; overflow-wrap: anywhere; }
.day-people li span { flex: 1; min-width: 0; }
.day-people li.own-leave { background: #d9f2e8; color: #125b49; }
.day-people strong { font-size: 0.65rem; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
@media (max-width: 700px) { .leave-calendar > header { align-items: flex-start; flex-direction: column; } }
</style>
