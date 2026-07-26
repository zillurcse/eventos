// app/composables/api/useFloorsApi.ts
import { toast } from "vue-sonner";
import type { Floor } from "@floorplan/types/canvas";

/**
 * Floor data access. Talks to the EventOS API project (runtimeConfig
 * public.apiBase, e.g. http://localhost:8088/api/v1) using the admin's
 * bearer-authenticated client (useApi). The logged-in user's token IS the auth —
 * no base64 event/user tokens. Mounted at /org/events/:id/floor, so the event
 * uuid comes from the route param `id`; list/create are event-scoped
 * (`/events/{uuid}/floors`), show/update/delete are id-based (`/floors/{id}`).
 *
 * Backend: FloorController on the EventOS API. Response handling is tolerant of
 * `{ floors }` / `{ floor }` and Laravel's `{ data }`.
 */
export const useFloorsApi = () => {
  const api = useApi();
  const auth = useAuthStore();
  const route = useRoute();

  // Event uuid from the route (mounted at /org/events/:id/floor).
  const eventId = computed(
    () => (route.params.id as string) || (route.query.event as string) || ""
  );

  // Authenticated user + a resolved event replace the old base64 token gate.
  const isReady = computed(() => auth.isAuthed && !!eventId.value);

  const pickList = (res: any): Floor[] =>
    res?.floors ?? res?.data ?? (Array.isArray(res) ? res : []);
  const pickOne = (res: any): Floor | null => res?.floor ?? res?.data ?? res ?? null;

  return {
    isReady,

    // Get all floors
    getFloors: async (): Promise<Floor[]> => {
      if (!eventId.value) return [];
      try {
        const res = await api(`/events/${eventId.value}/floors`);
        const floors = pickList(res);
        toast.success(`Found ${floors.length} floor(s)`);
        return floors;
      } catch (error: any) {
        toast.error(error.data?.message || error.message || "Could not load floors.");
        return [];
      }
    },

    // Get single floor
    getFloor: async (id: string): Promise<Floor | null> => {
      if (!id) return null;
      try {
        const res = await api(`/floors/${id}`);
        const floor = pickOne(res);
        toast.success(`Opened: ${(floor as any)?.name || "Untitled"}`);
        return floor;
      } catch (error: any) {
        toast.error(error.data?.message || error.message || "Floor not found.");
        return null;
      }
    },

    // Create floor
    createFloor: async (floor: Partial<Floor>): Promise<Floor | null> => {
      try {
        const res = await api(`/events/${eventId.value}/floors`, { method: "POST", body: floor });
        toast.success(`${floor.name || "New Floor"} is ready to design`);
        return pickOne(res);
      } catch (error: any) {
        toast.error(error.data?.message || error.message || "Could not create floor.");
        return null;
      }
    },

    // Update floor
    updateFloor: async (id: string, floor: Partial<Floor>): Promise<Floor | null> => {
      try {
        const res = await api(`/floors/${id}`, { method: "PUT", body: floor });
        toast.success(`${floor.name || "Floor"} updated successfully`);
        return pickOne(res);
      } catch (error: any) {
        toast.error(error.data?.message || error.message || "Changes were not saved.");
        return null;
      }
    },

    // Delete floor
    deleteFloor: async (id: string): Promise<boolean> => {
      try {
        await api(`/floors/${id}`, { method: "DELETE" });
        toast.success("Floor deleted");
        return true;
      } catch (error: any) {
        toast.error(error.data?.message || "Could not delete floor.");
        return false;
      }
    },
  };
};
