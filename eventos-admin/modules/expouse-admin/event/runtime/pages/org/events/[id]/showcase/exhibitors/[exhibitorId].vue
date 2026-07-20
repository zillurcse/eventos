<script setup lang="ts">
import { useRoute } from 'vue-router'

declare const definePageMeta: (meta: Record<string, unknown>) => void
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const eventId = route.params.id as string
const exhibitorId = route.params.exhibitorId as string

// This page owns its own manager instance (the list page's is gone once we
// navigate here) and provides it to the editor components via the same key.
const mgr = useExhibitorManager(eventId)
provide(ExhibitorKey, mgr)

onMounted(() => {
  mgr.loadMeta()
  mgr.loadForEdit(exhibitorId)
})
</script>

<template>
  <ExhibitorEditPage />
</template>
