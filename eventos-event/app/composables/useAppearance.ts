import type { SiteAppearance } from '~/stores/site'

/**
 * Branding › Appearance for the current event.
 * Use when branching layout/chrome by theme (Minimal / Modern / Advanced).
 */
export function useAppearance() {
  const site = useSiteStore()

  const appearance = computed<SiteAppearance>(() => site.appearance)
  const isMinimal = computed(() => appearance.value === 'minimal')
  const isModern = computed(() => appearance.value === 'modern')
  const isAdvanced = computed(() => appearance.value === 'advanced')

  return { appearance, isMinimal, isModern, isAdvanced }
}
