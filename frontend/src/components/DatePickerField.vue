<script setup>
import { computed, ref } from 'vue'
import AppModal from './AppModal.vue'
import { todayInPhilippines } from '../utils/dateBounds'
import { formatDate } from '../utils/formatters'

const props = defineProps({
  id: { type: String, required: true },
  modelValue: { type: String, default: '' },
  min: { type: String, default: '' },
  max: { type: String, default: '' },
  allowedWeekdays: { type: Array, default: () => [] },
  allowCurrentValue: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
  describedby: { type: String, default: undefined },
  title: { type: String, default: 'Choose a date' },
  description: { type: String, default: 'Select a date from the calendar.' },
  placeholder: { type: String, default: 'Choose a date' },
  testIdPrefix: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])
const open = ref(false)
const calendarMonth = ref('')
const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const testPrefix = computed(() => props.testIdPrefix || props.id)

const calendarMonthLabel = computed(() => {
  if (!calendarMonth.value) return ''

  const [year, month] = calendarMonth.value.split('-').map(Number)
  return new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(new Date(year, month - 1, 1))
})

const canGoPreviousMonth = computed(() => Boolean(calendarMonth.value))
const canGoNextMonth = computed(() => Boolean(calendarMonth.value))

const dateInputValue = (date) => [
  date.getFullYear(),
  String(date.getMonth() + 1).padStart(2, '0'),
  String(date.getDate()).padStart(2, '0'),
].join('-')

const weekdayNumber = (value) => {
  const [year, month, day] = value.split('-').map(Number)
  const weekday = new Date(year, month - 1, day).getDay()
  return weekday === 0 ? 7 : weekday
}

const isSelectable = (value) => {
  const withinBounds = (!props.min || value >= props.min) && (!props.max || value <= props.max)
  if (!withinBounds) return false
  if (props.allowedWeekdays.length && !props.allowedWeekdays.map(Number).includes(weekdayNumber(value))) {
    return props.allowCurrentValue && value === props.modelValue
  }
  return true
}

const calendarDays = computed(() => {
  if (!calendarMonth.value) return []

  const [year, month] = calendarMonth.value.split('-').map(Number)
  const firstDay = new Date(year, month - 1, 1)
  const leadingDays = (firstDay.getDay() + 6) % 7
  const daysInMonth = new Date(year, month, 0).getDate()
  const cellCount = Math.ceil((leadingDays + daysInMonth) / 7) * 7

  return Array.from({ length: cellCount }, (_, index) => {
    const date = new Date(year, month - 1, index - leadingDays + 1)
    const value = dateInputValue(date)
    const currentMonth = date.getFullYear() === year && date.getMonth() === month - 1
    const selectable = currentMonth && isSelectable(value)
    const dayLabel = new Intl.DateTimeFormat('en-US', {
      weekday: 'long',
      month: 'long',
      day: 'numeric',
      year: 'numeric',
    }).format(date)

    return {
      value,
      day: date.getDate(),
      currentMonth,
      selectable,
      selected: value === props.modelValue,
      label: selectable || !currentMonth || !props.allowedWeekdays.length
        ? dayLabel
        : `${dayLabel} (unavailable)`,
    }
  })
})

const openPicker = () => {
  calendarMonth.value = (props.modelValue || props.min || todayInPhilippines()).slice(0, 7)
  open.value = true
}

const closePicker = () => {
  open.value = false
}

const changeMonth = (offset) => {
  if ((offset < 0 && !canGoPreviousMonth.value) || (offset > 0 && !canGoNextMonth.value)) return

  const [year, month] = calendarMonth.value.split('-').map(Number)
  const nextMonth = new Date(year, month - 1 + offset, 1)
  calendarMonth.value = `${nextMonth.getFullYear()}-${String(nextMonth.getMonth() + 1).padStart(2, '0')}`
}

const selectDate = (day) => {
  if (!day.selectable) return

  emit('update:modelValue', day.value)
  closePicker()
}
</script>

<template>
  <div>
    <div class="relative mt-1">
      <input
        :id="id"
        type="text"
        readonly
        :value="modelValue ? formatDate(modelValue) : ''"
        :placeholder="placeholder"
        :required="required"
        :data-min="min || undefined"
        :data-max="max || undefined"
        :aria-invalid="invalid"
        :aria-describedby="describedby"
        aria-haspopup="dialog"
        class="block min-h-10 w-full cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 pr-28 text-slate-900 outline-none placeholder:text-slate-400 focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
        @click="openPicker"
        @keydown.enter.prevent="openPicker"
        @keydown.space.prevent="openPicker"
      />
      <button
        type="button"
        :data-testid="`open-${testPrefix}-picker`"
        class="absolute inset-y-0 right-1 my-1 rounded-md px-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-950"
        aria-label="Open date calendar"
        @click="openPicker"
      >
        Choose date
      </button>
    </div>

    <AppModal :open="open" :title="title" :description="description" :close-on-backdrop="false" @close="closePicker">
      <div :data-testid="`${testPrefix}-calendar`">
        <div class="flex items-center justify-between gap-3">
          <button type="button" class="app-button app-button--secondary" :data-testid="`${testPrefix}-previous`" :disabled="!canGoPreviousMonth" aria-label="Previous month" @click="changeMonth(-1)">Previous</button>
          <p class="text-sm font-semibold text-slate-950" aria-live="polite">{{ calendarMonthLabel }}</p>
          <button type="button" class="app-button app-button--secondary" :data-testid="`${testPrefix}-next`" :disabled="!canGoNextMonth" aria-label="Next month" @click="changeMonth(1)">Next</button>
        </div>
        <div class="mt-5 grid grid-cols-7 gap-2 text-center text-xs font-semibold text-slate-500" aria-hidden="true">
          <span v-for="weekday in weekdays" :key="weekday">{{ weekday }}</span>
        </div>
        <div class="mt-2 grid grid-cols-7 gap-2" role="grid" :aria-label="title">
          <button
            v-for="day in calendarDays"
            :key="day.value"
            type="button"
            :data-testid="`calendar-day-${day.value}`"
            :disabled="!day.selectable"
            :aria-label="day.label"
            :aria-current="day.selected ? 'date' : undefined"
            :class="[
              'min-h-10 rounded-lg border text-sm font-semibold transition-colors',
              day.currentMonth ? 'border-slate-300' : 'border-transparent text-slate-300',
              day.selectable ? 'bg-white text-slate-800 hover:border-slate-950 hover:bg-slate-50' : 'cursor-not-allowed opacity-50',
              day.selected ? 'border-slate-950 bg-slate-950 text-white hover:bg-slate-950' : '',
            ]"
            @click="selectDate(day)"
          >
            {{ day.day }}
          </button>
        </div>
        <p class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs leading-5 text-slate-600">Dates outside the allowed range{{ allowedWeekdays.length ? ' or OJT work schedule' : '' }} are disabled.</p>
      </div>
      <template #footer>
        <button type="button" class="app-button app-button--secondary" :data-testid="`cancel-${testPrefix}-picker`" @click="closePicker">Cancel</button>
      </template>
    </AppModal>
  </div>
</template>
