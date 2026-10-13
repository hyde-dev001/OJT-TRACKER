import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AboutView from '../views/AboutView.vue'
import HomeView from '../views/HomeView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import CompanyRegisterView from '../views/CompanyRegisterView.vue'
import CompanyInternsView from '../views/company/CompanyInternsView.vue'
import CompanyAccountView from '../views/company/CompanyAccountView.vue'
import CompanyAttendanceView from '../views/company/CompanyAttendanceView.vue'
import CompanyTasksView from '../views/company/CompanyTasksView.vue'
import CompanyRequirementsView from '../views/company/CompanyRequirementsView.vue'
import InternAccountView from '../views/intern/InternAccountView.vue'
import InternOverviewView from '../views/student/OverviewView.vue'
import InternWorkHoursView from '../views/intern/InternWorkHoursView.vue'
import InternTasksView from '../views/intern/InternTasksView.vue'
import InternRequirementsView from '../views/intern/InternRequirementsView.vue'
import NotFoundView from '../views/NotFoundView.vue'
import OverviewView from '../views/student/OverviewView.vue'
import StudentRequirementsView from '../views/student/RequirementsView.vue'
import StudentTasksView from '../views/student/TasksView.vue'
import WorkHoursView from '../views/student/WorkHoursView.vue'
import StudentProfileView from '../views/student/ProfileView.vue'
import { defaultWorkspacePath } from '../utils/accountNavigation'

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
    { path: '/register/company', name: 'company-register', component: CompanyRegisterView },
    {
      path: '/company/account',
      name: 'company-account',
      component: CompanyAccountView,
      meta: { requiresAuth: true, roles: ['company'] },
    },
    {
      path: '/company/interns',
      name: 'company-interns',
      component: CompanyInternsView,
      meta: { requiresAuth: true, roles: ['company'] },
    },
    {
      path: '/company/attendance',
      name: 'company-attendance',
      component: CompanyAttendanceView,
      meta: { requiresAuth: true, roles: ['company'] },
    },
    {
      path: '/company/tasks',
      name: 'company-tasks',
      component: CompanyTasksView,
      meta: { requiresAuth: true, roles: ['company'] },
    },
    {
      path: '/company/requirements',
      name: 'company-requirements',
      component: CompanyRequirementsView,
      meta: { requiresAuth: true, roles: ['company'] },
    },
    {
      path: '/intern/overview',
      name: 'intern-overview',
      component: InternOverviewView,
      props: { accountRole: 'intern' },
      meta: { requiresAuth: true, roles: ['intern'] },
    },
    {
      path: '/intern/account',
      name: 'intern-account',
      component: InternAccountView,
      meta: { requiresAuth: true, roles: ['intern'] },
    },
    {
      path: '/intern/work-hours',
      name: 'intern-work-hours',
      component: InternWorkHoursView,
      meta: { requiresAuth: true, roles: ['intern'] },
    },
    {
      path: '/intern/tasks',
      name: 'intern-tasks',
      component: InternTasksView,
      meta: { requiresAuth: true, roles: ['intern'] },
    },
    {
      path: '/intern/requirements',
      name: 'intern-requirements',
      component: InternRequirementsView,
      meta: { requiresAuth: true, roles: ['intern'] },
    },
    {
      path: '/student/overview',
      name: 'student-overview',
      component: OverviewView,
      meta: { requiresAuth: true, roles: ['student'] },
    },
    {
      path: '/student/work-hours',
      name: 'student-work-hours',
      component: WorkHoursView,
      meta: { requiresAuth: true, roles: ['student'] },
    },
    {
      path: '/student/tasks',
      name: 'student-tasks',
      component: StudentTasksView,
      meta: { requiresAuth: true, roles: ['student'] },
    },
    {
      path: '/student/requirements',
      name: 'student-requirements',
      component: StudentRequirementsView,
      meta: { requiresAuth: true, roles: ['student'] },
    },
    {
      path: '/student/profile',
      name: 'student-profile',
      component: StudentProfileView,
      meta: { requiresAuth: true, roles: ['student'] },
    },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.bootstrapped) {
    await auth.restore()
  }

  const role = auth.user?.role ?? 'student'
  const workspace = defaultWorkspacePath(role)

  if (role === 'intern' && auth.user?.must_change_password && to.path !== '/intern/account') {
    return { name: 'intern-account' }
  }

  if (to.name === 'home' && auth.isAuthenticated) {
    return workspace
  }

  if (['login', 'register', 'company-register'].includes(to.name) && auth.isAuthenticated) {
    return workspace
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.roles?.length && !to.meta.roles.includes(role)) {
    return workspace
  }
})

export default router
