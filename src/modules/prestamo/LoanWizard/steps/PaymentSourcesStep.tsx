import React, { useMemo } from "react";
import type {
  DiscountConfiguration,
  LoanApplicationDraft,
} from "../../types/loan.types";
import DiscountCard from "../components/DiscountCard";
import type { IncomeType } from "@/types/IncomeType.ts";

interface Props {
  draft: LoanApplicationDraft;
  incomeTypes: IncomeType[];
  addDiscount: (d: Partial<DiscountConfiguration>) => void;
  updateDiscount: (
    tempId: string,
    patch: Partial<DiscountConfiguration>,
  ) => void;
  removeDiscount: (tempId: string) => void;
  isDetails: boolean;
  // index del descuento que se debe mostrar cuando se renderiza un paso dinámico
  detailIndex?: number;
}

const PaymentSourcesStep: React.FC<Props> = ({
  draft,
  incomeTypes,
  addDiscount,
  updateDiscount,
  removeDiscount,
  isDetails,
  detailIndex,
}) => {
  const isSelected = (incomeTypeId: number) =>
    draft.discounts.some((d) => d.incomeTypeId === incomeTypeId);

  function toggleIncomeType(it: IncomeType) {
    const selected = draft.discounts.find((d) => d.incomeTypeId === it.id);
    if (selected) {
      removeDiscount(selected.tempId);
      return;
    }

    addDiscount({
      incomeTypeId: it.id,
      incomeTypeName: it.name,
      isPeriodic: it.isPeriodic,
    });
  }

  //   const selectedCount = draft.discounts.length;

  //   const canGoToDetails = selectedCount > 0;

  const detailsComplete = useMemo(() => {
    if (draft.discounts.length === 0) return false;
    for (const d of draft.discounts) {
      if (!d.amount || d.amount <= 0) return false;
      if (d.isPeriodic && !d.lastDiscountDate) return false;
      if (!d.supportingDocument) return false;
    }
    return true;
  }, [draft.discounts]);

  // Fecha actual para determinar si un tipo de descuento ya no es aplicable
  const today = useMemo(() => new Date(), []);
  const currentMonth = today.getMonth() + 1; // 1-12
  const currentDay = today.getDate();

  const isExpired = (it: IncomeType) => {
    if (typeof it.paymentMonth === "number") {
      if (it.paymentMonth < currentMonth) return true;
      if (
        it.paymentMonth === currentMonth &&
        typeof it.paymentDay === "number" &&
        it.paymentDay < currentDay
      )
        return true;
    }
    return false;
  };

  // Mostrar sólo los tipos vigentes en la selección (ocultar los vencidos)
  const visibleIncomeTypes = useMemo(
    () => incomeTypes.filter((it) => !isExpired(it)),
    [incomeTypes, currentMonth, currentDay],
  );

  return (
    <div>
      <div className="mb-3">
        <div className="fw-semibold h5">Cómo desea realizar los descuentos</div>
        <div className="small text-muted">
          Siga las indicaciones para seleccionar y completar cada forma de
          descuento.
        </div>
      </div>

      {!isDetails && (
        <>
          <div className="mb-3">
            <div className="small text-muted">
              Primero seleccione los tipos de descuento; presione "Siguiente"
              para registrar monto y comprobante por cada tipo.
            </div>
          </div>

          <div className="row gy-3 mb-4">
            {visibleIncomeTypes.map((it) => {
              const selected = isSelected(it.id);
              return (
                <div className="col-12 col-md-6 col-xl-4 col-xxl-3" key={it.id}>
                  <button
                    type="button"
                    className={`card btn h-100 w-100 text-start p-3 ${selected ? "border-primary bg-primary-subtle" : ""}`}
                    onClick={() => toggleIncomeType(it)}
                    style={{ minHeight: 140 }}
                  >
                    <div className="card-body">
                      <div className="fw-semibold fs-6">{it.name}</div>
                      <div className="small text-muted">{it.description}</div>
                      {it.isPeriodic && (
                        <div className="badge bg-primary text-dark mt-2">
                          Periódico
                        </div>
                      )}
                    </div>
                  </button>
                </div>
              );
            })}
          </div>

          <div className="d-flex justify-content-end">
            <div className="small text-muted">
              Presione "Siguiente" en la parte inferior para completar montos y
              comprobantes.
            </div>
          </div>
        </>
      )}

      {isDetails && (
        <>
          <div className="mb-3">
            <div className="small text-muted">
              Complete los datos del descuento seleccionado: monto, comprobante
              (obligatorio) y, si corresponde, fecha del último descuento.
            </div>
          </div>

          {draft.discounts.length === 0 && (
            <div className="alert alert-secondary">
              Aún no hay formas de descuento seleccionadas.
            </div>
          )}

          {/* Si se pasó detailIndex mostramos sólo ese descuento (un paso por tipo). Si no, mostramos la lista por compatibilidad. */}
          {typeof detailIndex === "number"
            ? (() => {
                const d = draft.discounts[detailIndex];
                if (!d)
                  return (
                    <div className="alert alert-warning">
                      Descuento no encontrado.
                    </div>
                  );
                return (
                  <DiscountCard
                    key={d.tempId}
                    discount={d}
                    onChange={(updated) => updateDiscount(d.tempId, updated)}
                    onRemove={removeDiscount}
                    autoFocus={detailIndex === 0}
                  />
                );
              })()
            : draft.discounts.map((d, idx) => (
                <DiscountCard
                  key={d.tempId}
                  discount={d}
                  onChange={(updated) => updateDiscount(d.tempId, updated)}
                  onRemove={removeDiscount}
                  autoFocus={isDetails && idx === 0}
                />
              ))}

          <div className="mt-2">
            {!detailsComplete && (
              <div className="small text-danger mb-2">
                Complete los comprobantes y montos para poder continuar.
              </div>
            )}
            <div className="small text-muted">
              Use el botón "Anterior" para volver a la selección y "Siguiente"
              en la parte inferior para continuar.
            </div>
          </div>
        </>
      )}
    </div>
  );
};

export default PaymentSourcesStep;
