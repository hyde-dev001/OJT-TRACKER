import { defineStore } from 'pinia'

export const useAppStore = defineStore('app', {
  state: () => ({
    applicationName: 'OJT Progress Tracker',
  }),
})
