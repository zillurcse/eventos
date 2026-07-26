/**
 * Laravel Echo → Reverb WebSocket for live feed / chat.
 *
 * Echo + Pusher are large; we defer the import and socket until the first
 * realtime subscriber needs them (feed, chat, exhibitor contact) — not on
 * every microsite page boot.
 */
let echoInstance: any = null
let echoPromise: Promise<any> | null = null

async function createEcho() {
  const [{ default: Echo }, { default: Pusher }] = await Promise.all([
    import('laravel-echo'),
    import('pusher-js'),
  ])

  const reverb = useRuntimeConfig().public.reverb as {
    key: string; host: string; port: number; scheme: string
  }

  ;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: reverb.key,
    wsHost: reverb.host,
    wsPort: reverb.port,
    wssPort: reverb.port,
    forceTLS: reverb.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
  })
  return echoInstance
}

export default defineNuxtPlugin(() => {
  const ensureEcho = () => {
    if (!echoPromise) echoPromise = createEcho()
    return echoPromise
  }

  /** Sync accessor — null until ensureEcho() has resolved at least once. */
  const getEcho = () => echoInstance

  return { provide: { ensureEcho, getEcho } }
})
