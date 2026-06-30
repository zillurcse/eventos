<!-- components/BoothItem.vue -->
<template>
  <div
    class="booth-item group border border-gray-200 rounded-lg bg-white shadow-sm hover:shadow-md transition-all duration-200 p-3 mb-2"
    :style="{
      backgroundColor: statusColor,
      borderColor: statusBorderColor,
    }"
    :class="{
      'ring-2 ring-blue-500 ring-offset-2 border-blue-500': isSelected,
      'border-gray-200': !isSelected,
    }"
    ref="boothItemRef"
  >
    <!-- Compact Header with Booth Info and Actions -->
    <div class="flex items-center justify-between mb-2">
      <div class="flex items-center gap-2">
        <!-- <div
          class="w-3 h-3 rounded-full border border-gray-300"
          :style="{ backgroundColor: statusColor }"
        ></div> -->
        <div>
          <h4 class="font-semibold text-gray-500 text-sm">
            {{ formattedLength }} × {{ formattedBreadth }}
          </h4>
          <p class="text-md font-medium text-gray-700">
            {{ booth.booth_name || "Unnamed Booth" }}
          </p>
        </div>
      </div>

      <div class="flex items-center gap-1">
        <!-- Status Badge -->
        <!-- <span
          class="px-2 py-0.5 text-xs font-medium rounded-full border capitalize"
          :class="statusBadgeClass"
        >
          {{ statusLabel }}
        </span> -->

        <!-- Action buttons -->
        <div
          class="flex gap-1 opacity-70 group-hover:opacity-100 transition-opacity"
        >
          <button
            @click.stop="$emit('edit', booth)"
            class="rounded hover:bg-blue-50 text-gray-600 hover:text-blue-600 transition"
            title="Edit Booth"
          >
            <NuxtIcon name="hugeicons:pencil-edit-02" size="20" />
          </button>
          <button
            @click.stop="$emit('delete', booth.id)"
            class="rounded hover:bg-red-50 text-gray-600 hover:text-red-600 transition"
            title="Delete Booth"
          >
            <NuxtIcon name="heroicons:trash" size="20" />
          </button>
        </div>
      </div>
    </div>

    <!-- Compact Status Buttons -->
    <div class="flex gap-1">
      <button
        v-for="status in availableStatuses"
        :key="status"
        @click.stop="updateStatus(status)"
        class="flex-1 py-1 text-lg font-medium border transition-all duration-150"
        :class="getStatusButtonClass(status)"
      >
        {{ getStatusShortLabel(status) }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick, onMounted } from "vue";
import { useBoothColors } from "@floorplan/composables/useBoothColors";
const { getStatusColor } = useBoothColors();

import {
  BOOTH_STATUS_COLORS,
  BOOTH_STATUS_LABELS,
  type BoothStatus,
} from "@floorplan/constants/boothConstants";

import { useUiStore } from "@floorplan/stores/uiStore";
const uiStore = useUiStore();

const props = defineProps<{
  booth: any;
  isSelected: boolean;
}>();

const emit = defineEmits(["edit", "delete", "update:status"]);

// 🆕 Ref for the booth item element
const boothItemRef = ref<HTMLElement>();

// 🆕 Auto-scroll when booth is selected
watch(
  () => props.isSelected,
  (newIsSelected) => {
    if (newIsSelected && boothItemRef.value) {
      nextTick(() => {
        // Scroll the booth item to the top of its scrollable container
        boothItemRef.value?.scrollIntoView({
          behavior: "smooth",
          block: "start",
          inline: "nearest",
        });
      });
    }
  }
);

const statusColor = computed(() => {
  return getStatusColor(props.booth.status);
});

const statusBorderColor = computed(() => {
  const color = getStatusColor(props.booth.status);
  return darkenColor(color, 0.3);
});

const statusLabel = computed(() => {
  return BOOTH_STATUS_LABELS[props.booth.status] || props.booth.status;
});

const statusBadgeClass = computed(() => {
  const base = "px-2 py-0.5 text-xs font-semibold border rounded-full";
  switch (props.booth.status) {
    case "AVAILABLE":
      return `${base} bg-green-50 text-green-700 border-green-200`;
    case "BOOKED":
      return `${base} bg-red-50 text-red-700 border-red-200`;
    case "ON_HOLD":
      return `${base} bg-blue-50 text-blue-700 border-blue-200`;
    default:
      return `${base} bg-gray-50 text-gray-700 border-gray-200`;
  }
});

const availableStatuses = computed<BoothStatus[]>(() => {
  return Object.keys(BOOTH_STATUS_LABELS) as BoothStatus[];
});

const formattedLength = computed(() => {
  return uiStore.formatMeasurement(props.booth.length);
});

const formattedBreadth = computed(() => {
  return uiStore.formatMeasurement(props.booth.breadth);
});

const darkenColor = (color: string, factor: number): string => {
  if (color.startsWith("#")) {
    const hex = color.replace("#", "");
    const r = Math.floor(parseInt(hex.substr(0, 2), 16) * (1 - factor));
    const g = Math.floor(parseInt(hex.substr(2, 2), 16) * (1 - factor));
    const b = Math.floor(parseInt(hex.substr(4, 2), 16) * (1 - factor));
    return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
  }
  return color;
};

const getStatusShortLabel = (status: BoothStatus): string => {
  const labels: Record<BoothStatus, string> = {
    AVAILABLE: "Available",
    BOOKED: "Booked",
    ON_HOLD: "Hold",
  };
  return labels[status] || status;
};

const updateStatus = (newStatus: BoothStatus) => {
  if (newStatus !== props.booth.status) {
    emit("update:status", { id: props.booth.id, status: newStatus });
  }
};

const getStatusButtonClass = (status: BoothStatus) => {
  const isActive = props.booth.status === status;
  const base = "flex-1 py-1 text-xs font-medium rounded border transition";

  if (isActive) {
    switch (status) {
      case "AVAILABLE":
        return `${base} bg-green-500 text-white border-green-500 hover:bg-green-600`;
      case "BOOKED":
        return `${base} bg-red-500 text-white border-red-500 hover:bg-red-600`;
      case "ON_HOLD":
        return `${base} bg-blue-500 text-white border-blue-500 hover:bg-blue-600`;
      default:
        return `${base} bg-gray-500 text-white border-gray-500 hover:bg-gray-600`;
    }
  }
  return `${base} bg-white text-gray-700 border-gray-300 hover:bg-gray-50`;
};
</script>

<style scoped>
.booth-item {
  min-height: auto;
  cursor: pointer;
  transition: all 0.2s ease;
}

.booth-item:hover {
  transform: translateY(-1px);
}
</style>
