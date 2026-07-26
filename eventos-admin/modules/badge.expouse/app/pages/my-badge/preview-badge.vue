<template>
  <div class="flex flex-col h-screen bg-gray-100">
    <div class="flex-1 flex flex-col items-center p-4">
      <div class="w-full flex flex-wrap justify-center items-center gap-3">
        <button
          @click="downloadPDF"
          :disabled="isDownloading || !badgeJson"
          class="flex gap-1 border border-slate-200 px-5 py-2 text-sm bg-blue-200 text-blue-500 rounded-lg font-medium transition-colors focus:outline-none focus:ring-1 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <NuxtIcon
            :name="isDownloading ? 'line-md:loading-twotone-loop' : 'tdesign:file-pdf'"
            class="text-xl"
          />
          <span>{{ isDownloading ? "Downloading..." : "Download" }}</span>
        </button>
      </div>

      <div
        class="flex-1 w-full flex flex-col items-center overflow-auto mt-3 space-y-4"
      >
        <p v-if="loading" class="text-sm text-gray-500 mt-8">Loading preview…</p>
        <p v-else-if="error" class="text-sm text-red-500 mt-8">{{ error }}</p>

        <template v-else-if="badgeJson">
          <!-- Same BadgePreview path as templates / guest wizard / My Badges. -->
          <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <BadgePreview
              :badge-json="badgeJson"
              :data="sampleData"
              side="front"
              :max-width="page.width"
              :max-height="page.height"
            />
          </div>
          <div
            v-if="hasBack"
            class="bg-white shadow-md rounded-lg overflow-hidden"
          >
            <BadgePreview
              :badge-json="badgeJson"
              :data="sampleData"
              side="back"
              :max-width="page.width"
              :max-height="page.height"
            />
          </div>

          <p class="text-xs text-gray-500 text-center max-w-sm">
            Showing sample attendee data on your saved design — same render as
            printed and in-app badges.
          </p>
        </template>
      </div>
    </div>

    <!-- Off-screen 1:1 capture for PDF (html2canvas cannot rasterise display:none). -->
    <div v-if="capturing && badgeJson" class="capture" aria-hidden="true">
      <div ref="captureFront">
        <BadgePreview
          :badge-json="badgeJson"
          :data="sampleData"
          side="front"
          :max-width="page.width"
          :max-height="page.height"
        />
      </div>
      <div v-if="hasBack" ref="captureBack">
        <BadgePreview
          :badge-json="badgeJson"
          :data="sampleData"
          side="back"
          :max-width="page.width"
          :max-height="page.height"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * Organizer preview of a saved badge design with dynamic sample data.
 *
 * Uses BadgePreview + badgeDesign.ts (same code path as the templates list,
 * guest wizard, and attendee My Badges) instead of PreviewCanvas / Pinia store
 * mutation, so what you see here is what prints — layout, fonts, and merges.
 */
import { ref, computed, nextTick, onMounted, watch } from "vue";
import { toast } from "vue-sonner";

const route = useRoute();
const api = useApi();

const eventId = computed(
  () => (route.params.id) || (route.query.event) || ""
);
const designId = computed(() => (route.query.design) || "");

const badgeJson = ref(null);
const sampleData = ref(null);
const badgeFor = ref(null);
const loading = ref(true);
const error = ref(null);
const isDownloading = ref(false);
const capturing = ref(false);
const captureFront = ref(null);
const captureBack = ref(null);

const page = computed(() => badgePageSize(badgeJson.value));
const hasBack = computed(
  () => (badgeJson.value?.backBoxes ?? []).length > 0
);

async function load() {
  if (!designId.value) {
    error.value = "Open this page with ?design=<id> to preview a badge.";
    loading.value = false;
    return;
  }

  loading.value = true;
  error.value = null;

  try {
    const designRes = await api(`/badge-designs/${designId.value}`);
    const design = designRes?.data ?? designRes;
    badgeJson.value = design?.badge_json ?? null;
    badgeFor.value = design?.badge_for ?? null;

    if (!badgeJson.value) {
      error.value = "This design has no canvas data yet.";
      return;
    }

    try {
      const sampleRes = await api(
        `/events/${eventId.value}/badge-designs/sample-data`,
        { query: { badge_for: badgeFor.value || undefined } }
      );
      sampleData.value = sampleRes?.data ?? sampleRes ?? null;
    } catch {
      // Design still previews without merge values (placeholders only).
      sampleData.value = null;
    }
  } catch (e) {
    console.error("Failed to load badge preview:", e);
    error.value = "Could not load this badge design.";
    badgeJson.value = null;
  } finally {
    loading.value = false;
  }
}

onMounted(load);
watch([designId, eventId], load);

async function downloadPDF() {
  if (isDownloading.value || !badgeJson.value) return;
  isDownloading.value = true;
  capturing.value = true;

  try {
    const { $html2canvas, $jsPDF } = useNuxtApp();
    if (!$html2canvas || !$jsPDF) {
      throw new Error("PDF utilities not available.");
    }

    await nextTick();

    const mm = { width: page.value.widthMm, height: page.value.heightMm };
    const shoot = (el) =>
      $html2canvas(el, {
        scale: 3,
        backgroundColor: null,
        useCORS: true,
        logging: false,
      });

    const front = await shoot(captureFront.value);
    const back =
      hasBack.value && captureBack.value
        ? await shoot(captureBack.value)
        : null;

    const pdf = new $jsPDF({
      orientation: mm.width > mm.height ? "landscape" : "portrait",
      unit: "mm",
      format: [mm.width, mm.height],
      compress: true,
    });

    pdf.addImage(
      front.toDataURL("image/png"),
      "PNG",
      0,
      0,
      mm.width,
      mm.height,
      undefined,
      "MEDIUM"
    );

    if (back) {
      pdf.addPage(
        [mm.width, mm.height],
        mm.width > mm.height ? "landscape" : "portrait"
      );
      pdf.addImage(
        back.toDataURL("image/png"),
        "PNG",
        0,
        0,
        mm.width,
        mm.height,
        undefined,
        "MEDIUM"
      );
    }

    const name = (
      sampleData.value?.full_name ||
      "badge"
    )
      .toLowerCase()
      .replace(/\s+/g, "_");
    pdf.save(`${name}-badge.pdf`);
  } catch (e) {
    console.error("Error generating PDF:", e);
    toast.error("Failed to generate PDF. Please try again.");
  } finally {
    isDownloading.value = false;
    capturing.value = false;
  }
}
</script>

<style scoped>
.capture {
  position: fixed;
  top: 0;
  left: -10000px;
  pointer-events: none;
}
</style>
