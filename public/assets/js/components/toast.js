/**
 * @typedef ToastType
 * @property {number} id
 * @property {"success" | "danger" | "warning"} type
 * @property {string} message
 */

document.addEventListener("alpine:init", () => {
  Alpine.store("toast", {
    /** @type {ToastType[]} */
    list: [],
    /** @param {ToastType} props */
    show(props) {
      const id = Date.now();
      const { message, type } = props;

      // Use assignment to ensure reactivity instead of push
      this.list = [...this.list, { id, message, type }];

      // Wait for Alpine to render the template before showing the toast
      setTimeout(async () => {
        await Alpine.nextTick(() => {
          const toastEl = document.getElementById(`toast-${id}`);

          if (!toastEl) {
            // Debug: log all toast elements in DOM
            const allToasts = document.querySelectorAll('[id^="toast-"]');
            return;
          }

          const toast = new bootstrap.Toast(toastEl, { delay: 5000 });
          toast.show();

          toastEl.addEventListener("hidden.bs.toast", () => {
            this.list = this.list.filter((t) => t.id !== id);
            toastEl.remove();
          });
        });
      }, 50);
    },
  });
});
