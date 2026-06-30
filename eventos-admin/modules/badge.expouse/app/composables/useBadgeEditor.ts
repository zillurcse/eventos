import { useCanvasStore } from "@badge/stores/useCanvasStore";
import { usePageStore } from "@badge/stores/usePageStore";

// Define interfaces for type safety
interface Layer {
  id: string;
  name: string;
  type: string;
  visible: boolean;
}

interface DragItem {
  type: string;
  [key: string]: any;
}

interface DragEndPayload {
  item: DragItem;
  x: number;
  y: number;
}

interface BadgeData {
  data: {
    font_json: string;
    back_json: string;
    format: string;
    layers: Layer[];
    width: number;
    height: number;
    [key: string]: any;
  };
}

export function useBadgeEditor() {
  // Stores
  const pageStore = usePageStore();
  const store = useCanvasStore();

  const {
    activeTab,
    frontBoxes,
    backBoxes,
    activeSide,
    selectedElement,

    currentProperties,
    dropzone,
    showImageModal,
    showGradientModal,
    showColorModal,
    frontBackground,
    backBackground,
    pendingImagePosition,
    pendingImageSide,
    cursorPosition,
    dropdownOpen,
    imageItem,
    punchArea,
    punchCircle,
    punchLong,
  } = storeToRefs(store);

  const presetWidth = pageStore.presetWidth;
  const presetHeight = pageStore.presetHeight;

  const applyGradient = store.applyGradient;

  // Toast Massage
  const toast = useToast();

  // Router and Route
  const router = useRouter();
  const route = useRoute();

  // Reactive state

  const zoomLevel: Ref<number> = ref(100);
  const showGrid: Ref<boolean> = ref(false);
  const isFlipping: Ref<boolean> = ref(false);
  const selectedLayer: Ref<string | null> = ref(null);
  const layers: Ref<Layer[]> = ref([]);
  const displayOption: Ref<string> = ref("both sides");

  // Computed properties
  const zoomScale: ComputedRef<number> = computed(() => zoomLevel.value / 100);
  const selectedElementType: ComputedRef<string | null> = computed(() => {
    const element = store.boxes.find(
      (e: any) => e.id === store.selectedElement
    );
    return element ? element.type : null;
  });

  // Initialize dropzone on mount
  onMounted(() => {
    store.dropzone = dropzone.value;
    store.frontBoxes.punchCirle = store.punchCirle;
    store.backBoxes.punchLong = store.punchLong;
  });

  // Watch for changes in store and update layers
  watch(
    [() => store.activeSide, () => store.frontBoxes, () => store.backBoxes],
    () => {
      layers.value = store.boxes.map((box: any) => ({
        id: box.id,
        name: box.label,
        type: box.type,
        visible: box.visible ?? true,
      }));
      selectedLayer.value = store.selectedElement;
    },
    { deep: true }
  );

  // The badge editor is mounted at /org/events/:id/badge, so the event uuid is
  // the `id` route param; a specific saved design (optional) comes from
  // ?design=<id>. Auth is the signed-in user's bearer token via the admin's
  // useApi() — replacing the old base64 event/badge/token query gate against
  // admin.expouse.com.
  const api = useApi();
  const event_id = ref(
    (route.params.id as string) || (route.query.event as string) || ""
  );
  const design_id = ref((route.query.design as string) || "");

  // Keep the selected design id in sync if the route query changes.
  watch(
    () => route.query,
    (q) => {
      design_id.value = (q.design as string) || design_id.value;
    },
    { immediate: true }
  );

  const badgeData = ref<any>(null);
  const error = ref<any>(null);

  function replaceTextValues(firstArray: any, secondArray: any) {
    // Flatten the items from designGroups for easier lookup
    const valueMap = {};
    secondArray.data.designGroups.forEach((group: any) => {
      group.items.forEach((item: any) => {
        if (item.key) {
          valueMap[item.key] = item.value;
        } else if (item.type) {
          valueMap[item.type] = item.value; // For cases like 'qrcode'
        }
      });
    });

    // Helper function to update text fields in a boxes array
    const updateBoxes = (boxes: any) => {
      return boxes.map((item: any) => {
        let newText = item.text;

        // Check if there's a matching key or type in the valueMap and value is valid
        if (
          item.key &&
          valueMap[item.key] !== undefined &&
          valueMap[item.key] !== null &&
          valueMap[item.key] !== ""
        ) {
          newText = valueMap[item.key];
        } else if (
          item.type &&
          valueMap[item.type] !== undefined &&
          valueMap[item.type] !== null &&
          valueMap[item.type] !== ""
        ) {
          newText = valueMap[item.type];
        }

        // Update QR code value if applicable and valid
        let updatedProperties = { ...item.properties, text: newText };
        if (
          item.type === "qrcode" &&
          valueMap["qrcode"] !== undefined &&
          valueMap["qrcode"] !== null &&
          valueMap["qrcode"] !== ""
        ) {
          updatedProperties = {
            ...updatedProperties,
            qrcode: {
              ...updatedProperties.qrcode,
              value: valueMap["qrcode"],
            },
          };
        }

        if (
          item.type === "avatar" &&
          valueMap["avatar"] !== undefined &&
          valueMap["avatar"] !== null &&
          valueMap["avatar"] !== ""
        ) {
          updatedProperties = {
            ...updatedProperties,
            avatar: {
              ...updatedProperties.avatar,
              value: valueMap["avatar"],
            },
          };
        }

        // console.log("item type", item);

        // Return updated item with all other properties unchanged
        return {
          ...item,
          text: newText,
          properties: updatedProperties,
        };
      });
    };

    // Return updated firstArray with modified frontBoxes and backBoxes
    return {
      ...firstArray,
      frontBoxes: updateBoxes(firstArray.frontBoxes),
      backBoxes: updateBoxes(firstArray.backBoxes),
    };
  }
  // Execute the replacement
  // const updatedArray = replaceTextValues(firstArray, secondArray);

  // Load a saved design (if ?design=<id> is present) from the EventOS API. The
  // response is { data: { badge_json, layers, ... } }, which setCavasElementData
  // reads directly. A fresh editor (no design id) just starts blank.
  const fetchBadgeData = async () => {
    if (!design_id.value) return;
    try {
      const res: any = await api(`/badge-designs/${design_id.value}`);
      badgeData.value = res;
      store.setCavasElementData(res);
    } catch (err) {
      error.value = err;
      console.error("Failed to fetch badge data:", err);
    }
  };

  // Fetch data on page load (client-side)
  onMounted(() => {
    fetchBadgeData();
  });

  // Refetch when the event or selected design changes.
  watch([event_id, design_id], () => {
    fetchBadgeData();
  });

  // State for API response and error
  const response: any = ref(null);
  const errorData = ref(null);

  // Function to send data
  const sendData = async (): Promise<any> => {
    try {
      if (!event_id.value) {
        throw new Error("Missing event id in the route");
      }

      // Create a plain object for badge_json to avoid circular references
      const badgeJson = {
        page_config: {
          pageWidth: pageStore.pageWidth,
          pageHeight: pageStore.pageHeight,
          presetWidth: pageStore.presetWidth,
          presetHeight: pageStore.presetHeight,
          showModal: pageStore.showModal,
          badgeOrientation: pageStore.badgeOrientation,
          badgeSize: pageStore.badgeSize,
          badgeSizePreset: pageStore.badgeSizePreset,
          customWidth: pageStore.customWidth,
          customHeight: pageStore.customHeight,
        },

        frontBoxes: frontBoxes.value,
        backBoxes: backBoxes.value,
        activeSide: activeSide.value,

        currentProperties: currentProperties.value,

        frontBackground: frontBackground.value,
        backBackground: backBackground.value,

        punchArea: punchArea.value,
        punchCircle: punchCircle.value,
        punchLong: punchLong.value,
      };

      // Sanitize layers to ensure no circular references
      const sanitizedLayers = toRaw(layers.value).map((layer) => ({
        // Adjust based on your layer structure; include only serializable properties
        id: layer.id,
        name: layer.name,
        type: layer.type,
        visible: layer.visible ?? true,
        // Add other safe properties as needed
      }));
      const payload = {
        name: badgeData.value?.data?.name || "Badge Design",
        badge_json: badgeJson,
        layers: sanitizedLayers,
      };

      // Update an existing design, or create a new one under the event and
      // remember its id so subsequent saves update the same design.
      let res: any;
      if (design_id.value) {
        res = await api(`/badge-designs/${design_id.value}`, {
          method: "PUT",
          body: payload,
        });
      } else {
        res = await api(`/events/${event_id.value}/badge-designs`, {
          method: "POST",
          body: payload,
        });
        if (res?.data?.id) design_id.value = String(res.data.id);
      }

      response.value = res;
      errorData.value = null;
      badgeData.value = res;
      toast.success({
        title: "Success!",
        message: "Badge design saved.",
        position: "topRight",
      });
      return res;
    } catch (err: any) {
      error.value = err.message || "Failed to send data";
      console.error("Error sending data:", err);
      throw err;
    }
  };

  const switchSideTab = (side: string): void => {
    if (isFlipping.value || store.activeSide === side) return;
    isFlipping.value = true;
    store.activeSide = side;
    setTimeout(() => (isFlipping.value = false), 600);
  };

  const zoom = (delta: number): void => {
    zoomLevel.value = Math.max(50, Math.min(200, zoomLevel.value + delta));
  };

  const toggleGrid = (): void => {
    showGrid.value = !showGrid.value;
  };

  const onDragStart = (item: DragItem): void => {
    // Prepare for drag
  };

  const onDragEnd = ({ item, x, y }: DragEndPayload): void => {
    if (!dropzone.value) return;
    const rect = dropzone.value.getBoundingClientRect();
    const dropXPos = x - rect.left;
    const dropYPos = y - rect.top;

    const canvasWidth = rect.width;
    const canvasHeight = rect.height;

    const elementWidth =
      item.type === "background" ? pageStore.presetWidth * 3.78 : 200;
    const elementHeight =
      item.type === "background" ? pageStore.presetHeight * 3.78 : 64;

    const adjustedX = Math.max(
      0,
      Math.min(dropXPos, canvasWidth - elementWidth)
    );
    const adjustedY = Math.max(
      0,
      Math.min(dropYPos, canvasHeight - elementHeight)
    );

    store.addElementFromDrag(item, { top: adjustedY, left: adjustedX });
  };

  const handleDrop = (event: DragEvent): void => {
    event.preventDefault();
  };

  const handleImageUploaded = (dataUrl: string): void => {
    store.handleImageUploaded(dataUrl);
    store.showImageModal = false;
  };

  return {
    activeSide,
    presetWidth,
    presetHeight,
    frontBoxes,
    backBoxes,
    currentProperties,
    showImageModal,
    pendingImageSide,
    applyGradient,
    showColorModal,
    selectedElement,
    dropzone,
    zoomLevel,
    showGrid,
    isFlipping,
    zoomScale,
    selectedLayer,
    layers,
    displayOption,
    selectedElementType,

    sendData,
    switchSideTab,
    zoom,
    toggleGrid,
    onDragStart,
    onDragEnd,
    handleDrop,
    handleImageUploaded,
  };
}
