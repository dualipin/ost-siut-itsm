import Filters from "@/modules/prestamo/LoanReview/components/Filters.tsx";
import LoanReviewTable from "@/modules/prestamo/LoanReview/components/LoanReviewTable.tsx";
import { useLoans } from "./hooks/useLoans";

export default function LoanReview() {
  const { data, isLoading, isError, error } = useLoans();

  function onFilter() {
    // Filters are in Zustand store; React Query refetches automatically via query key
  }

  return (
    <>
      <Filters onFilter={onFilter} />

      {isLoading && (
        <div className="text-center py-5">
          <div className="spinner-border text-primary" role="status">
            <span className="visually-hidden">Cargando...</span>
          </div>
        </div>
      )}

      {isError && (
        <div className="alert alert-danger rounded-3 mt-3">
          <i className="bi bi-exclamation-triangle me-2"></i>
          {error instanceof Error ? error.message : "Error al cargar préstamos"}
        </div>
      )}

      {data && (
        <>
          <div className="d-flex gap-2 mt-3 mb-3 flex-wrap">
            {data.summary.solicitado > 0 && (
              <span className="badge bg-warning-subtle text-warning fs-6 px-3 py-2 rounded-pill">
                Solicitados: {data.summary.solicitado}
              </span>
            )}
            {data.summary.en_espera > 0 && (
              <span className="badge bg-dark-subtle text-secondary fs-6 px-3 py-2 rounded-pill">
                En espera: {data.summary.en_espera}
              </span>
            )}
            {data.summary.con_docs_pendientes > 0 && (
              <span className="badge bg-danger-subtle text-danger fs-6 px-3 py-2 rounded-pill">
                <i className="bi bi-exclamation-circle me-1"></i>
                Con documentos pendientes: {data.summary.con_docs_pendientes}
              </span>
            )}
            <span className="badge bg-light text-dark fs-6 px-3 py-2 rounded-pill">
              Total: {data.summary.total}
            </span>
          </div>

          <LoanReviewTable loans={data.data} />
        </>
      )}
    </>
  );
}
