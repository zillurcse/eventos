<template>
  <div
    role="dialog"
    aria-modal="true"
    aria-label="Image Manager"
    class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4"
    @click.self="$emit('close')"
  >
    <div
      class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden animate-modalEnter"
    >
      <!-- Header -->
      <div class="px-8 py-5 flex justify-between items-center border-b border-gray-100 bg-gray-50/50">
        <div>
          <h3 class="text-xl font-bold text-gray-900">Image Asset Manager</h3>
          <p class="text-xs text-gray-500 mt-0.5">Upload and manage your floor plan assets</p>
        </div>
        <button
          class="p-2 hover:bg-red-50 text-gray-400 hover:text-red-500 rounded-full transition-all duration-200"
          @click="$emit('close')"
        >
          <NuxtIcon name="heroicons:x-mark" class="w-6 h-6" />
        </button>
      </div>

      <!-- Content Area -->
      <div class="flex-1 overflow-y-auto p-8">
        <!-- Tabs -->
        <div class="flex p-1 bg-gray-100 rounded-xl mb-8 w-max mx-auto border border-gray-200 shadow-sm">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            class="px-6 py-2.5 rounded-lg text-sm font-bold transition-all duration-300 flex items-center gap-2"
            :class="activeTab === tab.id 
              ? 'bg-white text-blue-600 shadow-sm' 
              : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
            @click="activeTab = tab.id"
          >
            <NuxtIcon :name="tab.id === 'upload' ? 'heroicons:arrow-up-tray' : 'heroicons:rectangle-group'" class="w-4 h-4" />
            {{ tab.label }}
          </button>
        </div>

        <!-- Progress Overlay (When uploading) -->
        <Transition name="fade">
          <div v-if="isUploading" class="mb-8 p-6 bg-blue-50/50 rounded-2xl border border-blue-100 shadow-inner">
            <div class="flex justify-between items-end mb-3">
              <div class="flex flex-col gap-1">
                <span class="text-sm font-bold text-blue-900">Processing Assets...</span>
                <span class="text-xs text-blue-600/80">Optimizing {{ totalFiles }} {{ totalFiles > 1 ? 'images' : 'image' }}</span>
              </div>
              <span class="text-sm font-mono font-bold text-blue-700">{{ uploadProgress }}%</span>
            </div>
            <div class="w-full bg-blue-200/30 rounded-full h-2.5 overflow-hidden">
              <div
                class="bg-blue-600 h-full rounded-full transition-all duration-500 ease-out shadow-[0_0_12px_rgba(37,99,235,0.4)]"
                :style="{ width: uploadProgress + '%' }"
              ></div>
            </div>
            <div class="mt-4 flex items-center justify-center gap-2 text-xs text-blue-500 font-medium">
              <NuxtIcon name="svg-spinners:ring-resize" class="w-4 h-4" />
              Processing file {{ currentFileIndex + 1 }} of {{ totalFiles }}
            </div>
          </div>
        </Transition>

        <!-- Error State -->
        <Transition name="fade">
          <div v-if="errorMessage" class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl flex items-center gap-3 text-red-700 text-sm font-medium">
            <NuxtIcon name="heroicons:exclamation-triangle" class="w-5 h-5 shrink-0" />
            {{ errorMessage }}
          </div>
        </Transition>

        <!-- Upload Tab -->
        <Transition name="slide-up" mode="out-in">
          <div v-if="activeTab === 'upload'" key="upload">
            <div
              class="relative group"
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
              @drop.prevent="handleDrop"
            >
              <div
                class="flex flex-col items-center justify-center py-16 px-10 border-2 border-dashed rounded-[2rem] transition-all duration-300 cursor-pointer overflow-hidden"
                :class="isDragging 
                  ? 'border-blue-500 bg-blue-50/50 scale-[0.98]' 
                  : 'border-gray-200 bg-gray-50 hover:bg-white hover:border-blue-400 hover:shadow-xl hover:shadow-blue-500/5'"
                @click="triggerInput"
              >
                <!-- Decorative background shapes -->
                <div class="absolute -top-12 -right-12 w-32 h-32 bg-blue-100/50 rounded-full blur-3xl transition-opacity group-hover:opacity-100 opacity-0"></div>
                <div class="absolute -bottom-12 -left-12 w-32 h-32 bg-indigo-100/50 rounded-full blur-3xl transition-opacity group-hover:opacity-100 opacity-0"></div>

                <div class="relative flex flex-col items-center">
                  <div class="w-20 h-20 bg-white rounded-2xl shadow-lg flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                    <NuxtIcon name="heroicons:cloud-arrow-up" class="w-10 h-10 text-blue-500" />
                  </div>
                  <h4 class="text-lg font-bold text-gray-900 mb-2">Drop your images here</h4>
                  <p class="text-sm text-gray-500 text-center max-w-[240px] leading-relaxed">
                    or <span class="text-blue-600 font-semibold underline underline-offset-4">browse your files</span> to upload from your computer
                  </p>
                  
                  <div class="mt-8 flex gap-3">
                    <span class="px-3 py-1 bg-white border border-gray-100 rounded-full text-[10px] font-bold text-gray-400 uppercase tracking-widest shadow-sm">PNG</span>
                    <span class="px-3 py-1 bg-white border border-gray-100 rounded-full text-[10px] font-bold text-gray-400 uppercase tracking-widest shadow-sm">JPG</span>
                    <span class="px-3 py-1 bg-white border border-gray-100 rounded-full text-[10px] font-bold text-gray-400 uppercase tracking-widest shadow-sm">WEBP</span>
                  </div>
                </div>

                <input
                  ref="fileInput"
                  type="file"
                  accept="image/*"
                  multiple
                  class="hidden"
                  @change="handleFileUpload"
                />
              </div>

              <!-- Dragging Overlay Inner Visual -->
              <div 
                v-if="isDragging"
                class="absolute inset-0 pointer-events-none border-4 border-blue-500 rounded-[2rem] flex items-center justify-center bg-blue-600/10 backdrop-blur-[2px] animate-pulse"
              >
                <div class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-bold shadow-xl">
                  Release to Upload
                </div>
              </div>
            </div>
          </div>

          <!-- Library Tab -->
          <div v-else-if="activeTab === 'library'" key="library">
            <div class="flex flex-col gap-6">
              <!-- Search & Filter Area -->
              <div class="relative group">
                <NuxtIcon name="heroicons:magnifying-glass" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 w-5 h-5 transition-colors" />
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Search assets by name..."
                  class="w-full pl-12 pr-4 py-4 bg-gray-50 border-2 border-transparent rounded-2xl text-sm focus:border-blue-500 focus:bg-white transition-all font-medium text-gray-900"
                />
              </div>

              <!-- Grid -->
              <div v-if="filteredImages.length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-6">
                <div
                  v-for="(img, idx) in filteredImages"
                  :key="idx"
                  class="group relative flex flex-col gap-3 cursor-pointer"
                  @click="selectImage(img)"
                >
                  <div 
                    class="relative aspect-[4/3] rounded-2xl overflow-hidden border-[3px] transition-all duration-300"
                    :class="selectedImage?.url === img.url 
                      ? 'border-blue-600 shadow-lg shadow-blue-500/20 scale-[0.98]' 
                      : 'border-transparent bg-gray-100 hover:border-blue-200'"
                  >
                    <img
                      :src="img.url"
                      :alt="img.title"
                      class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                    />
                    
                    <!-- Hover Actions Overlay -->
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                       <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-lg transform translate-y-2 group-hover:translate-y-0 transition-transform">
                          <NuxtIcon name="heroicons:check-badge" class="w-6 h-6 text-blue-600" />
                       </div>
                    </div>

                    <!-- Selected Indicator (Always visible if selected) -->
                    <div
                      v-if="selectedImage?.url === img.url"
                      class="absolute top-3 right-3 w-8 h-8 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg animate-bounce-short"
                    >
                      <NuxtIcon name="heroicons:check" class="w-5 h-5 text-white stroke-[3px]" />
                    </div>
                  </div>
                  <div class="flex flex-col px-1">
                    <span class="text-[11px] font-bold text-gray-900 truncate tracking-tight">{{ img.title }}</span>
                    <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">{{ img.size || 'Optimal Quality' }}</span>
                  </div>
                </div>
              </div>

              <!-- Empty State -->
              <div v-else class="py-20 flex flex-col items-center justify-center grayscale opacity-60">
                <div class="w-32 h-32 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                  <NuxtIcon name="ph:images-square-light" class="w-16 h-16 text-gray-400" />
                </div>
                <h4 class="text-xl font-bold text-gray-900">No assets found</h4>
                <p class="text-sm text-gray-500 mt-1">Try adjusting your search or upload new images</p>
                <button 
                  class="mt-8 px-6 py-2.5 bg-gray-900 text-white rounded-xl text-xs font-bold hover:bg-gray-800 transition-colors"
                  @click="activeTab = 'upload'"
                >
                  Upload your first image
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Footer Actions -->
      <div class="px-8 py-6 border-t border-gray-100 bg-gray-50/80 flex justify-between items-center">
        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">
          <span v-if="activeTab === 'library' && filteredImages.length > 0">
            {{ filteredImages.length }} Asset{{ filteredImages.length > 1 ? 's' : '' }} Available
          </span>
        </div>
        <div class="flex gap-4">
          <button
            class="px-6 py-3 text-sm font-bold text-gray-500 hover:text-gray-900 transition-colors"
            @click="$emit('close')"
          >
            Cancel
          </button>
          <button
            v-if="activeTab === 'library' && selectedImage"
            class="px-8 py-3 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white rounded-2xl text-sm font-bold shadow-xl shadow-blue-500/25 transition-all flex items-center gap-2"
            @click="chooseImage"
          >
            Insert Selection
            <NuxtIcon name="heroicons:plus-circle" class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import imageCompression from "browser-image-compression";

const emit = defineEmits(["uploaded", "close"]);

const tabs = [
  { id: 'upload', label: 'Upload', icon: 'heroicons:arrow-up-tray' },
  { id: 'library', label: 'Library', icon: 'heroicons:rectangle-group' }
];

const fileInput = ref(null);
const uploadProgress = ref(0);
const currentFileIndex = ref(0);
const totalFiles = ref(0);
const isUploading = ref(false);
const isDragging = ref(false);
const errorMessage = ref("");
const activeTab = ref("upload");
const uploadedImages = ref([]); // This persistence could be wired to a Pinia store if needed
const searchQuery = ref("");
const selectedImage = ref(null);

onMounted(() => {
  // If we wanted to persist images between sessions:
  // const saved = localStorage.getItem('app_asset_library');
  // if (saved) uploadedImages.value = JSON.parse(saved);
});

const filteredImages = computed(() => {
  if (!searchQuery.value) return uploadedImages.value;
  const q = searchQuery.value.toLowerCase();
  return uploadedImages.value.filter((img) =>
    img.title.toLowerCase().includes(q)
  );
});

function triggerInput() {
  fileInput.value?.click();
}

function handleDrop(e) {
  isDragging.value = false;
  errorMessage.value = "";
  const files = Array.from(e.dataTransfer.files);
  const imageFiles = files.filter(f => f.type.startsWith('image/'));
  
  if (imageFiles.length === 0) {
    errorMessage.value = "Please drop valid image files (PNG, JPG, WEBP).";
    return;
  }
  
  processFiles(imageFiles);
}

function handleFileUpload(event) {
  errorMessage.value = "";
  const files = Array.from(event.target.files);
  if (files.length > 0) {
    processFiles(files);
  }
}

async function processFiles(files) {
  totalFiles.value = files.length;
  currentFileIndex.value = 0;
  uploadProgress.value = 0;
  isUploading.value = true;
  
  for (const [index, file] of files.entries()) {
    currentFileIndex.value = index;
    const options = {
      maxSizeMB: 0.8,
      maxWidthOrHeight: 1200,
      useWebWorker: true,
    };

    try {
      // Small artificial delay for visual smoothness of the progress bar
      await new Promise(r => setTimeout(r, 150));
      
      const compressedFile = await imageCompression(file, options);
      const base64 = await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(compressedFile);
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
      });

      const newImage = {
        url: base64,
        title: file.name,
        size: (compressedFile.size / (1024 * 1024)).toFixed(2) + ' MB'
      };

      uploadedImages.value.unshift(newImage); 
      uploadProgress.value = Math.round(((index + 1) / files.length) * 100);
      
      // Save local for session persistence if needed:
      // localStorage.setItem('app_asset_library', JSON.stringify(uploadedImages.value));
      
    } catch (error) {
      console.error("Compression details:", error);
      errorMessage.value = `Failed to process "${file.name}". It might be corrupted or too large.`;
    }
  }

  // Final cleanup and transition
  setTimeout(() => {
    isUploading.value = false;
    activeTab.value = "library";
    if (files.length === 1 && !errorMessage.value) {
      selectedImage.value = uploadedImages.value[0];
    }
    if (fileInput.value) fileInput.value.value = '';
  }, 450);
}

function selectImage(image) {
  selectedImage.value = image;
}

function chooseImage() {
  if (selectedImage.value) {
    emit("uploaded", {
      url: selectedImage.value.url,
      title: selectedImage.value.title,
    });
    emit("close");
  }
}
</script>

<style scoped>
@keyframes modalEnter {
  0% { transform: scale(0.95); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

@keyframes bounceShort {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-3px); }
}

.animate-modalEnter {
  animation: modalEnter 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-bounce-short {
  animation: bounceShort 2s ease-in-out infinite;
}

/* Transitions */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

.slide-up-enter-active, .slide-up-leave-active {
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-up-enter-from {
  opacity: 0;
  transform: translateY(20px);
}
.slide-up-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}

/* Custom Scrollbar for modern look */
.flex-1::-webkit-scrollbar {
  width: 6px;
}
.flex-1::-webkit-scrollbar-track {
  background: transparent;
}
.flex-1::-webkit-scrollbar-thumb {
  background: #E5E7EB;
  border-radius: 10px;
}
.flex-1::-webkit-scrollbar-thumb:hover {
  background: #D1D5DB;
}
</style>

