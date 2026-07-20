import type { InjectionKey } from 'vue'
import { toast } from 'vue-sonner'

/**
 * State + data layer for the exhibitor management screen. Created once in
 * <ExhibitorManager> and shared with the table / drawer / tab components via
 * provide/inject (ExhibitorKey). The components stay presentational.
 *
 * This file orchestrates; it deliberately holds no mechanics of its own:
 *   - the table (search / filters / paging)      → useExhibitorTable
 *   - each edit tab's list (members, docs, …)    → useExhibitorCollection
 *   - the reset-password modal                   → useExhibitorPasswordReset
 *   - draft ⇄ API row mapping                    → utils/exhibitor (pure)
 */
export function useExhibitorManager(eventId: string) {
  const api = useApi()
  const { upload } = useUpload()

  // ── List + meta ──────────────────────────────────────────────────────
  const exhibitors = ref<Exhibitor[]>([])
  const packages = ref<ExhibitorPackage[]>([])
  const filters = ref<EventFilter[]>([])

  // ── Drawer / editing ─────────────────────────────────────────────────
  const drawerMode = ref<'add' | 'edit' | null>(null)
  const editingId = ref<string | null>(null)
  const activeTab = ref('Details')
  const saving = ref(false)
  const error = ref('')
  const draft = reactive<Draft>(freshDraft())

  const spotlightUploading = ref(false)
  const tagInput = ref('')

  // ── Edit tabs ────────────────────────────────────────────────────────
  // One saving/error pair across the tabs: only one form is on screen at a time.
  const subSaving = ref(false)
  const subError = ref('')
  const entitlements = ref<FeatureLine[]>([])

  const memberList = useExhibitorCollection<ExhibitorMember, typeof MEMBER_FORM>(editingId, subSaving, subError, {
    path: 'members',
    blank: MEMBER_FORM,
    required: 'email',
    // Buffered display row (add drawer) — mirror the shape the API would return.
    toItem: (f, id) => ({
      id,
      role: f.role,
      contact: { name: [f.first_name, f.last_name].filter(Boolean).join(' ') || undefined, email: f.email },
    }),
    confirmText: m => `Remove ${m.contact?.email || 'this member'}?`,
    noun: 'member',
  })
  const documentList = useExhibitorCollection<ExhibitorDocument, typeof DOC_FORM>(editingId, subSaving, subError, {
    path: 'documents',
    blank: DOC_FORM,
    required: 'title',
    toItem: (f, id) => ({ id, title: f.title, url: f.url }),
    confirmText: d => `Remove "${d.title}"?`,
    noun: 'document',
  })
  const projectList = useExhibitorCollection<ExhibitorProject, typeof PROJECT_FORM>(editingId, subSaving, subError, {
    path: 'projects',
    blank: PROJECT_FORM,
    required: 'name',
    toItem: (f, id) => ({ id, name: f.name, description: f.description, status: f.status }),
    confirmText: p => `Remove "${p.name}"?`,
    noun: 'project',
  })
  const productList = useExhibitorCollection<ExhibitorProduct, typeof PRODUCT_FORM>(editingId, subSaving, subError, {
    path: 'products',
    blank: PRODUCT_FORM,
    required: 'name',
    // The form takes dollars; the API stores cents. The builder fields (image,
    // button, attachment, job-offer flag) live in the product's meta jsonb.
    toBody: f => ({
      name: f.name,
      description: f.description || undefined,
      price_cents: f.price ? Math.round(Number(f.price) * 100) : undefined,
      meta: productMeta(f),
    }),
    toItem: (f, id) => ({
      id,
      name: f.name,
      description: f.description,
      price_cents: f.price ? Math.round(Number(f.price) * 100) : null,
      meta: productMeta(f),
    }),
    confirmText: p => `Remove "${p.name}"?`,
    noun: 'product',
  })

  // All buffered sub-resource lists, flushed together after a create().
  const collections = [memberList, documentList, projectList, productList]

  // ── Table ────────────────────────────────────────────────────────────
  const table = useExhibitorTable(exhibitors, packages)

  // ── Load ─────────────────────────────────────────────────────────────
  async function load() {
    try {
      exhibitors.value = (await api<{ data: Exhibitor[] }>(`/exhibitors?event=${eventId}`)).data
    } catch (e) {
      toast.error(exhibitorError(e, 'Could not load exhibitors.'))
    }
  }

  async function loadMeta() {
    try {
      const [pkgRes, settingsRes] = await Promise.all([
        api<{ data: ExhibitorPackage[] }>(`/exhibitor-packages?event=${eventId}`),
        api<{ data: { filters?: EventFilter[] } }>(`/events/${eventId}/settings`),
      ])
      packages.value = pkgRes.data
      filters.value = settingsRes.data.filters || []
    } catch (e) {
      toast.error(exhibitorError(e, 'Could not load packages and filters.'))
    }
  }

  function init() {
    load()
    loadMeta()
  }

  // ── Open drawers ─────────────────────────────────────────────────────
  function openAdd() {
    Object.assign(draft, freshDraft())
    editingId.value = null
    activeTab.value = 'Details'
    error.value = ''
    subError.value = ''
    // The tabs are live in the add drawer now: start every sub-list empty and
    // its form blank so nothing carries over from a previous edit/add.
    for (const c of collections) { c.set([]); c.reset() }
    entitlements.value = mergeFeatures(null)
    drawerMode.value = 'add'
  }

  // The record currently open in the full-page edit screen. Drives the top-bar
  // Deactivate toggle and Reset Password button (which need its status/email).
  const current = ref<Exhibitor | null>(null)

  /**
   * Load one exhibitor by uuid into the draft + sub-lists for the full-page
   * editor. Renders on the loaded-but-empty state first (the page is on screen
   * while the full record and its sub-resources are still in flight).
   */
  async function loadForEdit(uuid: string) {
    editingId.value = uuid
    activeTab.value = 'Details'
    error.value = ''
    subError.value = ''
    current.value = null
    Object.assign(draft, freshDraft())
    for (const c of collections) { c.set([]); c.reset() }
    entitlements.value = mergeFeatures(null)
    drawerMode.value = 'edit'

    try {
      const full = (await api<{ data: Exhibitor }>(`/exhibitors/${uuid}`)).data
      current.value = full
      Object.assign(draft, draftFromExhibitor(full))
      memberList.set(full.members ?? [])
      documentList.set(full.documents ?? [])
      projectList.set(full.projects ?? [])
      productList.set(full.products ?? [])
      entitlements.value = mergeFeatures(full.entitlements ?? null)
    } catch (err) {
      error.value = exhibitorError(err, 'Could not load this exhibitor.')
    }
  }

  // Name + package are required; an admin email is optional but, if given, valid.
  const canCreate = computed(() =>
    !!draft.name.trim()
    && !!draft.package_id
    && (!draft.email.trim() || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(draft.email.trim())))

  // ── Create / update / delete ─────────────────────────────────────────
  async function create() {
    error.value = ''
    saving.value = true
    const adminEmail = draft.email
    try {
      // Entitlements ride along in the create payload (stored in profile_data);
      // the sub-resources (members/docs/…) need the new id, so they're flushed
      // once we have it.
      const res = await api<{ data: { id: string }, admin_invited?: boolean }>('/exhibitors', {
        method: 'POST',
        body: { ...draftToPayload(draft, eventId), entitlements: JSON.parse(JSON.stringify(entitlements.value)) },
      })
      const newId = res.data.id
      const flushError = await flushCollections(newId)

      drawerMode.value = null
      await load()
      toast.success('Exhibitor created', res?.admin_invited
        ? { description: `A 6-digit access code was emailed to ${adminEmail}.` }
        : undefined)
      // The exhibitor exists; only some buffered sub-items failed. Say so rather
      // than let them vanish silently.
      if (flushError) toast.error(flushError)
    } catch (e) {
      error.value = exhibitorError(e, 'Could not create.')
      toast.error(error.value)
    } finally {
      saving.value = false
    }
  }

  /** POST every buffered sub-resource against the new exhibitor. Returns a
   *  user-facing message if any collection failed, else ''. */
  async function flushCollections(newId: string): Promise<string> {
    let message = ''
    for (const c of collections) {
      try {
        await c.flush(newId)
      } catch (e) {
        if (!message) message = exhibitorError(e, 'Exhibitor created, but some items could not be saved.')
      }
    }
    return message
  }

  async function update(): Promise<boolean> {
    error.value = ''
    saving.value = true
    try {
      const res = await api<{ data: Exhibitor }>(`/exhibitors/${editingId.value}`, { method: 'PUT', body: draftToPayload(draft, eventId) })
      // Keep the top-bar record (status/name/email) in step with what was saved.
      if (res?.data) current.value = res.data
      drawerMode.value = null
      await load()
      toast.success('Exhibitor updated')
      return true
    } catch (e) {
      error.value = exhibitorError(e, 'Could not update.')
      toast.error(error.value)
      return false
    } finally {
      saving.value = false
    }
  }

  async function remove(e: Exhibitor) {
    if (!confirm(`Delete "${e.name}"?`)) return
    try {
      await api(`/exhibitors/${e.id}`, { method: 'DELETE' })
      await load()
      toast.success('Exhibitor deleted')
    } catch (err) {
      toast.error(exhibitorError(err, 'Could not delete.'))
    }
  }

  async function toggleStatus(e: Exhibitor) {
    const status = isActive(e) ? 'suspended' : 'active'
    table.closeActions()
    try {
      await api(`/exhibitors/${e.id}`, { method: 'PUT', body: { status } })
      await load()
      toast.success(status === 'active' ? 'Exhibitor activated' : 'Exhibitor deactivated')
    } catch (err) {
      toast.error(exhibitorError(err, 'Could not update status.'))
    }
  }

  async function savePermissions() {
    subError.value = ''
    subSaving.value = true
    try {
      await api(`/exhibitors/${editingId.value}`, {
        method: 'PUT',
        body: { entitlements: JSON.parse(JSON.stringify(entitlements.value)) },
      })
      toast.success('Permissions saved')
    } catch (e) {
      subError.value = exhibitorError(e, 'Could not save permissions.')
      toast.error(subError.value)
    } finally {
      subSaving.value = false
    }
  }

  // ── Reset password ───────────────────────────────────────────────────
  const reset = useExhibitorPasswordReset(table.closeActions)

  // ── Previous exhibitors (import from the organizer's other events) ───
  const previous = usePreviousExhibitors(eventId, load)

  // ── Uploads / tags / CTA ─────────────────────────────────────────────
  async function pickSpotlight(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return

    spotlightUploading.value = true
    try {
      const r = await upload(file, { collection: 'exhibitor_spotlight' })
      draft.spotlight_url = r.url
      draft.spotlight_file_id = r.id
    } catch (err) {
      error.value = exhibitorError(err, 'Could not upload the spotlight file.')
    } finally {
      spotlightUploading.value = false
    }
  }

  function addTag(e: KeyboardEvent) {
    if (e.key !== 'Enter') return
    e.preventDefault()
    const tag = tagInput.value.trim()
    if (tag && !draft.tags.includes(tag)) draft.tags.push(tag)
    tagInput.value = ''
  }

  function removeTag(tag: string) {
    draft.tags = draft.tags.filter(t => t !== tag)
  }

  function addCta() {
    draft.cta.push({ id: `cta_${Date.now()}`, type: 'TEXT', label: '', value: '', open: false })
  }

  return {
    // list + meta
    eventId, exhibitors, packages, filters,
    // drawer / editing
    drawerMode, editingId, activeTab, saving, error, draft, spotlightUploading, tagInput, canCreate, current,
    init, load, loadMeta, openAdd, loadForEdit, create, update, remove, toggleStatus,
    pickSpotlight, addTag, removeTag, addCta,
    // table (search / filters / paging / row menu)
    ...table,
    // edit tabs — the components see the same flat names they always have
    subSaving, subError, entitlements, savePermissions,
    members: memberList.items,
    memberForm: memberList.form,
    addMember: memberList.add,
    removeMember: memberList.remove,
    documents: documentList.items,
    docForm: documentList.form,
    addDocument: documentList.add,
    removeDocument: documentList.remove,
    projects: projectList.items,
    projectForm: projectList.form,
    addProject: projectList.add,
    removeProject: projectList.remove,
    products: productList.items,
    productForm: productList.form,
    addProduct: productList.add,
    removeProduct: productList.remove,
    // previous exhibitors — the picker owns its own state; the table only opens it
    previous,
    // reset password
    resetTarget: reset.target,
    resetMode: reset.mode,
    resetPassword: reset.password,
    resetMustChange: reset.mustChange,
    resetSaving: reset.saving,
    resetError: reset.error,
    resetResult: reset.result,
    openResetPassword: reset.open,
    closeResetPassword: reset.close,
    submitResetPassword: reset.submit,
  }
}

export type ExhibitorManager = ReturnType<typeof useExhibitorManager>

/**
 * Symbol.for, not Symbol: the injection key has to survive this module being
 * evaluated more than once. A plain Symbol() is a fresh identity every time the
 * module is re-instantiated — which HMR does on every edit to this file — so
 * <ExhibitorManager> would still be providing under the *old* symbol while its
 * children injected with the new one and got nothing ("must be used within
 * <ExhibitorManager>" on a page that was working a second ago). Symbol.for
 * looks the key up in the global registry, so every instance agrees.
 */
export const ExhibitorKey: InjectionKey<ExhibitorManager> = Symbol.for('expouse.exhibitor-manager')

/**
 * Convenience inject for child components.
 *
 * The failure message names the component that could not find the context: this
 * is otherwise a needle-in-a-haystack in a SPA, where the throw surfaces as a
 * bare error page with no component in sight.
 */
export function useExhibitorContext(): ExhibitorManager {
  const ctx = inject(ExhibitorKey)

  if (!ctx) {
    const where = getCurrentInstance()?.type.__name ?? 'an exhibitor component'
    throw new Error(
      `<${where}> could not find the exhibitor context — it must be rendered inside <ExhibitorManager>. `
      + '(If this appeared after an edit, the dev server is serving a stale module: hard-reload the page.)',
    )
  }

  return ctx
}
