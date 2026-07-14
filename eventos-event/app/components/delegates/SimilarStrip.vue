<script setup lang="ts">
import type { SimilarDelegate } from '~/stores/delegates'

const store = useDelegatesStore()

function open(d: SimilarDelegate) {
  store.openConnect(d)
}
</script>

<template>
  <section v-if="store.similar.length" class="similar">
    <div class="head">
      <h2>Similar profiles</h2>
      <p class="sub">People here with your designation or company.</p>
    </div>

    <!-- Rectangles, not cards: avatar + name + presence, side by side. -->
    <ul class="strip">
      <li v-for="d in store.similar" :key="d.id">
        <button type="button" class="row" @click="open(d)">
          <span class="avatar">
            <UserAvatar :src="d.avatar_url" :name="d.name" />
            <span class="dot" :class="{ on: d.online }" :title="d.online ? 'Online' : 'Offline'" />
          </span>

          <span class="who">
            <strong class="name">{{ d.name }}</strong>
            <small class="match">{{ d.match }}</small>
          </span>
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.similar { margin-bottom: 22px; }

.head { margin-bottom: 12px; }
.head h2 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #1e293b; }
.sub { margin: 3px 0 0; color: #64748b; font-size: .84rem; }

/* One horizontal row that scrolls when it overflows, so the strip never grows
   taller than the directory heading below it. */
.strip { list-style: none; margin: 0; padding: 2px 2px 8px; display: flex; gap: 10px; overflow-x: auto; scrollbar-width: thin; }
.strip li { flex: 0 0 auto; }

.row { display: flex; align-items: center; gap: 10px; width: 230px; border: 1px solid #eef0f3; background: #fff; border-radius: 10px; padding: 10px 12px; cursor: pointer; text-align: left; font: inherit; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.row:hover { border-color: color-mix(in srgb, var(--brand-primary) 45%, #fff); }

.avatar { position: relative; width: 40px; height: 40px; border-radius: 8px; overflow: visible; flex: 0 0 auto; }
.avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }

.dot { position: absolute; right: -2px; bottom: -2px; width: 11px; height: 11px; border-radius: 50%; border: 2px solid #fff; background: #cbd5e1; }
.dot.on { background: #22c55e; }

.who { min-width: 0; display: flex; flex-direction: column; }
.name { font-size: .88rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.match { color: #64748b; font-size: .76rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
