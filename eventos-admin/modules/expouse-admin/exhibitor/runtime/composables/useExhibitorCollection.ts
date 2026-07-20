import type { Ref } from 'vue'

/**
 * One sub-resource of an exhibitor — members, documents, projects or products.
 *
 * All four behaved identically (add from a small form, remove with a confirm,
 * sharing one saving/error pair) and were written out four times. This is that
 * behaviour once, parameterised by the endpoint, the form and how the form maps
 * onto the request body.
 *
 * The list is kept client-side rather than re-fetched: the edit drawer already
 * loaded it with the exhibitor, and a POST/DELETE returns enough to patch it.
 */
export interface CollectionOptions<TItem, TForm extends object> {
  /** Path segment under /exhibitors/{id}/ — e.g. 'members'. */
  path: string
  /** Empty form / the values to restore after a successful add. */
  blank: TForm
  /** Which field must be filled before Add is allowed. */
  required: keyof TForm
  /** Form → request body. Defaults to the form as-is. */
  toBody?: (form: TForm) => Record<string, unknown>
  /** Form → display item, used while buffering in "add" mode (before the
   *  exhibitor exists, so the POST that would return the real item can't run
   *  yet). Given a negative temporary id to key the row on. */
  toItem: (form: TForm, tempId: number) => TItem
  /** Confirm text for a removal. */
  confirmText: (item: TItem) => string
  /** Singular noun for error messages ("Could not add {noun}."). */
  noun: string
}

export interface ExhibitorCollection<TItem, TForm extends object> {
  items: Ref<TItem[]>
  form: TForm
  add: () => Promise<void>
  remove: (item: TItem) => Promise<void>
  reset: () => void
  set: (items: TItem[]) => void
  /** POST every buffered item against a freshly-created exhibitor. */
  flush: (exhibitorId: string) => Promise<void>
}

/**
 * @param exhibitorId  the exhibitor being edited (null while the drawer is closed)
 * @param saving/error shared with the other collections, so the drawer shows one
 *                     spinner and one message regardless of which tab is open
 */
export function useExhibitorCollection<TItem extends { id: number }, TForm extends object>(
  exhibitorId: Ref<string | null>,
  saving: Ref<boolean>,
  error: Ref<string>,
  options: CollectionOptions<TItem, TForm>,
): ExhibitorCollection<TItem, TForm> {
  const api = useApi()

  const items = ref([]) as Ref<TItem[]>
  const form = reactive({ ...options.blank }) as TForm

  // Items added before the exhibitor exists (the "add" drawer). Each holds the
  // request body to POST once we have an id; the matching display row lives in
  // `items` keyed on the same negative tempId. Flushed by create().
  const pending = ref<{ tempId: number, body: Record<string, unknown> }[]>([])
  let tempSeq = -1

  const base = () => `/exhibitors/${exhibitorId.value}/${options.path}`

  function reset() {
    Object.assign(form, options.blank)
  }

  async function add() {
    if (!form[options.required]) return

    const body = options.toBody ? options.toBody(form) : { ...form }

    // No exhibitor yet → buffer locally; create() will flush these.
    if (!exhibitorId.value) {
      const tempId = tempSeq--
      items.value.push(options.toItem(form, tempId))
      pending.value.push({ tempId, body })
      reset()
      return
    }

    error.value = ''
    saving.value = true
    try {
      const r = await api<{ data: TItem }>(base(), { method: 'POST', body })
      items.value.push(r.data)
      reset()
    } catch (e) {
      error.value = exhibitorError(e, `Could not add ${options.noun}.`)
    } finally {
      saving.value = false
    }
  }

  async function remove(item: TItem) {
    if (!confirm(options.confirmText(item))) return

    // Buffered row (never persisted) → drop it locally, nothing to DELETE.
    if (item.id < 0 || !exhibitorId.value) {
      items.value = items.value.filter(x => x.id !== item.id)
      pending.value = pending.value.filter(p => p.tempId !== item.id)
      return
    }

    try {
      await api(`${base()}/${item.id}`, { method: 'DELETE' })
      items.value = items.value.filter(x => x.id !== item.id)
    } catch (e) {
      // A failed delete used to vanish silently, leaving the row on screen with
      // no explanation — say so instead.
      error.value = exhibitorError(e, `Could not remove ${options.noun}.`)
    }
  }

  function set(next: TItem[]) {
    items.value = next ?? []
    pending.value = []
  }

  async function flush(id: string) {
    if (!pending.value.length) return
    const path = `/exhibitors/${id}/${options.path}`
    for (const p of pending.value) {
      await api(path, { method: 'POST', body: p.body })
    }
    pending.value = []
  }

  return { items, form, add, remove, reset, set, flush }
}
