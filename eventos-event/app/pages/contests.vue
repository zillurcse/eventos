<script setup lang="ts">
definePageMeta({ layout: 'event', middleware: 'auth' })

const contests = useContestsStore()

onMounted(() => {
  if (!contests.loaded) contests.fetchContests()
  contests.fetchAds()
})
</script>

<template>
  <div>
    <ReceptionAdStrip v-if="contests.ads.length" :ads="contests.ads" class="ads" />

    <h1 class="title">Contests</h1>

    <div v-if="contests.loading && !contests.loaded" class="state">Loading contests…</div>
    <div v-else-if="contests.error" class="state">Couldn’t load contests. Please try again.</div>
    <div v-else-if="!contests.contests.length" class="state">
      No contests have been announced yet. Check back soon.
    </div>

    <div v-else class="grid">
      <ContestsContestCard v-for="c in contests.contests" :key="c.id" :contest="c" />
    </div>
  </div>
</template>

<style scoped>
.ads { margin-bottom: 18px; }

.title {
  margin: 0 0 22px;
  font-size: 1.65rem;
  font-weight: 800;
  color: #1e293b;
  letter-spacing: -0.02em;
}

.state {
  padding: 60px 0;
  text-align: center;
  color: #64748b;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

@media (min-width: 1100px) {
  .grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
</style>
