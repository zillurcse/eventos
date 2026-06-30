<template>
  <div
    v-if="qrcodeStore.showModal"
    class="fixed inset-0 flex items-center justify-center z-50 bg-black/50 backdrop-blur-sm animate-fadeIn"
  >
    <div
      class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 relative transform transition-all scale-95 animate-slideUp"
    >
      <!-- Header -->
      <div class="flex justify-between items-center border-b pb-3">
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
          <NuxtIcon name="bi:qr-code" /> QR Code Generator
        </h2>
        <button
          @click="qrcodeStore.qrCodetoggleModal"
          class="text-gray-400 hover:text-red-500 transition"
        >
          <span class="text-2xl">&times;</span>
        </button>
      </div>

      <!-- Body -->
      <div class="text-8xl py-2">
        <NuxtIcon name="vaadin:qrcode" />
      </div>
      <div class="mt-5 space-y-5" style="display: none">
        <!-- Radio Buttons -->
        <div class="flex gap-6">
          <label
            class="flex items-center gap-2 cursor-pointer text-gray-700 hover:text-blue-500"
          >
            <input
              type="radio"
              id="personalData"
              v-model="dataType"
              value="personalData"
              class="accent-blue-500"
            />
            <span>Personal Data</span>
          </label>

          <label
            class="flex items-center gap-2 cursor-pointer text-gray-700 hover:text-blue-500"
          >
            <input
              type="radio"
              id="otherData"
              v-model="dataType"
              value="otherData"
              class="accent-blue-500"
            />
            <span>Other Data</span>
          </label>
        </div>

        <!-- Input Fields -->
        <div v-if="dataType === 'personalData'">
          <label class="block text-sm font-semibold text-gray-600">
            Data to be associated
          </label>
          <select
            v-model="selectedPersonalData"
            class="mt-2 block w-full outline-none rounded-md p-2 border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition"
          >
            <option value="" disabled selected>Choose Ticket ID</option>
            <option
              v-for="(ticket, index) in ticketOptions"
              :key="ticket"
              :value="ticket"
              :selected="index === 0"
            >
              {{ ticket }}
            </option>
          </select>
        </div>

        <div v-else>
          <label class="block text-sm font-semibold text-gray-600">
            Data to be associated
          </label>
          <textarea
            v-model="selectedOtherContent"
            rows="3"
            placeholder="Enter your data here..."
            class="mt-2 p-2 block w-full outline-none rounded-md border border-gray-300 shadow-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 transition"
          ></textarea>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex justify-end gap-3">
        <button
          @click="qrcodeStore.qrCodetoggleModal"
          class="px-5 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition"
        >
          Cancel
        </button>
        <button
          @click="createQRCode"
          class="px-5 py-2 rounded-lg bg-blue-500 text-white hover:bg-blue-600 transition shadow-md"
        >
          Create
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useCanvasStore } from "@badge/stores/useCanvasStore";
import { useQRCodeStore } from "@badge/stores/useQRCodeStore";
const qrcodeStore = useQRCodeStore();
const store = useCanvasStore();
const dataType = ref("personalData");
const selectedPersonalData = ref("");
const selectedOtherContent = ref("");
const ticketOptions = ref(["Attendees", "Speakers", "Exibitors", "Sponsors"]);

const createQRCode = () => {
  console.log("Creating QR Code with:", {
    dataType: dataType.value,
    ticketId: selectedPersonalData.value,
    content: selectedOtherContent.value,
  });

  let qrcode = "";
  if (dataType.value == "personalData") {
    qrcode = selectedPersonalData.value;
  } else {
    qrcode = selectedOtherContent.value;
  }
  console.log("qrcode", qrcode);

  // store.handleImageUploaded("shereali");
  store.handleQRCodeGenerator(qrcode);

  qrcodeStore.showModal = false;
};
</script>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes slideUp {
  from {
    transform: translateY(30px) scale(0.95);
    opacity: 0;
  }
  to {
    transform: translateY(0) scale(1);
    opacity: 1;
  }
}

.animate-fadeIn {
  animation: fadeIn 0.3s ease-out;
}

.animate-slideUp {
  animation: slideUp 0.3s ease-out;
}
</style>
