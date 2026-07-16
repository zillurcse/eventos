<script setup lang="ts">
const profile = useProfileStore()

const tabs = [
  { key: 'personal', label: 'Personal Details' },
  { key: 'interest', label: 'Interest' },
  { key: 'looking', label: 'Looking & Offering' },
] as const

const active = ref<typeof tabs[number]['key']>('personal')
</script>

<template>
  <div>
    <h1>Edit Profile</h1>

    <nav class="tabs">
      <button
        v-for="t in tabs" :key="t.key" type="button"
        class="tab" :class="{ on: active === t.key }" @click="active = t.key"
      >
        {{ t.label }}
      </button>
    </nav>

    <div class="panel">
      <p v-if="profile.loading && !profile.data" class="loading">Loading…</p>
      <template v-else>
        <ProfilePersonalDetails v-if="active === 'personal'" />
        <ProfileInterestsTab v-else-if="active === 'interest'" />
        <ProfileLookingOffering v-else-if="active === 'looking'" />
      </template>
    </div>
  </div>
</template>

<style scoped>
.tabs { display: flex; gap: 6px; border-bottom: 1px solid #eef0f3; margin-bottom: 24px; }
.tab { border: none; background: none; padding: 10px 6px; margin-right: 18px; font: inherit; font-size: .9rem; font-weight: 600; color: #94a3b8; cursor: pointer; border-bottom: 2px solid transparent; }
.tab:hover { color: #475569; }
.tab.on { color: var(--brand-primary); border-bottom-color: var(--brand-primary); font-weight: 700; }

.loading { color: #94a3b8; font-size: .9rem; }
</style>
