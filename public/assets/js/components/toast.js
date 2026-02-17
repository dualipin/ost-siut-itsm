function addAndShowToast(
  message,
  containerId = "registroToast",
  type = "success" | "error" | "advertencia",
) {
  const container = document.getElementById(containerId);
  const template = document.getElementById("toast-template");

  if (!container || !template) return;

  // 1. Clonamos el contenido del template (el nodo completo)
  const clone = document.importNode(template.content, true);
  const toastElement = clone.querySelector(".toast");

  // 2. Mapeo de títulos y estilos
  const config = {
    success: { title: "Éxito", class: "text-success" },
    error: { title: "Error", class: "text-danger" },
    advertencia: { title: "Advertencia", class: "text-warning" },
  };
  const { title, class: textClass } = config[type] || {
    title: "Aviso",
    class: "",
  };

  // 3. Llenamos los datos manipulando el DOM directamente
  const headerStrong = toastElement.querySelector(".toast-header strong");
  headerStrong.textContent = title;
  if (textClass) headerStrong.classList.add(textClass);

  toastElement.querySelector(".toast-body").textContent = message;

  // 4. Lo inyectamos en el contenedor y lo activamos
  container.appendChild(toastElement);

  const toast = new bootstrap.Toast(toastElement);
  toast.show();

  // 5. Autolimpieza del DOM al cerrarse
  toastElement.addEventListener("hidden.bs.toast", () => toastElement.remove());
}
