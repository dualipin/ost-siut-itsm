class TablaGestionada extends HTMLElement {
  constructor() {
    super();
    this.currentPage = 1;
    this.rowsPerPage = parseInt(this.getAttribute("rows-per-page")) || 10;
  }

  connectedCallback() {
    // Esperar a que los elementos internos (de Latte) se carguen
    setTimeout(() => {
      this.table = this.querySelector("table");
      this.tbody = this.querySelector("tbody");
      this.rows = Array.from(
        this.tbody.querySelectorAll("tr:not(.no-results)"),
      );
      this.buscador = document.getElementById(
        this.getAttribute("search-input"),
      );
      this.paginationContainer = this.querySelector(".pagination");

      this.init();
    }, 0);
  }

  init() {
    if (this.buscador) {
      this.buscador.addEventListener("input", () => this.filtrar());
    }
    this.render();
  }

  filtrar() {
    const query = this.buscador.value.toLowerCase().trim();
    this.visibleRows = this.rows.filter((row) => {
      return row.textContent.toLowerCase().includes(query);
    });

    this.currentPage = 1;
    this.render();
  }

  render() {
    const dataToDisplay = this.visibleRows || this.rows;
    const totalPages = Math.ceil(dataToDisplay.length / this.rowsPerPage);

    // Ocultar todas
    this.rows.forEach((r) => (r.style.display = "none"));

    // Mostrar solo las de la página actual
    const start = (this.currentPage - 1) * this.rowsPerPage;
    const end = start + this.rowsPerPage;

    dataToDisplay.slice(start, end).forEach((r) => (r.style.display = ""));

    this.actualizarPaginacion(totalPages);
  }

  actualizarPaginacion(total) {
    if (!this.paginationContainer) return;
    this.paginationContainer.innerHTML = "";

    for (let i = 1; i <= total; i++) {
      const li = document.createElement("li");
      li.className = `page-item ${i === this.currentPage ? "active" : ""}`;
      li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
      li.onclick = (e) => {
        e.preventDefault();
        this.currentPage = i;
        this.render();
      };
      this.paginationContainer.appendChild(li);
    }
  }
}

// Registrar el componente
customElements.define("tabla-gestionada", TablaGestionada);
