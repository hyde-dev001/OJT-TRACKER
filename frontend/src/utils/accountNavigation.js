const workspaces = {
  student: { path: '/student/overview', prefix: '/student/' },
  company: { path: '/company/interns', prefix: '/company/' },
  intern: { path: '/intern/account', prefix: '/intern/' },
}

export function defaultWorkspacePath(role) {
  return workspaces[role]?.path ?? workspaces.student.path
}

export function canRedirectWithinWorkspace(path, role) {
  const prefix = workspaces[role]?.prefix
  return typeof path === 'string' && Boolean(prefix) && path.startsWith(prefix)
}
