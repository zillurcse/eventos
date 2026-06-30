<template>
  <div class="min-h-screen flex items-center justify-center p-6 bg-gray-50">
    <div class="w-full max-w-2xl">
      <!-- Controls -->
      <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="p-4 bg-white rounded-2xl shadow">
          <label class="block text-sm font-medium text-gray-700 mb-2"
            >Size (px)</label
          >
          <input
            type="range"
            min="40"
            max="800"
            v-model.number="size"
            class="w-full"
          />
          <div class="mt-2 text-sm text-gray-500">{{ size }}px</div>
        </div>

        <div
          class="p-4 bg-white rounded-2xl shadow"
          v-if="
            shape === 'rounded' || shape === 'squircle' || shape === 'custom'
          "
        >
          <label class="block text-sm font-medium text-gray-700 mb-2"
            >Border Radius (px)</label
          >
          <input
            type="range"
            min="0"
            max="400"
            v-model.number="radius"
            class="w-full"
          />
          <div class="mt-2 text-sm text-gray-500">{{ radius }}px</div>
        </div>

        <div class="p-4 bg-white rounded-2xl shadow">
          <label class="block text-sm font-medium text-gray-700 mb-2"
            >Shape</label
          >
          <select
            v-model="shape"
            class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none"
          >
            <option value="circle">Circle</option>
            <option value="rounded">Rounded</option>
            <option value="squircle">Squircle</option>
            <option value="diamond">Diamond</option>
            <option value="hex">Hexagon</option>
            <option value="triangle">Triangle</option>
            <option value="blob">Blob</option>
            <option value="custom">Custom (clip-path)</option>
          </select>

          <div v-if="shape === 'custom'" class="mt-3">
            <label class="block text-xs font-medium text-gray-600 mb-1"
              >CSS clip-path</label
            >
            <input
              v-model="customClipPath"
              placeholder="e.g. polygon(50% 0, 100% 50%, 50% 100%, 0 50%)"
              class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none"
            />
            <div class="mt-2 text-xs text-gray-500">
              Enter a valid <code>clip-path</code> value.
            </div>
          </div>
        </div>

        <div
          class="p-4 bg-white rounded-2xl shadow grid grid-cols-2 gap-3 items-center"
        >
          <label class="text-sm font-medium text-gray-700">Border</label>
          <input type="checkbox" v-model="showBorder" class="h-4 w-4" />
          <label class="text-sm font-medium text-gray-700">Ring</label>
          <input type="checkbox" v-model="showRing" class="h-4 w-4" />
        </div>
      </div>

      <!-- Preview -->
      <div class="flex items-center justify-center">
        <div
          :class="[
            'overflow-hidden shadow-sm transition-transform hover:scale-[1.02] flex items-center justify-center bg-gray-100',
            showBorder ? 'border border-gray-300' : '',
            showRing ? 'ring-2 ring-offset-2 ring-gray-400' : '',
          ]"
          :style="containerStyle"
        >
          <img
            :src="src"
            :alt="alt"
            class="object-cover"
            :style="imageStyle"
            draggable="false"
          />
        </div>
      </div>

      <!-- Info -->
      <div class="mt-4 text-center text-sm text-gray-600">
        <div>
          Shape: <span class="font-medium">{{ shapeLabel }}</span>
        </div>
        <div
          v-if="shape === 'custom'"
          class="mt-1 text-xs text-gray-500 break-words"
        >
          {{ customClipPath || "No custom clip-path set" }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";

// Replace with your image or let it be passed as a prop (here we use a demo image)
const src = ref(
  "https://ui-avatars.com/api/?background=c8c9ca&color=6c757d&size=200"
);
const alt = ref("Demo image");

// Controls
const size = ref(240);
const radius = ref(32);
const shape = ref("circle"); // circle | rounded | squircle | diamond | hex | triangle | blob | custom
const customClipPath = ref("");
const showBorder = ref(false);
const showRing = ref(false);

// Computed styles for the container that defines the shape
const containerStyle = computed(() => {
  const base = {
    width: `${size.value}px`,
    height: `${size.value}px`,
  };

  switch (shape.value) {
    case "circle":
      return { ...base, borderRadius: "9999px" };
    case "rounded":
      return { ...base, borderRadius: `${radius.value}px` };
    case "squircle":
      // A soft squircle using percentage radii (works well visually)
      return {
        ...base,
        borderRadius: `${Math.min(radius.value, 100)}% / ${Math.min(
          radius.value + 10,
          100
        )}%`,
      };
    case "diamond":
      return { ...base, clipPath: "polygon(50% 0, 100% 50%, 50% 100%, 0 50%)" };
    case "hex":
      return {
        ...base,
        clipPath: "polygon(25% 5%, 75% 5%, 100% 50%, 75% 95%, 25% 95%, 0 50%)",
      };
    case "triangle":
      return { ...base, clipPath: "polygon(50% 0, 0 100%, 100% 100%)" };
    case "blob":
      // Inline SVG path as clip-path (browser support is good in modern browsers)
      return {
        ...base,
        clipPath:
          'path("M74.7 12.9c11.8 7.3 20.2 20 23 34.2 2.7 14.2-.3 29.9-8.6 39.8-8.3 9.9-21.8 14-35.6 12.2-13.8-1.7-27.8-10.3-35.1-22.8-7.3-12.5-7.8-28.8-2.5-41.4 5.3-12.6 16.4-21.5 28.4-25C56.2 6.6 68.9 5.7 74.7 12.9z")',
      };
    case "custom":
      return customClipPath.value
        ? { ...base, clipPath: customClipPath.value }
        : { ...base, borderRadius: `${radius.value}px` };
    default:
      return { ...base, borderRadius: `${radius.value}px` };
  }
});

// Image style: ensure it fills the container and respects shape (we apply object-fit cover)
const imageStyle = computed(() => ({
  width: "100%",
  height: "100%",
  display: "block",
  objectFit: "cover",
  // If using shapes that aren't based on borderRadius, ensure image inherits clip-path from container by not overriding it
}));
const shapeLabel = computed(
  () => shape.value.charAt(0).toUpperCase() + shape.value.slice(1)
);
</script>

<style scoped>
/* Nothing required here — shapes are controlled inline.
   If you want smoother edges for clip-path shapes on some browsers, you can add:
   -webkit-clip-path and will-change hints, but that's optional. */
</style>
