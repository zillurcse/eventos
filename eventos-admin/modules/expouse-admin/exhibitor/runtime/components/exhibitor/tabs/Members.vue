<script setup lang="ts">
const { memberForm, subSaving, subError, members, addMember, removeMember } = useExhibitorContext()
</script>

<template>
  <div>
    <div class="border border-line rounded-xl p-4 mb-4">
      <p class="font-semibold text-[.92rem] m-0 mb-2">Invite a team member</p>
      <div class="flex flex-wrap gap-2 items-center">
        <input v-model="memberForm.email" type="email" placeholder="Email" class="flex-[1_1_180px] m-0">
        <input v-model="memberForm.first_name" placeholder="First name" class="flex-[1_1_120px] m-0">
        <input v-model="memberForm.last_name" placeholder="Last name" class="flex-[1_1_120px] m-0">
        <select v-model="memberForm.role" class="m-0" style="width:120px;">
          <option value="staff">Staff</option>
          <option value="admin">Admin</option>
        </select>
        <input v-model="memberForm.password" type="password" placeholder="Password (enables login)" class="flex-[1_1_160px] m-0">
        <button class="btn sm" :disabled="subSaving || !memberForm.email" @click="addMember">ADD</button>
      </div>
    </div>
    <table>
      <thead><tr><th>Member</th><th>Role</th><th>Login</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
        <tr v-for="m in members" :key="m.id">
          <td><span class="font-semibold text-ink">{{ m.contact?.name || m.contact?.email }}</span><br><span class="muted text-[.8rem]">{{ m.contact?.email }}</span></td>
          <td><span class="badge">{{ m.role }}</span></td>
          <td><span v-if="m.contact?.can_login" class="badge active">can sign in</span><span v-else class="muted">no login</span></td>
          <td class="text-right"><button class="bg-transparent border-0 cursor-pointer text-[#dc2626]" title="Remove" @click="removeMember(m)">🗑</button></td>
        </tr>
        <tr v-if="!members.length"><td colspan="4" class="muted text-center py-8">No members yet.</td></tr>
      </tbody>
    </table>
    <p v-if="subError" class="error mt-2">{{ subError }}</p>
  </div>
</template>
