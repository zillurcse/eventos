<script setup lang="ts">
/**
 * Full-page email editor. Splitting this out of the gallery gives every
 * template a real URL, so an in-progress design survives a refresh, can be
 * shared with a colleague, and the browser's back button behaves.
 *
 * `templateId` of `new` is the create case — once the first save returns a
 * uuid, the URL is rewritten in place so a refresh reopens the saved draft
 * rather than a blank canvas.
 */
import type { Block, EmailSettings } from '../../../../../../composables/useEmailBlocks'

definePageMeta({ middleware: 'organizer', layout: false })

interface TemplateDto {
  id: string
  name: string
  key?: string | null
  category?: string | null
  subject?: string | null
  preheader?: string | null
  from_name?: string | null
  from_email?: string | null
  reply_to?: string | null
  status?: string
  blocks?: Block[]
  settings?: Partial<EmailSettings>
}

const route = useRoute()
const router = useRouter()
const api = useApi()

const eventId = route.params.id as string
const templateId = computed(() => route.params.templateId as string)
const isNew = computed(() => templateId.value === 'new')

const listRoute = `/org/events/${eventId}/mail/email-builder`

const template = ref<TemplateDto | null>(null)
const loading = ref(!isNew.value)
const error = ref('')

/**
 * Remount key for the editor. The editor reads `template` only when it sets up,
 * so navigating between two templates has to give it a fresh instance. It is
 * bumped by `load()` alone — deliberately *not* derived from the route, because
 * the `/new` → uuid rewrite below must not remount and throw away the session.
 */
const docKey = ref(0)

/** Set while we rewrite our own URL, so the route watcher ignores that change. */
let selfNavigatedTo: string | null = null

async function load() {
  if (isNew.value) {
    template.value = null
    loading.value = false
    docKey.value++
    return
  }

  loading.value = true
  error.value = ''
  try {
    template.value = (await api<{ data: TemplateDto }>(`/email-templates/${templateId.value}`)).data
    docKey.value++
  } catch (e: any) {
    error.value = e?.response?.status === 404
      ? 'That template no longer exists.'
      : (e?.data?.message || 'Could not load this template.')
  } finally {
    loading.value = false
  }
}

/**
 * Swap `/new` for the real uuid after the first save, so a refresh reopens the
 * saved draft. `replace` rather than `push` keeps Back pointing at the gallery
 * instead of a dead `/new` URL.
 */
function onSaved(saved: TemplateDto) {
  if (isNew.value && saved.id) {
    selfNavigatedTo = saved.id
    template.value = saved
    router.replace(`${listRoute}/${saved.id}`)
  }
}

onMounted(load)

// Moving between templates without leaving the route (browser Back/Forward)
// must reload the document — but our own uuid rewrite is not a navigation.
watch(templateId, (id) => {
  if (id === selfNavigatedTo) { selfNavigatedTo = null; return }
  selfNavigatedTo = null
  load()
})
</script>

<template>
  <div>
    <div v-if="loading" class="fixed inset-0 grid place-items-center bg-[#eef0f4]">
      <p class="muted">Loading template…</p>
    </div>

    <div v-else-if="error" class="fixed inset-0 grid place-items-center bg-[#eef0f4]">
      <div class="card text-center max-w-[380px]">
        <div class="text-3xl mb-2">✉️</div>
        <h3 class="m-0 mb-1">Can't open this template</h3>
        <p class="muted text-[.88rem] mb-4">{{ error }}</p>
        <NuxtLink :to="listRoute" class="btn">Back to templates</NuxtLink>
      </div>
    </div>

    <MailEmailEditor
      v-else
      :key="docKey"
      :event-id="eventId"
      :template="template"
      @saved="onSaved"
      @close="router.push(listRoute)"
    />
  </div>
</template>
