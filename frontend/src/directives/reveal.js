const revealObservers = new WeakMap()

function reveal(el, callback) {
  el.classList.add('motion-revealed')
  callback?.()
}

export default {
  mounted(el, binding) {
    el.classList.add('motion-reveal')

    if (typeof window === 'undefined' || !('IntersectionObserver' in window)) {
      reveal(el, binding.value)
      return
    }

    const observer = new IntersectionObserver(([entry]) => {
      if (!entry.isIntersecting) return

      reveal(el, binding.value)
      observer.disconnect()
      revealObservers.delete(el)
    }, { threshold: 0.15 })

    revealObservers.set(el, observer)
    observer.observe(el)
  },

  unmounted(el) {
    revealObservers.get(el)?.disconnect()
    revealObservers.delete(el)
  },
}
