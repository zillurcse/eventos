<script setup lang="ts">
const board = useLeaderboardStore()

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') board.closeDrawer()
}

onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<template>
  <Teleport to="body">
    <div class="scrim" @click.self="board.closeDrawer()">
      <aside class="drawer" role="dialog" aria-label="Leaderboard">
        <header class="head">
          <span class="htitle">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M19 2H16V1C16 0.734784 15.8946 0.48043 15.7071 0.292893C15.5196 0.105357 15.2652 0 15 0H5C4.73478 0 4.48043 0.105357 4.29289 0.292893C4.10536 0.48043 4 0.734784 4 1V2H1C0.734784 2 0.48043 2.10536 0.292893 2.29289C0.105357 2.48043 0 2.73478 0 3V6C0 7.06087 0.421427 8.07828 1.17157 8.82843C1.92172 9.57857 2.93913 10 4 10H5.54C6.44453 11.0091 7.66406 11.6824 9 11.91V14H8C7.20435 14 6.44129 14.3161 5.87868 14.8787C5.31607 15.4413 5 16.2044 5 17V19C5 19.2652 5.10536 19.5196 5.29289 19.7071C5.48043 19.8946 5.73478 20 6 20H14C14.2652 20 14.5196 19.8946 14.7071 19.7071C14.8946 19.5196 15 19.2652 15 19V17C15 16.2044 14.6839 15.4413 14.1213 14.8787C13.5587 14.3161 12.7956 14 12 14H11V11.91C12.3359 11.6824 13.5555 11.0091 14.46 10H16C17.0609 10 18.0783 9.57857 18.8284 8.82843C19.5786 8.07828 20 7.06087 20 6V3C20 2.73478 19.8946 2.48043 19.7071 2.29289C19.5196 2.10536 19.2652 2 19 2ZM4 8C3.46957 8 2.96086 7.78929 2.58579 7.41421C2.21071 7.03914 2 6.53043 2 6V4H4V6C4.0022 6.68171 4.12056 7.35806 4.35 8H4ZM12 16C12.2652 16 12.5196 16.1054 12.7071 16.2929C12.8946 16.4804 13 16.7348 13 17V18H7V17C7 16.7348 7.10536 16.4804 7.29289 16.2929C7.48043 16.1054 7.73478 16 8 16H12ZM14 6C14 7.06087 13.5786 8.07828 12.8284 8.82843C12.0783 9.57857 11.0609 10 10 10C8.93913 10 7.92172 9.57857 7.17157 8.82843C6.42143 8.07828 6 7.06087 6 6V2H14V6ZM18 6C18 6.53043 17.7893 7.03914 17.4142 7.41421C17.0391 7.78929 16.5304 8 16 8H15.65C15.8794 7.35806 15.9978 6.68171 16 6V4H18V6Z" fill="currentColor"/>
            </svg>
            Leaderboard
          </span>
          <button class="x" type="button" title="Close" aria-label="Close" @click="board.closeDrawer()">
            <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </header>

        <div class="body">
          <div v-if="board.loading && !board.loaded" class="note">Loading…</div>

          <template v-else-if="!board.enabled">
            <div class="note">Gamification is not enabled for this event.</div>
          </template>

          <template v-else-if="!board.entries.length">
            <div class="note">
              No points yet. Earn points by engaging with the event.
            </div>
            <div v-if="board.myPoints" class="my-pts">Your points: {{ board.myPoints }}</div>
          </template>

          <template v-else>
            <!-- Top 3 featured rows -->
            <div v-if="board.topThree.length" class="top">
              <div
                v-for="e in board.topThree"
                :key="e.rank"
                class="row featured"
                :class="{ me: e.is_me }"
              >
                <span class="av">
                  <UserAvatar :src="e.avatar_url" :name="e.name" />
                </span>
                <span class="mid">
                  <span class="name">{{ e.name }}</span>
                  <span v-if="e.role" class="role">{{ e.role }}</span>
                </span>
                <span class="score featured-score">
                  <span class="rank">{{ e.rank }}</span>
                  <span class="pts">{{ e.points }}</span>
                </span>
              </div>
            </div>

            <!-- Ranks 4+ -->
            <div v-if="board.rest.length" class="rest">
              <div
                v-for="e in board.rest"
                :key="e.rank"
                class="row"
                :class="{ me: e.is_me }"
              >
                <span class="av sm">
                  <UserAvatar :src="e.avatar_url" :name="e.name" />
                </span>
                <span class="mid">
                  <span class="name">{{ e.name }}</span>
                  <span v-if="e.role" class="role">{{ e.role }}</span>
                </span>
                <span class="score">
                  <span class="rank muted">{{ e.rank }}</span>
                  <span class="badge">{{ e.points }}</span>
                </span>
              </div>
            </div>
          </template>
        </div>
      </aside>
    </div>
  </Teleport>
</template>

<style scoped>
.scrim {
  position: fixed;
  inset: 0;
  z-index: 70;
  background: rgba(15, 23, 42, .18);
}
.drawer {
  position: absolute;
  top: 0;
  right: 0;
  height: 100%;
  width: 420px;
  max-width: 100vw;
  background: #fff;
  box-shadow: -14px 0 40px rgba(15, 23, 42, .16);
  display: flex;
  flex-direction: column;
  min-height: 0;
  animation: slide .18s ease;
}
@keyframes slide {
  from { transform: translateX(30px); opacity: .4; }
  to { transform: none; opacity: 1; }
}

.head {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #eceef2;
  padding: 16px;
}
.htitle {
  flex: 1;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: #475569;
  font-weight: 700;
  font-size: 1rem;
}
.htitle svg { color: #64748b; }
.x {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: none;
  background: #e02d2d;
  color: #fff;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.x svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.4;
  stroke-linecap: round;
}

.body {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
  background: #fff;
}
.note {
  color: #94a3b8;
  font-size: .88rem;
  text-align: center;
  padding: 44px 24px;
}
.my-pts {
  text-align: center;
  color: #64748b;
  font-size: .85rem;
  font-weight: 600;
  padding-bottom: 24px;
}

.top {
  background: #f7f8fa;
  padding: 8px 0 4px;
  border-bottom: 1px solid #eef0f3;
}
.rest { padding: 0; }

.row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 20px;
  background: #fff;
}
.row.featured {
  background: transparent;
  padding: 16px 20px;
}
.row.me {
  background: #fff;
  box-shadow: 0 4px 18px rgba(15, 23, 42, .1);
  position: relative;
  z-index: 1;
}
.row.featured.me {
  margin: 4px 12px;
  border-radius: 10px;
  padding: 14px 12px;
}
.rest .row {
  border-bottom: 1px solid #eef0f3;
}
.rest .row:last-child { border-bottom: none; }
.rest .row.me {
  margin: 0;
  border-radius: 0;
  box-shadow: inset 0 0 0 1px #e8eaf0, 0 2px 10px rgba(15, 23, 42, .06);
}

.av {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  overflow: hidden;
  flex: 0 0 auto;
  background: #e2e8f0;
}
.av.sm {
  width: 42px;
  height: 42px;
}

.mid {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.name {
  color: var(--brand-primary, #6452e7);
  font-weight: 700;
  font-size: .95rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.role {
  color: #94a3b8;
  font-size: .68rem;
  font-weight: 600;
  letter-spacing: .4px;
  text-transform: uppercase;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.score {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 0 0 auto;
}
.featured-score {
  flex-direction: column;
  align-items: flex-end;
  gap: 0;
  min-width: 36px;
}
.rank {
  font-size: 1.65rem;
  font-weight: 700;
  color: #5b8def;
  line-height: 1.1;
}
.rank.muted {
  font-size: 1rem;
  color: #64748b;
  min-width: 1.2em;
  text-align: right;
}
.pts {
  color: #94a3b8;
  font-size: .78rem;
  font-weight: 600;
  line-height: 1.2;
}
.badge {
  min-width: 28px;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--brand-primary, #6452e7);
  color: #fff;
  font-size: .78rem;
  font-weight: 700;
  text-align: center;
  line-height: 1.2;
}
</style>
