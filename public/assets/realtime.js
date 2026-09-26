/**
 * Cliente SSE para una SPA. Usa fetch porque EventSource nativo no admite
 * Authorization Bearer. No guarda ni envía el token en la URL.
 * onEvent(nombre, datos) recibe snapshots, reloj y avisos propios.
 */
export async function watchAuction({ baseUrl, id, token, onEvent, signal }) {
  let cursor = "";
  let version = -1;
  const seen = new Set();
  while (!signal?.aborted) {
    try {
      const headers = { Accept: "text/event-stream", Authorization: `Bearer ${token}` };
      if (cursor) headers["Last-Event-ID"] = cursor;
      const response = await fetch(`${baseUrl}/api/subastas/${id}/eventos`, { headers, signal });
      if (response.status === 401 || response.status === 403) throw new Error("SESSION_EXPIRED");
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
      let buffer = "";
      try {
        for (;;) {
          const { done, value } = await reader.read();
          if (done) break;
          buffer += value.replaceAll("\r\n", "\n");
          let split;
          while ((split = buffer.indexOf("\n\n")) !== -1) {
            const block = buffer.slice(0, split); buffer = buffer.slice(split + 2);
            let event = "message", data = [], eventId;
            for (const line of block.split("\n")) {
              if (line.startsWith("event:")) event = line.slice(6).trim();
              if (line.startsWith("data:")) data.push(line.slice(5).trimStart());
              if (line.startsWith("id:")) eventId = line.slice(3).trim();
            }
            if (eventId) cursor = eventId;
            if (!data.length) continue;
            const payload = JSON.parse(data.join("\n"));
            if (payload.event_id) {
              if (seen.has(payload.event_id)) continue;
              seen.add(payload.event_id);
              if (seen.size > 512) seen.delete(seen.values().next().value);
            }
            if (event === "SesionFinalizada") throw new Error("SESSION_EXPIRED");
            // Las notificaciones siempre se entregan; la vista ignora estados antiguos.
            if (payload.version !== undefined && event !== "NotificacionCreada") {
              if (payload.version < version) continue;
              version = payload.version;
            }
            onEvent(event, payload);
          }
        }
      } finally { await reader.cancel().catch(() => {}); reader.releaseLock(); }
    } catch (error) {
      if (signal?.aborted) return;
      if (error.message === "SESSION_EXPIRED") throw error;
      onEvent("ConexionInterrumpida", { mensaje: "Reconectando…" });
    }
    if (!signal?.aborted) await new Promise(resolve => setTimeout(resolve, 1000));
  }
}
