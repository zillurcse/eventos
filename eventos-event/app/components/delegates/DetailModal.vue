<script setup lang="ts">
import type { Delegate } from '~/stores/delegates'

const props = defineProps<{ delegate: Delegate }>()

const store = useDelegatesStore()
const bookmarks = useBookmarksStore()
const auth = useAuthStore()
const site = useSiteStore()

const chatEnabled = computed(() => auth.isAuthed && site.navigation?.modules?.chat !== false)

const bioExpanded = ref(false)

onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') store.close()
}

const bookmarked = computed(() => bookmarks.isOn('delegate', props.delegate.id))

const bio = computed(() => (props.delegate.bio || '').replace(/<[^>]+>/g, '').trim())
const bioIsLong = computed(() => bio.value.length > 180)
const bioShown = computed(() =>
  bioExpanded.value || !bioIsLong.value ? bio.value : `${bio.value.slice(0, 180).trimEnd()}…`,
)

const socialIcons: Record<string, string> = {
  linkedin: 'M4 4h4v16H4zM6 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4M10 8h4v2a4 4 0 0 1 6 3v7h-4v-6a2 2 0 0 0-4 0v6h-4z',
  twitter: 'M22 5a8 8 0 0 1-2.3.6A4 4 0 0 0 21.4 3a8 8 0 0 1-2.5 1A4 4 0 0 0 12 7.5a11 11 0 0 1-8-4 4 4 0 0 0 1.2 5.3A4 4 0 0 1 3 8.3a4 4 0 0 0 3.2 4 4 4 0 0 1-1.8.1 4 4 0 0 0 3.7 2.8A8 8 0 0 1 2 18a11 11 0 0 0 18-8.5A6 6 0 0 0 22 5z',
  x: 'M22 5a8 8 0 0 1-2.3.6A4 4 0 0 0 21.4 3a8 8 0 0 1-2.5 1A4 4 0 0 0 12 7.5a11 11 0 0 1-8-4 4 4 0 0 0 1.2 5.3A4 4 0 0 1 3 8.3a4 4 0 0 0 3.2 4 4 4 0 0 1-1.8.1 4 4 0 0 0 3.7 2.8A8 8 0 0 1 2 18a11 11 0 0 0 18-8.5A6 6 0 0 0 22 5z',
  facebook: 'M14 9h3V5h-3a4 4 0 0 0-4 4v2H7v4h3v6h4v-6h3l1-4h-4V9a1 1 0 0 1 1-1z',
  instagram: 'M4 8a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6M17 6.5h.01',
  whatsapp: 'M12 3a9 9 0 0 0-7.8 13.5L3 21l4.6-1.2A9 9 0 1 0 12 3zM8.5 8.3c.2-.5.4-.5.6-.5h.5c.2 0 .4 0 .5.4l.7 1.7c0 .2 0 .3-.1.5l-.4.5c-.1.1-.2.3-.1.5.4.7 1 1.4 1.7 1.8.2.1.3.1.5-.1l.5-.5c.1-.1.3-.2.5-.1l1.6.8c.2.1.3.2.3.4v.6c0 .5-.6 1-1.1 1.1-1 .2-2.3 0-4-1.4-1.4-1.1-2.3-2.5-2.6-3.4-.2-.6-.2-1.2 0-1.6z',
  website: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18',
}
const socials = computed(() => Object.entries(props.delegate.social || {}).filter(([, v]) => v))

function openConnect() {
  store.openConnect(props.delegate, 'connect')
}
</script>

<template>
  <div class="overlay" @click.self="store.close()">
    <div class="modal-wrap">
      <button class="x" type="button" aria-label="Close" @click="store.close()">
        <svg viewBox="0 0 24 24">
          <path d="M6 6l12 12M18 6L6 18" />
        </svg>
      </button>

      <div class="modal" role="dialog" aria-modal="true" :aria-label="delegate.name || 'Delegate profile'">
        <header class="head">
          <div class="photo">
            <UserAvatar :src="delegate.avatar_url" :name="delegate.name" />
          </div>

          <div class="ident">
            <h2>{{ delegate.name }}</h2>
            <p v-if="delegate.job_title" class="role">{{ delegate.job_title }}</p>
            <p v-if="delegate.company" class="co">{{ delegate.company }}</p>
            <span v-if="delegate.is_featured" class="featured">
              <svg viewBox="0 0 24 24">
                <path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.4 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8z" />
              </svg>
              Featured
            </span>
          </div>
        </header>

        <div class="hacts">
          <button class="hact bm" :class="{ on: bookmarked }" type="button" :title="bookmarked ? 'Saved' : 'Save'"
            @click="bookmarks.toggle('delegate', delegate.id)">
            <svg viewBox="0 0 24 24">
              <path d="M6 3h12v18l-6-4-6 4z" />
            </svg>
          </button>
          <EventNotePopover v-if="auth.isAuthed" type="delegate" :id="delegate.id" block class="hact-note" />
          <button v-if="chatEnabled" class="hact chat" type="button" @click="openConnect">
            <svg viewBox="0 0 24 24"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" /></svg>
            Chat
          </button>
        </div>

        <div class="scroll">
          <div v-if="store.detailLoading && !bio && !socials.length" class="sload">Loading profile…</div>

          <template v-else-if="bio">
            <h3 class="sech">About</h3>
            <div class="bio">
              <p>{{ bioShown }}</p>
              <button v-if="bioIsLong" class="more" type="button" @click="bioExpanded = !bioExpanded">
                {{ bioExpanded ? '− Read less' : '+ Read more' }}
              </button>
            </div>
          </template>

          <div v-if="socials.length" class="socials">
            <a v-for="[k, v] in socials" :key="k" :href="v" target="_blank" rel="noopener" class="soc" :title="k">
              <svg viewBox="0 0 24 24">
                <path :d="socialIcons[k] || socialIcons.website" />
              </svg>
            </a>
          </div>

          <p v-if="!store.detailLoading && !bio && !socials.length" class="empty">
            No additional profile details yet.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, .5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  z-index: 60;
}

.modal-wrap {
  position: relative;
  width: 100%;
  max-width: 640px;
  max-height: 90vh;
}

.modal {
  position: relative;
  background: #fff;
  border-radius: 18px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(15, 23, 42, .28);
}

.x {
  position: absolute;
  top: -14px;
  right: -14px;
  z-index: 3;
  border: none;
  background: var(--brand-primary);
  width: 40px;
  height: 40px;
  border-radius: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6px 16px color-mix(in srgb, var(--brand-primary) 45%, transparent);
}

.x:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.x svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: #fff;
  stroke-width: 2.4;
  stroke-linecap: round;
}

.head {
  display: flex;
  gap: 20px;
  padding: 26px 26px 0;
  background: #fff;
}

.photo {
  width: 128px;
  flex: 0 0 auto;
  aspect-ratio: 4 / 5;
  border-radius: 8px;
  overflow: hidden;
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
}

.photo :deep(.ua) { border-radius: 8px; }

.ident {
  min-width: 0;
  flex: 1;
  padding-top: 4px;
}

.ident h2 {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 800;
  color: #1e293b;
}

.role {
  margin: 4px 0 0;
  font-size: .92rem;
  color: #64748b;
  line-height: 1.4;
}

.co {
  margin: 2px 0 0;
  font-size: .92rem;
  color: #64748b;
  line-height: 1.4;
}

.featured {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  color: var(--brand-primary);
  border-radius: 999px;
  padding: 6px 12px;
  font-size: .8rem;
  font-weight: 700;
}

.featured svg {
  width: 14px;
  height: 14px;
  fill: var(--brand-primary);
  stroke: none;
}

.hacts {
  display: flex;
  gap: 12px;
  padding: 24px 26px;
  border-bottom: 1px solid #eef0f3;
  margin-bottom: 4px;
}

.hact {
  flex: none;
  width: 52px;
  height: 48px;
  border-radius: 10px;
  border: 1.5px solid var(--brand-primary);
  background: #fff;
  color: var(--brand-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.hact svg {
  width: 19px;
  height: 19px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.hact.on {
  background: var(--brand-primary);
  color: #fff;
}

.hact.on svg { fill: currentColor; }

.hact.chat {
  flex: 1;
  gap: 10px;
  font: inherit;
  font-size: .92rem;
  font-weight: 700;
}

.hact-note {
  flex: 1;
  height: 48px !important;
  border-radius: 10px !important;
  border: 1.5px solid var(--brand-primary) !important;
  background: #fff !important;
  font-size: .92rem !important;
}

.scroll {
  padding: 18px 26px 26px;
  overflow-y: auto;
}

.sech {
  margin: 0 0 12px;
  font-size: 1.1rem;
  font-weight: 800;
  color: #1e293b;
}

.bio {
  margin-bottom: 22px;
  padding-bottom: 22px;
  border-bottom: 1px solid #eef0f3;
}

.bio p {
  margin: 0;
  color: #475569;
  font-size: .95rem;
  line-height: 1.65;
  white-space: pre-line;
}

.more {
  border: none;
  background: none;
  padding: 0;
  margin-top: 8px;
  color: var(--brand-primary);
  font: inherit;
  font-size: .82rem;
  font-weight: 700;
  letter-spacing: .3px;
  cursor: pointer;
}

.socials {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.soc {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: #f4f5f8;
  color: #475569;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.soc:hover { background: #eceef2; }

.soc svg {
  width: 19px;
  height: 19px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sload,
.empty {
  color: #94a3b8;
  font-size: .88rem;
  padding: 8px 0;
  margin: 0;
}

@media (max-width: 560px) {
  .head { flex-direction: column; }
  .photo { width: 108px; }
  .hacts { flex-wrap: wrap; }
  .x { top: 10px; right: 10px; }
}
</style>
