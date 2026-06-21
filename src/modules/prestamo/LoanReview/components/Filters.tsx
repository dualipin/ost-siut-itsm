import { LOAN_STATUS_OPTIONS } from "@/modules/prestamo/LoanReview/constants/filter.ts";
import { useFilters } from "../stores/filter.store";
import type { LoanStatus } from "../types/loan.type";

interface Props {
  onFilter: () => void;
}

export default function Filters({ onFilter }: Props) {
  const search = useFilters((s) => s.search);
  const status = useFilters((s) => s.status);
  const fromDate = useFilters((s) => s.fromDate);
  const toDate = useFilters((s) => s.toDate);

  return (
    <div className="accordion">
      <div className="accordion-item">
        <h2 className="accordion-header">
          <button
            className="accordion-button"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#collapseOne"
            aria-expanded="true"
            aria-controls="collapseOne"
          >
            Filtros de búsqueda
          </button>
        </h2>
        <div
          id="collapseOne"
          className="accordion-collapse collapse show p-2"
          data-bs-parent="#accordionExample"
        >
          <form
            className="row g-3 align-items-end"
            onSubmit={(e) => {
              e.preventDefault();
              onFilter();
            }}
          >
            <div className="col-12 col-lg-4">
              <label className="form-label fw-semibold small text-uppercase text-muted">
                Buscar
              </label>
              <input
                type="text"
                name="search"
                className="form-control rounded-3"
                value={search}
                onChange={(e) =>
                  useFilters.setState({ search: e.target.value })
                }
                placeholder="Folio, nombre o correo"
              />
            </div>
            <div className="col-12 col-lg-2">
              <label className="form-label fw-semibold small text-uppercase text-muted">
                Estatus
              </label>
              <select
                value={status ?? ""}
                name="status"
                className="form-select rounded-3"
                onChange={(e) =>
                  useFilters.setState({
                    status:
                      e.target.value === ""
                        ? undefined
                        : (e.target.value as LoanStatus),
                  })
                }
              >
                <option value="">Selecciona una opcion</option>
                {LOAN_STATUS_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-12 col-lg-2">
              <label className="form-label fw-semibold small text-uppercase text-muted">
                Desde
              </label>
              <input
                type="date"
                name="fecha_desde"
                className="form-control rounded-3"
                value={fromDate}
                onChange={(e) =>
                  useFilters.setState({ fromDate: e.target.value })
                }
              />
            </div>
            <div className="col-12 col-lg-2">
              <label className="form-label fw-semibold small text-uppercase text-muted">
                Hasta
              </label>
              <input
                type="date"
                name="fecha_hasta"
                className="form-control rounded-3"
                value={toDate}
                onChange={(e) =>
                  useFilters.setState({ toDate: e.target.value })
                }
              />
            </div>
            <div className="col-12 col-lg-2 d-flex gap-2">
              <button
                type="submit"
                className="btn btn-primary rounded-3 flex-grow-1"
              >
                <i className="bi bi-funnel me-1"></i>Filtrar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
