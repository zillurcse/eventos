<!-- components/BoothModal.vue -->
<template>
  <div
    class="fixed inset-0 bg-black/50 flex items-center justify-end z-50 p-0 md:justify-center"
    @click.self="$emit('close')"
  >
    <div
      class="bg-white h-full w-full max-w-md flex flex-col md:h-auto md:max-h-[90vh] md:rounded-lg md:shadow-xl"
    >
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b shrink-0">
        <h2 class="text-xl font-semibold text-gray-800">
          {{ isEditing ? "Edit Booth" : "Add Booth" }}
        </h2>
        <button
          @click="$emit('close')"
          class="text-gray-400 hover:text-gray-600 transition-colors"
          aria-label="Close modal"
        >
          <NuxtIcon name="heroicons:x-mark" class="w-6 h-6" />
        </button>
      </div>

      <!-- Content - Scrollable -->
      <div class="flex-1 overflow-y-auto p-6 space-y-6 scrollbar-custom">
        <!-- Booth Type Selection - Radio Buttons Inline -->
        <div>
          <h3 class="text-lg font-medium text-gray-700 mb-3">Booth Type</h3>
          <div class="grid grid-cols-2 gap-3">
            <label
              v-for="type in boothTypes"
              :key="type.id"
              class="flex items-center space-x-3 p-3 border-2 rounded-lg cursor-pointer transition-all"
              :class="
                selectedBoothType === type.id
                  ? 'border-blue-500 bg-blue-50'
                  : 'border-gray-200 hover:border-gray-300'
              "
            >
              <input
                type="radio"
                :value="type.id"
                v-model="selectedBoothType"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
              />
              <span class="text-sm font-medium text-gray-700">{{
                type.label
              }}</span>
            </label>
          </div>
        </div>

        <!-- Single Booth Form -->
        <div v-if="selectedBoothType === 'single'" class="space-y-4">
          <!-- Booth Number -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              <span class="font-bold">Booth Number</span>
              <span class="text-red-500 ml-1">*</span>
            </label>
            <input
              v-model="formData.boothNumber"
              type="text"
              placeholder="Booth001"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-500': boothNumberError }"
              @blur="validateBoothNumber"
            />
            <p v-if="boothNumberError" class="text-red-500 text-xs mt-1">
              {{ boothNumberError }}
            </p>
          </div>
        </div>

        <!-- Multiple Booths Form -->
        <div v-else class="space-y-4">
          <!-- Booth Number and Quantity Inline -->
          <div class="grid grid-cols-2 gap-4">
            <!-- Booth Number -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                <span class="font-bold">Booth Number</span>
                <span class="text-red-500 ml-1">*</span>
              </label>
              <input
                v-model="formData.boothNumber"
                type="text"
                placeholder="Tech101"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-500': boothNumberError }"
                @blur="validateBoothNumber"
              />
              <p v-if="boothNumberError" class="text-red-500 text-xs mt-1">
                {{ boothNumberError }}
              </p>
            </div>

            <!-- Quantity -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                <span class="font-bold">Quantity</span>
                <span class="text-red-500 ml-1">*</span>
              </label>
              <input
                v-model="formData.quantity"
                type="number"
                min="1"
                max="100"
                placeholder="1"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>
        </div>

        <!-- Divider -->
        <div class="border-t pt-6">
          <h3 class="text-lg font-medium text-gray-700 mb-4">
            Booth Dimensions
          </h3>

          <!-- Length and Breadth Inline -->
          <div class="grid grid-cols-2 gap-4">
            <!-- Length -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                <span class="font-bold">Length ({{ currentUnitLabel }})</span>
                <span class="text-red-500 ml-1">*</span>
              </label>
              <input
                v-model="displayLength"
                type="number"
                :placeholder="getPlaceholder(10)"
                min="0.1"
                step="0.1"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @input="updateLengthFromDisplay"
              />
            </div>

            <!-- Breadth -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                <span class="font-bold">Breadth ({{ currentUnitLabel }})</span>
                <span class="text-red-500 ml-1">*</span>
              </label>
              <input
                v-model="displayBreadth"
                type="number"
                :placeholder="getPlaceholder(10)"
                min="0.1"
                step="0.1"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @input="updateBreadthFromDisplay"
              />
            </div>
          </div>
        </div>

        <!-- Booth Name Section -->
        <div class="border-t pt-6">
          <h3 class="text-lg font-medium text-gray-700 mb-4">
            Booth Information
          </h3>

          <!-- Booth Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              <span class="font-bold">Booth Name</span>
              <span class="text-gray-400 ml-1"
                >(Optional - always displayed)</span
              >
            </label>
            <input
              v-model="formData.booth_name"
              type="text"
              placeholder="e.g., TechCorp Inc."
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div
        class="flex justify-between items-center p-6 border-t bg-gray-50 shrink-0 md:rounded-b-lg"
      >
        <button
          @click="$emit('close')"
          class="px-6 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors font-medium"
        >
          Close
        </button>
        <div class="flex items-center space-x-3">
          <button
            @click="saveBooth"
            :disabled="!canSave"
            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors font-medium"
          >
            {{ isEditing ? "Update" : "Save" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useUiStore } from "@floorplan/stores/uiStore";

const canvasStore = useCanvasStore();
const uiStore = useUiStore();

interface BoothModalProps {
  show: boolean;
  editingBooth?: any;
}

interface BoothModalEmits {
  (e: "close"): void;
  (e: "save", data: BoothData): void;
}

interface BoothData {
  boothNumber: string;
  length: number;
  breadth: number;
  type: string;
  quantity?: number;
  booth_name?: string;
}

const props = defineProps<BoothModalProps>();
const emit = defineEmits<BoothModalEmits>();

// Computed
const isEditing = computed(() => !!props.editingBooth);

// Booth types
const boothTypes = [
  { id: "single", label: "Single Booth", icon: "heroicons:cube" },
  { id: "multiple", label: "Multiple Booths", icon: "heroicons:cubes" },
];

// Form data (always stored in cm internally)
const formData = ref<BoothData>({
  boothNumber: "",
  length: 300, // cm
  breadth: 300, // cm
  type: "single",
  quantity: 1,
  booth_name: "",
});

const selectedBoothType = ref("single");
const boothNumberError = ref("");

// ✅ NEW: Display values in current unit
const displayLength = ref("300");
const displayBreadth = ref("300");

// ✅ NEW: Get current unit label
const currentUnitLabel = computed(() => {
  const converted = uiStore.convertToCurrentUnit(100); // Convert 100cm to get unit
  return converted.unit;
});

// ✅ NEW: Convert cm to display unit
const convertToDisplayUnit = (valueInCm: number): string => {
  const converted = uiStore.convertToCurrentUnit(valueInCm);
  return converted.value.toFixed(2);
};

// ✅ NEW: Convert display unit to cm
const convertToCm = (displayValue: number): number => {
  switch (uiStore.measurementUnit) {
    case "feet":
      return displayValue * 30.48;
    case "inches":
      return displayValue * 2.54;
    case "meter":
      return displayValue * 100;
    case "centimeter":
    default:
      return displayValue;
  }
};

// ✅ NEW: Update internal cm value from display input
const updateLengthFromDisplay = () => {
  const displayVal = parseFloat(displayLength.value);
  if (!isNaN(displayVal) && displayVal > 0) {
    formData.value.length = convertToCm(displayVal);
  }
};

const updateBreadthFromDisplay = () => {
  const displayVal = parseFloat(displayBreadth.value);
  if (!isNaN(displayVal) && displayVal > 0) {
    formData.value.breadth = convertToCm(displayVal);
  }
};

// ✅ NEW: Watch for measurement unit changes and update display values
watch(
  () => uiStore.measurementUnit,
  () => {
    displayLength.value = convertToDisplayUnit(formData.value.length);
    displayBreadth.value = convertToDisplayUnit(formData.value.breadth);
  }
);

// ✅ NEW: Helper to get placeholder in current unit
const getPlaceholder = (cmValue: number): string => {
  return convertToDisplayUnit(cmValue);
};

// Form validation
const isFormValid = computed(() => {
  const baseValidation =
    formData.value.boothNumber.trim() !== "" &&
    formData.value.length > 0 &&
    formData.value.breadth > 0;

  if (selectedBoothType.value === "multiple") {
    return baseValidation && (formData.value.quantity || 0) > 0;
  }

  return baseValidation;
});

// Check if we can save
const canSave = computed(() => {
  return isFormValid.value && !boothNumberError.value;
});

// Validate booth number uniqueness
const validateBoothNumber = () => {
  const boothNumber = formData.value.boothNumber.trim();

  if (!boothNumber) {
    boothNumberError.value = "Booth number is required";
    return;
  }

  // Check if booth number already exists
  const existingBooths = canvasStore.objects.filter(
    (obj) =>
      obj.type === "booth" &&
      obj.boothNumber?.toLowerCase() === boothNumber.toLowerCase()
  );

  // If editing, exclude the current booth from the check
  const isDuplicate = isEditing.value
    ? existingBooths.some((booth) => booth.id !== props.editingBooth?.id)
    : existingBooths.length > 0;

  boothNumberError.value = isDuplicate ? "Booth number already exists" : "";
};

// Watch for booth number changes and validate
let validationTimeout: NodeJS.Timeout | null = null;

watch(
  () => formData.value.boothNumber,
  (newValue) => {
    // Clear any existing timeout
    if (validationTimeout) {
      clearTimeout(validationTimeout);
    }

    // Clear error immediately when user starts typing
    if (boothNumberError.value && newValue.trim()) {
      boothNumberError.value = "";
    }

    // Validate after user stops typing for 500ms
    validationTimeout = setTimeout(() => {
      if (formData.value.boothNumber.trim()) {
        validateBoothNumber();
      } else {
        boothNumberError.value = "Booth number is required";
      }
    }, 500);
  }
);

// Watch for booth type changes
watch(selectedBoothType, (newType) => {
  formData.value.type = newType;
  if (newType === "single") {
    // Reset quantity for single booth
    formData.value.quantity = undefined;
  } else {
    // Set default quantity for multiple booths
    formData.value.quantity = 1;
  }
});

// Load editing data when modal opens
watch(
  () => props.editingBooth,
  (newBooth) => {
    if (newBooth) {
      formData.value.boothNumber = newBooth.boothNumber || "";
      formData.value.length = newBooth.length || 300;
      formData.value.breadth = newBooth.breadth || 300;
      formData.value.booth_name = newBooth.booth_name || "";
      selectedBoothType.value = "single";

      // ✅ Update display values
      displayLength.value = convertToDisplayUnit(formData.value.length);
      displayBreadth.value = convertToDisplayUnit(formData.value.breadth);

      // Clear validation error when editing
      boothNumberError.value = "";

      // Validate the loaded booth number after a short delay
      setTimeout(() => {
        if (formData.value.boothNumber.trim()) {
          validateBoothNumber();
        }
      }, 100);
    }
  },
  { immediate: true }
);

// Save booth data
const saveBooth = () => {
  // Final validation before saving
  validateBoothNumber();

  // If there's still an error after final validation, don't save
  if (boothNumberError.value) {
    return;
  }

  const boothData: BoothData = {
    boothNumber: formData.value.boothNumber.trim(),
    length: formData.value.length, // Save in cm
    breadth: formData.value.breadth, // Save in cm
    type: selectedBoothType.value,
    booth_name: formData.value.booth_name?.trim() || "",
  };

  // Add quantity only for multiple booths
  if (selectedBoothType.value === "multiple" && formData.value.quantity) {
    boothData.quantity = formData.value.quantity;
  }

  emit("save", boothData);

  // Reset form
  resetForm();
};

// Reset form to initial state
const resetForm = () => {
  formData.value = {
    boothNumber: "",
    length: 300,
    breadth: 300,
    type: "single",
    quantity: 1,
    booth_name: "",
  };
  displayLength.value = convertToDisplayUnit(300);
  displayBreadth.value = convertToDisplayUnit(300);
  selectedBoothType.value = "single";
  boothNumberError.value = "";
};

// Close modal on Escape key
const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === "Escape") {
    emit("close");
  }
};

onMounted(() => {
  document.addEventListener("keydown", handleKeydown);
  // Initialize display values
  displayLength.value = convertToDisplayUnit(formData.value.length);
  displayBreadth.value = convertToDisplayUnit(formData.value.breadth);
});

onUnmounted(() => {
  document.removeEventListener("keydown", handleKeydown);
  if (validationTimeout) {
    clearTimeout(validationTimeout);
  }
});
</script>
