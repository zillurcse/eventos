<script setup lang="ts">
import type { Block, EmailSettings } from '~/composables/useEmailBlocks'
import { FONT_STACKS } from '~/composables/useEmailBlocks'
import type { VarGroup } from '~/components/mail/MailVariableMenu.vue'

const props = defineProps<{ block: Block | null, settings: EmailSettings, varGroups: VarGroup[] }>()

const { upload } = useUpload()
const uploading = ref(false)

async function onImagePick(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file || !props.block) return
  uploading.value = true
  try {
    const res = await upload(file, { collection: 'email' })
    props.block.src = res.url
  } finally {
    uploading.value = false
    ;(e.target as HTMLInputElement).value = ''
  }
}

const ALIGNS = ['left', 'center', 'right'] as const
const s = computed(() => props.block?.style ?? {})

function insertVar(token: string, key: 'text' | 'html' | 'url') {
  if (!props.block) return
  const cur = (props.block as any)[key] || ''
  ;(props.block as any)[key] = cur + `{{ ${token} }}`
}
function addSocial() { props.block?.items?.push({ network: 'facebook', url: 'https://' }) }
function removeSocial(i: number) { props.block?.items?.splice(i, 1) }
</script>

<template>
  <div class="text-[.86rem]">
    <!-- ───────── Global canvas settings (nothing selected) ───────── -->
    <template v-if="!block">
      <h4 class="ins-h">Email settings</h4>
      <label class="ins-l">Backdrop color</label>
      <div class="ins-color"><input v-model="settings.backgroundColor" type="color"><input v-model="settings.backgroundColor" class="m-0"></div>

      <label class="ins-l">Content background</label>
      <div class="ins-color"><input v-model="settings.contentBackground" type="color"><input v-model="settings.contentBackground" class="m-0"></div>

      <label class="ins-l">Content width (px)</label>
      <input v-model.number="settings.contentWidth" type="number" min="320" max="800" step="10" class="m-0">

      <label class="ins-l">Corner radius (px)</label>
      <input v-model.number="settings.borderRadius" type="number" min="0" max="40" class="m-0">

      <label class="ins-l">Base font</label>
      <select v-model="settings.fontFamily" class="m-0">
        <option v-for="f in FONT_STACKS" :key="f.label" :value="f.value">{{ f.label }}</option>
      </select>

      <label class="ins-l">Default text color</label>
      <div class="ins-color"><input v-model="settings.textColor" type="color"><input v-model="settings.textColor" class="m-0"></div>

      <label class="ins-l">Link / accent color</label>
      <div class="ins-color"><input v-model="settings.linkColor" type="color"><input v-model="settings.linkColor" class="m-0"></div>

      <p class="text-[#8b93a7] text-[.78rem] mt-4 leading-relaxed">Select a block on the canvas to edit its content and style.</p>
    </template>

    <!-- ───────── Per-block inspector ───────── -->
    <template v-else>
      <h4 class="ins-h capitalize">{{ block.type }} block</h4>

      <!-- HEADING -->
      <template v-if="block.type === 'heading'">
        <label class="ins-l">Text</label>
        <textarea v-model="block.text" rows="2" class="m-0" />
        <div class="flex justify-end mt-1"><MailVariableMenu :groups="varGroups" compact @insert="t => insertVar(t, 'text')" /></div>
        <label class="ins-l">Level</label>
        <select v-model.number="block.level" class="m-0"><option :value="1">H1 — Large</option><option :value="2">H2 — Medium</option><option :value="3">H3 — Small</option></select>
        <label class="ins-l">Font size (px)</label>
        <input v-model.number="block.style.fontSize" type="number" min="10" max="60" class="m-0">
        <label class="ins-l">Weight</label>
        <select v-model="block.style.fontWeight" class="m-0"><option value="400">Regular</option><option value="600">Semibold</option><option value="700">Bold</option><option value="800">Extra bold</option></select>
        <label class="ins-l">Color</label>
        <div class="ins-color"><input v-model="block.style.color" type="color"><input v-model="block.style.color" class="m-0"></div>
      </template>

      <!-- TEXT -->
      <template v-else-if="block.type === 'text'">
        <p class="text-[#8b93a7] text-[.78rem] mb-2">Edit the copy directly on the canvas (bold, italic, links, variables). Styling below applies to the whole block.</p>
        <label class="ins-l">Font size (px)</label>
        <input v-model.number="block.style.fontSize" type="number" min="10" max="40" class="m-0">
        <label class="ins-l">Line height</label>
        <input v-model="block.style.lineHeight" class="m-0" placeholder="1.6">
        <label class="ins-l">Color</label>
        <div class="ins-color"><input v-model="block.style.color" type="color"><input v-model="block.style.color" class="m-0"></div>
      </template>

      <!-- BUTTON -->
      <template v-else-if="block.type === 'button'">
        <label class="ins-l">Label</label>
        <input v-model="block.text" class="m-0">
        <label class="ins-l">Link URL</label>
        <input v-model="block.url" class="m-0" placeholder="https://">
        <div class="flex justify-end mt-1"><MailVariableMenu :groups="varGroups" compact @insert="t => insertVar(t, 'url')" /></div>
        <label class="ins-l">Background</label>
        <div class="ins-color"><input v-model="block.style.backgroundColor" type="color"><input v-model="block.style.backgroundColor" class="m-0"></div>
        <label class="ins-l">Text color</label>
        <div class="ins-color"><input v-model="block.style.color" type="color"><input v-model="block.style.color" class="m-0"></div>
        <label class="ins-l">Corner radius (px)</label>
        <input v-model.number="block.style.borderRadius" type="number" min="0" max="40" class="m-0">
        <div class="flex gap-2">
          <div class="flex-1"><label class="ins-l">Pad X</label><input v-model.number="block.style.paddingX" type="number" class="m-0"></div>
          <div class="flex-1"><label class="ins-l">Pad Y</label><input v-model.number="block.style.paddingY" type="number" class="m-0"></div>
        </div>
        <label class="ins-l flex items-center justify-between m-0 mt-3 cursor-pointer"><span>Full width</span><input v-model="block.style.fullWidth" type="checkbox" class="w-4 h-4 m-0 accent-[#6352e7]"></label>
      </template>

      <!-- IMAGE -->
      <template v-else-if="block.type === 'image'">
        <label class="ins-l">Image</label>
        <div class="flex gap-2 items-center">
          <label class="btn ghost sm cursor-pointer m-0">{{ uploading ? 'Uploading…' : 'Upload' }}<input type="file" accept="image/*" class="hidden" @change="onImagePick"></label>
        </div>
        <label class="ins-l">Or image URL</label>
        <input v-model="block.src" class="m-0" placeholder="https://">
        <label class="ins-l">Alt text</label>
        <input v-model="block.alt" class="m-0">
        <label class="ins-l">Link URL (optional)</label>
        <input v-model="block.href" class="m-0" placeholder="https://">
        <label class="ins-l">Width (%)</label>
        <input v-model.number="block.style.width" type="number" min="10" max="100" class="m-0">
        <label class="ins-l">Corner radius (px)</label>
        <input v-model.number="block.style.borderRadius" type="number" min="0" max="40" class="m-0">
      </template>

      <!-- DIVIDER -->
      <template v-else-if="block.type === 'divider'">
        <label class="ins-l">Color</label>
        <div class="ins-color"><input v-model="block.style.color" type="color"><input v-model="block.style.color" class="m-0"></div>
        <label class="ins-l">Thickness (px)</label>
        <input v-model.number="block.style.height" type="number" min="1" max="12" class="m-0">
        <label class="ins-l">Width (%)</label>
        <input v-model.number="block.style.width" type="number" min="10" max="100" class="m-0">
      </template>

      <!-- SPACER -->
      <template v-else-if="block.type === 'spacer'">
        <label class="ins-l">Height (px)</label>
        <input v-model.number="block.style.height" type="number" min="4" max="160" class="m-0">
      </template>

      <!-- SOCIAL -->
      <template v-else-if="block.type === 'social'">
        <label class="ins-l">Networks</label>
        <div v-for="(it, i) in block.items" :key="i" class="flex gap-1.5 mb-1.5">
          <select v-model="it.network" class="m-0 w-[110px]"><option>twitter</option><option>facebook</option><option>linkedin</option><option>instagram</option><option>youtube</option><option>tiktok</option><option>website</option></select>
          <input v-model="it.url" class="m-0" placeholder="https://">
          <button class="text-[#dc2626] bg-transparent border-0 cursor-pointer" @click="removeSocial(i)">🗑</button>
        </div>
        <button class="text-[#6352e7] font-semibold text-[.8rem] bg-transparent border-0 cursor-pointer" @click="addSocial">+ Add network</button>
        <label class="ins-l">Icon size (px)</label>
        <input v-model.number="block.style.iconSize" type="number" min="16" max="60" class="m-0">
        <label class="ins-l">Icon color</label>
        <div class="ins-color"><input v-model="block.style.color" type="color"><input v-model="block.style.color" class="m-0"></div>
      </template>

      <!-- HTML -->
      <template v-else-if="block.type === 'html'">
        <label class="ins-l">Custom HTML</label>
        <textarea v-model="block.html" rows="8" class="m-0 font-mono text-[.78rem]" />
      </template>

      <!-- LOGO -->
      <template v-else-if="block.type === 'logo'">
        <label class="ins-l">Logo image</label>
        <div class="flex gap-2 items-center">
          <label class="btn ghost sm cursor-pointer m-0">{{ uploading ? 'Uploading…' : 'Upload' }}<input type="file" accept="image/*" class="hidden" @change="onImagePick"></label>
        </div>
        <label class="ins-l">Or image URL</label>
        <input v-model="block.src" class="m-0" placeholder="https://…">
        <label class="ins-l">Alt text</label>
        <input v-model="block.alt" class="m-0" placeholder="Company logo">
        <label class="ins-l">Link URL (optional)</label>
        <input v-model="block.href" class="m-0" placeholder="https://…">
        <label class="ins-l">Width (px)</label>
        <input v-model.number="block.style.width" type="number" min="40" max="600" class="m-0">
        <label class="ins-l">Background</label>
        <div class="ins-color"><input :value="block.style.backgroundColor || '#ffffff'" type="color" @input="block.style.backgroundColor = ($event.target as HTMLInputElement).value"><input v-model="block.style.backgroundColor" class="m-0" placeholder="#ffffff"></div>
      </template>

      <!-- VIDEO -->
      <template v-else-if="block.type === 'video'">
        <p class="text-[#8b93a7] text-[.78rem] mb-2">Email clients can't play video inline — we show a thumbnail with a play button that links to the video.</p>
        <label class="ins-l">Thumbnail image</label>
        <div class="flex gap-2 items-center">
          <label class="btn ghost sm cursor-pointer m-0">{{ uploading ? 'Uploading…' : 'Upload' }}<input type="file" accept="image/*" class="hidden" @change="onImagePick"></label>
        </div>
        <label class="ins-l">Or thumbnail URL</label>
        <input v-model="block.src" class="m-0" placeholder="https://…">
        <label class="ins-l">Video link URL</label>
        <input v-model="block.url" class="m-0" placeholder="https://youtube.com/…">
        <label class="ins-l">Corner radius (px)</label>
        <input v-model.number="block.style.borderRadius" type="number" min="0" max="40" class="m-0">
      </template>

      <!-- COLUMNS -->
      <template v-else-if="block.type === 'columns'">
        <label class="ins-l">Number of columns</label>
        <select :value="block.columns?.length" class="m-0" @change="(e) => { const n = +(e.target as HTMLSelectElement).value; const cols = block.columns!; while (cols.length < n) cols.push([]); cols.length = n }">
          <option :value="1">1</option><option :value="2">2</option><option :value="3">3</option>
        </select>
        <label class="ins-l">Gap (px)</label>
        <input v-model.number="block.style.gap" type="number" min="0" max="48" class="m-0">
        <p class="text-[#8b93a7] text-[.78rem] mt-2">Use the <strong>+ Add</strong> buttons inside each column on the canvas to place content.</p>
      </template>

      <!-- shared: alignment + spacing + background -->
      <template v-if="['heading','text','button','image','divider','social'].includes(block.type)">
        <label class="ins-l">Alignment</label>
        <div class="flex gap-1.5">
          <button v-for="a in ALIGNS" :key="a" class="flex-1 capitalize py-1.5 rounded-md border cursor-pointer text-[.8rem]" :class="(s.align || 'left') === a ? 'border-[#6352e7] bg-[#f5f3ff] text-[#6352e7] font-semibold' : 'border-line bg-white'" @click="block.style.align = a">{{ a }}</button>
        </div>
      </template>

      <div class="border-t border-line mt-4 pt-3">
        <h4 class="ins-h">Spacing</h4>
        <div class="flex gap-2">
          <div class="flex-1"><label class="ins-l">Top</label><input v-model.number="block.style.paddingTop" type="number" min="0" class="m-0"></div>
          <div class="flex-1"><label class="ins-l">Bottom</label><input v-model.number="block.style.paddingBottom" type="number" min="0" class="m-0"></div>
        </div>
        <div class="flex gap-2">
          <div class="flex-1"><label class="ins-l">Left</label><input v-model.number="block.style.paddingLeft" type="number" min="0" class="m-0"></div>
          <div class="flex-1"><label class="ins-l">Right</label><input v-model.number="block.style.paddingRight" type="number" min="0" class="m-0"></div>
        </div>
        <label class="ins-l">Block background</label>
        <div class="ins-color"><input :value="block.style.backgroundColor || '#ffffff'" type="color" @input="block.style.backgroundColor = ($event.target as HTMLInputElement).value"><input v-model="block.style.backgroundColor" class="m-0" placeholder="transparent"></div>
        <button v-if="block.style.backgroundColor" class="text-[.74rem] text-[#8b93a7] underline bg-transparent border-0 cursor-pointer mt-1" @click="block.style.backgroundColor = ''">Clear background</button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.ins-h { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: #8b93a7; font-weight: 700; margin: 0 0 .5rem; }
.ins-l { display: block; font-size: .76rem; color: #5f6b7a; font-weight: 600; margin: .7rem 0 .25rem; }
.ins-color { display: flex; gap: .4rem; align-items: center; }
.ins-color input[type=color] { width: 38px; height: 36px; padding: 2px; border: 1px solid var(--line, #e6e8ee); border-radius: 8px; background: #fff; cursor: pointer; flex: none; }
</style>
