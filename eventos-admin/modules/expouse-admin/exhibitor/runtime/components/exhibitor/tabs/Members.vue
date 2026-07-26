<script setup lang="ts">
const { memberForm, subSaving, subError, members, addMember, removeMember } = useExhibitorContext()

const showAdd = ref(false)

function openAdd() {
  // memberForm is shared with the collection (which blanks it after a save);
  // reset here too so a cancelled draft never leaks into the next open.
  Object.assign(memberForm, MEMBER_FORM)
  subError.value = ''
  showAdd.value = true
}

async function submit() {
  if (!memberForm.email.trim()) return
  await addMember()
  if (!subError.value) showAdd.value = false
}

const columns = [
  { key: 'member', label: 'Team Member' },
  { key: 'role', label: 'Role' },
]
</script>

<template>
  <div>
    <div class="flex items-center justify-between gap-3 mb-4">
      <p class="font-semibold text-[.92rem] m-0 text-ink">Teams</p>
      <button class="btn sm" @click="openAdd">+ ADD TEAM</button>
    </div>

    <!-- Teams table -->
    <DataTable
      :items="members"
      :columns="columns"
      row-key="id"
      storage-key="exhibitor-members"
      empty-text="No team members yet."
    >
      <template #cell-member="{ row }">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-full bg-[#F0EEFD] text-brand flex items-center justify-center font-bold text-[.72rem] shrink-0 uppercase">
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
        <ExhibitorRowDeleteButton title="Remove team member" @click="removeMember(row)" />
      </template>
    </DataTable>

    <!-- Add Team drawer -->
    <Drawer v-if="showAdd" title="Add Team" back @close="showAdd = false" @back="showAdd = false">
      <div>
        <AppInput v-model="memberForm.email" type="email" label="Email" placeholder="name@company.com" />
      </div>

      <div class="mt-4">
        <AppInput v-model="memberForm.first_name" label="First Name" placeholder="Enter First Name" />
      </div>

      <div class="mt-4">
        <AppInput v-model="memberForm.last_name" label="Last Name" placeholder="Enter Last Name" />
      </div>

      <div class="mt-4">
        <AppSelect
          v-model="memberForm.role"
          label="Role"
          :options="[{ value: 'staff', label: 'Staff' }, { value: 'admin', label: 'Admin' }]"
        />
      </div>

      <div class="mt-4">
        <AppInput v-model="memberForm.password" type="password" label="Password" placeholder="Enables login (optional)" />
      </div>

      <p v-if="subError" class="error mt-3 mb-0">{{ subError }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-6">
        <button
          class="btn"
          :disabled="subSaving || !memberForm.email.trim()"
          @click="submit"
        >
          {{ subSaving ? 'Adding…' : 'Add Team' }}
        </button>
        <button class="btn ghost" @click="showAdd = false">Cancel</button>
      </div>
    </Drawer>
  </div>
</template>
