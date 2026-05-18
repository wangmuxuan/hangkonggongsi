(() => {
  const copyButtons = document.querySelectorAll("[data-copy]");
  for (const btn of copyButtons) {
    btn.addEventListener("click", async () => {
      const text = btn.getAttribute("data-copy") || "";
      try {
        await navigator.clipboard.writeText(text);
        const prev = btn.textContent;
        btn.textContent = "已复制";
        setTimeout(() => (btn.textContent = prev || "复制链接"), 1200);
      } catch {
        // fallback
        const ta = document.createElement("textarea");
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand("copy");
        document.body.removeChild(ta);
      }
    });
  }

  const qrs = document.querySelectorAll(".qr[data-qr]");
  for (const el of qrs) {
    const text = el.getAttribute("data-qr") || "";
    if (!text) continue;
    try {
      // eslint-disable-next-line no-undef
      if (typeof QRCode === "function") {
        // eslint-disable-next-line no-undef
        new QRCode(el, { text, width: 176, height: 176, correctLevel: QRCode.CorrectLevel.M });
        continue;
      }
    } catch {
      // ignore and fallback below
    }

    // fallback: simple image QR (uses public API; only when local QR lib not available)
    const img = document.createElement("img");
    img.width = 176;
    img.height = 176;
    img.alt = "QR";
    img.referrerPolicy = "no-referrer";
    img.src = `https://api.qrserver.com/v1/create-qr-code/?size=176x176&data=${encodeURIComponent(text)}`;
    el.appendChild(img);
  }

  const dlButtons = document.querySelectorAll("[data-download-qr]");
  for (const btn of dlButtons) {
    btn.addEventListener("click", () => {
      const id = btn.getAttribute("data-download-qr");
      if (!id) return;
      const qrEl = document.querySelector(`.qr[data-qr-id='${CSS.escape(id)}']`);
      if (!qrEl) return;
      const canvas = qrEl.querySelector("canvas");
      if (!canvas) return;
      const a = document.createElement("a");
      a.download = `qrcode-${id}.png`;
      a.href = canvas.toDataURL("image/png");
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    });
  }
})();
