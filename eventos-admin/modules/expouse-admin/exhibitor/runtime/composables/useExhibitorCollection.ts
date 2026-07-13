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

  const base = () => `/exhibitors/${exhibitorId.value}/${options.path}`

  function reset() {
    Object.assign(form, options.blank)
  }

  async function add() {
    if (!exhibitorId.value || !form[options.required]) return

    error.value = ''
    saving.value = true
    try {
      const body = options.toBody ? options.toBody(form) : { ...form }
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
    if (!exhibitorId.value || !confirm(options.confirmText(item))) return

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
  }

  return { items, form, add, remove, reset, set }
}
