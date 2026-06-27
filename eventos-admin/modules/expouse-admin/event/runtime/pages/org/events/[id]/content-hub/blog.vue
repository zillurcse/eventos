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

const filtered = computed(() => {
  const q = search.value.toLowerCase().trim()
  if (!q) return posts.value
  return posts.value.filter((p: BlogPost) =>
    [p.title, p.excerpt ?? ''].some(f => f.toLowerCase().includes(q)),
  )
})

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
        <input v-model="search" placeholder="Search posts…" class="m-0 max-w-[260px]">
        <button class="btn" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          NEW POST
        </button>
      </div>

      <table>
        <thead>
          <tr>
            <th>POST</th>
            <th>STATUS</th>
            <th>PUBLISHED</th>
            <th class="text-right">ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in filtered" :key="p.id">
            <td>
              <div class="flex items-center gap-3">
                <div class="w-14 h-10 rounded-lg overflow-hidden shrink-0 bg-[#f3f4f6] border border-line flex items-center justify-center text-muted text-[.7rem]">
                  <img v-if="p.cover_url" :src="p.cover_url" :alt="p.title" class="w-full h-full object-cover">
                  <span v-else>No img</span>
                </div>
                <div class="min-w-0">
                  <div class="font-semibold text-ink leading-tight truncate max-w-[360px]">{{ p.title }}</div>
                  <div class="muted text-[.8rem] truncate max-w-[360px]">{{ p.excerpt || '—' }}</div>
                </div>
              </div>
            </td>
            <td>
              <button
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[.75rem] font-semibold cursor-pointer border-0"
                :class="p.status === 'published' ? 'bg-green-50 text-green-700' : 'bg-[#f1f1f5] text-muted'"
                :title="p.status === 'published' ? 'Click to unpublish' : 'Click to publish'"
                @click="togglePublish(p)"
              >{{ p.status === 'published' ? 'Published' : 'Draft' }}</button>
            </td>
            <td class="text-[.86rem] text-muted">{{ fmtDate(p.published_at) }}</td>
            <td class="text-right whitespace-nowrap">
              <button class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-brand" title="Edit" @click="openEdit(p)">✎</button>
              <button class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-[#dc2626]" title="Remove" @click="removePost(p)">🗑</button>
            </td>
          </tr>

          <tr v-if="!filtered.length">
            <td colspan="4" class="muted text-center py-8">
              {{ search ? 'No posts match your search.' : 'No blog posts yet. Click NEW POST to write one.' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add / Edit drawer -->
    <Drawer v-if="drawerOpen" :title="editingId ? 'Edit Blog Post' : 'New Blog Post'" @close="drawerOpen = false">
      <div class="mb-5">
        <label class="block mb-1.5">Cover Image</label>
        <UploadButton
          :preview="draft.cover_url"
          collection="cover"
          @uploaded="(v: any) => { draft.cover_file_id = v.id; draft.cover_url = v.url }"
        />
      </div>

      <label>
        Title
        <span class="text-[#dc2626] ml-0.5">*</span>
      </label>
      <input v-model="draft.title" placeholder="Post title">

      <label>Excerpt</label>
      <textarea v-model="draft.excerpt" rows="2" placeholder="Short summary shown in listings…" />
      <p class="muted text-[.82rem] -mt-2 mb-4">A one or two line teaser for the post.</p>

      <label>Content</label>
      <textarea v-model="draft.body" rows="10" placeholder="Write your article…" />

      <label>Status</label>
      <select v-model="draft.status">
        <option value="draft">Draft</option>
        <option value="published">Published</option>
      </select>

      <p v-if="error" class="error mt-3">{{ error }}</p>

      <div class="modal-actions">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!draft.title.trim() || saving" @click="saveDraft">
          {{ saving ? 'Saving…' : editingId ? 'UPDATE' : 'CREATE' }}
        </button>
      </div>
    </Drawer>
  </div>
</template>
