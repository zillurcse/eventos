<script setup lang="ts">
import type { ReceptionPartner } from '~/stores/reception'

const props = defineProps<{ title: string, partners: ReceptionPartner[], limit?: number, type?: 'exhibitor' | 'sponsor' }>()

const visible = computed(() => props.limit ? props.partners.slice(0, props.limit) : props.partners)

function subtitle(p: ReceptionPartner): string {
  return [p.booth, p.type ? p.type[0]!.toUpperCase() + p.type.slice(1) : ''].filter(Boolean).join(', ')
}
</script>

<template>
  <section class="partners-featured">
    <header class="head">
      <h2>Featured {{ title }} ({{ partners.length }})</h2>
    </header>

    <div class="grid">
      <article v-for="p in visible" :key="p.id" class="pcard">
        <div class="banner">
          <AppImage :src="p.logo_url" :alt="p.name" class="banner-logo" />
        </div>

        <div class="foot">
          <div class="logo-box">
            <AppImage :src="p.logo_url" :alt="p.name" />
          </div>
          <div class="info">
            <h3 class="name">{{ p.name }}</h3>
            <span v-if="subtitle(p)" class="sub">{{ subtitle(p) }}</span>
          </div>
        </div>
      </article>
    </div>

    <div class="viewall">
      <span class="line" />
      <NuxtLink :to="{ path: '/exhibitors', query: type ? { type } : undefined }" class="viewall-btn">View all {{ title.toLowerCase() }}</NuxtLink>
      <span class="line" />
    </div>
  </section>
</template>

<style scoped>
.partners-featured {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: #4D5154;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 20px;
}

.pcard {
  background: #fff;
  border: 1px solid #eef0f3;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.banner {
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, color-mix(in srgb, var(--brand-primary) 16%, #fff), color-mix(in srgb, var(--brand-primary) 4%, #fff));
}

.banner-logo {
  max-width: 100%;
  width: 100%;
  max-height: 160px;
  object-fit: cover;
}

.banner-ph {
  font-size: 2.4rem;
  font-weight: 800;
  color: var(--brand-primary);
}

.foot {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
}

.logo-box {
  flex: 0 0 48px;
  width: 48px;
  height: 48px;
  border-radius: 8px;
  border: 1px solid #E8E8EE;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.logo-box img {
  max-width: 80%;
  max-height: 80%;
  object-fit: contain;
}

.logo-box span {
  font-weight: 800;
  color: var(--brand-primary);
  font-size: .9rem;
}

.info {
  min-width: 0;
}

.name {
  margin: 0;
  font-size: 16px;
  line-height: 1.4;
  font-weight: 700;
  color: #212529;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sub {
  display: block;
  color: #64676A;
  font-size: 14px;
  line-height: 1.2;
  margin-top: 3px;
}

.viewall {
  display: flex;
  align-items: center;
  gap: 24px;
  margin-top: 8px;
}

.viewall .line {
  flex: 1;
  height: 1px;
  background: #D1D2DE;
}

.viewall-btn {
  flex: 0 0 auto;
  padding: 8px 16px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
  color: var(--brand-primary);
  font-weight: 700;
  font-size: .88rem;
  text-decoration: none;
  text-transform: capitalize;
}

.viewall-btn:hover {
  background: color-mix(in srgb, var(--brand-primary) 18%, #fff);
}
</style>
