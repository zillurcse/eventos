<script setup lang="ts">
import type { ReceptionPayload } from '~/stores/reception'

const props = defineProps<{ about: ReceptionPayload['about'] }>()

const expanded = ref(false)

const image = computed(() => props.about.logo_url || props.about.cover_url)

const locationText = computed(() => {
  const loc = props.about.location
  if (!loc) return ''
  if (typeof loc === 'string') return loc
  return loc.address || loc.url || ''
})

const dateRange = computed(() => {
  const s = props.about.starts_at ? new Date(props.about.starts_at) : null
  const e = props.about.ends_at ? new Date(props.about.ends_at) : null
  if (!s) return ''
  const opt: Intl.DateTimeFormatOptions = { day: '2-digit', month: 'short', year: 'numeric' }
  const sStr = s.toLocaleDateString('en-GB', opt)
  const eStr = e ? e.toLocaleDateString('en-GB', opt) : null
  return eStr && eStr !== sStr ? `${sStr.replace(/,.*/, '')} - ${eStr}` : sStr
})

const timeRange = computed(() => {
  const s = props.about.starts_at ? new Date(props.about.starts_at) : null
  const e = props.about.ends_at ? new Date(props.about.ends_at) : null
  const t = (d: Date) => d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
  if (!s) return ''
  return e ? `${t(s)} - ${t(e)}` : t(s)
})

const socials = computed(() => {
  const s = props.about.social || {}
  return ([
    ['facebook', s.facebook],
    ['instagram', s.instagram],
    ['whatsapp', s.whatsapp],
    ['twitter', s.twitter],
    ['linkedin', s.linkedin],
    ['website', s.website],
  ] as const).filter(([, url]) => !!url)
})

const ICON_VIEWBOX: Record<string, string> = {
  facebook: '0 0 13 22',
  instagram: '0 0 24 24',
  whatsapp: '0 0 22 22',
  twitter: '0 0 20 18',
  linkedin: '0 0 24 24',
  website: '0 0 24 24',
}

const ICON: Record<string, string> = {
  facebook: '<path d="M12 1H9C7.67392 1 6.40215 1.52678 5.46447 2.46447C4.52678 3.40215 4 4.67392 4 6V9H1V13H4V21H8V13H11L12 9H8V6C8 5.73478 8.10536 5.48043 8.29289 5.29289C8.48043 5.10536 8.73478 5 9 5H12V1Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
  instagram: '<path d="M17 2H7C4.23858 2 2 4.23858 2 7V17C2 19.7614 4.23858 22 7 22H17C19.7614 22 22 19.7614 22 17V7C22 4.23858 19.7614 2 17 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M15.9997 11.3701C16.1231 12.2023 15.981 13.0523 15.5935 13.7991C15.206 14.5459 14.5929 15.1515 13.8413 15.5297C13.0898 15.908 12.2382 16.0397 11.4075 15.906C10.5768 15.7723 9.80947 15.3801 9.21455 14.7852C8.61962 14.1903 8.22744 13.4229 8.09377 12.5923C7.96011 11.7616 8.09177 10.91 8.47003 10.1584C8.84829 9.40691 9.45389 8.7938 10.2007 8.4063C10.9475 8.0188 11.7975 7.87665 12.6297 8.00006C13.4786 8.12594 14.2646 8.52152 14.8714 9.12836C15.4782 9.73521 15.8738 10.5211 15.9997 11.3701Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M17.5 6.5H17.51" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
  whatsapp: '<path d="M16.0402 13.1827C15.7672 13.0463 14.4209 12.3861 14.17 12.2959C13.9191 12.2046 13.7367 12.1595 13.5544 12.4323C13.372 12.7041 12.847 13.3191 12.6867 13.5007C12.5275 13.6822 12.3673 13.7042 12.0943 13.5678C11.2874 13.248 10.5428 12.7908 9.89359 12.2167C9.29487 11.6658 8.78158 11.0295 8.37048 10.3287C8.21132 10.0558 8.3539 9.90838 8.49096 9.77305C8.61365 9.65092 8.76507 9.45508 8.90103 9.29555C9.01363 9.15782 9.10593 9.00481 9.17514 8.84115C9.21156 8.76593 9.22852 8.68285 9.22446 8.59946C9.22041 8.51606 9.19548 8.435 9.15193 8.36364C9.0834 8.22721 8.53627 6.88602 8.30858 6.3403C8.08642 5.80998 7.86093 5.8815 7.69182 5.87269C7.53266 5.86499 7.35028 5.86279 7.16791 5.86279C7.02917 5.86664 6.89274 5.89903 6.7672 5.95792C6.64165 6.01681 6.52971 6.10092 6.4384 6.20497C6.12895 6.49668 5.88392 6.84936 5.71893 7.24053C5.55394 7.63169 5.4726 8.05278 5.4801 8.47696C5.56886 9.50485 5.95724 10.4846 6.59757 11.2958C7.77131 13.0474 9.38253 14.4651 11.273 15.4096C11.783 15.6277 12.3039 15.8194 12.8337 15.9839C13.3923 16.1527 13.9827 16.1893 14.558 16.0906C14.939 16.0138 15.2998 15.8591 15.6177 15.6364C15.9356 15.4137 16.2037 15.1278 16.4049 14.7968C16.5845 14.3896 16.6398 13.9389 16.5641 13.5007C16.4967 13.3862 16.3143 13.3191 16.0402 13.1827ZM18.7946 3.19361C16.9155 1.32359 14.4178 0.197003 11.7664 0.0235996C9.11503 -0.149804 6.49069 0.64179 4.38197 2.25101C2.27324 3.86022 0.823849 6.1774 0.303621 8.77113C-0.216607 11.3649 0.227784 14.0584 1.55406 16.3503L0 22L5.80728 20.485C7.41346 21.3556 9.21315 21.8117 11.042 21.8119H11.0464C13.2129 21.8108 15.3305 21.1705 17.1316 19.9719C18.9327 18.7734 20.3365 17.0703 21.1656 15.0779C21.9948 13.0856 22.2121 10.8932 21.7901 8.77797C21.3681 6.66271 20.3257 4.71941 18.7946 3.1936V3.19361ZM15.8843 18.5849C14.4343 19.4896 12.7577 19.9696 11.0464 19.9701H11.042C9.41157 19.97 7.81118 19.5333 6.40856 18.7059L6.07586 18.51L2.62952 19.41L3.54914 16.0653L3.3336 15.722C2.37701 14.2035 1.89425 12.4368 1.94637 10.6452C1.99849 8.85354 2.58315 7.11755 3.62642 5.65669C4.66969 4.19583 6.12471 3.07571 7.80749 2.43798C9.49027 1.80025 11.3252 1.67354 13.0804 2.07389C14.8355 2.47423 16.4319 3.38364 17.6678 4.68713C18.9037 5.99061 19.7236 7.62963 20.0237 9.39692C20.3239 11.1642 20.0908 12.9804 19.354 14.6158C18.6172 16.2513 17.4097 17.6325 15.8843 18.5849Z" fill="currentColor"/>',
  twitter: '<path d="M15.7512 0H18.818L12.1179 7.62462L20 18H13.8284L8.99458 11.7074L3.46359 18H0.394938L7.5613 9.84461L0 0H6.32828L10.6976 5.75169L15.7512 0ZM14.6748 16.1723H16.3742L5.4049 1.73169H3.58133L14.6748 16.1723Z" fill="currentColor"/>',
  linkedin: '<path d="M6 9H2V21H6V9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 8C17.5913 8 19.1174 8.63214 20.2426 9.75736C21.3679 10.8826 22 12.4087 22 14V21H18V14C18 13.4696 17.7893 12.9609 17.4142 12.5858C17.0391 12.2107 16.5304 12 16 12C15.4696 12 14.9609 12.2107 14.5858 12.5858C14.2107 12.9609 14 13.4696 14 14V21H10V14C10 12.4087 10.6321 10.8826 11.7574 9.75736C12.8826 8.63214 14.4087 8 16 8V8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 6C5.10457 6 6 5.10457 6 4C6 2.89543 5.10457 2 4 2C2.89543 2 2 2.89543 2 4C2 5.10457 2.89543 6 4 6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
  website: '<path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 12H22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 2C14.5013 4.73835 15.9228 8.29203 16 12C15.9228 15.708 14.5013 19.2616 12 22C9.49872 19.2616 8.07725 15.708 8 12C8.07725 8.29203 9.49872 4.73835 12 2V2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
}
</script>

<template>
  <div class="about-card">
    <div class="head">
      <div v-if="image" class="mark">
        <img :src="image" :alt="about.name" />
      </div>
      <div v-else class="mark placeholder">{{ (about.name || 'E').slice(0, 3).toUpperCase() }}</div>

      <div class="head-text">
        <h3 class="ttl">{{ about.name }}</h3>

        <div class="meta">
          <div v-if="dateRange" class="meta-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M17 2H15V1C15 0.734784 14.8946 0.48043 14.7071 0.292893C14.5196 0.105357 14.2652 0 14 0C13.7348 0 13.4804 0.105357 13.2929 0.292893C13.1054 0.48043 13 0.734784 13 1V2H7V1C7 0.734784 6.89464 0.48043 6.70711 0.292893C6.51957 0.105357 6.26522 0 6 0C5.73478 0 5.48043 0.105357 5.29289 0.292893C5.10536 0.48043 5 0.734784 5 1V2H3C2.20435 2 1.44129 2.31607 0.87868 2.87868C0.316071 3.44129 0 4.20435 0 5V17C0 17.7956 0.316071 18.5587 0.87868 19.1213C1.44129 19.6839 2.20435 20 3 20H17C17.7956 20 18.5587 19.6839 19.1213 19.1213C19.6839 18.5587 20 17.7956 20 17V5C20 4.20435 19.6839 3.44129 19.1213 2.87868C18.5587 2.31607 17.7956 2 17 2ZM18 17C18 17.2652 17.8946 17.5196 17.7071 17.7071C17.5196 17.8946 17.2652 18 17 18H3C2.73478 18 2.48043 17.8946 2.29289 17.7071C2.10536 17.5196 2 17.2652 2 17V10H18V17ZM18 8H2V5C2 4.73478 2.10536 4.48043 2.29289 4.29289C2.48043 4.10536 2.73478 4 3 4H5V5C5 5.26522 5.10536 5.51957 5.29289 5.70711C5.48043 5.89464 5.73478 6 6 6C6.26522 6 6.51957 5.89464 6.70711 5.70711C6.89464 5.51957 7 5.26522 7 5V4H13V5C13 5.26522 13.1054 5.51957 13.2929 5.70711C13.4804 5.89464 13.7348 6 14 6C14.2652 6 14.5196 5.89464 14.7071 5.70711C14.8946 5.51957 15 5.26522 15 5V4H17C17.2652 4 17.5196 4.10536 17.7071 4.29289C17.8946 4.48043 18 4.73478 18 5V8Z" fill="#64676A"/>
              </svg>
            <span>{{ dateRange }}<template v-if="timeRange"> | {{ timeRange }}</template></span>
          </div>
          <div v-if="locationText" class="meta-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="21" viewBox="0 0 17 21" fill="none">
              <path d="M14.4853 2.48528C12.894 0.893983 10.7357 -1.67671e-08 8.48528 0C6.23484 1.67671e-08 4.07658 0.893983 2.48528 2.48528C0.893982 4.07658 1.67671e-08 6.23485 0 8.48528C-1.67671e-08 10.7357 0.893982 12.894 2.48528 14.4853L7.75528 19.7653C7.84824 19.859 7.95885 19.9334 8.0807 19.9842C8.20256 20.0349 8.33327 20.0611 8.46528 20.0611C8.59729 20.0611 8.728 20.0349 8.84986 19.9842C8.97172 19.9334 9.08232 19.859 9.17528 19.7653L14.4853 14.4353C16.0699 12.8506 16.9602 10.7013 16.9602 8.46028C16.9602 6.21923 16.0699 4.06996 14.4853 2.48528ZM13.0553 13.0053L8.48528 17.5953L3.91528 13.0053C3.01243 12.1016 2.39776 10.9505 2.14898 9.69757C1.90019 8.44462 2.02847 7.14603 2.51759 5.96596C3.0067 4.7859 3.8347 3.77733 4.89691 3.06776C5.95913 2.35819 7.20787 1.97946 8.48528 1.97946C9.7627 1.97946 11.0114 2.35819 12.0737 3.06776C13.1359 3.77733 13.9639 4.7859 14.453 5.96596C14.9421 7.14603 15.0704 8.44462 14.8216 9.69757C14.5728 10.9505 13.9581 12.1016 13.0553 13.0053ZM5.48528 5.41528C4.678 6.22505 4.22468 7.32185 4.22468 8.46528C4.22468 9.60872 4.678 10.7055 5.48528 11.5153C6.08504 12.1161 6.84887 12.5264 7.68093 12.6947C8.51299 12.863 9.37623 12.7819 10.1624 12.4614C10.9485 12.141 11.6225 11.5956 12.0998 10.8935C12.5771 10.1915 12.8364 9.36415 12.8453 8.51528C12.8498 7.94849 12.7406 7.38654 12.5242 6.86267C12.3078 6.3388 11.9885 5.86364 11.5853 5.46528C11.189 5.05986 10.7164 4.73682 10.1947 4.51475C9.67305 4.29268 9.11262 4.17596 8.54568 4.17131C7.97874 4.16666 7.41648 4.27418 6.89125 4.48767C6.36602 4.70116 5.8882 5.01641 5.48528 5.41528ZM10.1753 10.0953C9.79632 10.48 9.29549 10.7212 8.75841 10.7775C8.22132 10.8339 7.68134 10.7019 7.23079 10.4042C6.78023 10.1065 6.44708 9.66152 6.28829 9.14536C6.1295 8.62921 6.15493 8.07392 6.36024 7.57444C6.56555 7.07495 6.93798 6.6623 7.41387 6.40702C7.88975 6.15174 8.43953 6.06969 8.96922 6.1749C9.4989 6.28011 9.97559 6.56604 10.3178 6.98381C10.66 7.40158 10.8464 7.92525 10.8453 8.46528C10.8307 9.08255 10.5718 9.66881 10.1253 10.0953H10.1753Z" fill="#64676A"/>
            </svg>
            <span>{{ locationText }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="divider" />

    <div v-if="socials.length" class="socials">
      <a v-for="[key, url] in socials" :key="key" :href="url as string" target="_blank" rel="noopener"
        :aria-label="key">
        <svg :viewBox="ICON_VIEWBOX[key]" v-html="ICON[key]" />
      </a>
    </div>

    <p v-if="about.description" class="desc" :class="{ clamp: !expanded }">{{ about.description }}</p>
    <a v-if="about.description && about.description.length > 160" href="#" class="more"
      @click.prevent="expanded = !expanded">
      {{ expanded ? 'Less Details' : 'More Details' }}
      <svg viewBox="0 0 24 24" :class="{ up: expanded }">
        <path d="M6 9l6 6 6-6" />
      </svg>
    </a>
  </div>
</template>

<style scoped>
.about-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  border: 1px solid #E8E8EE
}

.head {
  display: flex;
  align-items: center;
  gap: 14px;
}

.mark {
  flex: 0 0 64px;
}

.mark img {
  width: 96px;
  height: 96px;
  object-fit: cover;
  border-radius: 14px;
}

.mark.placeholder {
  width: 64px;
  height: 64px;
  border-radius: 14px;
  background: var(--brand-primary);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: .8rem;
}

.head-text {
  flex: 1;
  min-width: 0;
}

.ttl {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: #1e293b;
  line-height: 1.3;
}

.meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 10px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #64676A;
  font-size: 14px;
}

.meta-item svg {
  width: 17px;
  height: 17px;
  flex: 0 0 auto;
}

.divider {
  margin-top: 16px;
  border-top: 1px solid #f1f5f9;
}

.socials {
  display: flex;
  gap: 12px;
  margin-top: 16px;
}

.socials a {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: #F7F7FB;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #475569;
  transition: all .15s ease;
}

.socials a:hover {
  background: var(--brand-primary);
  color: #fff;
}

.socials svg {
  width: 20px;
  height: 20px;
  fill: none;
  /* stroke: currentColor; */
}

.desc {
  margin: 16px 0 0;
  color: #64748b;
  font-size: .86rem;
  line-height: 1.6;
}

.desc.clamp {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.more {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 8px;
  font-size: 14px;
  font-weight: 400;
  color: var(--brand-primary);
}

.more svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform .15s ease;
}

.more svg.up {
  transform: rotate(180deg);
}

@media (max-width: 640px) {
  .head {
    align-items: flex-start;
  }
}
</style>
