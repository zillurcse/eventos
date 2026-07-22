/**
 * Typeahead for merge tags inside a contenteditable region.
 *
 * Typing `{{` opens a filtered list of valid tokens; choosing one replaces the
 * partial trigger with a complete `{{ token }}`. This exists because the
 * variable *menu* only helps people who already know a token is available —
 * autocomplete surfaces the catalogue at the moment of writing, and stops
 * hand-typed tokens (which silently render blank) from reaching a send.
 */
import type { VarGroup } from '../components/mail/MailVariableMenu.vue'

export interface VarSuggestion {
  token: string
  label: string
  sample: string
  group: string
}

/** The partial token being typed, e.g. "{{ contact.fi" → "contact.fi". */
const TRIGGER = /\{\{\s*([\w.]*)$/

export function useMergeAutocomplete(groups: Ref<VarGroup[]>) {
  const open = ref(false)
  const query = ref('')
  const activeIndex = ref(0)
  const position = reactive({ top: 0, left: 0 })

  /** Length of the matched `{{…` trigger, so selection can replace exactly it. */
  let triggerLength = 0
  let editable: HTMLElement | null = null

  const all = computed<VarSuggestion[]>(() =>
    groups.value.flatMap(g => g.variables.map(v => ({ ...v, group: g.label }))),
  )

  const matches = computed<VarSuggestion[]>(() => {
    const q = query.value.toLowerCase()
    const list = q
      ? all.value.filter(v => v.token.toLowerCase().includes(q) || v.label.toLowerCase().includes(q))
      : all.value
    return list.slice(0, 8)
  })

  function close() {
    open.value = false
    query.value = ''
    activeIndex.value = 0
    triggerLength = 0
  }

  /**
   * Re-evaluate after every keystroke. Reads the text between the start of the
   * caret's text node and the caret, which is enough context for the trigger
   * without walking the whole subtree.
   */
  function update(el: HTMLElement) {
    editable = el
    const selection = window.getSelection()
    if (!selection?.rangeCount || !el.contains(selection.anchorNode)) return close()

    const range = selection.getRangeAt(0)
    const before = range.startContainer.textContent?.slice(0, range.startOffset) ?? ''
    const match = TRIGGER.exec(before)

    if (!match) return close()

    query.value = match[1] ?? ''
    triggerLength = match[0].length
    activeIndex.value = 0
    open.value = true

    // Anchor the popup to the caret, falling back to the block when the caret
    // has no rect (an empty line reports a zero-size range).
    const rect = range.getClientRects()[0] ?? el.getBoundingClientRect()
    position.top = rect.bottom + 4
    position.left = rect.left
  }

  /** Replace the partial trigger with the finished token. */
  function select(suggestion?: VarSuggestion) {
    const chosen = suggestion ?? matches.value[activeIndex.value]
    if (!chosen || !editable) return close()

    const selection = window.getSelection()
    if (!selection?.rangeCount) return close()

    const range = selection.getRangeAt(0)
    // Walk the caret back over the `{{…` the user typed, then overwrite it.
    range.setStart(range.startContainer, Math.max(0, range.startOffset - triggerLength))
    range.deleteContents()
    range.insertNode(document.createTextNode(`{{ ${chosen.token} }}`))
    range.collapse(false)
    selection.removeAllRanges()
    selection.addRange(range)

    close()
    return true
  }

  /**
   * Keyboard handling while the list is open. Returns true when the key was
   * consumed, so the caller knows to preventDefault and not type the character.
   */
  function onKeydown(e: KeyboardEvent): boolean {
    if (!open.value || !matches.value.length) return false

    switch (e.key) {
      case 'ArrowDown':
        activeIndex.value = (activeIndex.value + 1) % matches.value.length
        return true
      case 'ArrowUp':
        activeIndex.value = (activeIndex.value - 1 + matches.value.length) % matches.value.length
        return true
      case 'Enter':
      case 'Tab':
        select()
        return true
      case 'Escape':
        close()
        return true
      default:
        return false
    }
  }

  return { open, query, matches, activeIndex, position, update, select, close, onKeydown }
}
