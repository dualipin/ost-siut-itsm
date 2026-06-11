import type { DiscountConfiguration } from "../types/loan.types";

interface Props {
  discount: DiscountConfiguration;
  onChange: (d: DiscountConfiguration) => void;
  onRemove: (tempId: string) => void;
  autoFocus?: boolean;
}

export default function DiscountCard({
  discount,
  onChange,
  onRemove,
  autoFocus,
}: Props) {
  const MIN_DAYS_FROM_TODAY = 21;
  const now = new Date();
  const minDate = new Date(now);
  minDate.setDate(minDate.getDate() + MIN_DAYS_FROM_TODAY);
  const minDateStr = `${minDate.getFullYear()}-${String(minDate.getMonth() + 1).padStart(2, "0")}-${String(minDate.getDate()).padStart(2, "0")}`;
  const maxDate = `${now.getFullYear()}-11-30`;

  const onAmount = (v: string) => onChange({ ...discount, amount: Number(v) });
  const onDate = (v: string) => onChange({ ...discount, lastDiscountDate: v });
  const onFile = (f?: File | null) =>
    onChange({ ...discount, supportingDocument: f ?? null });

  return (
    <div className="card mb-3">
      <div className="card-body">
        <div className="d-flex justify-content-between mb-2">
          <div>
            <h6 className="card-title mb-0">
              {discount.incomeTypeName ?? "Descuento"}
            </h6>
            {discount.isPeriodic && (
              <div className="small text-muted">Periódico</div>
            )}
          </div>
          <button
            type="button"
            className="btn btn-sm btn-link text-danger"
            onClick={() => onRemove(discount.tempId)}
          >
            Eliminar
          </button>
        </div>

        <div className="mb-2">
          <label className="form-label">Monto</label>
          <input
            className="form-control"
            type="number"
            value={discount.amount ?? ""}
            onChange={(e) => onAmount(e.target.value)}
            autoFocus={!!autoFocus}
          />
        </div>

        {discount.isPeriodic && (
          <div className="mb-2">
            <label className="form-label">Fecha del último descuento</label>
            <input
              className="form-control"
              type="date"
              value={discount.lastDiscountDate ?? ""}
              min={minDateStr}
              max={maxDate}
              onChange={(e) => onDate(e.target.value)}
            />
          </div>
        )}

        <div className="mb-0">
          <label className="form-label">Comprobante (obligatorio)</label>
          <input
            className="form-control"
            type="file"
            onChange={(e) => onFile(e.target.files?.[0] ?? null)}
          />
          {discount.supportingDocument ? (
            <div className="small text-success mt-1">
              {discount.supportingDocument.name}
            </div>
          ) : (
            <div className="small text-danger mt-1">
              <i className="bi bi-exclamation-triangle me-2" />
              Falta comprobante
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
