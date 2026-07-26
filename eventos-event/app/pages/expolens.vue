<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ layout: 'event', middleware: 'auth' })

const store = useExpoLensStore()
const profile = useProfileStore()
const tab = ref<'mine' | 'all'>('mine')
const consentChecked = ref(false)
const lightbox = ref<string | null>(null)

onMounted(async () => {
  await Promise.all([
    profile.fetch(),
    store.fetchEnrollment(true),
    store.fetchMine(true),
    store.fetchAll(true),
  ])
})

const photos = computed(() => (tab.value === 'mine' ? store.myPhotos : store.photos))
const loading = computed(() => (tab.value === 'mine' ? store.loadingMine : store.loadingAll))
const needsConsent = computed(() => !store.enrollment?.consented)

async function enableMatching() {
  if (!consentChecked.value) {
    toast.error('Please accept face matching to continue.')
    return
  }
  try {
    await store.enroll({ consent: true })
    toast.success('Face matching enabled — we are enrolling your profile photo.')
    setTimeout(() => store.fetchEnrollment(true), 2500)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not enable face matching.')
  }
}

async function withdraw() {
  if (!confirm('Turn off face matching and delete your biometric face data for this event?')) return
  try {
    await store.withdraw()
    toast.success('Face matching turned off.')
  } catch {
    toast.error('Could not withdraw consent.')
  }
}

async function refreshMine() {
  await store.fetchMine(true)
  await store.fetchEnrollment(true)
}
</script>

<template>
  <div class="page">
    <header class="head">
      <div>
        <h1>ExpoLens</h1>
        <p>Find every event photo where you appear — solo or group.</p>
      </div>
      <div class="tabs">
        <button type="button" :class="{ active: tab === 'mine' }" @click="tab = 'mine'">My Photos</button>
        <button type="button" :class="{ active: tab === 'all' }" @click="tab = 'all'">All Photos</button>
      </div>
    </header>

    <section v-if="tab === 'mine'" class="consent card">
      <div v-if="needsConsent">
        <h2>Enable face matching</h2>
        <p>
          We compare your profile photo with event photos using on-platform AI.
          Embeddings stay inside this event and are never shared across events.
        </p>
        <label class="check">
          <input v-model="consentChecked" type="checkbox">
          <span>I consent to biometric face matching for this event.</span>
        </label>
        <button class="primary" :disabled="store.enrolling" @click="enableMatching">
          {{ store.enrolling ? 'Enabling…' : 'Find my photos' }}
        </button>
        <p v-if="!profile.data?.avatar_url" class="hint">
          Tip: add a clear face photo in Profile first for better matches.
        </p>
      </div>
      <div v-else class="status-row">
        <div>
          <strong>
            {{ store.enrollment?.enrolled ? 'Face matching is on' : 'Enrollment processing…' }}
          </strong>
          <p>
            Status: {{ store.enrollment?.status }}
            <span v-if="store.enrollment?.quality_score != null">
              · quality {{ Math.round(Number(store.enrollment.quality_score) * 100) }}%
            </span>
          </p>
        </div>
        <div class="actions">
          <button type="button" class="ghost" @click="refreshMine">Refresh</button>
          <button type="button" class="ghost danger" @click="withdraw">Turn off</button>
        </div>
      </div>
    </section>

    <div v-if="loading" class="empty">Loading photos…</div>
    <div v-else-if="photos.length" class="grid">
      <button
        v-for="photo in photos"
        :key="photo.id"
        type="button"
        class="shot"
        @click="lightbox = photo.url"
      >
        <img :src="photo.url" :alt="photo.caption || 'Event photo'" loading="lazy">
      </button>
    </div>
    <div v-else class="empty">
      <template v-if="tab === 'mine'">
        {{ needsConsent
          ? 'Enable face matching to unlock My Photos.'
          : 'No matches yet. Photos appear here once photographers upload and processing finishes.' }}
      </template>
      <template v-else>
        No approved ExpoLens photos have been published yet.
      </template>
    </div>

    <div v-if="lightbox" class="lightbox" @click.self="lightbox = null">
      <img :src="lightbox" alt="">
      <button type="button" class="close" @click="lightbox = null">Close</button>
    </div>
  </div>
</template>

<style scoped>
.page { max-width: 1100px; margin: 0 auto; padding: 24px 18px 48px; }
.head { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
.head h1 { margin: 0; font-size: 1.55rem; color: #0f172a; }
.head p { margin: 4px 0 0; color: #64748b; }
.tabs { display: flex; gap: 8px; }
.tabs button {
  border: 1px solid #e2e8f0; background: #fff; border-radius: 999px;
  padding: 8px 14px; font: inherit; font-weight: 600; color: #64748b; cursor: pointer;
}
.tabs button.active { border-color: var(--brand-primary); color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 8%, white); }
.card { border: 1px solid #e6e8ec; border-radius: 14px; background: #fff; padding: 16px 18px; margin-bottom: 18px; }
.card h2 { margin: 0 0 6px; font-size: 1.05rem; }
.card p { margin: 0; color: #64748b; line-height: 1.45; }
.check { display: flex; gap: 10px; align-items: flex-start; margin: 14px 0; font-weight: 500; color: #334155; }
.primary, .ghost {
  border: none; border-radius: 10px; padding: 10px 14px; font: inherit; font-weight: 700; cursor: pointer;
}
.primary { background: var(--brand-primary); color: #fff; }
.ghost { background: #f8fafc; color: #334155; border: 1px solid #e2e8f0; }
.ghost.danger { color: #b91c1c; }
.hint { margin-top: 10px; font-size: .86rem; }
.status-row { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center; }
.actions { display: flex; gap: 8px; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
.shot { border: none; padding: 0; border-radius: 12px; overflow: hidden; aspect-ratio: 1; cursor: pointer; background: #e2e8f0; }
.shot img { width: 100%; height: 100%; object-fit: cover; display: block; }
.empty { text-align: center; color: #94a3b8; padding: 48px 12px; }
.lightbox {
  position: fixed; inset: 0; z-index: 80; background: rgba(15, 23, 42, .9);
  display: grid; place-items: center; padding: 24px;
}
.lightbox img { max-width: min(92vw, 980px); max-height: 82vh; object-fit: contain; border-radius: 12px; }
.close {
  position: absolute; top: 18px; right: 18px; border: none; background: rgba(255,255,255,.15);
  color: #fff; border-radius: 999px; padding: 8px 14px; font: inherit; cursor: pointer;
}
</style>
