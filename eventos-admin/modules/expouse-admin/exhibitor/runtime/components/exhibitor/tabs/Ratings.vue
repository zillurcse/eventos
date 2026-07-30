<script setup lang="ts">
const {
  ratingsLoading,
  ratings,
  ratingsSummary,
  loadRatings,
} = useExhibitorContext()

const topScore = computed(() =>
  [...ratingsSummary.value.distribution].sort((a, b) => b.count - a.count || b.score - a.score)[0]?.score ?? null,
)

onMounted(() => {
  loadRatings()
})
</script>

<template>
  <div>
    <div class="flex items-start justify-between gap-4 mb-4">
      <div>
        <p class="font-semibold text-[.92rem] m-0 text-ink">Ratings</p>
        <p class="muted text-[.8rem] mt-1 mb-0">
          See who rated this exhibitor and how the scores are distributed.
        </p>
      </div>
      <button class="btn ghost sm" :disabled="ratingsLoading" @click="loadRatings(true)">
        {{ ratingsLoading ? 'Refreshing…' : 'Refresh' }}
      </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
      <div class="border border-line rounded-xl p-4 bg-[#fcfcfd]">
        <div class="muted text-[.76rem] uppercase tracking-wide mb-1">Average Rating</div>
        <div class="text-[1.7rem] font-bold text-ink">
          {{ ratingsSummary.average_score ?? '—' }}
          <span class="text-[1rem] text-muted font-medium">/ 5</span>
        </div>
      </div>
      <div class="border border-line rounded-xl p-4 bg-[#fcfcfd]">
        <div class="muted text-[.76rem] uppercase tracking-wide mb-1">Total Ratings</div>
        <div class="text-[1.7rem] font-bold text-ink">{{ ratingsSummary.ratings_count }}</div>
      </div>
      <div class="border border-line rounded-xl p-4 bg-[#fcfcfd]">
        <div class="muted text-[.76rem] uppercase tracking-wide mb-1">Top Score</div>
        <div class="text-[1.7rem] font-bold text-ink">
          {{ topScore ?? '—' }}
          <span class="text-[1rem] text-muted font-medium">star</span>
        </div>
      </div>
    </div>

    <div class="border border-line rounded-xl p-4 mb-5">
      <div class="font-semibold text-[.88rem] text-ink mb-3">Distribution</div>
      <div class="flex flex-col gap-2">
        <div v-for="bucket in [...ratingsSummary.distribution].sort((a, b) => b.score - a.score)" :key="bucket.score" class="flex items-center gap-3">
          <div class="w-12 text-[.84rem] text-ink font-medium">{{ bucket.score }} star</div>
          <div class="flex-1 h-2.5 rounded-full bg-[#eef0f4] overflow-hidden">
            <div
              class="h-full bg-brand rounded-full transition-[width]"
              :style="{ width: `${ratingsSummary.ratings_count ? (bucket.count / ratingsSummary.ratings_count) * 100 : 0}%` }"
            />
          </div>
          <div class="w-10 text-right text-[.82rem] text-muted">{{ bucket.count }}</div>
        </div>
      </div>
    </div>

    <div class="border border-line rounded-xl overflow-hidden">
      <div class="grid grid-cols-[minmax(0,1.4fr)_120px_160px_120px] gap-3 px-4 py-3 bg-[#f8f9fc] text-[.76rem] font-semibold uppercase tracking-wide text-muted">
        <div>Attendee</div>
        <div>Score</div>
        <div>Rated At</div>
        <div>Status</div>
      </div>

      <div v-if="ratingsLoading && !ratings.length" class="px-4 py-8 text-center muted text-[.84rem]">
        Loading ratings…
      </div>
      <div v-else-if="!ratings.length" class="px-4 py-8 text-center muted text-[.84rem]">
        No one has rated this exhibitor yet.
      </div>

      <div
        v-for="row in ratings" :key="row.id"
        class="grid grid-cols-[minmax(0,1.4fr)_120px_160px_120px] gap-3 px-4 py-3 border-t border-line items-center"
      >
        <div class="min-w-0">
          <div class="text-[.88rem] text-ink font-medium truncate">{{ row.participation.name || 'Unnamed attendee' }}</div>
          <div class="text-[.8rem] text-muted truncate">{{ row.participation.email || 'No email' }}</div>
        </div>
        <div class="text-[.88rem] text-ink font-semibold">{{ row.score }} / 5</div>
        <div class="text-[.82rem] text-muted">
          {{ row.rated_at ? new Date(row.rated_at).toLocaleString() : '—' }}
        </div>
        <div>
          <span
            class="inline-flex items-center px-2 py-1 rounded-full text-[.72rem] font-semibold"
            :class="row.participation.status === 'confirmed'
              ? 'bg-[#dcfce7] text-[#15803d]'
              : 'bg-[#eef0f4] text-[#475569]'"
          >
            {{ row.participation.status || 'unknown' }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
