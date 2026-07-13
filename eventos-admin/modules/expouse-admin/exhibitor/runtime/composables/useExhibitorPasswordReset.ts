import { toast } from 'vue-sonner'

/**
 * The Reset Password modal (Exhibitors › ACTIONS › Reset Password).
 *
 * Two modes. `auto` has the server generate a password and hand it back once,
 * which we reveal for copying — hence the second step (`result`) rather than
 * closing the modal on success. `manual` sets one the organizer typed and never
 * shows anything back.
 */
export function useExhibitorPasswordReset(onDone: () => void) {
  const api = useApi()

  const target = ref<Exhibitor | null>(null)
  const mode = ref<'auto' | 'manual'>('auto')
  const password = ref('')
  const mustChange = ref(true)
  const saving = ref(false)
  const error = ref('')
  const result = ref<ResetResult | null>(null) // step 2 (auto mode only)

  const MIN_LENGTH = 8

  function open(exhibitor: Exhibitor) {
    target.value = exhibitor
    mode.value = 'auto'
    password.value = ''
    mustChange.value = true
    error.value = ''
    result.value = null
    onDone() // close the row's ACTIONS menu behind the modal
  }

  function close() {
    target.value = null
    result.value = null
  }

  async function submit() {
    if (!target.value) return

    if (mode.value === 'manual' && password.value.length < MIN_LENGTH) {
      error.value = `Password must have at least ${MIN_LENGTH} characters`
      return
    }

    error.value = ''
    saving.value = true
    try {
      const body: Record<string, unknown> = { mode: mode.value, must_change: mustChange.value }
      if (mode.value === 'manual') body.password = password.value

      const r = await api<{ data: ResetResult }>(
        `/exhibitors/${target.value.id}/reset-password`,
        { method: 'POST', body },
      )

      if (mode.value === 'auto') {
        result.value = r.data // reveal the generated credentials
      } else {
        toast.success('Password reset')
        target.value = null
      }
    } catch (e) {
      error.value = exhibitorError(e, 'Could not reset password.')
    } finally {
      saving.value = false
    }
  }

  return { target, mode, password, mustChange, saving, error, result, open, close, submit }
}
