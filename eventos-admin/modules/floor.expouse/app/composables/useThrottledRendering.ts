// composables/useThrottledRendering.ts
import { ref, onUnmounted } from "vue";

export function useThrottledRendering() {
  const lastRenderTime = ref(0);
  const renderScheduled = ref(false);
  const throttleTimeout = ref<NodeJS.Timeout | null>(null);

  // Throttled render request
  const requestThrottledRender = (
    renderFunction: () => void,
    throttleMs: number = 16
  ): void => {
    const now = Date.now();
    const timeSinceLastRender = now - lastRenderTime.value;

    // If enough time has passed, render immediately
    if (timeSinceLastRender >= throttleMs) {
      lastRenderTime.value = now;
      renderFunction();
      return;
    }

    // Otherwise, schedule a render
    if (!renderScheduled.value) {
      renderScheduled.value = true;

      if (throttleTimeout.value) {
        clearTimeout(throttleTimeout.value);
      }

      throttleTimeout.value = setTimeout(() => {
        renderScheduled.value = false;
        lastRenderTime.value = Date.now();
        renderFunction();
      }, throttleMs - timeSinceLastRender);
    }
  };

  // Cancel any pending renders
  const cancelPendingRender = (): void => {
    if (throttleTimeout.value) {
      clearTimeout(throttleTimeout.value);
      throttleTimeout.value = null;
    }
    renderScheduled.value = false;
  };

  onUnmounted(() => {
    cancelPendingRender();
  });

  return {
    requestThrottledRender,
    cancelPendingRender,
    isRenderScheduled: renderScheduled,
  };
}
