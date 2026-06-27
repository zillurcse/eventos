// server/api/floors/index.ts
import {
  defineEventHandler,
  getQuery,
  getHeader,
  getMethod,
  readBody,
  createError,
} from "h3";

const API_BASE = "https://admin.expouse.com/api";
const SANCTUM_BASE = "https://admin.expouse.com";

export default defineEventHandler(async (event) => {
  const query = getQuery(event);
  const cookie = getHeader(event, "cookie") || "";
  const method = getMethod(event);

  // ── Safe Base64 decoder ──
  const safeDecode = (s?: string, name?: string): string => {
    if (!s?.trim()) {
      throw createError({
        statusCode: 400,
        statusMessage: `${name || "Parameter"} is missing`,
      });
    }
    try {
      return atob(s.trim());
    } catch {
      throw createError({
        statusCode: 400,
        statusMessage: `Invalid Base64 in ${name || "parameter"}`,
      });
    }
  };

  // ── Decode credentials ──
  let event_id: string;
  let token: string;

  try {
    event_id = safeDecode(query.event as string, "event");
    token = safeDecode(query.user as string, "user");
  } catch (err) {
    throw err; // Already a createError, just re-throw
  }

  const backendPath = `/floors?event_id=${event_id}&token=${token}`;

  // ── CSRF Token helper ──
  const getCsrfToken = async (): Promise<void> => {
    if (method === "GET") return;

    try {
      await $fetch("/sanctum/csrf-cookie", {
        baseURL: SANCTUM_BASE,
        credentials: "include",
        headers: { Cookie: cookie },
      });
    } catch (e) {
      console.warn("CSRF token fetch failed:", e);
    }
  };

  // ── Parse request body safely ──
  let body: any = undefined;
  if (method !== "GET") {
    try {
      body = await readBody(event);
    } catch {
      body = {};
    }
  }

  // ── API Call ──
  try {
    // Get CSRF token for non-GET requests
    await getCsrfToken();

    const response = await $fetch(backendPath, {
      baseURL: API_BASE,
      method,
      body,
      headers: {
        Cookie: cookie,
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      credentials: "include",
      timeout: 8000,
      retry: 1,
      // Don't throw on response errors, handle them manually
      ignoreResponseError: false,
    });

    const data = (response as any)?.data;

    // Handle array response (GET all floors)
    if (Array.isArray(data)) {
      return { floors: data.map(normalizeFloor) };
    }

    // Handle single floor response (POST create)
    if (data && typeof data === "object") {
      return { floor: normalizeFloor(data) };
    }

    // Empty or invalid response
    throw createError({
      statusCode: 502,
      statusMessage: "Invalid response from backend",
    });
  } catch (err: any) {
    console.error("[Floors API Error]:", err.message || err);

    // Handle timeout
    if (err.message?.includes("timeout")) {
      throw createError({
        statusCode: 504,
        statusMessage: "Backend request timeout",
      });
    }

    // Handle fetch errors
    throw createError({
      statusCode: err.statusCode || 502,
      statusMessage: err.message || "Failed to fetch floors",
    });
  }
});

// ── Floor normalizer ──
function normalizeFloor(f: any) {
  return {
    ...f,
    id: Number(f.id) || 0,
    building_id: Number(f.building_id) || 0,
    rooms: f.rooms ?? [],
  };
}
