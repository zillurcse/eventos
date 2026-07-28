<script setup lang="ts">
import type { FeedMention } from '~/utils/mentions'
import { segmentMentions } from '~/utils/mentions'

const props = defineProps<{
  body: string
  mentions?: FeedMention[]
}>()

const delegates = useDelegatesStore()

const segments = computed(() => segmentMentions(props.body, props.mentions ?? []))

function openMention(m: FeedMention) {
  delegates.open({
    id: m.id,
    name: m.name,
    company: '',
    job_title: '',
    avatar_url: m.avatar_url ?? null,
    online: false,
  })
}
</script>

<template>
  <span class="mention-text">
    <template v-for="(seg, i) in segments" :key="i">
      <button
        v-if="seg.type === 'mention' && seg.mention"
        type="button"
        class="mention"
        @click.stop="openMention(seg.mention)"
      >{{ seg.text }}</button>
      <template v-else>{{ seg.text }}</template>
    </template>
  </span>
</template>

<style scoped>
.mention-text {
  white-space: pre-wrap;
  word-break: break-word;
}

.mention {
  display: inline;
  padding: 0;
  margin: 0;
  border: none;
  background: none;
  color: var(--brand-primary);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.mention:hover {
  text-decoration: underline;
}
</style>
