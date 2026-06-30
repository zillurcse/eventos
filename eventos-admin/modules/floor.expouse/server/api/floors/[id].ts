// server/api/floors/[id].ts
import {
  defineEventHandler,
  getRouterParam,
  getQuery,
  createError,
  getHeader,
  getMethod,
  readBody,
} from "h3";

const API_BASE = "https://admin.expouse.com/api";
const SANCTUM_BASE = "https://admin.expouse.com";

// const API_BASE = "https://lm.deshicoder.com/floor-plan-api/api";
// const SANCTUM_BASE = "https://lm.deshicoder.com/floor-plan-api";

export default defineEventHandler(async (event) => {
  const query = getQuery(event);
  const cookie = getHeader(event, "cookie") || "";
  const id = getRouterParam(event, "id");

  // ── EARLY VALIDATION ──
  if (!id?.trim()) {
    throw createError({
      statusCode: 400,
      statusMessage: "Floor ID is required",
    });
  }

  const safeDecode = (s?: string, name?: string) => {
    if (!s?.trim()) {
      throw createError({
        statusCode: 400,
        statusMessage: `${name || "Param"} is missing`,
      });
    }
    try {
      return atob(s.trim());
    } catch {
      throw createError({
        statusCode: 400,
        statusMessage: `Invalid Base64 in ${name || "param"}`,
      });
    }
  };

  let event_id: string, token: string;
  try {
    event_id = safeDecode(query.event as string, "event");
    token = safeDecode(query.user as string, "user");
  } catch (err) {
    throw err; // already createError
  }

  const basePath = `/floors/${id}?event_id=${event_id}&token=${token}`;

  // ── CSRF Helper ──
  const getCsrfToken = async () => {
    try {
      await $fetch("/sanctum/csrf-cookie", {
        baseURL: SANCTUM_BASE,
        credentials: "include",
        headers: { Cookie: cookie },
      });
    } catch (e) {
      console.warn("CSRF cookie failed (non-GET request may fail)", e);
    }
  };

  // ── Safe API Client ──
  const api = $fetch.create({
    baseURL: API_BASE,
    credentials: "include",
    headers: {
      Cookie: cookie,
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    timeout: 8000,
    retry: 1,
    async onRequest({ options }) {
      if (options.method && options.method !== "GET") {
        await getCsrfToken();
      }
    },
    onRequestError({ error }) {
      console.error("Request failed:", error);
    },
    onResponseError({ response }) {
      console.error("API error:", response.status, response.statusText);
    },
  });

  const method = getMethod(event);
  const body =
    method !== "GET" ? await readBody(event).catch(() => ({})) : undefined;

  // ── FINAL CALL WITH TIMEOUT WRAPPER ──
  try {
    const res = (await Promise.race([
      api.raw(basePath, { method, body }),
      new Promise((_, reject) =>
        setTimeout(() => reject(new Error("Request timeout")), 7500)
      ),
    ])) as any;

    const data = res?._data?.data;
    if (!data) throw new Error("Empty response");

    return { floor: normalize(data) };
  } catch (err: any) {
    console.error("Floor API failed:", err.message);
    throw createError({
      statusCode: err.message.includes("timeout") ? 504 : 502,
      statusMessage: err.message || "Backend unreachable",
    });
  }
});

function normalize(f: any) {
  return {
    ...f,
    id: Number(f.id) || 0,
    building_id: Number(f.building_id) || 0,
    rooms: f.rooms ?? [],
  };
}
