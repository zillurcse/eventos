<script setup lang="ts">
definePageMeta({ middleware: 'partner', title: 'My Booth', subtitle: 'Your exhibitor / sponsor space' })

const api = useApi()
const partner = ref<any>(null)
const booth = ref<any>(null)
const suspended = ref(false)
const savedProfile = ref(false)
const savedBooth = ref(false)
const error = ref('')

const profile = reactive({ name: '', description: '', website: '', logo_file_id: null as number | null })
const boothForm = reactive({ code: '', type: 'physical', links: [] as { label: string, url: string }[] })

const isExhibitor = computed(() => partner.value?.type === 'exhibitor')

async function load() {
  try {
    partner.value = (await api<any>('/partner/space')).data
    profile.name = partner.value.name ?? ''
    profile.description = partner.value.description ?? ''
    profile.website = partner.value.website ?? ''
    if (isExhibitor.value) {
      booth.value = (await api<any>('/partner/booth')).data
      if (booth.value) {
        boothForm.code = booth.value.code ?? ''
        boothForm.type = booth.value.type ?? 'physical'
        boothForm.links = Array.isArray(booth.value.resources?.links) ? booth.value.resources.links : []
      }
    }
  } catch (e: any) {
    if (e?.response?.status === 403) suspended.value = true
  }
}

async function saveProfile() {
  error.value = ''
  try {
    const body: any = { name: profile.name, description: profile.description, website: profile.website }
    if (profile.logo_file_id) body.logo_file_id = profile.logo_file_id
    partner.value = (await api<any>('/partner/space', { method: 'PATCH', body })).data
    savedProfile.value = true; setTimeout(() => (savedProfile.value = false), 1500)
  } catch (e: any) { error.value = e?.data?.message || 'Could not save.' }
}

async function saveBooth() {
  const body = { code: boothForm.code || null, type: boothForm.type, resources: { links: boothForm.links.filter(l => l.url) } }
  booth.value = (await api<any>('/partner/booth', { method: 'PUT', body })).data
  savedBooth.value = true; setTimeout(() => (savedBooth.value = false), 1500)
}
function addLink() { boothForm.links.push({ label: '', url: '' }) }
function removeLink(i: number) { boothForm.links.splice(i, 1) }

onMounted(load)
</script>

<template>
  <div>
    <div v-if="suspended" class="card">
      <p class="error">This partner account is suspended. Contact the event organizer.</p>
    </div>

    <template v-else-if="partner">
      <div class="grid grid-cols-[1.4fr_1fr] gap-[18px] items-start">
        <!-- Profile -->
        <div class="card">
          <h2>Profile <span v-if="savedProfile" class="badge active">saved ✓</span></h2>
          <div class="flex gap-4 items-start">
            <div class="w-40">
              <UploadButton :preview="partner.logo_url" collection="logo" path="/partner/uploads" @uploaded="v => profile.logo_file_id = v.id" />
            </div>
            <div class="flex-1">
              <label>Name</label>
              <input v-model="profile.name">
              <label>Website</label>
              <input v-model="profile.website" placeholder="https://…">
            </div>
          </div>
          <label>Description</label>
          <textarea v-model="profile.description" rows="3" />
          <p v-if="error" class="error">{{ error }}</p>
          <button class="btn" @click="saveProfile">Save profile</button>
        </div>

        <!-- Status / stats -->
        <div class="card">
          <h2>Booth</h2>
          <p class="muted -mt-1.5">
            <span class="badge">{{ partner.type }}</span>
            <span class="badge ml-1.5" :class="partner.status">{{ partner.status }}</span>
            <span v-if="partner.package?.name" class="badge ml-1.5">{{ partner.package.name }}</span>
          </p>
          <div class="stats grid-cols-[1fr_1fr]">
            <div class="stat"><div class="n">{{ partner.products?.length ?? 0 }}</div><div class="l">Products</div></div>
            <div class="stat"><div class="n">{{ partner.members_count ?? partner.members?.length ?? 0 }}</div><div class="l">Members</div></div>
          </div>
          <NuxtLink class="btn ghost sm" to="/partner/products">Products</NuxtLink>
          <NuxtLink class="btn ghost sm ml-2" to="/partner/members">Team</NuxtLink>
        </div>
      </div>

      <!-- Exhibitor booth details -->
      <div v-if="isExhibitor" class="card">
        <h2>Booth details <span v-if="savedBooth" class="badge active">saved ✓</span></h2>
        <div class="flex gap-3">
          <div class="flex-1"><label>Booth code</label><input v-model="boothForm.code" placeholder="e.g. A-12"></div>
          <div class="flex-1"><label>Type</label>
            <select v-model="boothForm.type"><option value="physical">Physical</option><option value="virtual">Virtual</option></select>
          </div>
        </div>
        <label>Resource links</label>
        <div v-for="(l, i) in boothForm.links" :key="i" class="flex gap-2 items-center mb-1.5">
          <input v-model="l.label" placeholder="Label" class="flex-none w-40 m-0">
          <input v-model="l.url" placeholder="https://…" class="flex-1 m-0">
          <button class="btn ghost sm" @click="removeLink(i)">✕</button>
        </div>
        <button class="btn ghost sm" @click="addLink"><Icon name="plus" class="w-3.5 h-3.5" /> Add link</button>
        <div class="mt-3.5"><button class="btn" @click="saveBooth">Save booth</button></div>
      </div>
    </template>
  </div>
</template>
