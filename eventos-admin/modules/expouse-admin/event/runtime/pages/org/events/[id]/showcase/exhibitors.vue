<script setup lang="ts">
import { useRoute } from 'vue-router'

declare const definePageMeta: (meta: Record<string, unknown>) => void
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const id = route.params.id as string
</script>

<template>
  <ExhibitorManager :event-id="id" />
</template>
