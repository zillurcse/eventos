<script setup lang="ts">
import type { MeetingAreaTable } from '~/stores/meetings'

defineProps<{ tables: MeetingAreaTable[] }>()

function fmtDate(iso: string): string {
  const [y, m, dd] = iso.split('-').map(Number)
  const d = new Date(y ?? 1970, (m ?? 1) - 1, dd ?? 1)
  return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
}

function upcoming(bookings: MeetingAreaTable['bookings']) {
  const now = new Date()
  const today = now.toISOString().slice(0, 10)
  return bookings
    .filter(b => b.date >= today)
    .sort((a, b) => `${a.date}${a.slot}`.localeCompare(`${b.date}${b.slot}`))
    .slice(0, 3)
}
</script>

<template>
  <section class="map">
    <div class="head">
      <h2>Meeting Area</h2>
      <p>Tables and upcoming bookings</p>
    </div>

    <div v-if="!tables.length" class="empty">No meeting tables configured yet.</div>

    <ul v-else class="tables">
      <li v-for="t in tables" :key="t.id" class="table">
        <div class="top">
          <span class="dot" :class="t.design" />
          <div class="meta">
            <strong>{{ t.name }}</strong>
            <small>{{ t.capacity }} seats · {{ t.design }}</small>
          </div>
        </div>

        <div v-if="t.image_url" class="thumb">
          <img :src="t.image_url" :alt="t.name">
        </div>

        <div v-if="upcoming(t.bookings).length" class="bookings">
          <span
            v-for="b in upcoming(t.bookings)" :key="`${b.date}|${b.slot}`"
            class="booking" :class="b.status"
          >
            {{ fmtDate(b.date) }} · {{ b.slot }}
          </span>
        </div>
        <p v-else class="free">Available</p>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.map {
  background: #fff;
  border-radius: 14px;
  padding: 20px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.head h2 {
  margin: 0;
  font-size: .92rem;
  font-weight: 800;
  color: #1e293b;
}

.head p {
  margin: 4px 0 0;
  font-size: .78rem;
  color: #94a3b8;
}

.empty {
  margin-top: 16px;
  text-align: center;
  color: #94a3b8;
  font-size: .84rem;
}

.tables {
  list-style: none;
  margin: 16px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.table {
  border: 1px solid #eef0f3;
  border-radius: 12px;
  padding: 12px;
}

.top {
  display: flex;
  align-items: center;
  gap: 10px;
}

.dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--brand-primary);
  flex: 0 0 auto;
}

.dot.boardroom { background: #6366f1; }
.dot.lounge { background: #14b8a6; }

.meta strong {
  display: block;
  font-size: .86rem;
  color: #1e293b;
}

.meta small {
  color: #94a3b8;
  font-size: .74rem;
  text-transform: capitalize;
}

.thumb {
  margin-top: 10px;
  border-radius: 10px;
  overflow: hidden;
  max-height: 72px;
}

.thumb img {
  width: 100%;
  height: 72px;
  object-fit: cover;
}

.bookings {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 10px;
}

.booking {
  font-size: .72rem;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 999px;
  background: #fff6e5;
  color: #d97706;
}

.booking.confirmed {
  background: #e6f9f0;
  color: #16a34a;
}

.free {
  margin: 10px 0 0;
  font-size: .76rem;
  color: #16a34a;
  font-weight: 600;
}
</style>
