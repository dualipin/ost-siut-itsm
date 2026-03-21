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
  const qrLibraryFallbacks = [
    "https://cdn.jsdelivr.net/npm/qr-code-styling@1.9.2/lib/qr-code-styling.js",
    "https://unpkg.com/qr-code-styling@1.9.2/lib/qr-code-styling.js",
  ];

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

  function loadScript(src) {
    return new Promise((resolve, reject) => {
      const existing = document.querySelector(`script[src="${src}"]`);
      if (existing) {
        if (window.QRCodeStyling) {
          resolve();
          return;
        }

        existing.addEventListener("load", () => resolve(), { once: true });
        existing.addEventListener("error", () => reject(new Error("No se pudo cargar script")), { once: true });
        return;
      }

      const script = document.createElement("script");
      script.src = src;
      script.async = true;
      script.onload = () => resolve();
      script.onerror = () => reject(new Error("No se pudo cargar script"));
      document.head.appendChild(script);
    });
  }

  async function ensureQrStylingLoaded() {
    if (window.QRCodeStyling) {
      return;
    }

    for (const source of qrLibraryFallbacks) {
      try {
        await loadScript(source);
        if (window.QRCodeStyling) {
          return;
        }
      } catch (error) {
      }
    }

    throw new Error("No se pudo cargar la libreria qr-code-styling");
  }

  function loadImageFromBlob(blob) {
    return new Promise((resolve, reject) => {
      const objectUrl = URL.createObjectURL(blob);
      const image = new Image();
      image.onload = () => {
        URL.revokeObjectURL(objectUrl);
        resolve(image);
      };
      image.onerror = () => {
        URL.revokeObjectURL(objectUrl);
        reject(new Error("No se pudo procesar la imagen QR"));
      };
      image.src = objectUrl;
    });
  }

  async function buildQrCanvas(url) {
    await ensureQrStylingLoaded();
    if (!window.QRCodeStyling) {
      throw new Error("No se pudo cargar la libreria qr-code-styling");
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

    const qrGenerator = new window.QRCodeStyling({
      width: composition.qrSize,
      height: composition.qrSize,
      type: "canvas",
      data: url,
      image: new URL(brandLogoUrl, window.location.origin).toString(),
      qrOptions: {
        errorCorrectionLevel: "H",
      },
      imageOptions: {
        crossOrigin: "anonymous",
        margin: 6,
        imageSize: 0.24,
      },
      dotsOptions: {
        color: primaryColor,
        type: "rounded",
      },
      cornersSquareOptions: {
        color: primaryColor,
        type: "extra-rounded",
      },
      cornersDotOptions: {
        color: primaryColor,
        type: "dot",
      },
      backgroundOptions: {
        color: "#ffffff",
      },
    });

    const qrBlob = await qrGenerator.getRawData("png");
    if (!qrBlob) {
      throw new Error("No se pudo generar la imagen QR");
    }
    const qrImage = await loadImageFromBlob(qrBlob);

    const qrX = (composition.width - composition.qrSize) / 2;
    context.fillStyle = "#ffffff";
    context.fillRect(qrX - 12, composition.qrY - 12, composition.qrSize + 24, composition.qrSize + 24);

    context.strokeStyle = borderColor;
    context.lineWidth = 2;
    context.strokeRect(qrX - 12, composition.qrY - 12, composition.qrSize + 24, composition.qrSize + 24);

    context.drawImage(qrImage, qrX, composition.qrY, composition.qrSize, composition.qrSize);

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
