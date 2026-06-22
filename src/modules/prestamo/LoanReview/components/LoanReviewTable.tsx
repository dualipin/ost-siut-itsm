import type { Loan } from "@/modules/prestamo/LoanReview/types/loan.type";
import { useFilters } from "@/modules/prestamo/LoanReview/stores/filter.store";

interface Props {
  loans: Loan[];
}

function StatusBadge({ label, badge }: { label: string; badge: string }) {
  return (
    <span className={`badge ${badge} px-3 py-2 rounded-pill`}>{label}</span>
  );
}

function DaysBadge({ days }: { days: number }) {
  if (days >= 7)
    return <span className="fw-semibold text-danger">{days} días</span>;
  if (days >= 3)
    return <span className="fw-semibold text-warning">{days} días</span>;
  return <span className="text-muted">{days} días</span>;
}

function DocsCell({
  pendingDocs,
  totalConfigs,
}: {
  pendingDocs: number;
  totalConfigs: number;
}) {
  if (totalConfigs > 0) {
    if (pendingDocs > 0) {
      return (
        <span className="badge bg-danger-subtle text-danger px-2 py-1 rounded-pill">
          <i className="bi bi-exclamation-circle me-1"></i>
          {pendingDocs} pendiente(s)
        </span>
      );
    }
    return (
      <span className="badge bg-success-subtle text-success px-2 py-1 rounded-pill">
        <i className="bi bi-check-circle me-1"></i>Completos
      </span>
    );
  }
  return <span className="text-muted small">Sin configurar</span>;
}

export default function LoanReviewTable({ loans }: Props) {
  const orderBy = useFilters((s) => s.orderBy);
  const orderDir = useFilters((s) => s.orderDir);

  return (
    <div className="table-responsive">
      <table className="table table-hover align-middle mb-0">
        <thead className="bg-light text-uppercase small text-muted">
          <tr>
            <th
              className="px-4 py-3 border-0 cursor-pointer"
              onClick={() =>
                useFilters.setState((s) => ({
                  orderBy: "folio",
                  orderDir:
                    s.orderBy === "folio"
                      ? s.orderDir === "asc"
                        ? "desc"
                        : "asc"
                      : "desc",
                }))
              }
              style={{ cursor: "pointer" }}
            >
              <strong>Folio</strong>
              <i
                className={`bi ms-1 ${
                  orderBy === "folio"
                    ? orderDir === "asc"
                      ? "bi-sort-up"
                      : "bi-sort-down"
                    : "bi-sort-down"
                }`}
              ></i>
              <div className="small text-muted fw-normal">Fecha</div>
            </th>
            <th className="py-3 border-0">Solicitante</th>
            <th className="py-3 border-0">Estatus</th>
            <th className="py-3 border-0">Monto solicitado</th>
            <th className="py-3 border-0">Plazo</th>
            <th className="py-3 border-0">Documentos</th>
            <th className="py-3 border-0">Días en espera</th>
            <th className="py-3 border-0 text-end pe-4">Acción</th>
          </tr>
        </thead>
        <tbody>
          {loans.length === 0 && (
            <tr>
              <td colSpan={8} className="text-center py-5 text-muted">
                <i className="bi bi-inbox display-6 d-block mb-2 opacity-25"></i>
                No hay solicitudes pendientes de revisión.
              </td>
            </tr>
          )}
          {loans.map((loan) => (
            <tr key={loan.loan_id}>
              <td className="px-4 py-3">
                <div className="fw-semibold text-dark">{loan.folio}</div>
                <div className="small text-muted">
                  {loan.application_date_label}
                </div>
                {loan.is_restructuring && (
                  <span className="badge bg-primary-subtle text-primary rounded-pill small mt-1">
                    <i className="bi bi-arrow-repeat me-1"></i>
                    Reestructuración
                  </span>
                )}
              </td>
              <td className="py-3">
                <div className="fw-semibold text-dark">
                  {loan.borrower_name}
                </div>
                <div className="small text-muted">{loan.borrower_email}</div>
                {loan.borrower_department && (
                  <div className="small text-muted">
                    {loan.borrower_department}
                  </div>
                )}
              </td>
              <td className="py-3">
                <StatusBadge label={loan.status_label} badge={loan.status_badge} />
              </td>
              <td className="py-3">
                <div className="fw-semibold">
                  {loan.requested_amount_label}
                </div>
                <div className="small text-muted">
                  {loan.applied_interest_rate}% anual
                </div>
              </td>
              <td className="py-3">
                {loan.term_fortnights ? (
                  <div className="fw-semibold">
                    {loan.term_fortnights} quincenas
                  </div>
                ) : (
                  <div className="text-muted">&mdash;</div>
                )}
              </td>
              <td className="py-3">
                <DocsCell
                  pendingDocs={loan.pending_docs}
                  totalConfigs={loan.total_configs}
                />
              </td>
              <td className="py-3">
                <DaysBadge days={loan.days_elapsed} />
              </td>
              <td className="py-3 text-end pe-4">
                <a
                  href={`/portal/prestamos/detalle.php?id=${loan.loan_id}`}
                  className="btn btn-sm btn-primary rounded-3 px-3"
                >
                  <i className="bi bi-search me-1"></i>Revisar
                </a>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
