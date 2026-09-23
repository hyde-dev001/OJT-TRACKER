const PHILIPPINE_TIME_ZONE = 'Asia/Manila'

export function todayInPhilippines(value = new Date()) {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: PHILIPPINE_TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(value)
  const get = (type) => parts.find((part) => part.type === type)?.value
  return get('year') + '-' + get('month') + '-' + get('day')
}

export function internshipDateBounds(internship, capAtToday = true) {
  const today = todayInPhilippines()
  const endDate = internship?.end_date

  return {
    min: internship?.start_date ?? undefined,
    max: capAtToday
      ? endDate && endDate < today ? endDate : today
      : endDate ?? undefined,
  }
}
