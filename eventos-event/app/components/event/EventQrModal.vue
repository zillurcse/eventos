<script setup lang="ts">
import { renderSVG } from 'uqr'

const emit = defineEmits<{ (e: 'close'): void }>()

const chat = useChatStore()
const auth = useAuthStore()

// The current attendee's participation uuid = the connect target. The chat
// store loads it (res.me) with the inbox; fetch it if we arrived before that.
onMounted(() => { if (!chat.me) chat.fetchInbox() })

const me = computed(() => chat.me)
const name = computed(() => chat.profile?.name || auth.user?.name || 'Me')
const subtitle = computed(() => [chat.profile?.job_title, chat.profile?.company].filter(Boolean).join(' · '))
const avatar = computed(() => chat.profile?.avatar_url || null)

/** Scannable link to my connect page, preserving the event subdomain in dev. */
const link = computed(() => {
  if (!import.meta.client || !me.value) return ''
  const origin = window.location.origin
  const host = window.location.hostname
  const isDevHost = host === 'localhost' || /^\d+\.\d+\.\d+\.\d+$/.test(host)
  const sub = useEventSubdomain()
  const url = `${origin}/connect/${me.value}`
  return isDevHost && sub ? `${url}?subdomain=${sub}` : url
})

const qr = computed(() => (link.value ? renderSVG(link.value, { border: 2 }) : ''))

const copied = ref(false)
async function copyLink() {
  if (!link.value) return
  try {
    await navigator.clipboard.writeText(link.value)
    copied.value = true
    setTimeout(() => (copied.value = false), 1600)
  } catch { /* clipboard blocked */ }
}

async function share() {
  if (!link.value) return
  try {
    if (navigator.share) await navigator.share({ title: `Connect with ${name.value}`, url: link.value })
    else await copyLink()
  } catch { /* dismissed */ }
}
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <div class="modal" role="dialog" aria-modal="true">
      <button class="x" type="button" aria-label="Close" @click="emit('close')">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>

      <div class="who">
        <span class="av">
          <UserAvatar :src="avatar" :name="name" />
        </span>
        <strong class="name">{{ name }}</strong>
        <span v-if="subtitle" class="sub">{{ subtitle }}</span>
      </div>

      <div class="qr-wrap">
        <div v-if="qr" class="qr" v-html="qr" />
        <div v-else class="qr loading">Generating…</div>
      </div>

      <p class="hint">Scan to connect with me</p>

      <div class="acts">
        <button class="btn ghost" type="button" @click="copyLink">{{ copied ? 'Copied!' : 'Copy link' }}</button>
        <button class="btn" type="button" @click="share">
          <svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4" /></svg>
          Share
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 80; }
.modal { position: relative; background: #fff; border-radius: 20px; width: 100%; max-width: 360px; padding: 30px 24px 24px; text-align: center; box-shadow: 0 20px 50px rgba(15,23,42,.28); }

.x { position: absolute; top: 14px; right: 14px; border: none; background: #f1f5f9; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.x svg { width: 15px; height: 15px; fill: none; stroke: #64748b; stroke-width: 2.2; stroke-linecap: round; }

.who { display: flex; flex-direction: column; align-items: center; gap: 4px; margin-bottom: 18px; }
.av { width: 60px; height: 60px; border-radius: 50%; background: var(--brand-primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; overflow: hidden; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.name { font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-top: 6px; }
.sub { font-size: .82rem; color: #64748b; }

.qr-wrap { display: flex; justify-content: center; }
.qr { width: 220px; height: 220px; padding: 12px; border: 1px solid #eef0f3; border-radius: 16px; background: #fff; }
.qr :deep(svg) { width: 100%; height: 100%; display: block; }
.qr.loading { display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: .85rem; }

.hint { margin: 14px 0 18px; color: #475569; font-size: .9rem; font-weight: 600; }

.acts { display: flex; gap: 10px; }
.btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 7px; border: none; border-radius: 12px; padding: 12px; font: inherit; font-size: .88rem; font-weight: 700; cursor: pointer; background: var(--brand-primary); color: #fff; }
.btn:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn.ghost { background: #f1f5f9; color: #475569; }
.btn.ghost:hover { background: #e7ebf0; }
.btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
</style>
