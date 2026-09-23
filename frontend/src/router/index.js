import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AboutView from '../views/AboutView.vue'
import HomeView from '../views/HomeView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import NotFoundView from '../views/NotFoundView.vue'
import OverviewView from '../views/student/OverviewView.vue'
import StudentRequirementsView from '../views/student/RequirementsView.vue'
import StudentTasksView from '../views/student/TasksView.vue'
import WorkHoursView from '../views/student/WorkHoursView.vue'
import StudentProfileView from '../views/student/ProfileView.vue'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.hash) {
      const reducedMotion = typeof window !== 'undefined'
        && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
      return { el: to.hash, behavior: reducedMotion ? 'auto' : 'smooth' }
    }
    return { top: 0 }
  },
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/about', name: 'about', component: AboutView },
    { path: '/login', name: 'login', component: LoginView },
    { path: '/register', name: 'register', component: RegisterView },
    {
      path: '/student/overview',
      name: 'student-overview',
      component: OverviewView,
      meta: { requiresAuth: true },
    },
    {
      path: '/student/work-hours',
      name: 'student-work-hours',
      component: WorkHoursView,
      meta: { requiresAuth: true },
    },
    {
      path: '/student/tasks',
      name: 'student-tasks',
      component: StudentTasksView,
      meta: { requiresAuth: true },
    },
    {
      path: '/student/requirements',
      name: 'student-requirements',
      component: StudentRequirementsView,
      meta: { requiresAuth: true },
    },
    {
      path: '/student/profile',
      name: 'student-profile',
      component: StudentProfileView,
      meta: { requiresAuth: true },
    },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.bootstrapped) {
    await auth.restore()
  }

  if (to.name === 'home' && auth.isAuthenticated) {
    return { name: 'student-overview' }
  }

  if (['login', 'register'].includes(to.name) && auth.isAuthenticated) {
    return '/student/overview'
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
})

export default router
