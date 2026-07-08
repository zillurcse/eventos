import type { InjectionKey } from 'vue'
import { toast } from 'vue-sonner'

/**
 * State + data layer for the exhibitor management screen. Created once in
 * <ExhibitorManager> and shared with the table / drawer / tab components via
 * provide/inject (ExhibitorKey). Keeps all API and mutation logic in one place;
 * the components are presentational.
 */
export function useExhibitorManager(eventId: string) {
  const api = useApi()
  const { upload } = useUpload()

  // ── List + meta ──────────────────────────────────────────────────────
  const exhibitors = ref<any[]>([])
  const packages   = ref<any[]>([])
  const filters    = ref<any[]>([])

  // ── Drawer / editing ─────────────────────────────────────────────────
  const drawerMode = ref<'add' | 'edit' | null>(null)
  const editingId  = ref<string | null>(null)
  const activeTab  = ref('Details')
  const saving     = ref(false)
  const error      = ref('')
  const draft      = reactive<Draft>(freshDraft())

  const spotlightUploading = ref(false)
  const tagInput = ref('')

  // ── Sub-resources (edit tabs) ────────────────────────────────────────
  const members      = ref<any[]>([])
  const documents    = ref<any[]>([])
  const projects     = ref<any[]>([])
  const products     = ref<any[]>([])
  const entitlements = ref<FeatureLine[]>([])
  const subSaving    = ref(false)
  const subError     = ref('')
  const memberForm   = reactive({ email: '', first_name: '', last_name: '', role: 'staff', password: '' })
  const docForm      = reactive({ title: '', url: '' })
  const projectForm  = reactive({ name: '', description: '', status: '' })
  const productForm  = reactive({ name: '', description: '', price: '' })

  // ── Table / filter / pagination ──────────────────────────────────────
  const search        = ref('')
  const filterType    = ref('')
  const filterPackage = ref('')
  const page          = ref(1)
  const perPage       = ref(10)
  const actionsOpenId = ref<string | null>(null)

  // ── Reset password modal ─────────────────────────────────────────────
  const resetTarget   = ref<any | null>(null)            // exhibitor being reset
  const resetMode     = ref<'auto' | 'manual'>('auto')
  const resetPassword = ref('')
  const resetMustChange = ref(true)
  const resetSaving   = ref(false)
  const resetError    = ref('')
  const resetResult   = ref<{ email: string, password: string } | null>(null) // step 2 (auto)

  watch(perPage, () => { page.value = 1 })
  watch([search, filterType, filterPackage], () => { page.value = 1 })

  const filtered = computed(() => {
    let list = exhibitors.value
    if (search.value) {
      const q = search.value.toLowerCase()
      list = list.filter((p: any) => p.name?.toLowerCase().includes(q))
    }
    if (filterType.value) {
      list = list.filter((p: any) => p.type === filterType.value.toLowerCase())
    }
    if (filterPackage.value) {
      // eslint-disable-next-line eqeqeq
      list = list.filter((p: any) => p.package_id == filterPackage.value)
    }
    return list
  })
  const paginated = computed(() => {
    const start = (page.value - 1) * perPage.value
    return filtered.value.slice(start, start + perPage.value)
  })
  const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))
  const paginationLabel = computed(() => {
    if (!filtered.value.length) return '0 - 0 of 0'
    const from = (page.value - 1) * perPage.value + 1
    const to   = Math.min(page.value * perPage.value, filtered.value.length)
    return `${from} - ${to} of ${filtered.value.length}`
  })

  function packageName(pid: string | number) {
    // eslint-disable-next-line eqeqeq
    return packages.value.find((p: any) => p.id == pid)?.name || (pid ? String(pid) : '—')
  }
  function resetFilters() { search.value = ''; filterType.value = ''; filterPackage.value = ''; page.value = 1 }
  function toggleActions(pid: string) { actionsOpenId.value = actionsOpenId.value === pid ? null : pid }

  // ── Load ─────────────────────────────────────────────────────────────
  async function load() {
    try { exhibitors.value = (await api<any>(`/exhibitors?event=${eventId}`)).data } catch { /* */ }
  }
  async function loadMeta() {
    try {
      const [pkgRes, settingsRes] = await Promise.all([
        api<any>(`/exhibitor-packages?event=${eventId}`),
        api<any>(`/events/${eventId}/settings`),
      ])
      packages.value = pkgRes.data
      filters.value  = settingsRes.data.filters || []
    } catch { /* */ }
  }
  function init() { load(); loadMeta() }

  // ── Open drawers ─────────────────────────────────────────────────────
  function openAdd() {
    Object.assign(draft, freshDraft()); editingId.value = null
    activeTab.value = 'Details'; error.value = ''; drawerMode.value = 'add'
  }
  function populateDraft(p: any) {
    Object.assign(draft, {
      ...freshDraft(),
      name: p.name || '', email: p.email || '',
      logo_url: p.logo_url || '', logo_file_id: p.logo_file_id ?? null,
      package_id: p.package_id ?? '', stall_no: p.stall_no || '',
      type: p.type ? p.type.charAt(0).toUpperCase() + p.type.slice(1) : 'Exhibitor',
      phone_code: p.phone_code || '+880', phone: p.phone || '',
      rating: !!p.rating, featured: !!p.featured, premium: !!p.premium,
      about: p.about || '',
      street: p.street || '', city: p.city || '', state: p.state || '',
      zip: p.zip || '', country: p.country || '',
      location_url: p.location_url || '', website_url: p.website_url || '',
      tags: Array.isArray(p.tags) ? [...p.tags] : [], filter_id: p.filter_id || '',
      spotlight_type: p.spotlight_type || 'image',
      spotlight_url: p.spotlight_url || '', spotlight_file_id: p.spotlight_file_id ?? null,
      cta: Array.isArray(p.cta) ? p.cta.map((c: any) => ({ ...c, open: false })) : [],
      social: { facebook: '', linkedin: '', twitter: '', instagram: '', whatsapp: '', youtube: '', ...(p.social || {}) },
      contact: { full_name: '', company_name: '', position: '', email: '', phone_code: '+880', phone: '', ...(p.contact || {}) },
    })
  }
  async function openEdit(p: any) {
    editingId.value = p.id; activeTab.value = 'Details'
    error.value = ''; subError.value = ''
    Object.assign(draft, freshDraft())
    members.value = []; documents.value = []; projects.value = []; products.value = []
    entitlements.value = mergeFeatures(null)
    drawerMode.value = 'edit'
    try {
      const full = (await api<any>(`/exhibitors/${p.id}`)).data
      populateDraft(full)
      members.value   = full.members ?? []
      documents.value = full.documents ?? []
      projects.value  = full.projects ?? []
      products.value  = full.products ?? []
      entitlements.value = mergeFeatures(full.entitlements)
    } catch { /* */ }
  }

  // ── Save (create / update / delete) ──────────────────────────────────
  function buildPayload() {
    return {
      event: eventId, name: draft.name, email: draft.email,
      logo_file_id: draft.logo_file_id,
      package_id: draft.package_id, stall_no: draft.stall_no,
      type: draft.type.toLowerCase(),
      phone_code: draft.phone_code, phone: draft.phone,
      rating: draft.rating, featured: draft.featured, premium: draft.premium,
      about: draft.about, street: draft.street, city: draft.city,
      state: draft.state, zip: draft.zip, country: draft.country,
      location_url: draft.location_url, website_url: draft.website_url,
      tags: draft.tags, filter_id: draft.filter_id,
      spotlight_type: draft.spotlight_type, spotlight_file_id: draft.spotlight_file_id,
      cta: draft.cta, social: draft.social, contact: draft.contact,
    }
  }
  async function create() {
    error.value = ''; saving.value = true
    const adminEmail = draft.email
    try {
      const res = await api<any>('/exhibitors', { method: 'POST', body: buildPayload() })
      drawerMode.value = null; await load()
      if (res?.admin_invited) toast.success('Exhibitor created', { description: `A 6-digit access code was emailed to ${adminEmail}.` })
      else toast.success('Exhibitor created')
    } catch (e: any) { error.value = e?.data?.message || 'Could not create.'; toast.error(error.value) }
    finally { saving.value = false }
  }
  async function update() {
    error.value = ''; saving.value = true
    try {
      await api(`/exhibitors/${editingId.value}`, { method: 'PUT', body: buildPayload() })
      drawerMode.value = null; await load()
      toast.success('Exhibitor updated')
    } catch (e: any) { error.value = e?.data?.message || 'Could not update.'; toast.error(error.value) }
    finally { saving.value = false }
  }
  async function remove(p: any) {
    if (!confirm(`Delete "${p.name}"?`)) return
    try { await api(`/exhibitors/${p.id}`, { method: 'DELETE' }); await load(); toast.success('Exhibitor deleted') }
    catch (e: any) { toast.error(e?.data?.message || 'Could not delete.') }
  }

  // ── Activate / deactivate ────────────────────────────────────────────
  async function setStatus(p: any, status: 'active' | 'suspended') {
    try {
      await api(`/exhibitors/${p.id}`, { method: 'PUT', body: { status } })
      await load()
      toast.success(status === 'active' ? 'Exhibitor activated' : 'Exhibitor deactivated')
    } catch (e: any) { toast.error(e?.data?.message || 'Could not update status.') }
  }
  function toggleStatus(p: any) {
    setStatus(p, (p.status || 'active') === 'active' ? 'suspended' : 'active')
    actionsOpenId.value = null
  }

  // ── Reset password ───────────────────────────────────────────────────
  function openResetPassword(p: any) {
    resetTarget.value = p
    resetMode.value = 'auto'
    resetPassword.value = ''
    resetMustChange.value = true
    resetError.value = ''
    resetResult.value = null
    actionsOpenId.value = null
  }
  function closeResetPassword() { resetTarget.value = null; resetResult.value = null }
  async function submitResetPassword() {
    if (!resetTarget.value) return
    if (resetMode.value === 'manual' && resetPassword.value.length < 8) {
      resetError.value = 'Password must have at least 8 characters'
      return
    }
    resetError.value = ''; resetSaving.value = true
    try {
      const body: any = { mode: resetMode.value, must_change: resetMustChange.value }
      if (resetMode.value === 'manual') body.password = resetPassword.value
      const r = await api<any>(`/exhibitors/${resetTarget.value.id}/reset-password`, { method: 'POST', body })
      if (resetMode.value === 'auto') {
        resetResult.value = r.data                 // { email, password } → reveal step
      } else {
        toast.success('Password reset')
        resetTarget.value = null
      }
    } catch (e: any) {
      resetError.value = e?.data?.message || 'Could not reset password.'
    } finally { resetSaving.value = false }
  }

  // ── Uploads ──────────────────────────────────────────────────────────
  async function pickSpotlight(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0]; if (!file) return
    spotlightUploading.value = true
    try { const r = await upload(file, { collection: 'exhibitor_spotlight' }); draft.spotlight_url = r.url; draft.spotlight_file_id = r.id }
    finally { spotlightUploading.value = false }
  }

  // ── Tags / CTA ───────────────────────────────────────────────────────
  function addTag(e: KeyboardEvent) {
    if (e.key !== 'Enter') return; e.preventDefault()
    const t = tagInput.value.trim()
    if (t && !draft.tags.includes(t)) draft.tags.push(t)
    tagInput.value = ''
  }
  function removeTag(tag: string) { draft.tags = draft.tags.filter(t => t !== tag) }
  function addCta() { draft.cta.push({ id: 'cta_' + Date.now(), type: 'TEXT', label: '', value: '', open: false }) }

  // ── Sub-resource CRUD ────────────────────────────────────────────────
  async function addMember() {
    if (!memberForm.email) return
    subError.value = ''; subSaving.value = true
    try {
      const r = await api<any>(`/exhibitors/${editingId.value}/members`, { method: 'POST', body: { ...memberForm } })
      members.value.push(r.data)
      Object.assign(memberForm, { email: '', first_name: '', last_name: '', role: 'staff', password: '' })
    } catch (e: any) { subError.value = e?.data?.message || 'Could not add member.' }
    finally { subSaving.value = false }
  }
  async function removeMember(m: any) {
    if (!confirm(`Remove ${m.contact?.email || 'this member'}?`)) return
    try { await api(`/exhibitors/${editingId.value}/members/${m.id}`, { method: 'DELETE' }); members.value = members.value.filter((x: any) => x.id !== m.id) } catch { /* */ }
  }
  async function addDocument() {
    if (!docForm.title) return
    subError.value = ''; subSaving.value = true
    try {
      const r = await api<any>(`/exhibitors/${editingId.value}/documents`, { method: 'POST', body: { ...docForm } })
      documents.value.push(r.data); docForm.title = ''; docForm.url = ''
    } catch (e: any) { subError.value = e?.data?.message || 'Could not add document.' }
    finally { subSaving.value = false }
  }
  async function removeDocument(d: any) {
    if (!confirm(`Remove "${d.title}"?`)) return
    try { await api(`/exhibitors/${editingId.value}/documents/${d.id}`, { method: 'DELETE' }); documents.value = documents.value.filter((x: any) => x.id !== d.id) } catch { /* */ }
  }
  async function addProject() {
    if (!projectForm.name) return
    subError.value = ''; subSaving.value = true
    try {
      const r = await api<any>(`/exhibitors/${editingId.value}/projects`, { method: 'POST', body: { ...projectForm } })
      projects.value.push(r.data); projectForm.name = ''; projectForm.description = ''; projectForm.status = ''
    } catch (e: any) { subError.value = e?.data?.message || 'Could not add project.' }
    finally { subSaving.value = false }
  }
  async function removeProject(p: any) {
    if (!confirm(`Remove "${p.name}"?`)) return
    try { await api(`/exhibitors/${editingId.value}/projects/${p.id}`, { method: 'DELETE' }); projects.value = projects.value.filter((x: any) => x.id !== p.id) } catch { /* */ }
  }
  async function addProduct() {
    if (!productForm.name) return
    subError.value = ''; subSaving.value = true
    try {
      const body = { name: productForm.name, description: productForm.description || undefined, price_cents: productForm.price ? Math.round(Number(productForm.price) * 100) : undefined }
      const r = await api<any>(`/exhibitors/${editingId.value}/products`, { method: 'POST', body })
      products.value.push(r.data); productForm.name = ''; productForm.description = ''; productForm.price = ''
    } catch (e: any) { subError.value = e?.data?.message || 'Could not add product.' }
    finally { subSaving.value = false }
  }
  async function removeProduct(p: any) {
    if (!confirm(`Remove "${p.name}"?`)) return
    try { await api(`/exhibitors/${editingId.value}/products/${p.id}`, { method: 'DELETE' }); products.value = products.value.filter((x: any) => x.id !== p.id) } catch { /* */ }
  }
  async function savePermissions() {
    subError.value = ''; subSaving.value = true
    try {
      await api(`/exhibitors/${editingId.value}`, { method: 'PUT', body: { entitlements: JSON.parse(JSON.stringify(entitlements.value)) } })
      toast.success('Permissions saved')
    } catch (e: any) { subError.value = e?.data?.message || 'Could not save permissions.'; toast.error(subError.value) }
    finally { subSaving.value = false }
  }

  return {
    // list + meta
    eventId, exhibitors, packages, filters,
    // drawer / editing
    drawerMode, editingId, activeTab, saving, error, draft, spotlightUploading, tagInput,
    // sub-resources
    members, documents, projects, products, entitlements, subSaving, subError,
    memberForm, docForm, projectForm, productForm,
    // table
    search, filterType, filterPackage, page, perPage, actionsOpenId,
    filtered, paginated, totalPages, paginationLabel, packageName, resetFilters, toggleActions,
    // reset password + status
    resetTarget, resetMode, resetPassword, resetMustChange, resetSaving, resetError, resetResult,
    openResetPassword, closeResetPassword, submitResetPassword, toggleStatus,
    // actions
    init, load, loadMeta, openAdd, openEdit, populateDraft,
    buildPayload, create, update, remove,
    pickSpotlight, addTag, removeTag, addCta,
    addMember, removeMember, addDocument, removeDocument,
    addProject, removeProject, addProduct, removeProduct, savePermissions,
  }
}

export type ExhibitorManager = ReturnType<typeof useExhibitorManager>
export const ExhibitorKey: InjectionKey<ExhibitorManager> = Symbol('exhibitor-manager')

/** Convenience inject for child components. */
export function useExhibitorContext(): ExhibitorManager {
  const ctx = inject(ExhibitorKey)
  if (!ctx) throw new Error('Exhibitor components must be used within <ExhibitorManager>')
  return ctx
}
