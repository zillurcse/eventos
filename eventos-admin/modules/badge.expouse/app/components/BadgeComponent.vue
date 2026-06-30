<template>
  <div
    class="bg-white rounded-md border border-gray-300 p-4"
    :style="{
      width: getWidth() + 'px',
      height: getHeight() + 'px',
    }"
  >
    <div class="flex flex-col justify-between h-full">
      <!-- Top Icon -->
      <div class="flex justify-center">
        <NuxtIcon name="mdi:city" class="text-gray-400 text-2xl" />
      </div>

      <!-- Middle Lines -->
      <div class="flex flex-col items-center space-y-2">
        <div class="w-28 h-2 bg-gray-400 rounded"></div>
        <div class="w-20 h-1.5 bg-gray-400 rounded"></div>
        <div class="w-32 h-2 bg-gray-400 rounded"></div>
        <div class="w-16 h-1.5 bg-gray-400 rounded"></div>
      </div>

      <!-- Bottom Row -->
      <div
        class="flex justify-center items-end mt-auto"
        v-if="orientation === 'portrait'"
      >
        <div
          class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center"
        >
          <NuxtIcon name="mdi:qrcode" class="text-gray-500 text-xl" />
        </div>
      </div>
      <div
        class="flex justify-end items-end mt-auto"
        v-if="orientation === 'landscape'"
      >
        <div
          class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center"
        >
          <NuxtIcon name="mdi:qrcode" class="text-gray-500 text-xl" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  badgeSizePreset: String,
  badgeSize: String,
  badgeOrientation: String,
  customWidth: Number,
  customHeight: Number,
});

const orientation = ref(props.badgeOrientation);

const getWidth = () => {
  if (props.badgeSizePreset === "custom") {
    return props.badgeOrientation === "landscape"
      ? props.customHeight * 1.5
      : props.customWidth * 1.5;
  }
  const sizeMap = {
    A4: [210, 297],
    A6: [105, 148],
    A7: [74, 105],
  };
  const [w, h] = sizeMap[props.badgeSize] || sizeMap.A4;
  return props.badgeOrientation === "landscape" ? h * 1.5 : w * 1.5;
};

const getHeight = () => {
  if (props.badgeSizePreset === "custom") {
    return props.badgeOrientation === "landscape"
      ? props.customWidth * 1.5
      : props.customHeight * 1.5;
  }
  const sizeMap = {
    A4: [210, 297],
    A6: [105, 148],
    A7: [74, 105],
  };
  const [w, h] = sizeMap[props.badgeSize] || sizeMap.A4;
  return props.badgeOrientation === "landscape" ? w * 1.5 : h * 1.5;
};

watch(
  () => props.badgeOrientation,
  (val) => (orientation.value = val)
);
</script>

<style scoped></style>
