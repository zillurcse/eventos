<!-- WhiteboardPreview.vue - FIXED VERSION FOR EXACT SCREEN CAPTURE -->
<script setup lang="ts">
import { ref, computed, onMounted, nextTick, watch } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useCanvasRendering } from "@floorplan/composables/useCanvasRendering";
import { useIconRenderer } from "@floorplan/composables/useIconRenderer";
import html2canvas from "html2canvas";
import { jsPDF } from "jspdf";

const emit = defineEmits<{
  close: [];
}>();

const store = useCanvasStore();
const previewContainer = ref<HTMLElement>();
const previewCanvas = ref<HTMLCanvasElement>();
const { fetchIcon, getShapeIconName, getElementIconName } = useIconRenderer();
const isGeneratingPreview = ref(false);
const downloadQuality = ref<"standard" | "high">("high");
const exportProgress = ref("");

const shapeIcons = ref<Record<string, string>>({});
const elementIcons = ref<Record<string, string>>({});

// Use the same rendering engine
const canvasRendering = useCanvasRendering();

// Load icons when component mounts
const loadIcons = async () => {
  // Load shape icons
  const shapeTypes = [
    ...new Set(
      store.domElements
        .filter((el) => el.type === "shape")
        .map((el) => el.subtype)
    ),
  ];
  for (const subtype of shapeTypes) {
    const iconName = getShapeIconName(subtype);
    if (iconName) {
      shapeIcons.value[subtype] = await fetchIcon(iconName);
    }
  }

  // Load element icons
  const elementTypes = [
    ...new Set(
      store.domElements
        .filter((el) => el.type === "elements")
        .map((el) => el.subtype)
    ),
  ];
  for (const subtype of elementTypes) {
    const iconName = getElementIconName(subtype);
    if (iconName) {
      elementIcons.value[subtype] = await fetchIcon(iconName);
    }
  }
};

// Store readiness check
const isStoreReady = computed(() => {
  return (
    store &&
    typeof store.currentTool !== "undefined" &&
    typeof store.zoom !== "undefined" &&
    typeof store.offset !== "undefined" &&
    Array.isArray(store.objects) &&
    Array.isArray(store.domElements)
  );
});

// FIXED: Get actual whiteboard viewport dimensions (full-screen)
const getWhiteboardViewport = () => {
  // Get the actual canvas element from the main Whiteboard component
  const mainCanvas = document.querySelector(
    ".whiteboard-container canvas"
  ) as HTMLCanvasElement;

  if (mainCanvas) {
    const rect = mainCanvas.getBoundingClientRect();
    return {
      width: rect.width,
      height: rect.height,
      offsetX: 0,
      offsetY: 0,
    };
  }

  // Fallback to window dimensions if canvas not found
  return {
    width: window.innerWidth,
    height: window.innerHeight,
    offsetX: 0,
    offsetY: 0,
  };
};

// FIXED: Use actual screen dimensions without any bounds calculation
const whiteboardBounds = computed(() => {
  const viewport = getWhiteboardViewport();

  return {
    x: store.offset.x,
    y: store.offset.y,
    width: viewport.width / store.zoom,
    height: viewport.height / store.zoom,
  };
});

// Helper function to rotate a point around a center
const rotatePoint = (point: any, center: any, angle: number) => {
  const rad = (angle * Math.PI) / 180;
  const cos = Math.cos(rad);
  const sin = Math.sin(rad);

  const translatedX = point.x - center.x;
  const translatedY = point.y - center.y;

  return {
    x: translatedX * cos - translatedY * sin + center.x,
    y: translatedX * sin + translatedY * cos + center.y,
  };
};

// Get shape clip-path style
const getShapeClipPath = (subtype: string) => {
  const clipPaths: Record<string, string> = {
    triangle: "polygon(50% 0%, 0% 100%, 100% 100%)",
    diamond: "polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%)",
    star: "polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%)",
    hexagon: "polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%)",
    pentagon: "polygon(50% 0%, 100% 38%, 82% 100%, 18% 100%, 0% 38%)",
  };
  return clipPaths[subtype] || "";
};

// FIXED: Render preview using exact screen dimensions and current zoom/offset
const renderPreview = () => {
  if (!previewCanvas.value || !isStoreReady.value) return;

  const ctx = previewCanvas.value.getContext("2d");
  if (!ctx) return;

  const bounds = whiteboardBounds.value;

  // Set canvas size to match current viewport
  const dpr = downloadQuality.value === "high" ? 2 : 1;
  previewCanvas.value.width = bounds.width * store.zoom * dpr;
  previewCanvas.value.height = bounds.height * store.zoom * dpr;
  previewCanvas.value.style.width = `${bounds.width * store.zoom}px`;
  previewCanvas.value.style.height = `${bounds.height * store.zoom}px`;

  ctx.scale(dpr, dpr);

  // Clear canvas with white background
  ctx.fillStyle = "#ffffff";
  ctx.fillRect(0, 0, bounds.width * store.zoom, bounds.height * store.zoom);

  // Render all canvas objects using current zoom and offset
  store.objects.forEach((obj) => {
    if (obj.isVisible !== false) {
      canvasRendering.renderObject(ctx, obj, store.zoom, store.offset);
    }
  });
};

const closePreview = () => {
  emit("close");
};

// FIXED: Capture exactly what's on screen using html2canvas
const generatePreviewImage = async (): Promise<string> => {
  if (!previewContainer.value) throw new Error("Preview container not found");

  isGeneratingPreview.value = true;
  exportProgress.value = "Capturing screen...";

  try {
    // Render canvas first
    renderPreview();

    const scale = downloadQuality.value === "high" ? 2 : 1;
    const viewport = getWhiteboardViewport();

    exportProgress.value = "Rendering elements...";

    // Capture the preview container exactly as rendered
    const canvas = await html2canvas(previewContainer.value, {
      backgroundColor: "#ffffff",
      scale: scale,
      useCORS: true,
      allowTaint: false,
      logging: false,
      width: viewport.width,
      height: viewport.height,
      scrollX: 0,
      scrollY: 0,
      windowWidth: viewport.width,
      windowHeight: viewport.height,
    });

    exportProgress.value = "Finalizing...";
    return canvas.toDataURL("image/png", 1.0);
  } catch (error) {
    console.error("Preview generation error:", error);
    throw error;
  } finally {
    isGeneratingPreview.value = false;
    exportProgress.value = "";
  }
};

// FIXED: PDF export with exact screen dimensions
const downloadAsPDF = async () => {
  try {
    isGeneratingPreview.value = true;
    exportProgress.value = "Generating PDF...";

    const dataUrl = await generatePreviewImage();

    const img = new Image();
    img.src = dataUrl;

    await new Promise((resolve, reject) => {
      img.onload = resolve;
      img.onerror = reject;
    });

    // Calculate PDF dimensions based on image aspect ratio
    const imgRatio = img.width / img.height;
    let pdfWidth, pdfHeight;

    if (imgRatio > 1) {
      // Landscape orientation
      pdfWidth = 297; // A4 landscape width in mm
      pdfHeight = pdfWidth / imgRatio;
    } else {
      // Portrait orientation
      pdfHeight = 297; // A4 portrait height in mm
      pdfWidth = pdfHeight * imgRatio;
    }

    const pdf = new jsPDF({
      orientation: imgRatio > 1 ? "landscape" : "portrait",
      unit: "mm",
      format: [pdfWidth, pdfHeight],
    });

    // Add image to PDF, fitting to page
    pdf.addImage(dataUrl, "PNG", 0, 0, pdfWidth, pdfHeight);

    // Add footer with metadata
    pdf.setFontSize(8);
    pdf.setTextColor(100, 100, 100);
    pdf.text(
      `Generated on ${new Date().toLocaleDateString()} | Floor Plan Export`,
      10,
      pdfHeight - 5
    );

    pdf.save(`floor-plan-${new Date().getTime()}.pdf`);
    exportProgress.value = "PDF downloaded successfully!";

    setTimeout(() => {
      exportProgress.value = "";
    }, 2000);
  } catch (error) {
    console.error("Failed to generate PDF:", error);
    alert("Failed to generate PDF. Please try again.");
    exportProgress.value = "";
  }
};

// FIXED: PNG export with exact screen dimensions
const downloadAsPNG = async () => {
  try {
    isGeneratingPreview.value = true;
    exportProgress.value = "Generating PNG...";

    const dataUrl = await generatePreviewImage();
    const link = document.createElement("a");
    link.download = `floor-plan-${new Date().getTime()}.png`;
    link.href = dataUrl;
    link.click();

    exportProgress.value = "PNG downloaded successfully!";
    setTimeout(() => {
      exportProgress.value = "";
    }, 2000);
  } catch (error) {
    console.error("Failed to generate PNG:", error);
    alert("Failed to generate PNG preview");
    exportProgress.value = "";
  }
};

// Export as JSON
const exportAsJSON = () => {
  try {
    const previewData = {
      objects: store.objects.map((obj) => ({
        id: obj.id,
        type: obj.type,
        points: obj.points,
        color: obj.color,
        strokeWidth: obj.strokeWidth,
        lineWidth: obj.lineWidth,
        lineCap: obj.lineCap,
        lineJoin: obj.lineJoin,
        dashStyle: obj.dashStyle,
        fill: obj.fill,
        stroke: obj.stroke,
        opacity: obj.opacity,
        rotation: obj.rotation,
        zIndex: obj.zIndex,
        boothNumber: obj.boothNumber,
        length: obj.length,
        breadth: obj.breadth,
        status: obj.status,
        companyName: obj.companyName,
        displayOption: obj.displayOption,
        isLocked: obj.isLocked,
        isVisible: obj.isVisible,
      })),
      domElements: store.domElements.map((el) => ({
        id: el.id,
        type: el.type,
        subtype: el.subtype,
        position: el.position,
        size: el.size,
        rotation: el.rotation,
        content: el.content,
        src: el.src,
        styleProps: el.styleProps,
        zIndex: el.zIndex,
      })),
      viewport: {
        zoom: store.zoom,
        offset: store.offset,
      },
      metadata: {
        generatedAt: new Date().toISOString(),
        totalObjects: store.objects.length,
        totalDomElements: store.domElements.length,
        bounds: whiteboardBounds.value,
      },
    };

    const dataStr = JSON.stringify(previewData, null, 2);
    const dataBlob = new Blob([dataStr], { type: "application/json" });
    const link = document.createElement("a");
    link.download = `floor-plan-${new Date().getTime()}.json`;
    link.href = URL.createObjectURL(dataBlob);
    link.click();
    URL.revokeObjectURL(link.href);
  } catch (error) {
    console.error("Failed to export JSON:", error);
    alert("Failed to export JSON");
  }
};

// Import from JSON
const importFromJSON = (event: Event) => {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];

  if (!file) return;

  const reader = new FileReader();
  reader.onload = (e) => {
    try {
      const data = JSON.parse(e.target?.result as string);

      if (!data.objects || !data.domElements) {
        throw new Error("Invalid whiteboard data format");
      }

      store.objects.length = 0;
      store.domElements.length = 0;

      data.objects.forEach((obj: any) => {
        store.objects.push({
          ...obj,
          isSelected: false,
        });
      });

      data.domElements.forEach((el: any) => {
        store.domElements.push(el);
      });

      // Restore viewport if available
      if (data.viewport) {
        store.zoom = data.viewport.zoom || 1;
        store.offset = data.viewport.offset || { x: 0, y: 0 };
      }

      store.updateCurrentFloor();

      nextTick(() => {
        renderPreview();
      });

      alert("Floor plan imported successfully!");
    } catch (error) {
      console.error("Failed to import JSON:", error);
      alert("Failed to import JSON file");
    }
  };

  reader.readAsText(file);
  input.value = "";
};

// Copy JSON to clipboard
const copyJSONToClipboard = async () => {
  try {
    const previewData = {
      objects: store.objects,
      domElements: store.domElements,
      viewport: {
        zoom: store.zoom,
        offset: store.offset,
      },
      metadata: {
        generatedAt: new Date().toISOString(),
        totalObjects: store.objects.length,
        totalDomElements: store.domElements.length,
      },
    };

    const jsonString = JSON.stringify(previewData, null, 2);
    await navigator.clipboard.writeText(jsonString);
    alert("JSON copied to clipboard!");
  } catch (error) {
    console.error("Failed to copy to clipboard:", error);
    alert("Failed to copy to clipboard");
  }
};

// Setup preview
const setupPreview = () => {
  renderPreview();
};

// Auto-render when objects change
watch(
  () => [store.objects, store.domElements, store.zoom, store.offset],
  () => {
    nextTick(() => {
      renderPreview();
    });
  },
  { deep: true, immediate: true }
);

onMounted(async () => {
  await loadIcons();
  nextTick(() => {
    setupPreview();
  });
});
</script>

<template>
  <div class="whiteboard-preview-fullscreen">
    <!-- Enhanced Controls -->
    <div class="preview-controls-fullscreen">
      <div class="control-group">
        <span class="control-label">Download Quality:</span>
        <select v-model="downloadQuality" class="quality-select">
          <option value="standard">Standard</option>
          <option value="high">High Quality</option>
        </select>
      </div>

      <!-- <div class="control-buttons">
        <button
          @click="downloadAsPNG"
          :disabled="isGeneratingPreview"
          class="control-btn"
        >
          <NuxtIcon name="mdi:image" class="btn-icon" />
          PNG ({{ downloadQuality }})
        </button>
        <button
          @click="downloadAsPDF"
          :disabled="isGeneratingPreview"
          class="control-btn"
        >
          <NuxtIcon name="mdi:file-pdf-box" class="btn-icon" />
          PDF ({{ downloadQuality }})
        </button>
        <button @click="exportAsJSON" class="control-btn">
          <NuxtIcon name="mdi:code-json" class="btn-icon" />
          JSON
        </button>
        <button @click="copyJSONToClipboard" class="control-btn">
          <NuxtIcon name="mdi:content-copy" class="btn-icon" />
          Copy JSON
        </button>
        <label class="control-btn import-btn">
          <NuxtIcon name="mdi:import" class="btn-icon" />
          Import JSON
          <input
            type="file"
            accept=".json"
            @change="importFromJSON"
            style="display: none"
          />
        </label>
        <button
          @click="closePreview"
          class="p-2 hover:bg-gray-600 rounded-lg transition-colors"
          aria-label="Close Preview"
        >
          <NuxtIcon name="mdi:close" class="text-xl text-white" />
        </button>
      </div> -->

      <!-- Progress Indicator -->
      <div v-if="exportProgress" class="progress-indicator">
        <NuxtIcon name="mdi:loading" class="animate-spin" />
        <span>{{ exportProgress }}</span>
      </div>
    </div>

    <!-- Fullscreen Preview Container -->
    <div
      ref="previewContainer"
      class="preview-container-fullscreen"
      :style="{
        width: `${whiteboardBounds.width}px`,
        height: `${whiteboardBounds.height}px`,
        minWidth: `${whiteboardBounds.width}px`,
        minHeight: `${whiteboardBounds.height}px`,
      }"
    >
      <!-- Canvas that renders all canvas objects -->
      <canvas
        ref="previewCanvas"
        class="preview-canvas-fullscreen"
        :style="{
          width: `${whiteboardBounds.width}px`,
          height: `${whiteboardBounds.height}px`,
        }"
      />

      <!-- DOM Elements Overlay - EXACT SCREEN POSITIONING -->
      <div
        class="dom-elements-overlay"
        :style="{
          width: `${whiteboardBounds.width * store.zoom}px`,
          height: `${whiteboardBounds.height * store.zoom}px`,
          position: 'absolute',
          top: 0,
          left: 0,
          pointerEvents: 'none',
        }"
      >
        <!-- Render ALL DOM element types with exact screen coordinates -->
        <div
          v-for="element in store.domElements"
          :key="element.id"
          class="preview-dom-element"
          :data-element-type="element.type"
          :data-element-subtype="element.subtype"
          :style="{
            position: 'absolute',
            left: `${(element.position.x - store.offset.x) * store.zoom}px`,
            top: `${(element.position.y - store.offset.y) * store.zoom}px`,
            width: `${(element.size?.width || 100) * store.zoom}px`,
            height: `${(element.size?.height || 100) * store.zoom}px`,
            transform: `rotate(${element.rotation || 0}deg)`,
            transformOrigin: 'center',
            zIndex: element.zIndex || 0,
          }"
        >
          <!-- TEXT Elements -->
          <div
            v-if="element.type === 'text'"
            class="preview-text-element"
            :style="{
              ...element.styleProps,
              width: '100%',
              height: '100%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              padding: '8px',
              boxSizing: 'border-box',
              fontFamily:
                element.styleProps?.fontFamily || 'Inter, Arial, sans-serif',
              fontSize: element.styleProps?.fontSize || '14px',
              fontWeight: element.styleProps?.fontWeight || '500',
              color: element.styleProps?.color || '#000000',
              backgroundColor:
                element.styleProps?.backgroundColor || 'transparent',
              border: element.styleProps?.border || 'none',
              borderRadius: element.styleProps?.borderRadius || '0px',
              textAlign: element.styleProps?.textAlign || 'center',
              wordBreak: 'break-word',
              whiteSpace: 'pre-wrap',
              lineHeight: element.styleProps?.lineHeight || '1.2',
              fontStyle: element.styleProps?.fontStyle || 'normal',
              textDecoration: element.styleProps?.textDecoration || 'none',
              textTransform: element.styleProps?.textTransform || 'none',
              letterSpacing: element.styleProps?.letterSpacing || 'normal',
              wordSpacing: element.styleProps?.wordSpacing || 'normal',
              opacity:
                element.styleProps?.opacity !== undefined
                  ? element.styleProps.opacity
                  : 1,
            }"
          >
            {{ element.content }}
          </div>

          <!-- SHAPE Elements -->
          <div
            v-else-if="element.type === 'shape'"
            class="preview-shape-element"
            :class="[
              `shape-${element.subtype}`,
              { 'shape-label': element.content },
            ]"
            :style="{
              backgroundColor: element.color,
              border: element.strokeWidth
                ? `${element.strokeWidth}px solid ${
                    element.strokeColor || '#000'
                  }`
                : 'none',
              width: '100%',
              height: '100%',
              position: 'relative',
              overflow: 'hidden',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              clipPath: getShapeClipPath(element.subtype),
            }"
          >
            <!-- Render SVG icon if available -->
            <div
              v-if="shapeIcons[element.subtype]"
              class="icon-container"
              v-html="shapeIcons[element.subtype]"
              style="
                width: 60%;
                height: 60%;
                display: flex;
                align-items: center;
                justify-content: center;
              "
            />

            <!-- Label (if any) -->
            <div
              v-if="element.content"
              class="shape-label"
              :style="{
                color: element.textColor || '#ffffff',
                position: 'absolute',
              }"
            >
              {{ element.content }}
            </div>
          </div>

          <!-- ELEMENT Icons (non-shape) -->
          <div
            v-else-if="
              element.type === 'elements' && elementIcons[element.subtype]
            "
            class="preview-element-icon"
            v-html="elementIcons[element.subtype]"
            style="
              width: 100%;
              height: 100%;
              display: flex;
              align-items: center;
              justify-content: center;
            "
          />

          <!-- ELEMENTS (Generic) -->
          <div
            v-else-if="element.type === 'elements'"
            class="preview-generic-element"
            :style="{
              ...element.styleProps,
              width: '100%',
              height: '100%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              padding: '8px',
              boxSizing: 'border-box',
              backgroundColor: element.styleProps?.backgroundColor || '#f3f4f6',
              border: element.styleProps?.border || '2px dashed #d1d5db',
              borderRadius: element.styleProps?.borderRadius || '8px',
              fontFamily: 'Inter, Arial, sans-serif',
              fontSize: '12px',
              color: element.styleProps?.color || '#6b7280',
              textAlign: 'center',
              wordBreak: 'break-word',
              fontWeight: '500',
            }"
          >
            <div v-if="element.content" class="element-content">
              {{ element.content }}
            </div>
            <div v-else class="element-placeholder">
              {{ element.subtype || "Element" }}
            </div>
          </div>

          <!-- IMAGE Elements -->
          <img
            v-else-if="element.type === 'image' && element.src"
            :src="element.src"
            :alt="element.content || 'Image'"
            class="preview-image-element"
            :style="{
              width: '100%',
              height: '100%',
              objectFit: element.styleProps?.objectFit || 'contain',
              borderRadius: element.styleProps?.borderRadius || '4px',
              border: element.styleProps?.border || 'none',
            }"
          />

          <!-- FALLBACK for unknown types -->
          <div
            v-else
            class="preview-unknown-element"
            :style="{
              width: '100%',
              height: '100%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              backgroundColor: '#fef3c7',
              border: '2px dashed #f59e0b',
              borderRadius: '6px',
              fontFamily: 'Inter, Arial, sans-serif',
              fontSize: '12px',
              color: '#92400e',
              textAlign: 'center',
              padding: '8px',
              boxSizing: 'border-box',
            }"
          >
            {{ element.type }}:
            {{ element.content || element.subtype || "Element" }}
          </div>
        </div>
      </div>
    </div>

    <!-- Loading overlay -->
    <div v-if="isGeneratingPreview" class="loading-overlay-fullscreen">
      <div class="loading-spinner-fullscreen">
        <div class="spinner"></div>
        <div class="loading-text">
          Generating
          {{ downloadQuality === "high" ? "High-Quality" : "Standard" }}
          Preview...
        </div>
        <div class="loading-subtext">
          Capturing all canvas objects and DOM elements
        </div>
      </div>
    </div>

    <!-- Data summary -->
    <div class="data-summary-fullscreen">
      <h3>Floor Plan Summary</h3>
      <div class="summary-grid">
        <div class="summary-item">
          <span class="summary-label">Canvas Objects:</span>
          <span class="summary-value">{{ store.objects.length }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">DOM Elements:</span>
          <span class="summary-value">{{ store.domElements.length }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">Canvas Width:</span>
          <span class="summary-value"
            >{{ Math.round(whiteboardBounds.width) }}px</span
          >
        </div>
        <div class="summary-item">
          <span class="summary-label">Canvas Height:</span>
          <span class="summary-value"
            >{{ Math.round(whiteboardBounds.height) }}px</span
          >
        </div>
        <div class="summary-item">
          <span class="summary-label">Export Quality:</span>
          <span class="summary-value">{{
            downloadQuality === "high" ? "High (2x)" : "Standard (1x)"
          }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Styles remain the same as in the original file */
.whiteboard-preview-fullscreen {
  width: 100%;
  height: 100%;
  background: #1f2937;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.preview-controls-fullscreen {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #374151;
  border-bottom: 1px solid #4b5563;
  shrink: 0;
  flex-wrap: wrap;
  gap: 12px;
}

.control-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.control-label {
  font-size: 14px;
  font-weight: 500;
  color: #d1d5db;
}

.quality-select {
  border: 1px solid #6b7280;
  border-radius: 6px;
  padding: 6px 10px;
  font-size: 14px;
  background: #4b5563;
  color: white;
  min-width: 120px;
  cursor: pointer;
}

.quality-select:focus {
  outline: none;
  ring: 2px;
  ring-color: #3b82f6;
}

.control-buttons {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.control-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s ease;
}

.control-btn:hover:not(:disabled) {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.control-btn:disabled {
  background: #6b7280;
  cursor: not-allowed;
  transform: none;
}

.btn-icon {
  width: 14px;
  height: 14px;
}

.import-btn {
  cursor: pointer;
  background: #10b981;
}

.import-btn:hover {
  background: #059669;
}

.progress-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #1e40af;
  color: white;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  width: 100%;
  margin-top: 8px;
}

.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.preview-container-fullscreen {
  flex: 1;
  background: white;
  margin: 0;
  overflow: auto;
  display: flex;
  align-items: flex-start;
  justify-content: flex-start;
  position: relative;
}

.preview-canvas-fullscreen {
  display: block;
  background: white;
}

.dom-elements-overlay {
  pointer-events: none;
}

.preview-dom-element {
  pointer-events: none;
}

/* Shape specific styles */
.shape-circle {
  border-radius: 50% !important;
}

.shape-label {
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
  font-weight: 600;
  font-size: 14px;
}

.icon-container {
  display: flex;
  align-items: center;
  justify-content: center;
}

.icon-container :deep(svg) {
  width: 100%;
  height: 100%;
  fill: currentColor;
}

.loading-overlay-fullscreen {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.loading-spinner-fullscreen {
  background: white;
  padding: 24px;
  border-radius: 12px;
  text-align: center;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
  min-width: 280px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top: 3px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 12px;
}

.loading-text {
  font-weight: 600;
  font-size: 14px;
  color: #111827;
  margin-bottom: 4px;
}

.loading-subtext {
  font-size: 12px;
  color: #6b7280;
}

.data-summary-fullscreen {
  padding: 12px 16px;
  background: #374151;
  border-top: 1px solid #4b5563;
  shrink: 0;
}

.data-summary-fullscreen h3 {
  margin: 0 0 8px 0;
  color: #f9fafb;
  font-size: 14px;
  font-weight: 600;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 6px;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 8px;
  background: #4b5563;
  border-radius: 4px;
  font-size: 12px;
}

.summary-label {
  color: #d1d5db;
}

.summary-value {
  font-weight: 600;
  color: #f9fafb;
}

@media (max-width: 768px) {
  .preview-controls-fullscreen {
    flex-direction: column;
    gap: 8px;
    align-items: stretch;
  }

  .control-group {
    justify-content: center;
  }

  .control-buttons {
    justify-content: center;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }

  .control-btn {
    font-size: 12px;
    padding: 6px 10px;
  }
}
</style>
