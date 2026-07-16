<script setup lang="ts">
import { COUNTRIES } from '~/utils/countries'

const profile = useProfileStore()
const auth = useAuthStore()

const saving = ref(false)
const uploading = ref(false)
const savedFlash = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const form = reactive({
  first_name: '', last_name: '', bio: '', gender: '', job_title: '', company: '',
  country: '', state: '', city: '', zip_code: '', website: '', purpose_of_visit: '', purchasing_decision: '',
})

function fillFromProfile() {
  const p = profile.data
  if (!p) return
  form.first_name = p.first_name || ''
  form.last_name = p.last_name || ''
  form.bio = p.bio || ''
  form.gender = p.gender || ''
  form.job_title = p.job_title || ''
  form.company = p.company || ''
  form.country = p.country || ''
  form.state = p.state || ''
  form.city = p.city || ''
  form.zip_code = p.zip_code || ''
  form.website = p.social?.website || ''
  form.purpose_of_visit = p.purpose_of_visit || ''
  form.purchasing_decision = p.purchasing_decision || ''
}

watch(() => profile.data, fillFromProfile, { immediate: true })

async function pickAvatar() { fileInput.value?.click() }

const cropFile = ref<File | null>(null)

function onAvatarChange(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) cropFile.value = file
  input.value = ''
}

async function onCropSave(blob: Blob) {
  cropFile.value = null
  uploading.value = true
  try {
    await profile.uploadAvatar(new File([blob], 'avatar.jpg', { type: 'image/jpeg' }))
  } finally {
    uploading.value = false
  }
}

async function deleteAvatar() {
  await profile.deleteAvatar()
}

async function save() {
  if (saving.value) return
  saving.value = true
  try {
    await profile.save({
      first_name: form.first_name.trim() || undefined,
      last_name: form.last_name.trim() || undefined,
      bio: form.bio.trim() || undefined,
      gender: form.gender || undefined,
      job_title: form.job_title.trim() || undefined,
      company: form.company.trim() || undefined,
      country: form.country || undefined,
      state: form.state.trim() || undefined,
      city: form.city.trim() || undefined,
      zip_code: form.zip_code.trim() || undefined,
      purpose_of_visit: form.purpose_of_visit.trim() || undefined,
      purchasing_decision: form.purchasing_decision.trim() || undefined,
      social: { website: form.website.trim() || undefined },
    })
    if (auth.user) auth.user.name = `${form.first_name} ${form.last_name}`.trim()
    savedFlash.value = true
    setTimeout(() => { savedFlash.value = false }, 2500)
  } finally {
    saving.value = false
  }
}

function cancel() { fillFromProfile() }
</script>

<template>
  <div class="tab">
    <div class="avatar-row">
      <span class="av"><UserAvatar :src="profile.data?.avatar_url" :name="auth.user?.name" /></span>
      <div class="av-actions">
        <button type="button" class="btn ghost" :disabled="uploading" @click="pickAvatar">
          <svg viewBox="0 0 24 24"><path d="M4 8l1-3h4l1 3h5a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2z" /><circle cx="12" cy="14" r="3.5" /></svg>
          {{ uploading ? 'Uploading…' : 'Change' }}
        </button>
        <button v-if="profile.data?.avatar_url" type="button" class="btn ghost danger" @click="deleteAvatar">
          <svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M7 7l1 13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-13" /></svg>
          Delete
        </button>
        <input ref="fileInput" type="file" accept="image/*" hidden @change="onAvatarChange">
      </div>
    </div>

    <ProfileAvatarCropModal
      v-if="cropFile"
      :file="cropFile"
      @save="onCropSave"
      @cancel="cropFile = null"
    />

    <div class="grid">
      <label class="field">
        <span>First Name<em>*</em></span>
        <input v-model="form.first_name" type="text" maxlength="100" required>
      </label>
      <label class="field">
        <span>Last Name<em>*</em></span>
        <input v-model="form.last_name" type="text" maxlength="100" required>
      </label>
    </div>

    <label class="field">
      <span>About</span>
      <textarea v-model="form.bio" rows="3" maxlength="2000" placeholder="Enter about your self" />
    </label>

    <label class="field">
      <span>Gender</span>
      <select v-model="form.gender">
        <option value="">Select</option>
        <option value="male">Male</option>
        <option value="female">Female</option>
        <option value="other">Other</option>
        <option value="prefer_not_to_say">Prefer not to say</option>
      </select>
    </label>

    <div class="grid">
      <label class="field">
        <span>Designation</span>
        <input v-model="form.job_title" type="text" maxlength="150" placeholder="Enter Designation">
      </label>
      <label class="field">
        <span>Organisation</span>
        <input v-model="form.company" type="text" maxlength="150" placeholder="Enter Organisation">
      </label>
    </div>

    <div class="grid">
      <label class="field">
        <span>Country</span>
        <select v-model="form.country">
          <option value="">Select</option>
          <option v-for="c in COUNTRIES" :key="c" :value="c">{{ c }}</option>
        </select>
      </label>
      <label class="field">
        <span>State</span>
        <input v-model="form.state" type="text" maxlength="100" placeholder="Enter State">
      </label>
    </div>

    <div class="grid">
      <label class="field">
        <span>City/Town</span>
        <input v-model="form.city" type="text" maxlength="100" placeholder="Enter City/Town">
      </label>
      <label class="field">
        <span>Zip Code</span>
        <input v-model="form.zip_code" type="text" maxlength="20" placeholder="Enter Zip Code">
      </label>
    </div>

    <label class="field">
      <span>Website</span>
      <input v-model="form.website" type="url" maxlength="300" placeholder="https://">
    </label>

    <label class="field">
      <span>Purpose of Visit</span>
      <input v-model="form.purpose_of_visit" type="text" maxlength="300" placeholder="Enter Purpose of Visit">
    </label>

    <label class="field">
      <span>Purchasing Decision</span>
      <input v-model="form.purchasing_decision" type="text" maxlength="300" placeholder="Enter Purchasing Decision">
    </label>

    <div class="foot">
      <button type="button" class="btn primary" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save' }}</button>
      <button type="button" class="btn text" :disabled="saving" @click="cancel">Cancel</button>
      <span v-if="savedFlash" class="saved">Saved</span>
    </div>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 20px; max-width: 720px; }

.avatar-row { display: flex; align-items: center; gap: 20px; }
.av { width: 84px; height: 84px; border-radius: 12px; overflow: hidden; flex: 0 0 auto; background: #e2e8f0; }
.av-actions { display: flex; gap: 10px; }

.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 620px) { .grid { grid-template-columns: 1fr; } }

.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { font-size: .84rem; font-weight: 600; color: #334155; }
.field em { color: #ef4444; font-style: normal; }
.field input, .field select, .field textarea {
  border: 1px solid #d7dae1; border-radius: 10px; padding: 11px 13px; font: inherit; font-size: .9rem; color: #1e293b; outline: none; background: #fff;
}
.field input:focus, .field select:focus, .field textarea:focus { border-color: var(--brand-primary); }

.foot { display: flex; align-items: center; gap: 16px; padding-top: 8px; border-top: 1px solid #f1f2f6; margin-top: 4px; }
.btn { border: none; border-radius: 10px; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; padding: 11px 22px; }
.btn.primary { background: var(--brand-primary); color: #fff; }
.btn.primary:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn.text { background: none; color: var(--brand-primary); padding: 11px 4px; }
.btn.ghost { background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: .82rem; }
.btn.ghost svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.btn.ghost.danger { color: #ef4444; background: #fef2f2; }
.btn:disabled { opacity: .6; cursor: default; }
.saved { color: #16a34a; font-size: .85rem; font-weight: 600; }
</style>
