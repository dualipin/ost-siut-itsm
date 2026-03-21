document.addEventListener("DOMContentLoaded", () => {
  const modalElement = document.getElementById("qrShareModal");
  if (!modalElement) {
    return;
  }

  const urlInput = document.getElementById("qrShareUrl");
  const generateButton = document.getElementById("qrGenerateButton");
  const downloadButton = document.getElementById("qrDownloadButton");
  const copyLinkButton = document.getElementById("qrCopyLinkButton");
  const openLinkButton = document.getElementById("qrOpenLinkButton");
  const currentPageButton = document.getElementById("qrUseCurrentPage");
  const previewLegend = document.getElementById("qrPreviewLegend");
  const previewCanvas = document.getElementById("qrShareCanvas");
  const presetButtons = modalElement.querySelectorAll("[data-qr-preset]");

  const brandLogoUrl = "/assets/images/logo.webp";
  const brandFooterText = "SIUT ITSM";

  const composition = {
    width: 900,
    height: 1020,
    footerHeight: 130,
    qrSize: 760,
    qrY: 50,
  };

  let currentLabel = "enlace";

  function showToast(type, message) {
    if (window.Alpine && typeof Alpine.store === "function") {
      const toastStore = Alpine.store("toast");
      if (toastStore && typeof toastStore.show === "function") {
        toastStore.show({ type, message });
        return;
      }
    }

    if (type === "error") {
      window.alert(message);
    }
  }

  function getCssVar(name, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return value || fallback;
  }

  function sanitizeFilename(text) {
    return text
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "") || "enlace";
  }

  function normalizeUrl(rawValue) {
    const value = rawValue.trim();
    if (value === "") {
      return "";
    }

    try {
      return new URL(value).toString();
    } catch (error) {
      try {
        return new URL(value, window.location.origin).toString();
      } catch (innerError) {
        return "";
      }
    }
  }

  function loadImage(src) {
    return new Promise((resolve, reject) => {
      const image = new Image();
      image.onload = () => resolve(image);
      image.onerror = () => reject(new Error("No fue posible cargar la imagen del logo"));
      image.src = src;
    });
  }

  async function buildQrCanvas(url) {
    if (!window.QRCode || typeof window.QRCode.toCanvas !== "function") {
      throw new Error("No se pudo cargar la libreria de QR");
    }

    const primaryColor = getCssVar("--bs-primary", "#611232");
    const bodyBg = getCssVar("--bs-body-bg", "#ffffff");
    const footerTextColor = getCssVar("--bs-primary-text", "#ffffff");
    const borderColor = getCssVar("--bs-border-color", "#dee2e6");

    const outputCanvas = document.createElement("canvas");
    outputCanvas.width = composition.width;
    outputCanvas.height = composition.height;

    const context = outputCanvas.getContext("2d");
    if (!context) {
      throw new Error("No se pudo inicializar el lienzo de QR");
    }

    context.fillStyle = bodyBg;
    context.fillRect(0, 0, composition.width, composition.height);

    const qrCanvas = document.createElement("canvas");
    await window.QRCode.toCanvas(qrCanvas, url, {
      width: composition.qrSize,
      margin: 1,
      errorCorrectionLevel: "H",
      color: {
        dark: primaryColor,
        light: "#ffffff",
      },
    });

    const qrX = (composition.width - composition.qrSize) / 2;
    context.fillStyle = "#ffffff";
    context.fillRect(qrX - 12, composition.qrY - 12, composition.qrSize + 24, composition.qrSize + 24);

    context.strokeStyle = borderColor;
    context.lineWidth = 2;
    context.strokeRect(qrX - 12, composition.qrY - 12, composition.qrSize + 24, composition.qrSize + 24);

    context.drawImage(qrCanvas, qrX, composition.qrY, composition.qrSize, composition.qrSize);

    const logo = await loadImage(brandLogoUrl);
    const logoSize = Math.round(composition.qrSize * 0.22);
    const logoX = (composition.width - logoSize) / 2;
    const logoY = composition.qrY + (composition.qrSize - logoSize) / 2;

    context.beginPath();
    context.arc(composition.width / 2, logoY + logoSize / 2, logoSize * 0.6, 0, 2 * Math.PI);
    context.fillStyle = "#ffffff";
    context.fill();

    context.drawImage(logo, logoX, logoY, logoSize, logoSize);

    const footerY = composition.height - composition.footerHeight;
    context.fillStyle = primaryColor;
    context.fillRect(0, footerY, composition.width, composition.footerHeight);

    context.fillStyle = footerTextColor;
    context.font = "700 46px Spline Sans, Inter, sans-serif";
    context.textAlign = "center";
    context.textBaseline = "middle";
    context.fillText(brandFooterText, composition.width / 2, footerY + composition.footerHeight / 2);

    return outputCanvas;
  }

  async function renderQr() {
    const normalized = normalizeUrl(urlInput.value);
    if (!normalized) {
      showToast("error", "Ingresa una URL valida para generar el codigo QR.");
      return;
    }

    urlInput.value = normalized;
    openLinkButton.href = normalized;

    try {
      const generated = await buildQrCanvas(normalized);
      previewCanvas.width = generated.width;
      previewCanvas.height = generated.height;

      const previewContext = previewCanvas.getContext("2d");
      if (!previewContext) {
        throw new Error("No se pudo actualizar la vista previa");
      }

      previewContext.clearRect(0, 0, previewCanvas.width, previewCanvas.height);
      previewContext.drawImage(generated, 0, 0);

      previewLegend.textContent = `${brandFooterText} | ${currentLabel}`;
      showToast("success", "QR generado correctamente.");
    } catch (error) {
      showToast("error", error instanceof Error ? error.message : "No se pudo generar el QR");
    }
  }

  function setUrl(value, label) {
    urlInput.value = value;
    if (label) {
      currentLabel = label;
    }
    renderQr();
  }

  presetButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const url = button.getAttribute("data-qr-url") || "";
      const label = button.getAttribute("data-qr-label") || "enlace";
      setUrl(url, label);
    });
  });

  currentPageButton.addEventListener("click", () => {
    setUrl(window.location.href, "pagina-actual");
  });

  generateButton.addEventListener("click", () => {
    renderQr();
  });

  urlInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      renderQr();
    }
  });

  copyLinkButton.addEventListener("click", async () => {
    const normalized = normalizeUrl(urlInput.value);
    if (!normalized) {
      showToast("error", "No hay un enlace valido para copiar.");
      return;
    }

    try {
      await navigator.clipboard.writeText(normalized);
      showToast("success", "Enlace copiado al portapapeles.");
    } catch (error) {
      showToast("error", "No se pudo copiar el enlace.");
    }
  });

  downloadButton.addEventListener("click", () => {
    if (previewCanvas.width === 0 || previewCanvas.height === 0) {
      showToast("error", "Primero genera un QR antes de descargar.");
      return;
    }

    const anchor = document.createElement("a");
    const filename = `siut-itsm-qr-${sanitizeFilename(currentLabel)}.png`;
    anchor.href = previewCanvas.toDataURL("image/png");
    anchor.download = filename;
    anchor.click();
  });

  modalElement.addEventListener("shown.bs.modal", () => {
    if (!urlInput.value) {
      setUrl(window.location.href, "pagina-actual");
      return;
    }

    renderQr();
  });
});
