<script setup lang="ts">
import type { BreakoutRoom } from '~/stores/rooms'

const props = defineProps<{
  room: BreakoutRoom
  joining: boolean
}>()

defineEmits<{ join: [] }>()

const AVATAR_SHOW = 4

const isPrivate = computed(() => props.room.access_type === 'coded')
const shown = computed(() => props.room.occupants.slice(0, AVATAR_SHOW))
const moreCount = computed(() => Math.max(0, props.room.occupied - shown.value.length))

const startedLabel = computed(() => {
  if (!props.room.starts_at) return null
  const d = new Date(props.room.starts_at)
  if (Number.isNaN(d.getTime())) return null
  const time = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
  const now = Date.now()
  if (d.getTime() <= now) return `Started at ${time}`
  return `Starts at ${time}`
})
</script>

<template>
  <article class="card">
    <div class="poster">
      <AppImage :src="room.poster_url" :alt="room.name" />
      <span v-if="isPrivate" class="badge lock" title="Access code required">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M7 10V8a5 5 0 0 1 10 0v2" />
          <rect x="5" y="10" width="14" height="10" rx="2" />
        </svg>
      </span>
      <span v-else class="badge open">
        <i class="dot" />
        Open
      </span>
    </div>

    <div class="body">
      <h3>{{ room.name }}</h3>
      <p v-if="startedLabel" class="when">{{ startedLabel }}</p>
      <p v-else-if="room.description" class="when">{{ room.description }}</p>

      <div class="foot">
        <div v-if="shown.length" class="people">
          <div class="avs">
            <span v-for="o in shown" :key="o.identity" class="av" :title="o.name">
              <UserAvatar :src="o.avatar_url" :name="o.name" />
            </span>
          </div>
          <span v-if="moreCount" class="more">+{{ moreCount }} More</span>
        </div>
        <div v-else class="people empty" />

        <button class="join" type="button" :disabled="joining" @click="$emit('join')">
          {{ joining ? 'Joining…' : 'Join' }}
        </button>
      </div>
    </div>
  </article>
</template>

<style scoped>
.card {
  background: #fff;
  border: 1px solid #e8ecf1;
  border-radius: 12px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
  transition: box-shadow .15s, transform .15s, border-color .15s;
}

.card:hover {
  border-color: #dde3ea;
  box-shadow: 0 8px 22px rgba(15, 23, 42, .08);
  transform: translateY(-2px);
}

.poster {
  position: relative;
  min-height: 168px;
  background: #eef0f3;
}

.poster :deep(img) {
  display: block;
  width: 100%;
  min-height: 168px;
  max-height: 168px;
  object-fit: cover;
}

.badge {
  position: absolute;
  top: 12px;
  left: 12px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--brand-primary);
  color: #fff;
  font-size: .72rem;
  font-weight: 700;
  padding: 5px 10px;
  border-radius: 8px;
  line-height: 1;
}

.badge.open .dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #fff;
  flex: 0 0 auto;
}

.badge.lock {
  padding: 6px 8px;
}

.badge.lock svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: #fff;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.body {
  padding: 14px 16px 16px;
  display: flex;
  flex-direction: column;
  flex: 1;
  gap: 4px;
}

.body h3 {
  margin: 0;
  font-size: 1.02rem;
  line-height: 1.3;
  font-weight: 800;
  color: #1e293b;
}

.when {
  margin: 0;
  color: #94a3b8;
  font-size: .86rem;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: auto;
  padding-top: 14px;
}

.people {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  flex: 1;
}

.people.empty {
  min-height: 28px;
}

.avs {
  display: flex;
  align-items: center;
}

.av {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid #fff;
  margin-left: -8px;
  flex: 0 0 auto;
  background: #e2e8f0;
}

.av:first-child {
  margin-left: 0;
}

.av :deep(img),
.av :deep(span) {
  width: 100%;
  height: 100%;
}

.more {
  color: #3b82f6;
  font-size: .82rem;
  font-weight: 700;
  white-space: nowrap;
}

.join {
  background: var(--brand-primary);
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 9px 20px;
  font-weight: 700;
  cursor: pointer;
  font-size: .88rem;
  flex: 0 0 auto;
}

.join:disabled {
  opacity: .6;
  cursor: default;
}
</style>
