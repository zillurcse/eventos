<script setup lang="ts">
import { useRoute } from 'vue-router'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const id = route.params.id as string
</script>

<template>
  <div>
    <h1 class="text-[1.4rem] font-bold text-ink mb-4">Communication</h1>
    <CommunicationTabs :event-id="id" active="profiles" />

    <SectionPlaceholder section="Communication" title="Profiles" />
  </div>
</template>
