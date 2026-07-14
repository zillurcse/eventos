<script setup lang="ts">
/**
 * The organizer's welcome video (admin › Navigation & Menu › Welcome Video),
 * shown as a modal over the app shell.
 *
 * Two independent triggers, one video:
 *   - show_after_login — greet them once when they arrive, wherever they land;
 *   - show_on_home     — greet them when they reach the reception page.
 *
 * Either way it plays *once*. A welcome that reappears on every page load stops
 * being a welcome and becomes a pop-up, so a "seen" flag is kept per event in
 * localStorage — and cleared on logout (see the auth store), which is what makes
 * "after login" mean the next login rather than never again.
 *
 * The flag is per event: an attendee at two events on the same platform should
 * be welcomed to each of them.
 */
const site = useSiteStore()
const auth = useAuthStore()
const route = useRoute()

const open = ref(false)

const video = computed(() => site.welcomeVideo)
const isFile = computed(() => video.value?.type === 'uploaded')

const seenKey = computed(() => `eventos:welcome_seen:${site.event?.uuid ?? 'unknown'}`)

function alreadySeen(): boolean {
  if (!import.meta.client) return true
  try {
    return localStorage.getItem(seenKey.value) === '1'
  } catch {
    // Private mode / storage blocked: better to show it once per page than to
    // crash the shell over a nice-to-have.
    return false
  }
}

function dismiss() {
  open.value = false
  try {
    localStorage.setItem(seenKey.value, '1')
  } catch { /* storage blocked — it just shows again next time */ }
}

/** Is this the moment to greet them? */
function shouldShow(): boolean {
  const v = video.value
  // No video, an unparseable link, or nobody signed in yet → nothing to do.
  if (!v?.embed_url || !auth.user || !site.event) return false
  if (alreadySeen()) return false

  if (v.show_after_login) return true
  if (v.show_on_home) return route.path === '/reception'

  return false
}

function evaluate() {
  if (shouldShow()) open.value = true
}

// The site config, the user and the route all arrive at different times (the
// payload is fetched at boot, auth is restored after it) — so re-check whenever
// any of them lands rather than only on mount.
watch(
  () => [site.site, auth.user, route.path],
  () => evaluate(),
  { immediate: true },
)

onMounted(evaluate)
</script>

<template>
  <div v-if="open && video?.embed_url" class="overlay" @click.self="dismiss">
    <div class="modal" role="dialog" aria-modal="true" aria-label="Welcome video">
      <button class="x" type="button" aria-label="Close" @click="dismiss">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>

      <div class="frame">
        <!-- An upload is a plain file; YouTube/Vimeo need their player. -->
        <video
          v-if="isFile"
          :src="video.embed_url"
          controls
          playsinline
          class="player"
        />
        <iframe
          v-else
          :src="video.embed_url"
          class="player"
          title="Welcome video"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen
        />
      </div>

      <div class="foot">
        <button class="btn" type="button" @click="dismiss">Continue to the event</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.62); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 90; }
.modal { position: relative; background: #fff; border-radius: 18px; width: 100%; max-width: 860px; overflow: hidden; box-shadow: 0 24px 60px rgba(15,23,42,.34); }

.x { position: absolute; top: 12px; right: 12px; z-index: 2; border: none; background: rgba(15,23,42,.55); width: 34px; height: 34px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.x svg { width: 16px; height: 16px; fill: none; stroke: #fff; stroke-width: 2.2; stroke-linecap: round; }
.x:hover { background: rgba(15,23,42,.75); }

/* 16:9, so the player never letterboxes itself against a guessed height. */
.frame { position: relative; width: 100%; aspect-ratio: 16 / 9; background: #000; }
.player { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; display: block; }

.foot { display: flex; justify-content: flex-end; padding: 14px 16px; }
.btn { border: none; border-radius: 11px; background: var(--brand-primary); color: #fff; font: inherit; font-size: .9rem; font-weight: 700; padding: 11px 18px; cursor: pointer; }
.btn:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
</style>
