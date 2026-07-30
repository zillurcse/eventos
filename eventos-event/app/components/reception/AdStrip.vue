<script setup lang="ts">
import type { ReceptionAd } from '~/stores/reception'

const props = defineProps<{ ads: ReceptionAd[] }>()

interface Banner { key: string, src: string, href: string | null, alt: string }

const banners = computed<Banner[]>(() =>
  props.ads.flatMap(ad =>
    (ad.images || [])
      .filter(img => (img.is_active ?? true) && (img.image_url || img.url))
      .map((img, i) => ({
        key: `${ad.id}-${i}`,
        src: (img.image_url || img.url) as string,
        href: (img.redirect_url as string) || null,
        alt: ad.title,
      })),
  ),
)
</script>

<template>
  <div v-if="banners.length" class="strip">
    <component
      :is="b.href ? 'a' : 'div'"
      v-for="b in banners"
      :key="b.key"
      class="banner"
      :href="b.href || undefined"
      :target="b.href ? '_blank' : undefined"
      rel="noopener"
    >
      <img :src="b.src" :alt="b.alt" />
    </component>
  </div>
</template>

<style scoped>
.strip { display: flex; flex-direction: column; gap: 12px; }
.banner { display: block; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.banner img { display: block; width: 100%;max-height: 120px;  height: auto; object-fit: cover; }

:global(html[data-theme="minimal"]) .strip { gap: 8px; }
:global(html[data-theme="minimal"]) .banner {
  border-radius: var(--theme-radius, 8px);
  box-shadow: none;
  border: 1px solid var(--line, #e2e8f0);
}
:global(html[data-theme="minimal"]) .banner img { max-height: 88px; }

:global(html[data-theme="modern"]) .strip { gap: 16px; }
:global(html[data-theme="modern"]) .banner {
  border-radius: var(--theme-radius, 18px);
  box-shadow: var(--theme-shadow, 0 8px 28px rgba(15, 23, 42, 0.06));
}
:global(html[data-theme="modern"]) .banner img { max-height: 140px; }
</style>
