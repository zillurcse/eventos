// server/middleware/error.ts
export default defineEventHandler((event) => {
  event.node.res.on("finish", () => {
    if (event.node.res.statusCode >= 500) {
      console.error(
        `[API ERROR] ${event.method} ${event.path} → ${event.node.res.statusCode}`
      );
    }
  });
});
