<script setup lang="ts">
import type { ChatPerson } from '~/stores/chat'

definePageMeta({ layout: 'event', middleware: 'auth' })

const chat = useChatStore()
const pickerOpen = ref(false)

onMounted(() => { if (!chat.loaded) chat.fetchInbox() })

async function pick(person: ChatPerson) {
  pickerOpen.value = false
  await chat.openWith(person.id)
}

// On phones the two panes stack: list first, thread after a selection.
const mobileThread = computed(() => !!chat.activeId)
</script>

<template>
  <div class="wrap">
    <div class="shell" :class="{ 'show-thread': mobileThread }">
      <ChatConversationList
        class="left"
        :conversations="chat.conversations"
        :active-id="chat.activeId"
        :loading="chat.loading"
        @select="chat.select($event)"
        @new="pickerOpen = true"
      />

      <div class="right">
        <button v-if="chat.activeId" class="back" type="button" @click="chat.activeId = null">
          <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6" /></svg> All conversations
        </button>
        <ChatThread
          class="grow"
          :conversation="chat.active"
          :messages="chat.messages"
          :loading="chat.messagesLoading"
          :sending="chat.sending"
          @send="chat.send($event)"
        />
      </div>
    </div>

    <ChatNewChatModal v-if="pickerOpen" @close="pickerOpen = false" @pick="pick" />
  </div>
</template>

<style scoped>
.wrap { max-width: 1180px; margin: 0 auto; }
.shell {
  display: grid; grid-template-columns: 330px minmax(0, 1fr);
  height: calc(100vh - 210px); min-height: 460px;
  background: #fff; border-radius: 14px; overflow: hidden;
  box-shadow: 0 1px 2px rgba(15,23,42,.05);
}
.right { display: flex; flex-direction: column; min-height: 0; }
.grow { flex: 1; min-height: 0; }
.back { display: none; align-items: center; gap: 6px; border: none; background: #fff; border-bottom: 1px solid #eef0f3; color: var(--brand-primary); font: inherit; font-size: .8rem; font-weight: 700; padding: 10px 14px; cursor: pointer; }
.back svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

@media (max-width: 860px) {
  .shell { grid-template-columns: 1fr; height: calc(100vh - 190px); }
  .right { display: none; }
  .shell.show-thread .left { display: none; }
  .shell.show-thread .right { display: flex; }
  .back { display: inline-flex; }
}
</style>
