```vue
<template>
  <div class="toolbar bg-gray-200 p-4 space-x-2">
    <button
      @click="$emit('add', 'text')"
      class="bg-blue-500 text-white px-3 py-1 rounded"
    >
      Add Text
    </button>
    <button
      @click="$emit('add', 'qr')"
      class="bg-blue-500 text-white px-3 py-1 rounded"
    >
      Add QR
    </button>
    <button
      @click="handleImageUpload"
      class="bg-blue-500 text-white px-3 py-1 rounded"
    >
      Add Image
    </button>
    <input
      type="file"
      ref="fileInput"
      @change="uploadImage"
      class="hidden"
      accept="image/*"
    />
  </div>
</template>

<script setup>
import { ref } from "vue";

const emit = defineEmits(["add"]);
const fileInput = ref(null);

const handleImageUpload = () => {
  fileInput.value.click();
};

const uploadImage = (event) => {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      emit("add", "image", e.target.result);
    };
    reader.readAsDataURL(file);
  }
};
</script>

<style scoped>
.toolbar {
  display: flex;
  justify-content: center;
}
</style>
```
