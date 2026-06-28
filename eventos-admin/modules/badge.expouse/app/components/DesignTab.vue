<template>
  <div class="space-y-2">
    <div
      v-for="group in designGroups"
      :key="group.type"
      class="bg-white rounded"
    >
      <div
        @click="toggleGroup(group.type)"
        class="flex items-center p-2 cursor-pointer hover:bg-gray-50"
      >
        <iconify-icon
          :icon="group.icon"
          class="w-5 h-5 mr-2"
          aria-hidden="true"
        ></iconify-icon>
        <span>{{ group.label }}</span>
        <iconify-icon
          icon="mdi:chevron-down"
          class="ml-auto w-5 h-5"
          :class="{ 'transform rotate-180': openGroups[group.type] }"
          aria-hidden="true"
        ></iconify-icon>
      </div>
      <div v-if="openGroups[group.type]" class="pl-6 space-y-2">
        <div
          v-for="item in group.items"
          :key="item.type"
          draggable="true"
          @dragstart="$emit('drag-start', item.type)"
          class="flex items-center p-2 bg-white rounded cursor-move hover:bg-gray-50"
        >
          <iconify-icon
            :icon="item.icon"
            class="w-5 h-5 mr-2"
            aria-hidden="true"
          ></iconify-icon>
          <span>{{ item.label }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, defineEmits } from "vue";

const openGroups = ref({
  user_info: false,
  event_info: false,
  qr_code: false,
  background: false,
  static_field: false,
  punching_area: false,
});

const designGroups = [
  {
    type: "user_info",
    label: "User Info",
    icon: "mdi:account",
    items: [
      { type: "text", label: "TICKETID", icon: "mdi:ticket" },
      { type: "eventlogo", label: "EVENT LOGO", icon: "mdi:image" },
      { type: "text", label: "EMAIL", icon: "mdi:email" },
      { type: "text", label: "LAST NAME", icon: "mdi:account" },
      { type: "text", label: "FIRST NAME", icon: "mdi:account" },
      { type: "qrcode", label: "QR CODE", icon: "mdi:qrcode" },
    ],
  },
  {
    type: "event_info",
    label: "Event Info",
    icon: "mdi:calendar",
    items: [
      { type: "text", label: "Event Name", icon: "mdi:text" },
      { type: "text", label: "Venue", icon: "mdi:map-marker" },
      { type: "text", label: "Date", icon: "mdi:calendar" },
      { type: "text", label: "ZIP Code", icon: "mdi:map" },
      { type: "text", label: "City", icon: "mdi:city" },
      { type: "eventlogo", label: "Event Logo", icon: "mdi:image" },
    ],
  },
  {
    type: "qr_code",
    label: "QR Code",
    icon: "mdi:qrcode",
    items: [{ type: "qrcode", label: "QR Code", icon: "mdi:qrcode" }],
  },
  {
    type: "background",
    label: "Background",
    icon: "mdi:image",
    items: [
      { type: "background", label: "Image", icon: "mdi:image" },
      { type: "background", label: "Gradient", icon: "mdi:gradient" },
      { type: "background", label: "Color", icon: "mdi:palette" },
      { type: "background", label: "None", icon: "mdi:close" },
    ],
  },
  {
    type: "static_field",
    label: "Static Fields",
    icon: "mdi:shape",
    items: [
      { type: "text", label: "Text", icon: "mdi:text" },
      { type: "image", label: "Image", icon: "mdi:image" },
      { type: "line", label: "Rectangle", icon: "mdi:rectangle" },
    ],
  },
  {
    type: "punching_area",
    label: "Punching Area Reference",
    icon: "mdi:gesture-tap",
    items: [
      { type: "punching_area", label: "No Punching", icon: "mdi:close" },
      { type: "punching_area", label: "Top", icon: "mdi:arrow-up" },
      { type: "punching_area", label: "Bottom", icon: "mdi:arrow-down" },
    ],
  },
];

const toggleGroup = (groupType) => {
  openGroups.value[groupType] = !openGroups.value[groupType];
};

defineEmits(["drag-start"]);
</script>
