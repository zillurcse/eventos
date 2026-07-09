<script setup lang="ts">
definePageMeta({ middleware: 'exhibitor', title: 'Company Profile', subtitle: 'Your exhibitor / sponsor space' })

const api = useApi()

const exhibitor = ref<any>(null)
const documents = ref<any[]>([])
const projects = ref<any[]>([])
const leadsCount = ref(0)
const meetingsCount = ref(0)
const suspended = ref(false)

const tab = ref<'overview' | 'products' | 'documents' | 'projects'>('overview')

// ── Edit profile ───────────────────────────────────────────────────────────
const editing = ref(false)
const saving = ref(false)
const error = ref('')
const form = reactive({
  name: '', description: '', website_url: '',
  stall_no: '', phone_code: '+880', phone: '', logo_file_id: null as number | null,
})

const phoneDisplay = computed(() => {
  const e = exhibitor.value
  if (!e?.phone) return ''
  return [e.phone_code, e.phone].filter(Boolean).join(' ')
})

const stats = computed(() => [
  { label: 'Team Members', value: exhibitor.value?.members?.length ?? exhibitor.value?.members_count ?? 0 },
  { label: 'Leads', value: leadsCount.value },
  { label: 'Meetings', value: meetingsCount.value },
  { label: 'Products', value: exhibitor.value?.products?.length ?? 0 },
  { label: 'Documents', value: documents.value.length },
  { label: 'Projects', value: projects.value.length },
])

async function load() {
  try {
    exhibitor.value = (await api<any>('/exhibitor/space')).data
    syncForm()
    // Secondary data (counts / tab lists) — failures here shouldn't blank the page.
    const [docs, projs, convos, meetings] = await Promise.allSettled([
      api<any>('/exhibitor/documents'),
      api<any>('/exhibitor/projects'),
      api<any>('/exhibitor/inbox/conversations'),
      api<any>('/exhibitor/inbox/meeting-requests'),
    ])
    if (docs.status === 'fulfilled') documents.value = docs.value.data
    if (projs.status === 'fulfilled') projects.value = projs.value.data
    if (convos.status === 'fulfilled') leadsCount.value = convos.value.data.length
    if (meetings.status === 'fulfilled') meetingsCount.value = meetings.value.data.length
  } catch (e: any) {
    if (e?.response?.status === 403) suspended.value = true
  }
}

function syncForm() {
  const e = exhibitor.value ?? {}
  form.name = e.name ?? ''
  form.description = e.description ?? ''
  form.website_url = e.website_url ?? ''
  form.stall_no = e.stall_no ?? ''
  form.phone_code = e.phone_code || '+880'
  form.phone = e.phone ?? ''
  form.logo_file_id = null
}

function startEdit() { syncForm(); error.value = ''; editing.value = true }
function cancelEdit() { editing.value = false }

async function save() {
  error.value = ''
  saving.value = true
  try {
    const body: any = {
      name: form.name,
      description: form.description || null,
      website_url: form.website_url || null,
      stall_no: form.stall_no || null,
      phone_code: form.phone_code || null,
      phone: form.phone || null,
    }
    if (form.logo_file_id) body.logo_file_id = form.logo_file_id
    exhibitor.value = (await api<any>('/exhibitor/space', { method: 'PATCH', body })).data
    editing.value = false
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save your changes.'
  } finally {
    saving.value = false
  }
}

// ── Virtual booth QR code ───────────────────────────────────────────────────
const qrValue = computed(() => exhibitor.value?.public_url || '')
const qrColor = ref('#111827')
const customizing = ref(false)
const swatches = ['#111827', '#4f46e5', '#0f766e', '#b91c1c', '#c2410c']

async function downloadQr() {
  if (!qrValue.value) return
  const QRCode = (await import('qrcode')).default
  const dataUrl = await QRCode.toDataURL(qrValue.value, {
    width: 720, margin: 2, color: { dark: qrColor.value, light: '#ffffff' },
  })
  const a = document.createElement('a')
  const slug = (exhibitor.value?.slug || exhibitor.value?.name || 'company').toString().toLowerCase().replace(/[^a-z0-9]+/g, '-')
  a.href = dataUrl
  a.download = `${slug}-booth-qr.png`
  a.click()
}

function docKindLabel(url?: string | null) {
  if (!url) return 'LINK'
  const ext = url.split('?')[0].split('.').pop()?.toLowerCase()
  return ({ pdf: 'PDF', doc: 'DOC', docx: 'DOC', xls: 'EXCEL', xlsx: 'EXCEL', png: 'IMAGE', jpg: 'IMAGE', jpeg: 'IMAGE' } as Record<string, string>)[ext || ''] || 'LINK'
}

onMounted(load)
</script>

<template>
  <div>
    <div v-if="suspended" class="card">
      <p class="error">This exhibitor account is suspended. Contact the event organizer.</p>
    </div>

    <template v-else-if="exhibitor">
      <!-- Stat cards -->
      <div class="grid grid-cols-6 gap-3 mb-5 max-lg:grid-cols-3 max-sm:grid-cols-2">
        <div v-for="s in stats" :key="s.label" class="rounded-xl border border-line bg-white px-4 py-4 text-center">
          <div class="text-2xl font-extrabold text-ink leading-none">{{ s.value }}</div>
          <div class="mt-1.5 text-[.82rem] text-muted">{{ s.label }}</div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 mb-4 border-b border-line">
        <button
          v-for="t in [
            { k: 'overview', label: 'Overview' },
            { k: 'products', label: 'Products' },
            { k: 'documents', label: 'Documents & Links' },
            { k: 'projects', label: 'Projects' },
          ]"
          :key="t.k"
          class="px-4 py-2.5 text-[.9rem] font-semibold border-b-2 -mb-px"
          :class="tab === t.k ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink'"
          @click="tab = (t.k as any)"
        >{{ t.label }}</button>
      </div>

      <!-- ── Overview ── -->
      <div v-if="tab === 'overview'" class="grid grid-cols-[1.5fr_1fr] gap-[18px] items-start max-lg:grid-cols-1">
        <!-- Company card -->
        <div class="card">
          <template v-if="!editing">
            <div class="flex items-start gap-4">
              <div class="w-[92px] h-[92px] rounded-xl border border-line bg-white grid place-items-center overflow-hidden shrink-0">
                <img v-if="exhibitor.logo_url" :src="exhibitor.logo_url" :alt="exhibitor.name" class="w-full h-full object-contain">
                <AppIcon v-else name="store" class="w-8 h-8 text-muted" />
              </div>
              <div class="min-w-0 flex-1">
                <h2 class="mt-0! mb-1! text-xl">{{ exhibitor.name }}</h2>
                <p v-if="exhibitor.stall_no" class="muted m-0! text-[.9rem]">Stall : {{ exhibitor.stall_no }}</p>
                <span class="badge mt-2 inline-block capitalize">{{ exhibitor.type }}</span>
              </div>
              <button class="btn ghost sm shrink-0" @click="startEdit">
                <AppIcon name="pencil" class="w-3.5 h-3.5" /> Edit
              </button>
            </div>

            <p v-if="exhibitor.description" class="muted mt-4 text-[.9rem] leading-relaxed">{{ exhibitor.description }}</p>

            <div class="mt-5 pt-5 border-t border-line">
              <h3 class="mt-0! mb-3! text-[1.05rem]">Get in Touch</h3>
              <div class="flex flex-col gap-2.5">
                <a v-if="phoneDisplay" :href="`tel:${exhibitor.phone_code || ''}${exhibitor.phone}`" class="flex items-center gap-2.5 text-brand font-semibold text-[.92rem]">
                  <AppIcon name="phone" class="w-4 h-4" /> {{ phoneDisplay }}
                </a>
                <a v-if="exhibitor.email" :href="`mailto:${exhibitor.email}`" class="flex items-center gap-2.5 text-brand font-semibold text-[.92rem]">
                  <AppIcon name="mail" class="w-4 h-4" /> {{ exhibitor.email }}
                </a>
                <a v-if="exhibitor.website_url" :href="exhibitor.website_url" target="_blank" rel="noopener" class="flex items-center gap-2.5 text-brand font-semibold text-[.92rem]">
                  <AppIcon name="link" class="w-4 h-4" /> {{ exhibitor.website_url }}
                </a>
                <p v-if="!phoneDisplay && !exhibitor.email && !exhibitor.website_url" class="muted text-[.88rem] m-0!">
                  No contact details yet — add them with <button class="text-brand font-semibold" @click="startEdit">Edit</button>.
                </p>
              </div>
            </div>
          </template>

          <!-- Edit form -->
          <template v-else>
            <h2 class="mt-0!">Edit company profile</h2>
            <div class="flex gap-4 items-start max-sm:flex-col">
              <div class="w-40">
                <UploadButton :preview="exhibitor.logo_url" collection="logo" path="/exhibitor/uploads" @uploaded="v => form.logo_file_id = v.id" />
              </div>
              <div class="flex-1 w-full">
                <label>Company name</label>
                <input v-model="form.name" placeholder="Company name">
                <div class="flex gap-3 max-sm:flex-col">
                  <div class="flex-1"><label>Stall no</label><input v-model="form.stall_no" placeholder="e.g. 2025"></div>
                  <div class="flex-1"><label>Website</label><input v-model="form.website_url" placeholder="https://…"></div>
                </div>
              </div>
            </div>

            <label>Phone</label>
            <div class="flex gap-2 max-w-[360px]">
              <input v-model="form.phone_code" class="flex-none w-20 m-0" placeholder="+880">
              <input v-model="form.phone" class="flex-1 m-0" placeholder="Phone number" type="tel">
            </div>

            <label class="mt-3.5">Description</label>
            <textarea v-model="form.description" rows="3" placeholder="Tell attendees about your company…" />

            <p v-if="error" class="error">{{ error }}</p>
            <div class="flex gap-2 mt-1">
              <button class="btn" :disabled="saving || !form.name" @click="save">{{ saving ? 'Saving…' : 'Save changes' }}</button>
              <button class="btn ghost" :disabled="saving" @click="cancelEdit">Cancel</button>
            </div>
          </template>
        </div>

        <!-- Virtual booth QR code -->
        <div class="card">
          <div class="flex items-center justify-between gap-2">
            <h3 class="m-0! text-[1.05rem]">Virtual booth QR code</h3>
            <button v-if="qrValue" class="btn ghost sm" @click="customizing = !customizing">Customize</button>
          </div>
          <p class="muted mt-2 text-[.86rem] leading-relaxed">
            The QR code links to your company page in the event app — display it on your onsite booth or in your email signature.
          </p>

          <template v-if="qrValue">
            <div v-if="customizing" class="flex items-center gap-2 mt-3">
              <span class="text-[.82rem] text-muted">Colour</span>
              <button
                v-for="c in swatches" :key="c"
                class="w-6 h-6 rounded-full border-2"
                :style="{ background: c }"
                :class="qrColor === c ? 'border-ink' : 'border-transparent'"
                @click="qrColor = c"
              />
            </div>

            <div class="mt-4 flex justify-center">
              <div class="w-[190px] h-[190px]">
                <Qrcode :value="qrValue" :black-color="qrColor" white-color="#ffffff" class="w-full h-full" />
              </div>
            </div>

            <div class="mt-4 flex justify-end">
              <button class="btn ghost sm text-brand" @click="downloadQr">
                <AppIcon name="download" class="w-3.5 h-3.5" /> Get QR Code
              </button>
            </div>
          </template>
          <p v-else class="muted text-[.86rem] mt-4">
            Your event site isn't published yet, so there's no company page to link to. The QR code will appear once the organizer sets up the event domain.
          </p>
        </div>
      </div>

      <!-- ── Products ── -->
      <div v-else-if="tab === 'products'" class="card">
        <div class="flex items-center justify-between mb-3">
          <h2 class="m-0!">Products</h2>
          <NuxtLink class="btn sm" to="/exhibitor/products">Manage products</NuxtLink>
        </div>
        <table v-if="exhibitor.products?.length">
          <thead><tr><th>Product</th><th>Description</th></tr></thead>
          <tbody>
            <tr v-for="p in exhibitor.products" :key="p.id">
              <td><strong>{{ p.name }}</strong></td>
              <td class="muted">{{ p.description || '—' }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="muted">No products yet. <NuxtLink class="text-brand font-semibold" to="/exhibitor/products">Add your first product →</NuxtLink></p>
      </div>

      <!-- ── Documents & Links ── -->
      <div v-else-if="tab === 'documents'" class="card">
        <div class="flex items-center justify-between mb-3">
          <h2 class="m-0!">Documents &amp; Links</h2>
          <NuxtLink class="btn sm" to="/exhibitor/documents">Manage documents</NuxtLink>
        </div>
        <div v-if="documents.length" class="flex flex-col gap-2">
          <a
            v-for="d in documents" :key="d.id"
            :href="d.url" target="_blank" rel="noopener"
            class="flex items-center justify-between gap-3 px-3.5 py-3 rounded-lg border border-line hover:bg-[#f7f8fa]"
          >
            <span class="flex items-center gap-2.5 min-w-0">
              <AppIcon name="clipboard" class="w-4 h-4 text-muted shrink-0" />
              <strong class="text-[.9rem] text-ink truncate">{{ d.title }}</strong>
            </span>
            <span class="badge shrink-0">{{ docKindLabel(d.url) }}</span>
          </a>
        </div>
        <p v-else class="muted">No documents or links yet. <NuxtLink class="text-brand font-semibold" to="/exhibitor/documents">Add one →</NuxtLink></p>
      </div>

      <!-- ── Projects ── -->
      <div v-else class="card">
        <div class="flex items-center justify-between mb-3">
          <h2 class="m-0!">Projects</h2>
          <NuxtLink class="btn sm" to="/exhibitor/projects">Manage projects</NuxtLink>
        </div>
        <div v-if="projects.length" class="flex flex-col gap-2">
          <div v-for="p in projects" :key="p.id" class="px-3.5 py-3 rounded-lg border border-line">
            <div class="flex items-center gap-2">
              <strong class="text-[.92rem] text-ink">{{ p.name }}</strong>
              <span v-if="p.status" class="badge capitalize">{{ p.status }}</span>
            </div>
            <p v-if="p.description" class="muted m-0! mt-1 text-[.86rem]">{{ p.description }}</p>
          </div>
        </div>
        <p v-else class="muted">No projects yet. <NuxtLink class="text-brand font-semibold" to="/exhibitor/projects">Add one →</NuxtLink></p>
      </div>
    </template>
  </div>
</template>
