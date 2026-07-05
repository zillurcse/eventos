<script setup lang="ts">
export interface LoginConfig {
  type:        string
  banner_url:  string | null
  video_url:   string
  website_url: string
}

const props = defineProps<{ login: LoginConfig }>()
const emit  = defineEmits<{ (e: 'update', v: Partial<LoginConfig>): void }>()

const TYPES = [
  {
    value: 'banner',
    label: 'Banner image',
    icon: 'M2 7h20v14a1 1 0 01-1 1H3a1 1 0 01-1-1V7z M2 7l10 8 10-8',
  },
  {
    value: 'video',
    label: 'YouTube video',
    icon: 'M15 12l-5 3.5V8.5L15 12z M22 12c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2s10 4.477 10 10z',
  },
  {
    value: 'website',
    label: 'Website URL',
    icon: 'M12 2a10 10 0 100 20A10 10 0 0012 2z M2 12h20 M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20',
  },
]

function pathsFor(icon: string) {
  return icon.split(' M').map((s, i) => (i ? 'M' + s : s))
}

const drawerOpen = ref(false)
const draft = reactive<LoginConfig>({ type: 'banner', banner_url: null, video_url: '', website_url: '' })

function openDrawer() {
  Object.assign(draft, props.login)
  drawerOpen.value = true
}

function onBannerChange(v: string | string[] | null) {
  draft.banner_url = Array.isArray(v) ? v[0] ?? null : v
}

function save() {
  emit('update', {
    banner_url:  draft.banner_url,
    video_url:   draft.video_url,
    website_url: draft.website_url,
  })
  drawerOpen.value = false
}

const drawerTitle = computed(() =>
  props.login.type === 'banner' ? 'Login Banner' : props.login.type === 'video' ? 'Login Video' : 'Login Website URL',
)
</script>

<template>
  <div class="card">
    <!-- Section header -->
    <div class="flex items-start justify-between gap-4 mb-1.5">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
        </div>
        <div>
          <h2 class="mb-0!">Login Page Design</h2>
          <p class="text-[.8rem] text-muted mt-0.5">Brand-wise customisation of the sign-in page for your event.</p>
        </div>
      </div>
      <button class="btn sm ghost shrink-0" @click="openDrawer">Customise</button>
    </div>

    <!-- Design type pills (selected on the page; the sidebar edits the matching field) -->
    <div class="flex gap-2 mt-4 mb-4">
      <button
        v-for="t in TYPES" :key="t.value" type="button"
        class="flex items-center gap-2 px-4 py-2.5 rounded-xl border-[1.5px] cursor-pointer font-semibold text-[.85rem] transition-all duration-150"
        :class="login.type === t.value
          ? 'border-brand bg-brand-soft text-brand'
          : 'border-line bg-white text-muted hover:border-[#c7c2f5] hover:text-brand'"
        @click="emit('update', { type: t.value })"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path v-for="(p, i) in pathsFor(t.icon)" :key="i" :d="p" />
        </svg>
        {{ t.label }}
      </button>
    </div>

    <!-- Current value preview -->
    <div class="flex items-center gap-3">
      <template v-if="login.type === 'banner'">
        <div v-if="login.banner_url" class="w-24 rounded-lg overflow-hidden border border-line" :style="{ aspectRatio: '1796 / 1390' }">
          <img :src="login.banner_url" alt="Login banner" class="w-full h-full object-cover">
        </div>
        <span v-else class="text-[.85rem] text-muted">No banner set yet — use Customise to add one.</span>
      </template>
      <span v-else-if="login.type === 'video'" class="text-[.85rem] text-muted truncate">{{ login.video_url || 'No video URL set yet — use Customise to add one.' }}</span>
      <span v-else class="text-[.85rem] text-muted truncate">{{ login.website_url || 'No website URL set yet — use Customise to add one.' }}</span>
    </div>

    <!-- Customise sidebar: only the field for the selected design type -->
    <Drawer v-if="drawerOpen" :title="drawerTitle" @close="drawerOpen = false">
      <div class="flex flex-col gap-5">
        <div v-if="login.type === 'banner'">
          <label class="block mb-2">Login banner image</label>
          <ImageField
            :model-value="draft.banner_url"
            :aspect="1796 / 1390"
            :output-width="1796"
            :output-height="1390"
            collection="banner"
            hint="1796×1390px recommended"
            card-width="220px"
            @update:model-value="onBannerChange"
          />
        </div>
        <AppInput
          v-else-if="login.type === 'video'"
          v-model="draft.video_url"
          label="YouTube video URL"
          placeholder="https://www.youtube.com/watch?v=…"
        />
        <AppInput
          v-else
          v-model="draft.website_url"
          label="Website URL"
          placeholder="https://yourcompany.com"
        />

        <div class="flex justify-end gap-2.5 mt-2">
          <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
          <button class="btn" @click="save">Save changes</button>
        </div>
      </div>
    </Drawer>
  </div>
</template>
