<script setup lang="ts">
const { memberForm, subSaving, subError, members, addMember, removeMember } = useExhibitorContext()

const columns = [
  { key: 'member', label: 'Member' },
  { key: 'role', label: 'Role' },
]
</script>

<template>
  <div>
    <!-- Invite form -->
    <div class="border border-line rounded-xl p-4 mb-5 bg-[#f7f8fa]">
      <p class="font-semibold text-[.92rem] m-0 mb-3 text-ink">Invite a team member</p>
      <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
        <AppInput v-model="memberForm.email" type="email" label="Email" placeholder="name@company.com" />
        <AppInput v-model="memberForm.first_name" label="First name" placeholder="First name" />
        <AppInput v-model="memberForm.last_name" label="Last name" placeholder="Last name" />
        <AppSelect v-model="memberForm.role" label="Role" :options="[{ value: 'staff', label: 'Staff' }, { value: 'admin', label: 'Admin' }]" />
        <AppInput v-model="memberForm.password" type="password" label="Password" placeholder="Enables login" />
      </div>
      <div class="flex justify-end mt-3">
        <button class="btn sm" :disabled="subSaving || !memberForm.email" @click="addMember">
          {{ subSaving ? 'ADDING…' : '+ ADD MEMBER' }}
        </button>
      </div>
      <p v-if="subError" class="error mt-2 mb-0">{{ subError }}</p>
    </div>

    <!-- Members table -->
    <DataTable
      :items="members"
      :columns="columns"
      row-key="id"
      storage-key="exhibitor-members"
      empty-text="No members yet."
    >
      <template #cell-member="{ row }">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-full bg-brand-soft text-brand flex items-center justify-center font-bold text-[.72rem] shrink-0 uppercase">
            {{ exhibitorInitials(row.contact?.name || row.contact?.email || '') }}
          </div>
          <div class="min-w-0">
            <div class="font-semibold text-ink text-[.88rem] truncate">{{ row.contact?.name || row.contact?.email }}</div>
            <div class="muted text-[.78rem] truncate">{{ row.contact?.email }}</div>
          </div>
        </div>
      </template>
      <template #cell-role="{ row }">
        <span class="badge capitalize">{{ row.role }}</span>
      </template>
      <template #actions="{ row }">
        <ExhibitorRowDeleteButton title="Remove member" @click="removeMember(row)" />
      </template>
    </DataTable>
  </div>
</template>
