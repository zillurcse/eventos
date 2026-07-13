import { toast } from 'vue-sonner'

/**
 * The PREVIOUS EXHIBITORS picker: exhibitors the organizer has run at their
 * other events, and the import of the chosen ones into this event.
 *
 * Candidates come back deduplicated per company (most recent appearance) and
 * flagged `already_added` when they are in this event already — those stay
 * visible, greyed out, because "why isn't Acme in the list?" is a worse
 * experience than seeing them there, plainly marked.
 */
export interface ImportCandidate {
  id: string
  name: string
  email: string | null
  type: string
  logo_url: string | null
  package_name: string | null
  event: { id: string, name: string, starts_at: string | null }
  counts: { members: number, products: number, documents: number, projects: number }
  already_added: boolean
}

export function usePreviousExhibitors(eventId: string, onImported: () => void) {
  const api = useApi()

  const open = ref(false)
  const loading = ref(false)
  const importing = ref(false)
  const error = ref('')

  const candidates = ref<ImportCandidate[]>([])
  const selected = ref<string[]>([])
  const search = ref('')

  /** What travels with the exhibitor. Their team is the usual reason to import
   *  at all — a returning company keeps the same booth staff — so it defaults on. */
  const include = reactive({ members: true, products: false, documents: false, projects: false })

  const importable = computed(() => candidates.value.filter(c => !c.already_added))

  const visible = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return candidates.value
    return candidates.value.filter(c =>
      c.name.toLowerCase().includes(q)
      || c.email?.toLowerCase().includes(q)
      || c.event.name.toLowerCase().includes(q),
    )
  })

  const allSelected = computed(() =>
    importable.value.length > 0 && selected.value.length === importable.value.length,
  )

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const r = await api<{ data: ImportCandidate[] }>(`/exhibitors/importable?event=${eventId}`)
      candidates.value = r.data
    } catch (e) {
      error.value = exhibitorError(e, 'Could not load your previous exhibitors.')
    } finally {
      loading.value = false
    }
  }

  function openPicker() {
    open.value = true
    selected.value = []
    search.value = ''
    load()
  }

  function closePicker() {
    open.value = false
  }

  function toggle(c: ImportCandidate) {
    if (c.already_added) return
    selected.value = selected.value.includes(c.id)
      ? selected.value.filter(id => id !== c.id)
      : [...selected.value, c.id]
  }

  function toggleAll() {
    selected.value = allSelected.value ? [] : importable.value.map(c => c.id)
  }

  async function runImport() {
    if (!selected.value.length || importing.value) return

    importing.value = true
    error.value = ''
    try {
      const r = await api<{ meta: { imported: number, skipped: { name: string, reason: string }[] } }>(
        '/exhibitors/import',
        { method: 'POST', body: { event: eventId, exhibitors: selected.value, include } },
      )

      const { imported, skipped } = r.meta
      toast.success(
        `${imported} exhibitor${imported === 1 ? '' : 's'} imported`,
        skipped.length
          ? { description: `${skipped.length} skipped — already in this event.` }
          : undefined,
      )

      open.value = false
      onImported() // refresh the table behind the drawer
    } catch (e) {
      error.value = exhibitorError(e, 'Could not import.')
      toast.error(error.value)
    } finally {
      importing.value = false
    }
  }

  return {
    open, loading, importing, error,
    candidates, visible, importable, selected, search, include, allSelected,
    openPicker, closePicker, toggle, toggleAll, runImport,
  }
}
