<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface BlogPost {
  id: string
  title: string
  slug: string
  excerpt: string | null
  body: string | null
  status: 'draft' | 'published'
  cover_file_id: number | null
  cover_url: string | null
  published_at: string | null
  created_at: string | null
  updated_at: string | null
}

interface DraftShape {
  title: string
  excerpt: string
  body: string
  status: 'draft' | 'published'
  cover_file_id: number | null
  cover_url: string | null
}

const posts = ref<BlogPost[]>([])
const search = ref('')
const drawerOpen = ref(false)
const editingId = ref<string | null>(null)
const saving = ref(false)
const error = ref('')

function freshDraft(): DraftShape {
  return { title: '', excerpt: '', body: '', status: 'draft', cover_file_id: null, cover_url: null }
}

const draft = reactive<DraftShape>(freshDraft())

const columns = [
  { key: 'post', label: 'Post' },
  { key: 'status', label: 'Status' },
  { key: 'published', label: 'Published', align: 'right' as const },
]

function searchText(p: BlogPost) {
  return `${p.title} ${p.excerpt ?? ''}`
}

function fmtDate(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

async function load() {
  try {
    const res = await api<{ data: BlogPost[] }>(`/events/${id}/blog-posts`)
    posts.value = res.data
  } catch { /* */ }
}

function openAdd() {
  Object.assign(draft, freshDraft())
  editingId.value = null
  error.value = ''
  drawerOpen.value = true
}

function openEdit(p: BlogPost) {
  Object.assign(draft, {
    title: p.title,
    excerpt: p.excerpt ?? '',
    body: p.body ?? '',
    status: p.status,
    cover_file_id: p.cover_file_id,
    cover_url: p.cover_url,
  })
  editingId.value = p.id
  error.value = ''
  drawerOpen.value = true
}

async function saveDraft() {
  if (!draft.title.trim()) return
  error.value = ''
  saving.value = true
  try {
    const payload = {
      title: draft.title.trim(),
      excerpt: draft.excerpt.trim() || null,
      body: draft.body,
      status: draft.status,
      cover_file_id: draft.cover_file_id,
    }
    if (editingId.value) {
      const res = await api<{ data: BlogPost }>(`/events/${id}/blog-posts/${editingId.value}`, { method: 'PUT', body: payload })
      const i = posts.value.findIndex((p: BlogPost) => p.id === editingId.value)
      if (i >= 0) posts.value[i] = res.data
    } else {
      const res = await api<{ data: BlogPost }>(`/events/${id}/blog-posts`, { method: 'POST', body: payload })
      posts.value.unshift(res.data)
    }
    drawerOpen.value = false
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save blog post.'
  } finally {
    saving.value = false
  }
}

async function removePost(p: BlogPost) {
  if (!confirm(`Remove blog post "${p.title}"?`)) return
  try {
    await api(`/events/${id}/blog-posts/${p.id}`, { method: 'DELETE' })
    posts.value = posts.value.filter((x: BlogPost) => x.id !== p.id)
  } catch { /* */ }
}

async function togglePublish(p: BlogPost) {
  const next = p.status === 'published' ? 'draft' : 'published'
  try {
    const res = await api<{ data: BlogPost }>(`/events/${id}/blog-posts/${p.id}`, { method: 'PUT', body: { status: next } })
    const i = posts.value.findIndex((x: BlogPost) => x.id === p.id)
    if (i >= 0) posts.value[i] = res.data
  } catch { /* */ }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="section-title m-0">Blog</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Write articles and news shown on your event website.</p>
    </div>

    <div class="card">
      <div class="flex items-center justify-between gap-4 mb-4">
        <SearchInput v-model="search" placeholder="Search posts…" class="max-w-80" />
        <button class="btn" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          NEW POST
        </button>
      </div>

      <DataTable
        :items="posts"
        :columns="columns"
        :search="search"
        :search-text="searchText"
        row-key="id"
        storage-key="content-hub-blog"
      >
        <template #cell-post="{ row }">
          <div class="flex items-center gap-3">
            <div class="w-14 h-10 rounded-lg overflow-hidden shrink-0 bg-[#f3f4f6] border border-line flex items-center justify-center text-muted text-[.7rem]">
              <img v-if="row.cover_url" :src="row.cover_url" :alt="row.title" class="w-full h-full object-cover">
              <span v-else>No img</span>
            </div>
            <div class="min-w-0">
              <div class="font-semibold text-ink leading-tight truncate max-w-[360px]">{{ row.title }}</div>
              <div class="muted text-[.8rem] truncate max-w-[360px]">{{ row.excerpt || '—' }}</div>
            </div>
          </div>
        </template>

        <template #cell-status="{ row }">
          <button
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[.75rem] font-semibold cursor-pointer border-0"
            :class="row.status === 'published' ? 'bg-green-50 text-green-700' : 'bg-[#f1f1f5] text-muted'"
            :title="row.status === 'published' ? 'Click to unpublish' : 'Click to publish'"
            @click="togglePublish(row)"
          >{{ row.status === 'published' ? 'Published' : 'Draft' }}</button>
        </template>

        <template #cell-published="{ row }">
          <span class="text-[.86rem] text-muted">{{ fmtDate(row.published_at) }}</span>
        </template>

        <template #actions="{ row }">
          <button class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-brand" title="Edit" @click="openEdit(row)">✎</button>
          <button class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-[#dc2626]" title="Remove" @click="removePost(row)">🗑</button>
        </template>

        <template #empty>
          <span class="muted">No blog posts yet. Click <strong>NEW POST</strong> to write one.</span>
        </template>
      </DataTable>
    </div>

    <!-- Add / Edit drawer -->
    <Drawer v-if="drawerOpen" :title="editingId ? 'Edit Blog Post' : 'New Blog Post'" @close="drawerOpen = false">
      <div class="mb-5">
        <FormField label="Cover Image">
          <ImageField
            :model-value="draft.cover_url"
            :aspect="16 / 9"
            :output-width="1200"
            :output-height="675"
            collection="cover"
            card-width="300px"
            :gallery-path="`/events/${id}/gallery`"
            hint="Recommended 16:9, shown in listings and social shares."
            @update:model-value="draft.cover_url = (Array.isArray($event) ? $event[0] : $event) || null"
            @uploaded="(v: any) => draft.cover_file_id = v.id"
          />
        </FormField>
      </div>

      <div class="mb-4">
        <AppInput v-model="draft.title" label="Title" required placeholder="Post title" />
      </div>

      <div class="mb-4">
        <AppTextarea v-model="draft.excerpt" label="Excerpt" :rows="2" placeholder="Short summary shown in listings…" hint="A one or two line teaser for the post." />
      </div>

      <div class="mb-4">
        <AppTextarea v-model="draft.body" label="Content" :rows="10" placeholder="Write your article…" />
      </div>

      <div class="mb-1">
        <AppSelect
          v-model="draft.status"
          label="Status"
          :options="[{ value: 'draft', label: 'Draft' }, { value: 'published', label: 'Published' }]"
        />
      </div>

      <p v-if="error" class="error mt-3">{{ error }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-5">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!draft.title.trim() || saving" @click="saveDraft">
          {{ saving ? 'Saving…' : editingId ? 'UPDATE' : 'CREATE' }}
        </button>
      </div>
    </Drawer>
  </div>
</template>
