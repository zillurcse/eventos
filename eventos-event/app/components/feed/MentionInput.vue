<script setup lang="ts">
import type { FeedMention } from '~/utils/mentions'
import { activeMentionQuery, pruneMentions } from '~/utils/mentions'

const props = withDefaults(defineProps<{
  modelValue: string
  mentions?: FeedMention[]
  placeholder?: string
  rows?: number
  disabled?: boolean
  multiline?: boolean
}>(), {
  mentions: () => [],
  placeholder: '',
  rows: 2,
  disabled: false,
  multiline: true,
})

const emit = defineEmits<{
  'update:modelValue': [string]
  'update:mentions': [FeedMention[]]
  submit: []
}>()

interface Suggestion {
  id: string
  name: string
  company: string
  job_title: string
  avatar_url: string | null
}

const inputEl = ref<HTMLTextAreaElement | HTMLInputElement | null>(null)
const suggestions = ref<Suggestion[]>([])
const loading = ref(false)
const open = ref(false)
const activeIndex = ref(0)
const mentionStart = ref<number | null>(null)

let searchTimer: ReturnType<typeof setTimeout> | undefined
let searchSeq = 0

const showMenu = computed(() => open.value && (loading.value || suggestions.value.length > 0))

function caret(): number {
  return inputEl.value?.selectionStart ?? props.modelValue.length
}

function syncMentionState(text: string, pos: number) {
  const hit = activeMentionQuery(text, pos)
  if (!hit) {
    open.value = false
    mentionStart.value = null
    suggestions.value = []
    return
  }
  mentionStart.value = hit.start
  open.value = true
  activeIndex.value = 0
  scheduleSearch(hit.query)
}

function scheduleSearch(q: string) {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => void searchPeople(q), 220)
}

async function searchPeople(q: string) {
  const uuid = useSiteStore().event?.uuid
  if (!uuid) return
  const seq = ++searchSeq
  loading.value = true
  try {
    const api = useApi()
    const res = await api<{ data: Suggestion[] }>(`/events/${uuid}/delegates`, {
      query: { q: q || undefined, per_page: 8 },
    })
    if (seq !== searchSeq) return
    suggestions.value = res.data
    activeIndex.value = 0
  } catch {
    if (seq === searchSeq) suggestions.value = []
  } finally {
    if (seq === searchSeq) loading.value = false
  }
}

function onInput(e: Event) {
  const el = e.target as HTMLTextAreaElement | HTMLInputElement
  emit('update:modelValue', el.value)
  nextTick(() => syncMentionState(el.value, el.selectionStart ?? el.value.length))
}

function onSelect() {
  if (!inputEl.value) return
  syncMentionState(props.modelValue, inputEl.value.selectionStart ?? 0)
}

function onKeydown(e: KeyboardEvent) {
  if (showMenu.value && suggestions.value.length) {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      activeIndex.value = (activeIndex.value + 1) % suggestions.value.length
      return
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault()
      activeIndex.value = (activeIndex.value - 1 + suggestions.value.length) % suggestions.value.length
      return
    }
    if (e.key === 'Enter' || e.key === 'Tab') {
      e.preventDefault()
      pick(suggestions.value[activeIndex.value]!)
      return
    }
    if (e.key === 'Escape') {
      e.preventDefault()
      open.value = false
      return
    }
  }

  if (!props.multiline && e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    emit('submit')
  }
}

function pick(person: Suggestion) {
  if (mentionStart.value === null || !inputEl.value) return
  const start = mentionStart.value
  const end = caret()
  const insert = `@${person.name} `
  const next = props.modelValue.slice(0, start) + insert + props.modelValue.slice(end)
  emit('update:modelValue', next)

  const nextMentions = pruneMentions(next, [
    ...props.mentions.filter(m => m.id !== person.id),
    { id: person.id, name: person.name, avatar_url: person.avatar_url },
  ])
  emit('update:mentions', nextMentions)

  open.value = false
  mentionStart.value = null
  suggestions.value = []

  nextTick(() => {
    const el = inputEl.value
    if (!el) return
    const pos = start + insert.length
    el.focus()
    el.setSelectionRange(pos, pos)
  })
}

onBeforeUnmount(() => clearTimeout(searchTimer))

defineExpose({ focus: () => inputEl.value?.focus() })
</script>

<template>
  <div class="mention-input">
    <textarea
      v-if="multiline"
      ref="inputEl"
      :value="modelValue"
      :rows="rows"
      :placeholder="placeholder"
      :disabled="disabled"
      @input="onInput"
      @keydown="onKeydown"
      @click="onSelect"
      @keyup="onSelect"
    />
    <input
      v-else
      ref="inputEl"
      :value="modelValue"
      type="text"
      :placeholder="placeholder"
      :disabled="disabled"
      @input="onInput"
      @keydown="onKeydown"
      @click="onSelect"
      @keyup="onSelect"
    >

    <div v-if="showMenu" class="menu" role="listbox" aria-label="Mention suggestions">
      <div v-if="loading && !suggestions.length" class="note">Searching…</div>
      <button
        v-for="(p, i) in suggestions"
        :key="p.id"
        type="button"
        class="row"
        :class="{ on: i === activeIndex }"
        role="option"
        :aria-selected="i === activeIndex"
        @mousedown.prevent="pick(p)"
        @mouseenter="activeIndex = i"
      >
        <span class="av">
          <UserAvatar :src="p.avatar_url" :name="p.name" />
        </span>
        <span class="who">
          <span class="name">{{ p.name }}</span>
          <span v-if="p.job_title || p.company" class="sub">
            <template v-if="p.job_title">{{ p.job_title }}</template>
            <template v-if="p.job_title && p.company"> · </template>
            <template v-if="p.company">{{ p.company }}</template>
          </span>
        </span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.mention-input {
  position: relative;
  flex: 1;
  min-width: 0;
}

textarea,
input {
  width: 100%;
  box-sizing: border-box;
  border: none;
  border-radius: 8px;
  padding: 10px 0;
  font: inherit;
  font-size: .92rem;
  outline: none;
  color: #353942;
  background: transparent;
}

textarea {
  resize: vertical;
  min-height: 42px;
  padding-top: 0;
}

textarea::placeholder,
input::placeholder {
  color: #a3a5ab;
}

.menu {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 4px);
  z-index: 20;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 12px 32px rgba(15, 23, 42, .12);
  max-height: 260px;
  overflow-y: auto;
  padding: 6px;
}

.note {
  color: #94a3b8;
  font-size: .82rem;
  text-align: center;
  padding: 16px 10px;
}

.row {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  border: none;
  background: none;
  padding: 8px 10px;
  border-radius: 9px;
  cursor: pointer;
  text-align: left;
  font: inherit;
}

.row:hover,
.row.on {
  background: color-mix(in srgb, var(--brand-primary) 8%, #fff);
}

.av {
  flex: 0 0 auto;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  overflow: hidden;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--brand-primary);
  color: #fff;
  font-weight: 700;
  font-size: .72rem;
}

.who {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.name {
  font-weight: 700;
  color: #1e293b;
  font-size: .86rem;
}

.sub {
  color: #94a3b8;
  font-size: .74rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
