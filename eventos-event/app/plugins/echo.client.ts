import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

/**
 * Laravel Echo wired to the Reverb WebSocket server for live feed updates.
 * Connects to the published Reverb host port (localhost:8081 in dev).
 */
export default defineNuxtPlugin(() => {
  const reverb = useRuntimeConfig().public.reverb as {
    key: string; host: string; port: number; scheme: string
  }

  ;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher

  const echo = new Echo({
    broadcaster: 'reverb',
    key: reverb.key,
    wsHost: reverb.host,
    wsPort: reverb.port,
    wssPort: reverb.port,
    forceTLS: reverb.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
  })

  return { provide: { echo } }
})
