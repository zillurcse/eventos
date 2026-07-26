import type { Ref } from 'vue'

/**
 * The exhibitors table: search, the two filters, and the per-row ACTIONS menu.
 *
 * Filtering is client-side because the list is capped at EXHIBITOR_LIMIT per
 * event. Paging is <DataTable>'s job — it slices whatever list it is given, so
 * we hand it `filtered` and keep no page state of our own.
 */
export function useExhibitorTable(exhibitors: Ref<Exhibitor[]>, packages: Ref<ExhibitorPackage[]>) {
  const search = ref('')
  const filterType = ref('')
  const filterPackage = ref('')
  const actionsOpenId = ref<string | null>(null)
  // The actions menu is teleported to <body> (DataTable clips overflow), so track
  // the trigger button's viewport position to anchor the menu there.
  const actionsAnchor = ref<{ top: number; right: number } | null>(null)

  const filtered = computed(() => {
    const q = search.value.trim().toLowerCase()

    return exhibitors.value.filter((e) => {
      if (q && !e.name?.toLowerCase().includes(q)) return false
      if (filterType.value && e.type !== filterType.value.toLowerCase()) return false
      if (filterPackage.value && !sameId(e.package_id, filterPackage.value)) return false
      return true
    })
  })

  function packageName(id: Exhibitor['package_id']) {
    const pkg = packages.value.find(p => sameId(p.id, id))
    return pkg?.name || (id ? String(id) : '—')
  }

  function resetFilters() {
    search.value = ''
    filterType.value = ''
    filterPackage.value = ''
  }

  function toggleActions(id: string, ev: MouseEvent) {
    if (actionsOpenId.value === id) {
      closeActions()
      return
    }
    actionsOpenId.value = id
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    actionsAnchor.value = { top: rect.bottom + 4, right: window.innerWidth - rect.right }
  }

  function closeActions() {
    actionsOpenId.value = null
    actionsAnchor.value = null
  }

  // Fixed-position teleported menu lives outside the table's own DOM, so close
  // it on any window click/scroll rather than relying solely on a root click.
  const onWindowClick = () => closeActions()
  const onWindowScroll = () => closeActions()
  onMounted(() => {
    window.addEventListener('click', onWindowClick)
    window.addEventListener('scroll', onWindowScroll, true)
  })
  onBeforeUnmount(() => {
    window.removeEventListener('click', onWindowClick)
    window.removeEventListener('scroll', onWindowScroll, true)
  })

  return {
    search, filterType, filterPackage, actionsOpenId, actionsAnchor,
    filtered, packageName, resetFilters, toggleActions, closeActions,
  }
}
