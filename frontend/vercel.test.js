import { describe, expect, it } from 'vitest'
import vercelConfig from './vercel.json'

describe('Vercel API proxy', () => {
  it('proxies API and Sanctum requests before the SPA fallback', () => {
    expect(vercelConfig.rewrites.slice(0, 2)).toEqual([
      {
        source: '/api/:path*',
        destination: 'https://ojt-tracker-trws.onrender.com/api/:path*',
      },
      {
        source: '/sanctum/:path*',
        destination: 'https://ojt-tracker-trws.onrender.com/sanctum/:path*',
      },
    ])
    expect(vercelConfig.rewrites.at(-1)).toEqual({
      source: '/(.*)',
      destination: '/index.html',
    })
  })
})
