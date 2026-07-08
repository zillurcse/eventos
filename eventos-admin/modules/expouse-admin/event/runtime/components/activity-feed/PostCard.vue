<script setup lang="ts">
/**
 * One attendee post on Manage Activity Feed: author header, body + media/poll/
 * tag previews, engagement counters, and the approve/reject actions for the
 * active tab. Presentational — moderation calls live in the page.
 */
interface Attachment { kind: 'image' | 'video' | 'pdf', url: string, name?: string | null }

interface ModPost {
  id: string
  type: string
  body: string
  status: 'pending' | 'published' | 'rejected'
  is_pinned: boolean
  author: string
  author_avatar: string | null
  author_role: 'attendee' | 'organizer'
  likes: number
  comments: number
  reports: number
  attachments: Attachment[]
  tags: string[]
  poll_options: string[]
  created_at: string | null
}

defineProps<{
  post: ModPost
  /** Tab the card is rendered on — drives which actions show. */
  tab: ModPost['status']
  /** True while a decision on THIS post is in flight. */
  busy?: boolean
}>()

defineEmits<{
  (e: 'approve' | 'reject'): void
}>()

const TYPE_CHIP: Record<string, string> = {
  poll: 'Poll', looking_for: 'Looking for', offering: 'Offering',
  image: 'Photo', video: 'Video', pdf: 'PDF',
}

function initials(name: string) {
  const p = name.trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

function fmtDate(iso: string | null) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString([], { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
  <article class="bg-white border border-line rounded-xl flex flex-col overflow-hidden">
    <!-- Author -->
    <div class="flex items-center gap-3 p-3.5 pb-2.5">
      <span class="w-10 h-10 rounded-full overflow-hidden bg-[#6352e7] text-white font-bold text-[.85rem] inline-flex items-center justify-center shrink-0">
        <img v-if="post.author_avatar" :src="post.author_avatar" class="w-full h-full object-cover" :alt="post.author">
        <template v-else>{{ initials(post.author) }}</template>
      </span>
      <div class="min-w-0">
        <div class="flex items-center gap-1.5">
          <span class="font-semibold text-ink text-[.9rem] truncate">{{ post.author }}</span>
          <span v-if="post.is_pinned" title="Pinned">📌</span>
        </div>
        <div class="text-muted text-[.76rem]">{{ fmtDate(post.created_at) }}</div>
      </div>
      <span
        v-if="TYPE_CHIP[post.type]"
        class="ml-auto shrink-0 px-2 py-0.5 rounded-full text-[.68rem] font-semibold bg-[#eef0ff] text-[#6352e7]"
      >{{ TYPE_CHIP[post.type] }}</span>
    </div>

    <!-- Body -->
    <div class="px-3.5 flex-1 min-w-0">
      <p v-if="post.body" class="text-[.87rem] text-ink whitespace-pre-line break-words line-clamp-5 m-0">{{ post.body }}</p>

      <!-- Media preview -->
      <div v-if="post.attachments.length" class="mt-2.5 rounded-lg overflow-hidden border border-line bg-[#f7f8fa]">
        <img v-if="post.attachments[0].kind === 'image'" :src="post.attachments[0].url" class="w-full max-h-52 object-cover" alt="">
        <video v-else-if="post.attachments[0].kind === 'video'" :src="post.attachments[0].url" class="w-full max-h-52" controls muted />
        <a v-else :href="post.attachments[0].url" target="_blank" class="flex items-center gap-2 p-3 text-[.82rem] text-muted no-underline">
          <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0" fill="none" stroke="#ef4444" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h8l4 4v14H7zM15 3v4h4" /></svg>
          <span class="truncate">{{ post.attachments[0].name || 'PDF document' }}</span>
        </a>
        <div v-if="post.attachments.length > 1" class="text-[.72rem] text-muted px-2.5 py-1.5 border-t border-line">
          +{{ post.attachments.length - 1 }} more attachment{{ post.attachments.length > 2 ? 's' : '' }}
        </div>
      </div>

      <!-- Poll preview -->
      <div v-if="post.poll_options.length" class="mt-2.5 flex flex-col gap-1">
        <div v-for="o in post.poll_options.slice(0, 4)" :key="o" class="text-[.8rem] text-muted bg-[#f7f8fa] border border-line rounded-lg px-2.5 py-1.5 truncate">{{ o }}</div>
        <div v-if="post.poll_options.length > 4" class="text-[.72rem] text-muted px-1">+{{ post.poll_options.length - 4 }} more options</div>
      </div>

      <!-- Networking tags -->
      <div v-if="post.tags.length" class="mt-2.5 flex flex-wrap gap-1.5">
        <span v-for="t in post.tags" :key="t" class="px-2 py-0.5 rounded-full text-[.72rem] font-medium bg-[#eef0ff] text-[#6352e7]">{{ t }}</span>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-2 px-3.5 pt-3 mt-3 border-t border-line">
      <div>
        <div class="text-[.74rem] text-muted">Likes</div>
        <div class="font-bold text-[.86rem] text-[#6352e7]">{{ post.likes }}</div>
      </div>
      <div>
        <div class="text-[.74rem] text-muted">Comments</div>
        <div class="font-bold text-[.86rem] text-[#6352e7]">{{ post.comments }}</div>
      </div>
      <div>
        <div class="text-[.74rem] text-muted">Reports</div>
        <div class="font-bold text-[.86rem]" :class="post.reports ? 'text-[#dc2626]' : 'text-[#6352e7]'">{{ post.reports }}</div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-2 p-3.5 pt-2.5">
      <button
        v-if="tab !== 'published'"
        class="px-3.5 py-1.5 rounded-lg text-[.76rem] font-bold text-white bg-[#22a55b] hover:bg-[#1d9150] disabled:opacity-60 transition-colors"
        :disabled="busy"
        @click="$emit('approve')"
      >{{ tab === 'rejected' ? 'RESTORE' : 'APPROVE' }}</button>
      <button
        v-if="tab !== 'rejected'"
        class="px-3.5 py-1.5 rounded-lg text-[.76rem] font-bold text-white bg-[#f26d6d] hover:bg-[#e05555] disabled:opacity-60 transition-colors"
        :disabled="busy"
        @click="$emit('reject')"
      >REJECT</button>
    </div>
  </article>
</template>
