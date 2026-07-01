<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api   = useApi()
const id    = route.params.id as string

const form = reactive({
  from: '',
  sender_name: '',
  cc: '',
  bcc: '',
  reply_to: '',
  is_smtp_config: false,
  mail_mailer: 'smtp',
  mail_host: '',
  mail_port: '587',
  mail_encryption: 'tls',
  mail_username: '',
  mail_password: '',
})

const hasSmtpPassword = ref(false)   // a password is already stored server-side
const touched = ref(false)
const saving  = ref(false)
const saved   = ref(false)
const error   = ref('')

const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const errors = computed(() => {
  const e: Record<string, string> = {}

  if (!form.from.trim()) e.from = 'From email is required.'
  else if (!emailRe.test(form.from.trim())) e.from = 'Enter a valid email address.'

  if (!form.sender_name.trim()) e.sender_name = 'Sender name is required.'

  if (form.reply_to.trim() && !emailRe.test(form.reply_to.trim())) e.reply_to = 'Enter a valid email address.'

  if (form.is_smtp_config) {
    for (const f of ['mail_mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username'] as const) {
      if (!String(form[f]).trim()) e[f] = 'Required for custom SMTP.'
    }
    if (!hasSmtpPassword.value && !form.mail_password.trim()) e.mail_password = 'Required for custom SMTP.'
  }

  return e
})

const isValid = computed(() => Object.keys(errors.value).length === 0)
function err(field: string) {
  return touched.value ? errors.value[field] : undefined
}

async function load() {
  try {
    const s = (await api<any>(`/events/${id}/settings`)).data
    const snd = s.sender || {}
    form.from           = snd.from ?? ''
    form.sender_name    = snd.sender_name ?? ''
    form.cc             = snd.cc ?? ''
    form.bcc            = snd.bcc ?? ''
    form.reply_to       = snd.reply_to ?? ''
    form.is_smtp_config = !!snd.is_smtp_config
    form.mail_mailer    = snd.mail_mailer ?? 'smtp'
    form.mail_host      = snd.mail_host ?? ''
    form.mail_port      = snd.mail_port ?? '587'
    form.mail_encryption = snd.mail_encryption ?? 'tls'
    form.mail_username  = snd.mail_username ?? ''
    form.mail_password  = ''
    hasSmtpPassword.value = !!snd.has_smtp_password
  } catch { /* */ }
}

async function save() {
  touched.value = true
  if (!isValid.value) return

  saving.value = true
  error.value = ''
  try {
    const sender: Record<string, any> = {
      from:            form.from.trim() || null,
      sender_name:     form.sender_name.trim() || null,
      cc:              form.cc.trim() || null,
      bcc:             form.bcc.trim() || null,
      reply_to:        form.reply_to.trim() || null,
      is_smtp_config:  form.is_smtp_config,
      mail_mailer:     form.mail_mailer.trim() || null,
      mail_host:       form.mail_host.trim() || null,
      mail_port:       form.mail_port.trim() || null,
      mail_encryption: form.mail_encryption.trim() || null,
      mail_username:   form.mail_username.trim() || null,
    }
    // Only send the password when the user actually typed a new one; a blank
    // value keeps the stored password server-side.
    if (form.mail_password.trim()) sender.mail_password = form.mail_password

    const res = await api<any>(`/events/${id}/settings`, { method: 'PUT', body: { sender } })
    hasSmtpPassword.value = !!res.data?.sender?.has_smtp_password
    form.mail_password = ''
    saved.value = true
    setTimeout(() => (saved.value = false), 1800)
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save sender details.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="card max-w-[720px]">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4 pb-4 mb-4 border-b border-line">
      <div>
        <h2 class="section-title m-0">
          Sender Details
          <span v-if="saved" class="badge active ml-2">saved ✓</span>
        </h2>
        <p class="muted text-[.86rem] mt-0.5 mb-0">
          Sender details are used for your event's Web/Mobile app notification emails.
        </p>
      </div>
    </div>

    <!-- Email -->
    <div class="pb-5 mb-5 border-b border-line">
      <h3 class="text-[1rem] font-semibold text-ink m-0">Email</h3>
      <p class="muted text-[.82rem] mt-0.5 mb-3">The default from-identity applied to outgoing emails.</p>

      <div class="flex flex-col gap-3">
        <AppInput
          v-model="form.from"
          label="From"
          required
          type="email"
          placeholder="events@yourcompany.com"
          :error="err('from')"
        />
        <AppInput
          v-model="form.sender_name"
          label="Sender name"
          required
          placeholder="e.g. Acme Events Team"
          :error="err('sender_name')"
        />
        <AppInput
          v-model="form.reply_to"
          label="Reply-To (optional)"
          type="email"
          placeholder="reply@yourcompany.com"
          :error="err('reply_to')"
        />
        <AppInput
          v-model="form.cc"
          label="CC (comma separated)"
          placeholder="cc1@example.com, cc2@example.com"
        />
        <AppInput
          v-model="form.bcc"
          label="BCC (comma separated)"
          placeholder="admin@example.com"
        />
      </div>
    </div>

    <!-- SMTP -->
    <div>
      <div class="flex items-center justify-between gap-4">
        <div>
          <h3 class="text-[1rem] font-semibold text-ink m-0">SMTP Configuration</h3>
          <p class="muted text-[.82rem] mt-0.5 mb-0">
            Configure a custom SMTP server <span v-if="!form.is_smtp_config">(optional)</span>.
          </p>
        </div>
        <NavigationToggleSwitch v-model="form.is_smtp_config" label="" class="w-auto!" />
      </div>

      <div v-if="form.is_smtp_config" class="grid grid-cols-2 gap-x-5 gap-y-3 mt-4">
        <AppInput v-model="form.mail_mailer"     label="Mail Mailer"     required placeholder="smtp"            :error="err('mail_mailer')" />
        <AppInput v-model="form.mail_host"       label="Mail Host"       required placeholder="mail.example.com" :error="err('mail_host')" />
        <AppInput v-model="form.mail_port"       label="Mail Port"       required placeholder="587"             :error="err('mail_port')" />
        <AppInput v-model="form.mail_encryption" label="Mail Encryption" required placeholder="tls"             :error="err('mail_encryption')" />
        <AppInput v-model="form.mail_username"   label="Mail Username"   required placeholder="user@example.com" :error="err('mail_username')" />
        <AppInput
          v-model="form.mail_password"
          label="Mail Password"
          required
          type="password"
          :placeholder="hasSmtpPassword ? '•••••• (leave blank to keep)' : '••••••'"
          :hint="hasSmtpPassword ? 'A password is already saved. Leave blank to keep it.' : undefined"
          :error="err('mail_password')"
        />
      </div>
    </div>

    <p v-if="error" class="error mt-4">{{ error }}</p>

    <div class="flex justify-end border-t border-line pt-4 mt-5">
      <button class="btn" :disabled="saving" @click="save">
        {{ saving ? 'Saving…' : 'SAVE' }}
      </button>
    </div>
  </div>
</template>
