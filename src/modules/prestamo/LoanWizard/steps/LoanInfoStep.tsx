import type { LoanApplicationDraft } from "../types/loan.types";
import { WORKER_PROFILES } from "../constants/profiles";

interface Props {
  draft: LoanApplicationDraft;
  onChange: (patch: Partial<LoanApplicationDraft>) => void;
}

export default function LoanInfoStep({ draft, onChange }: Props) {
  return (
    <div>
      <div className="mb-3 container overflow-hidden">
        <label className="form-label fw-medium mb-3 h5">
          Tipo de trabajador solicitante
        </label>
        {/* Usamos gy-3 para espaciado vertical si saltan a otra línea en pantallas chicas */}
        <div className="row gx-3 gy-3">
          {WORKER_PROFILES.map((w) => (
            // La columna maneja el ancho (2 columnas en pantallas medianas/grandes, 1 en móviles)
            <div key={w.key} className="col-12 col-md-6">
              <button
                type="button"
                // w-100 hace que el botón ocupe todo el ancho de su columna correspondiente
                className={`btn w-100 h-100 p-3 text-start ${
                  draft.workerType === w.key
                    ? "border-primary bg-primary-subtle"
                    : "border"
                }`}
                onClick={() =>
                  onChange({ workerType: w.key, interestRate: w.interestRate })
                }
              >
                <div className="p-2 text-center">
                  <div className="fw-semibold fs-6 text-dark">{w.label}</div>
                  <div className="text-muted small">
                    Tasa: {w.interestRate}%
                  </div>
                </div>
              </button>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
