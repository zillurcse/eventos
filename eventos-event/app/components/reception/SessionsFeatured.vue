<script setup lang="ts">
import type { ReceptionSession } from '~/stores/reception'

const props = withDefaults(defineProps<{ sessions: ReceptionSession[], title?: string, limit?: number }>(), {
  title: 'Live Sessions',
})

const bookmarks = useBookmarksStore()

const visible = computed(() => props.limit ? props.sessions.slice(0, props.limit) : props.sessions)

const heading = computed(() => {
  const first = props.sessions[0]
  if (!first?.starts_at) return ''
  return new Date(first.starts_at).toLocaleDateString('en-US', {
    weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
  })
})

// Ticks so "Starts in X" pills stay accurate without a page refresh.
const now = ref(Date.now())
let ticker: ReturnType<typeof setInterval> | null = null
onMounted(() => { ticker = setInterval(() => (now.value = Date.now()), 15_000) })
onBeforeUnmount(() => { if (ticker) clearInterval(ticker) })

function startsInLabel(s: ReceptionSession): string {
  if (!s.starts_at) return 'Starts soon'
  let diff = Math.max(0, new Date(s.starts_at).getTime() - now.value)
  const d = Math.floor(diff / 86_400_000); diff -= d * 86_400_000
  const h = Math.floor(diff / 3_600_000); diff -= h * 3_600_000
  const m = Math.floor(diff / 60_000)
  if (d) return `Starts in ${d}d ${h}h`
  if (h) return `Starts in ${h}h ${m}m`
  return `Starts in ${m} minute${m === 1 ? '' : 's'}`
}

function liveProgress(s: ReceptionSession): number {
  if (!s.starts_at || !s.ends_at) return 0
  const start = new Date(s.starts_at).getTime()
  const end = new Date(s.ends_at).getTime()
  if (end <= start) return 0
  return Math.min(100, Math.max(0, ((now.value - start) / (end - start)) * 100))
}

function timeRange(s: ReceptionSession): string {
  if (!s.starts_at) return 'Time to be announced'
  const t = (d: string) => new Date(d).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
  return s.ends_at ? `${t(s.starts_at)} - ${t(s.ends_at)}` : t(s.starts_at)
}

function calendarLink(s: ReceptionSession): string | null {
  if (!s.starts_at) return null
  const fmt = (iso: string) => iso.replace(/[-:]/g, '').replace(/\.\d+/, '')
  const start = fmt(new Date(s.starts_at).toISOString())
  const end = fmt(new Date(s.ends_at || s.starts_at).toISOString())
  const params = new URLSearchParams({
    action: 'TEMPLATE',
    text: s.title,
    dates: `${start}/${end}`,
    details: (s.description || '').replace(/<[^>]+>/g, '').slice(0, 500),
  })
  return `https://calendar.google.com/calendar/render?${params.toString()}`
}

</script>

<template>
  <section class="sessions-featured">
    <header class="head">
      <h2>{{ title }} ({{ sessions.length }})</h2>
      <p v-if="heading" class="date">{{ heading }}</p>
    </header>

    <div class="grid">
      <article v-for="s in visible" :key="s.id" class="scard">
        <div class="top">
          <span class="badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
              <path
                d="M6.66699 0C10.3489 0 13.334 2.98509 13.334 6.66699C13.334 10.3489 10.3489 13.334 6.66699 13.334C2.98509 13.334 0 10.3489 0 6.66699C1.69109e-07 2.98509 2.98509 1.69115e-07 6.66699 0ZM7.80859 3.50879C7.44913 2.40371 5.88485 2.40371 5.52539 3.50879L5.36328 4.00977C5.20252 4.50399 4.7414 4.83886 4.22168 4.83887H3.69727C2.53447 4.83887 2.05111 6.32679 2.99219 7.00977L3.41406 7.31641C3.83502 7.62191 4.01132 8.16356 3.85059 8.6582L3.68848 9.15625C3.32917 10.2615 4.5945 11.1807 5.53516 10.498L5.96191 10.1885C6.38228 9.8834 6.9517 9.8834 7.37207 10.1885L7.79883 10.498C8.73948 11.1807 10.0048 10.2615 9.64551 9.15625L9.4834 8.6582C9.32266 8.16356 9.49897 7.62191 9.91992 7.31641L10.3418 7.00977C11.2829 6.32679 10.7995 4.83887 9.63672 4.83887H9.1123C8.59259 4.83886 8.13147 4.50399 7.9707 4.00977L7.80859 3.50879Z"
                fill="#6452E7" />
            </svg>
          </span>
          <span class="time">{{ timeRange(s) }}</span>

          <div class="acts">
            <button class="act" :class="{ on: bookmarks.isOn('session', s.id) }" type="button" title="Bookmark"
              @click="bookmarks.toggle('session', s.id)">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="20" viewBox="0 0 14 20" fill="none">
                <path d="M11 0H3.00001C2.20436 0 1.4413 0.316071 0.878688 0.87868C0.316078 1.44129 7.88292e-06 2.20435 7.88292e-06 3V19C-0.000691684 19.1762 0.0451825 19.3495 0.132986 19.5023C0.220789 19.655 0.347404 19.7819 0.500008 19.87C0.652027 19.9578 0.824471 20.004 1.00001 20.004C1.17554 20.004 1.34799 19.9578 1.50001 19.87L7.00001 16.69L12.5 19.87C12.6524 19.9564 12.8248 20.0012 13 20C13.1752 20.0012 13.3476 19.9564 13.5 19.87C13.6526 19.7819 13.7792 19.655 13.867 19.5023C13.9548 19.3495 14.0007 19.1762 14 19V3C14 2.20435 13.6839 1.44129 13.1213 0.87868C12.5587 0.316071 11.7957 0 11 0ZM12 17.27L7.50001 14.67C7.34799 14.5822 7.17554 14.536 7.00001 14.536C6.82447 14.536 6.65203 14.5822 6.50001 14.67L2.00001 17.27V3C2.00001 2.73478 2.10536 2.48043 2.2929 2.29289C2.48044 2.10536 2.73479 2 3.00001 2H11C11.2652 2 11.5196 2.10536 11.7071 2.29289C11.8947 2.48043 12 2.73478 12 3V17.27Z" fill="var(--brand-primary)" />
              </svg>
            </button>
            <a v-if="calendarLink(s)" :href="calendarLink(s)!" target="_blank" rel="noopener" class="act"
              title="Add to Google Calendar">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M6.79101 1.07141L6.79102 4.69882" stroke="var(--brand-primary)" stroke-width="2.14286"
                  stroke-linecap="round" />
                <path d="M13.1992 1.07141L13.1992 4.69882" stroke="var(--brand-primary)" stroke-width="2.14286"
                  stroke-linecap="round" />
                <path
                  d="M10 18.9285C8.41623 18.9285 6.95697 18.8927 5.52847 18.8239C3.46172 18.7244 1.79038 17.0917 1.5599 15.0355C1.40469 13.6508 1.27734 12.2311 1.27734 10.7849C1.27734 9.33865 1.40468 7.91896 1.5599 6.53423C1.79038 4.47797 3.46172 2.84533 5.52847 2.74579C6.95697 2.67699 8.41623 2.64124 10 2.64124C11.5838 2.64124 13.043 2.67699 14.4715 2.74579C16.5383 2.84533 18.2096 4.47797 18.4401 6.53423C18.5953 7.91896 18.7227 9.33865 18.7227 10.7849"
                  stroke="var(--brand-primary)" stroke-width="2.14286" stroke-linecap="round" />
                <path d="M15.2148 12L15.2148 18.4286" stroke="var(--brand-primary)" stroke-width="1.72194"
                  stroke-linecap="round" />
                <path d="M18.4277 15.2143L11.9991 15.2143" stroke="var(--brand-primary)" stroke-width="1.72194"
                  stroke-linecap="round" />
                <path
                  d="M5.90988 8.79467C5.71263 8.79467 5.55273 8.63477 5.55273 8.43753C5.55273 8.24028 5.71263 8.08038 5.90988 8.08038"
                  stroke="var(--brand-primary)" stroke-width="2.14286" />
                <path
                  d="M5.90848 8.79467C6.10573 8.79467 6.26562 8.63477 6.26562 8.43753C6.26562 8.24028 6.10573 8.08038 5.90848 8.08038"
                  stroke="var(--brand-primary)" stroke-width="2.14286" />
                <path
                  d="M5.90988 13.4598C5.71263 13.4598 5.55273 13.2999 5.55273 13.1027C5.55273 12.9054 5.71263 12.7455 5.90988 12.7455"
                  stroke="var(--brand-primary)" stroke-width="2.14286" />
                <path
                  d="M5.90848 13.4598C6.10573 13.4598 6.26562 13.2999 6.26562 13.1027C6.26562 12.9054 6.10573 12.7455 5.90848 12.7455"
                  stroke="var(--brand-primary)" stroke-width="2.14286" />
                <path
                  d="M9.99972 8.79467C9.80248 8.79467 9.64258 8.63477 9.64258 8.43753C9.64258 8.24028 9.80248 8.08038 9.99972 8.08038"
                  stroke="var(--brand-primary)" stroke-width="2.14286" />
                <path
                  d="M10.0003 8.79467C10.1975 8.79467 10.3574 8.63477 10.3574 8.43753C10.3574 8.24028 10.1975 8.08038 10.0003 8.08038"
                  stroke="var(--brand-primary)" stroke-width="2.14286" />
              </svg>
            </a>
          </div>
        </div>

        <div class="banner">
          <img v-if="s.logo_url || s.icon_url" :src="(s.logo_url || s.icon_url) as string" :alt="s.title" />
        </div>

        <h3 class="title">{{ s.title }}</h3>

        <div v-if="s.session_place" class="place">
            <div class="icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="17" height="21" viewBox="0 0 17 21" fill="none">
                <path d="M14.4853 2.48528C12.894 0.893983 10.7357 -1.67671e-08 8.48528 0C6.23484 1.67671e-08 4.07658 0.893983 2.48528 2.48528C0.893982 4.07658 1.67671e-08 6.23485 0 8.48528C-1.67671e-08 10.7357 0.893982 12.894 2.48528 14.4853L7.75528 19.7653C7.84824 19.859 7.95885 19.9334 8.0807 19.9842C8.20256 20.0349 8.33327 20.0611 8.46528 20.0611C8.59729 20.0611 8.728 20.0349 8.84986 19.9842C8.97172 19.9334 9.08232 19.859 9.17528 19.7653L14.4853 14.4353C16.0699 12.8506 16.9602 10.7013 16.9602 8.46028C16.9602 6.21923 16.0699 4.06996 14.4853 2.48528ZM13.0553 13.0053L8.48528 17.5953L3.91528 13.0053C3.01243 12.1016 2.39776 10.9505 2.14898 9.69757C1.90019 8.44462 2.02847 7.14603 2.51759 5.96596C3.0067 4.7859 3.8347 3.77733 4.89691 3.06776C5.95913 2.35819 7.20787 1.97946 8.48528 1.97946C9.7627 1.97946 11.0114 2.35819 12.0737 3.06776C13.1359 3.77733 13.9639 4.7859 14.453 5.96596C14.9421 7.14603 15.0704 8.44462 14.8216 9.69757C14.5728 10.9505 13.9581 12.1016 13.0553 13.0053ZM5.48528 5.41528C4.678 6.22505 4.22468 7.32185 4.22468 8.46528C4.22468 9.60872 4.678 10.7055 5.48528 11.5153C6.08504 12.1161 6.84887 12.5264 7.68093 12.6947C8.51299 12.863 9.37623 12.7819 10.1624 12.4614C10.9485 12.141 11.6225 11.5956 12.0998 10.8935C12.5771 10.1915 12.8364 9.36415 12.8453 8.51528C12.8498 7.94849 12.7406 7.38654 12.5242 6.86267C12.3078 6.3388 11.9885 5.86364 11.5853 5.46528C11.189 5.05986 10.7164 4.73682 10.1947 4.51475C9.67305 4.29268 9.11262 4.17596 8.54568 4.17131C7.97874 4.16666 7.41648 4.27418 6.89125 4.48767C6.36602 4.70116 5.8882 5.01641 5.48528 5.41528ZM10.1753 10.0953C9.79632 10.48 9.29549 10.7212 8.75841 10.7775C8.22132 10.8339 7.68134 10.7019 7.23079 10.4042C6.78023 10.1065 6.44708 9.66152 6.28829 9.14536C6.1295 8.62921 6.15493 8.07392 6.36024 7.57444C6.56555 7.07495 6.93798 6.6623 7.41387 6.40702C7.88975 6.15174 8.43953 6.06969 8.96922 6.1749C9.4989 6.28011 9.97559 6.56604 10.3178 6.98381C10.66 7.40158 10.8464 7.92525 10.8453 8.46528C10.8307 9.08255 10.5718 9.66881 10.1253 10.0953H10.1753Z" fill="#64676A"/>
              </svg>
            </div>
          <span>{{ s.session_place }}</span>
        </div>

        <div v-if="s.speakers?.length" class="speakers">
          <span class="label">Speakers ({{ s.speakers.length }})</span>
          <div class="row">
            <div class="avatars">
              <span v-for="sp in s.speakers.slice(0, 4)" :key="sp.id" class="av" :title="sp.name || ''">
                <UserAvatar :src="sp.profile?.image_url" :name="sp.name" />
              </span>
            </div>
            <span v-if="s.speakers.length > 4" class="more">+{{ s.speakers.length - 4 }} More</span>
          </div>
        </div>

        <span v-if="s.status === 'scheduled'" class="starts-in">
          <svg viewBox="0 0 24 24">
            <circle cx="12" cy="13" r="8" />
            <path d="M12 9v4l2.5 2.5M9 1h6" />
          </svg>
          {{ startsInLabel(s) }}
        </span>
        <NuxtLink v-else :to="`/session/${s.id}`" class="join">Join Now</NuxtLink>
        <span class="accent" />
        <div v-if="s.status === 'live'" class="sessions-progress">
          <div class="sessions-progress-bar" :style="{ width: `${liveProgress(s)}%` }" />
        </div>
      </article>
    </div>

    <div class="viewall">
      <span class="line" />
      <NuxtLink to="/sessions" class="viewall-btn">View all {{ title.toLowerCase() }}</NuxtLink>
      <span class="line" />
    </div>
  </section>
</template>

<style scoped>
.sessions-featured {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.4;
  color: #4D5154;
}

.head .date {
  margin: 2px 0 0;
  color: #64676A;
  font-size: 12px;
  line-height: 1.4;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 20px;
}

.scard {
  position: relative;
  background: #fff;
  border: 1px solid #E8E8EE;
  border-radius: 12px;
  padding: 16px;
  padding-bottom: 30px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  /* box-shadow: 0 1px 2px rgba(15, 23, 42, .05); */
}

.top {
  display: flex;
  align-items: center;
  gap: 8px;
}

.badge {
  flex: 0 0 auto;
  width: 26px;
  height: 26px;
  border-radius: 4px;
  background: #F0EEFD;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}

.badge svg {
  width: 14px;
  height: 14px;
  fill: currentColor;
  stroke: none;
  color: var(--brand-primary);
}

.badge svg path {
  fill: currentColor;
}

.time {
  flex: 1;
  min-width: 0;
  font-weight: 700;
  color: #1e293b;
  font-size: .92rem;
}

.acts {
  display: flex;
  gap: 6px;
  flex: 0 0 auto;
}

.act {
  width: 32px;
  height: 32px;
  border-radius: 4px;
  border: none;
  /* background: color-mix(in srgb, var(--brand-primary) 10%, #fff); */
  background: transparent;
  color: var(--brand-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

/* .act.on {
  background: var(--brand-primary);
  color: #fff;
} */

/* .act:hover {
  background: var(--brand-primary);
  color: #fff;
} */

.act svg {
  width: 18px;
  height: 18px;
}

.act.on svg {
  /* fill: currentColor; */
}
.act svg path {
  /* stroke: currentColor; */
}

.banner {
  margin-top: 14px;
  border-radius: 12px;
  overflow: hidden;
  aspect-ratio: 16 / 10;
  background: #f1f5f9;
  border: 1px solid #f1f5f9;
}

.banner img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.title {
  margin: 14px 0 0;
  font-size: 1rem;
  font-weight: 800;
  color: #1e293b;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: calc(1.35em * 2);
}

.place {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  margin-bottom: 8px;
  padding-top: 12px;
  color: #64748b;
  font-size: 14px;
}
.place .icon{
  width: 32px;
  height: 32px;
  border-radius: 8px;
  flex: 0 0 auto;
  background: #F7F7FB;
  display: flex;
  align-items: center;
  justify-content: center;
}

.place span {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.4;
}



.speakers {
  margin-top: 12px;
  padding-top: 12px;
  margin-bottom: 12px;
  padding-bottom: 14px;

  border-top: 1px solid #f1f2f6;
  border-bottom: 1px solid #f1f2f6;

}

.speakers .label {
  display: block;
  color: #64676A;
  font-size: 12px;
  font-weight: 400;
  margin-bottom: 8px;
}

.speakers .row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatars {
  display: flex;
}

.av {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  margin-left: -8px;
  border: 0.78px solid #FFFFFF;
  background: var(--brand-primary);
  color: #fff;
  font-size: .68rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.av:first-child {
  margin-left: 0;
}

.av img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.more {
  color: var(--brand-primary);
  font-weight: 700;
  font-size: .84rem;
}

.join {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-top: auto;
  padding: 12px 20px;
  max-height: 42px;
  border-radius: 10px;
  background: var(--brand-primary);
  color: #fff;
  font-weight: 700;
  font-size: .88rem;
  text-decoration: none;
  align-self: flex-start;
}

.join:hover {
  background: color-mix(in srgb, var(--brand-primary) 88%, #000);
}

.starts-in {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  margin-top: auto;
  padding: 12px 14px;
  max-height: 42px;
  border-radius: 10px;
  background: #E7F8EE;
  color: #1A9A55;
  font-weight: 600;
  font-size: 14px;
  align-self: flex-start;
}

.starts-in svg {
  width: 16px;
  height: 16px;
  flex: 0 0 auto;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.sessions-progress {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 8px;
  background: #f1f2f6;
  border-radius: 999px;
  margin-top: 8px;
}
.sessions-progress-bar {
  position: absolute;
  left: 0;
  top: 0;
  height: 100%;
  background: var(--brand-primary);
  border-radius: 999px;
}

.viewall {
  display: flex;
  align-items: center;
  gap: 24px;
  margin-top: 8px;
}

.viewall .line {
  flex: 1;
  height: 1px;
  background: #D1D2DE;
}

.viewall-btn {
  flex: 0 0 auto;
  padding: 8px 16px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
  color: var(--brand-primary);
  font-weight: 700;
  font-size: .88rem;
  text-decoration: none;
  text-transform: capitalize;
}

.viewall-btn:hover {
  background: color-mix(in srgb, var(--brand-primary) 18%, #fff);
}
/* .accent {
  position: absolute;
  left: 16px;
  bottom: -4px;
  width: 30%;
  height: 4px;
  border-radius: 999px;
  background: var(--brand-primary);
} */


</style>
