<script setup lang="ts">
/**
 * PREVIOUS EXHIBITORS — pick companies from the organizer's past events and
 * carry them into this one. State lives in usePreviousExhibitors; this is the
 * screen for it.
 */
const { previous } = useExhibitorContext()
const {
  loading, importing, error,
  visible, importable, selected, search, include, allSelected,
  closePicker, toggle, toggleAll, runImport,
} = previous

function eventYear(iso: string | null) {
  return iso ? new Date(iso).getFullYear() : ''
}
</script>

<template>
  <Drawer title="Previous Exhibitors" @close="closePicker">
    <p class="muted text-[.85rem] mt-0 mb-4">
      Exhibitors from your other events. Importing copies their profile into this
      event — their package and stall are set here, not carried over.
    </p>

    <SearchInput v-model="search" placeholder="Search by name, email or event" class="w-full mb-3" />

    <!-- What travels with them -->
    <div class="border border-line rounded-xl p-4 mb-4 bg-[#f7f8fa]">
      <p class="font-semibold text-[.9rem] m-0 mb-2.5 text-ink">Also copy</p>
      <div class="flex flex-wrap gap-x-5 gap-y-2">
        <AppCheckbox v-model="include.members" label="Team members" />
        <AppCheckbox v-model="include.products" label="Products" />
        <AppCheckbox v-model="include.documents" label="Documents" />
        <AppCheckbox v-model="include.projects" label="Projects" />
      </div>
      <p v-if="include.members" class="muted text-[.78rem] mt-2 mb-0">
        Team members keep the logins they already have — no invite emails are sent.
      </p>
    </div>

    <!-- Select all -->
    <div v-if="importable.length" class="flex items-center justify-between mb-2">
      <button class="btn ghost sm" @click="toggleAll">
        {{ allSelected ? 'Clear selection' : `Select all (${importable.length})` }}
      </button>
      <span class="muted text-[.82rem]">{{ selected.length }} selected</span>
    </div>

    <p v-if="loading" class="muted text-[.85rem]">Loading…</p>
    <p v-else-if="!visible.length" class="muted text-[.85rem] py-6 text-center">
      {{ search ? 'No exhibitor matches that search.' : 'You have no exhibitors at any other event yet.' }}
    </p>

    <!-- Candidates -->
    <div v-else class="flex flex-col gap-2">
      <label
        v-for="c in visible" :key="c.id"
        class="flex items-center gap-3 border border-line rounded-xl p-3 transition-colors"
        :class="c.already_added
          ? 'opacity-55 cursor-default bg-[#f7f8fa]'
          : selected.includes(c.id) ? 'border-brand bg-brand-soft cursor-pointer' : 'cursor-pointer hover:border-brand'"
      >
        <input
          type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand shrink-0"
          :checked="selected.includes(c.id)"
          :disabled="c.already_added"
          @change="toggle(c)"
        >

        <div class="w-9 h-9 rounded-lg overflow-hidden shrink-0">
          <img v-if="c.logo_url" :src="c.logo_url" class="w-full h-full object-cover" :alt="c.name">
          <div v-else class="w-full h-full bg-brand-soft flex items-center justify-center text-brand font-bold text-[.72rem] uppercase">
            {{ exhibitorInitials(c.name) }}
          </div>
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="font-semibold text-ink text-[.9rem] truncate">{{ c.name }}</span>
            <span v-if="c.already_added" class="badge">Already added</span>
            <span v-else-if="c.type === 'sponsor'" class="badge capitalize">{{ c.type }}</span>
          </div>
          <div class="muted text-[.78rem] truncate">
            {{ c.email || 'No email' }} · {{ c.event.name }} {{ eventYear(c.event.starts_at) }}
          </div>
          <div v-if="c.counts.members || c.counts.products || c.counts.documents || c.counts.projects" class="muted text-[.76rem] mt-0.5">
            <span v-if="c.counts.members">{{ c.counts.members }} team</span>
            <span v-if="c.counts.products"> · {{ c.counts.products }} products</span>
            <span v-if="c.counts.documents"> · {{ c.counts.documents }} docs</span>
            <span v-if="c.counts.projects"> · {{ c.counts.projects }} projects</span>
          </div>
        </div>
      </label>
    </div>

    <p v-if="error" class="error mt-3">{{ error }}</p>

    <div class="pt-4 mt-2 sticky bottom-0 bg-white">
      <button
        class="btn w-full py-3 text-[.95rem] tracking-widest"
        :disabled="!selected.length || importing"
        @click="runImport"
      >
        {{ importing ? 'IMPORTING…' : selected.length ? `IMPORT ${selected.length} EXHIBITOR${selected.length === 1 ? '' : 'S'}` : 'IMPORT' }}
      </button>
    </div>
  </Drawer>
</template>
