import { createApp } from 'vue'
import './assets/main.css'
import App from './App.vue'
import router from './router'
import { createPinia } from 'pinia'
import { useAuthStore } from './stores/auth'
import { initializeTheme } from './composables/useTheme'

initializeTheme()

const pinia = createPinia()
await useAuthStore(pinia).restore()

const app = createApp(App)

app
  .use(pinia)
  .use(router)
  .mount('#app')
