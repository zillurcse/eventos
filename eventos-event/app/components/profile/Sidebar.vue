<script setup lang="ts">
/**
 * Left rail of the Profile area: who you are, then two nav groups. Each row
 * is its own route under /profile/* (Edit Profile lives at /profile itself)
 * — Briefcase's file count badge still comes from the same store that backs
 * the header's quick-access drawer. The rest render disabled with "Coming
 * soon", same convention as the top nav's not-yet-built tabs (see
 * EventHeader's TAB_META fallback).
 */
const auth = useAuthStore()
const profile = useProfileStore()
const bookmarks = useBookmarksStore()
const briefcase = useBriefcaseStore()
const route = useRoute()

const jobLine = computed(() => {
  const p = profile.data
  if (!p) return ''
  return [p.job_title, p.company].filter(Boolean).join(' at ')
})
</script>

<template>
  <aside class="side">
    <div class="who">
      <span class="av"><UserAvatar :src="profile.data?.avatar_url" :name="auth.user?.name" /></span>
      <div class="who-text">
        <strong>{{ auth.user?.name }}</strong>
        <small v-if="jobLine">{{ jobLine }}</small>
      </div>
    </div>

    <p class="group-title">General</p>
    <nav class="list">
      <NuxtLink to="/profile" class="row" :class="{ active: route.path === '/profile' }">
        <svg viewBox="0 0 24 24"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 3 21.5l1-4.5z" /></svg>
        Edit Profile
        <svg v-if="route.path === '/profile'" class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
      </NuxtLink>
      <NuxtLink to="/profile/bookmarks" class="row" :class="{ active: route.path === '/profile/bookmarks' }">
        <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
        My Bookmarks
        <svg v-if="route.path === '/profile/bookmarks'" class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
        <span v-else-if="bookmarks.count('speaker') + bookmarks.count('session') + bookmarks.count('delegate') + bookmarks.count('exhibitor')" class="count">
          {{ bookmarks.count('speaker') + bookmarks.count('session') + bookmarks.count('delegate') + bookmarks.count('exhibitor') }}
        </span>
      </NuxtLink>
      <NuxtLink to="/profile/briefcase" class="row" :class="{ active: route.path === '/profile/briefcase' }">
        <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" /></svg>
        My Briefcase
        <svg v-if="route.path === '/profile/briefcase'" class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
        <span v-else-if="briefcase.count" class="count">{{ briefcase.count }}</span>
      </NuxtLink>
      <NuxtLink to="/profile/schedule" class="row" :class="{ active: route.path === '/profile/schedule' }">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M8 2v4M16 2v4M3 10h18" /></svg>
        My Schedule
        <svg v-if="route.path === '/profile/schedule'" class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
      </NuxtLink>
      <button type="button" class="row" disabled title="Coming soon">
        <svg viewBox="0 0 24 24"><path d="M4 4h16v12H4zM9 21h6M9 16v5" /></svg>
        My Certificates
      </button>
      <button type="button" class="row" disabled title="Coming soon">
        <svg viewBox="0 0 24 24"><path d="M12 3l7 4v5c0 4.4-3 8.3-7 9-4-.7-7-4.6-7-9V7z" /></svg>
        My Badge
      </button>
    </nav>

    <p class="group-title">Account Settings</p>
    <nav class="list">
      <NuxtLink to="/profile/password" class="row" :class="{ active: route.path === '/profile/password' }">
        <svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></svg>
        Change Password
        <svg v-if="route.path === '/profile/password'" class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
      </NuxtLink>
      <NuxtLink to="/profile/notifications" class="row" :class="{ active: route.path === '/profile/notifications' }">
        <svg viewBox="0 0 24 24"><path d="M6 16V10a6 6 0 0 1 12 0v6l2 2H4zM10 21h4" /></svg>
        Notifications
        <svg v-if="route.path === '/profile/notifications'" class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
      </NuxtLink>
      <NuxtLink to="/profile/language" class="row" :class="{ active: route.path === '/profile/language' }">
        <svg viewBox="0 0 24 24"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM3 11h16M11 3a13 13 0 0 1 0 16 13 13 0 0 1 0-16z" /></svg>
        Language &amp; Time zone
        <svg v-if="route.path === '/profile/language'" class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
      </NuxtLink>
      <NuxtLink to="/profile/sessions" class="row" :class="{ active: route.path === '/profile/sessions' }">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="13" rx="2" /><path d="M8 21h8M12 17v4" /></svg>
        Browser Session
        <svg v-if="route.path === '/profile/sessions'" class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
      </NuxtLink>
    </nav>

    <button type="button" class="logout" @click="auth.logout()">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" /></svg>
      Log out
    </button>
  </aside>
</template>

<style scoped>
.side { background: #fff; border-radius: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.05); padding: 18px; display: flex; flex-direction: column; gap: 4px; }

.who { display: flex; align-items: center; gap: 12px; padding: 6px 6px 18px; }
.av { width: 48px; height: 48px; border-radius: 50%; flex: 0 0 auto; overflow: hidden; background: var(--brand-primary); }
.who-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.who-text strong { color: #1e293b; font-size: .95rem; }
.who-text small { color: #94a3b8; font-size: .78rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.group-title { margin: 10px 6px 6px; color: #94a3b8; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.list { display: flex; flex-direction: column; gap: 2px; margin-bottom: 6px; }

.row {
  display: flex; align-items: center; gap: 10px; width: 100%; border: none; background: none; border-radius: 10px;
  padding: 10px 10px; font: inherit; font-size: .88rem; font-weight: 600; color: #475569; text-align: left; cursor: pointer;
  text-decoration: none; box-sizing: border-box;
}
.row svg { flex: 0 0 auto; width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.row:not(:disabled):not(.active):hover { background: #f7f8fa; }
.row.active { background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); }
.row .chev { margin-left: auto; width: 14px; height: 14px; }
.row .count { margin-left: auto; background: #f1f5f9; color: #64748b; border-radius: 999px; padding: 1px 8px; font-size: .72rem; }
.row:disabled { opacity: .55; cursor: default; }

.logout {
  display: flex; align-items: center; gap: 10px; width: 100%; border: none; border-radius: 10px; margin-top: 10px;
  padding: 11px 10px; font: inherit; font-size: .88rem; font-weight: 700; color: #ef4444; background: #fef2f2; cursor: pointer;
}
.logout:hover { background: #fee2e2; }
.logout svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
</style>
