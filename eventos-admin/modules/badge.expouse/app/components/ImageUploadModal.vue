<template>
  <div
    role="dialog"
    aria-modal="true"
    aria-label="Image Manager"
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    @click.self="$emit('close')"
  >
    <div
      class="bg-white rounded-2xl shadow-lg w-full max-w-2xl p-6 animate-fadeIn flex flex-col"
    >
      <div class="flex justify-between items-center border-b pb-3 mb-4">
        <h3 class="text-xl font-semibold text-gray-800">Image Manager</h3>
        <button
          class="text-gray-400 hover:text-red-500 transition"
          @click="$emit('close')"
        >
          ✕
        </button>
      </div>

      <!-- Tabs -->
      <div class="flex space-x-2 mb-4">
        <button
          class="px-4 py-2 rounded"
          :class="
            activeTab === 'upload'
              ? 'bg-blue-600 text-white'
              : 'bg-gray-200 text-gray-700'
          "
          @click="activeTab = 'upload'"
        >
          Upload Image
        </button>
        <button
          class="px-4 py-2 rounded"
          :class="
            activeTab === 'library'
              ? 'bg-blue-600 text-white'
              : 'bg-gray-200 text-gray-700'
          "
          @click="activeTab = 'library'"
        >
          Uploaded Images
        </button>
      </div>

      <!-- Upload Tab -->
      <div v-if="activeTab === 'upload'">
        <div
          class="flex flex-col items-center justify-center p-6 border-2 border-dashed border-blue-400 rounded-lg cursor-pointer hover:bg-blue-50 transition"
          @click="triggerInput"
        >
          <svg
            class="w-12 h-12 text-blue-500 mb-2"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M3 15a4 4 0 014-4h1V7a4 4 0 014-4h2a4 4 0 014 4v4h1a4 4 0 014 4v5H3v-5z"
            />
          </svg>
          <p class="text-sm text-gray-600">Click to select an image</p>
          <input
            ref="fileInput"
            type="file"
            accept="image(/*"
            multiple
            class="hidden"
            @change="handleFileUpload"
          />
        </div>

        <div v-if="uploadProgress > 0" class="mt-6">
          <div class="w-full bg-gray-200 rounded-full h-3">
            <div
              class="bg-blue-500 h-3 rounded-full transition-all duration-300 ease-in-out"
              :style="{ width: uploadProgress + '%' }"
            ></div>
          </div>
          <p class="text-sm text-gray-700 text-center mt-2">
            Uploading... {{ uploadProgress }}%
          </p>
        </div>
      </div>

      <!-- Uploaded Images Tab -->
      <div v-if="activeTab === 'library'">
        <!-- Search + Category -->
        <div class="flex justify-between items-center mb-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search image..."
            class="w-full px-3 py-2 border rounded"
          />
        </div>

        <!-- Preview List -->
        <div
          class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-64 overflow-y-auto"
        >
          <div
            v-for="(img, idx) in filteredImages"
            :key="idx"
            class="relative cursor-pointer border rounded hover:border-blue-500"
            :class="{ 'border-blue-500': img === selectedImage }"
            @click="selectImage(img)"
          >
            <!-- {{ img }} -->
            <img
              :src="img.url"
              :alt="img.title"
              class="w-full h-24 object-cover rounded"
            />
            <p class="text-xs text-center text-gray-500 truncate">
              {{ img.title }}
            </p>
            <div
              v-if="img === selectedImage"
              class="absolute top-1 right-1 w-5 h-5 bg-green-500 rounded-full flex items-center justify-center"
            >
              <svg
                class="w-3 h-3 text-white"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7"
                />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer with Buttons -->
      <div class="mt-6 flex justify-end space-x-2">
        <button
          v-if="activeTab === 'library' && selectedImage"
          class="px-4 py-2 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 transition"
          @click="chooseImage"
        >
          Choose
        </button>
        <button
          class="px-4 py-2 text-sm bg-red-500 text-white rounded hover:bg-red-600 transition"
          @click="$emit('close')"
        >
          Cancel
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";

const props = defineProps({
  side: {
    type: String,
    default: "front", // or "back"
  },
});

const emit = defineEmits(["uploaded", "close"]);

// Uploads go to EventOS object storage (MinIO) via the admin's useUpload(),
// which posts to /uploads with the signed-in user's bearer token — replacing the
// old base64 token gallery against admin.expouse.com. The session gallery starts
// empty and fills as the user uploads.
const { upload } = useUpload();

const fileInput = ref(null);
const uploadProgress = ref(0);
const activeTab = ref("upload");
const uploadedImages = ref([]);

// if (pending.value) {
//   console.log("Loading images...");
// } else if (error.value) {
//   console.error("Error fetching images:", error.value);
// } else {
//   console.log("Images loaded:", uploadedImages.value);
// }

const searchQuery = ref("");

const selectedImage = ref(null);

// console.log("Initial Uploaded Images:", uploadedImages.value);

const filteredImages = computed(() => {
  // console.log("Filtering images with query:", uploadedImages.value);

  return uploadedImages.value.filter((img) => {
    const matchesSearch = img.title
      .toLowerCase()
      .includes(searchQuery.value.toLowerCase());
    return matchesSearch;
  });
});

function triggerInput() {
  fileInput.value?.click();
}

async function handleFormSubmit(file) {
  // Push the file to MinIO and record the returned public URL.
  const data = await upload(file, { collection: "badge" });
  const image = { url: data.url, title: file.name };

  selectedImage.value = image;
  uploadedImages.value.push(image);
  activeTab.value = "library"; // Switch to library tab
  return image;
}

async function handleFileUpload(event) {
  const files = Array.from(event.target.files);
  if (!files.length) return;

  let processed = 0;
  uploadProgress.value = 0;

  for (const file of files) {
    try {
      await handleFormSubmit(file);
    } catch (err) {
      console.error("Image upload failed:", err);
    }
    processed++;
    uploadProgress.value = Math.round((processed / files.length) * 100);
  }

  setTimeout(() => (uploadProgress.value = 0), 600);
}

function selectImage(image) {
  // console.log("Selected Image:", image);

  selectedImage.value = image;
}

function chooseImage() {
  // console.log("Chosen Image:", selectedImage.value);

  if (selectedImage.value) {
    emit("uploaded", {
      side: props.side,
      url: selectedImage.value.url,
      title: selectedImage.value.title,
    });
    emit("close");
  }
}
</script>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
.animate-fadeIn {
  animation: fadeIn 0.3s ease-out;
}
</style>
