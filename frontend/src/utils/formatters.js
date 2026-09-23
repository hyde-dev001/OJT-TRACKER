const dateFormatter = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
const timeFormatter = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' })

export function formatDate(value) {
  if (!value) return '—'
  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)
  return Number.isNaN(date.getTime()) ? String(value) : dateFormatter.format(date)
}

export function formatTime(value) {
  if (!value) return '—'
  const match = String(value).match(/^(\d{1,2}):(\d{2})/)
  if (!match) return String(value)
  const date = new Date(1970, 0, 1, Number(match[1]), Number(match[2]))
  return timeFormatter.format(date)
}

export function formatDuration(value) {
  const minutes = Math.max(0, Math.floor(Number(value) || 0))
  const hours = Math.floor(minutes / 60)
  const remainder = minutes % 60
  if (hours && remainder) return `${hours}h ${remainder}m`
  if (hours) return `${hours}h`
  return `${remainder}m`
}
