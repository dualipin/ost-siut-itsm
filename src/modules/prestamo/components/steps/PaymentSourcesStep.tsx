import React, { useMemo } from "react";
import type { DiscountConfiguration } from "@/types/DiscountConfiguration";
import DiscountCard from "../DiscountCard";
import type { IncomeType } from "@/types/IncomeType";

interface Props {
  discounts: DiscountConfiguration[];
  incomeTypes: IncomeType[];
  addDiscount: (d: Partial<DiscountConfiguration>) => void;
  updateDiscount: (tempId: string, patch: Partial<DiscountConfiguration>) => void;
  removeDiscount: (tempId: string) => void;
  isDetails: boolean;
  detailIndex?: number;
  requireDocument?: boolean;
}

const PaymentSourcesStep: React.FC<Props> = ({
  discounts,
  incomeTypes,
  addDiscount,
  updateDiscount,
  removeDiscount,
  isDetails,
  detailIndex,
  requireDocument = true,
}) => {
  const isSelected = (incomeTypeId: number) =>
    discounts.some((d) => d.incomeTypeId === incomeTypeId);

  function toggleIncomeType(it: IncomeType) {
    const selected = discounts.find((d) => d.incomeTypeId === it.id);
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

  const detailsComplete = useMemo(() => {
    if (discounts.length === 0) return false;
    for (const d of discounts) {
      if (!d.amount || d.amount <= 0) return false;
      if (d.isPeriodic && !d.lastDiscountDate) return false;
      if (requireDocument && !d.supportingDocument) return false;
    }
    return true;
  }, [discounts, requireDocument]);

  const today = useMemo(() => new Date(), []);
  const currentMonth = today.getMonth() + 1;
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
              para registrar{requireDocument ? " monto y comprobante" : " monto"} por cada tipo.
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
              Presione "Siguiente" en la parte inferior para completar{requireDocument ? " montos y comprobantes" : " montos"}.
            </div>
          </div>
        </>
      )}

      {isDetails && (
        <>
          <div className="mb-3">
            <div className="small text-muted">
              Complete los datos del descuento seleccionado: monto
              {requireDocument ? ", comprobante (obligatorio)" : ""} y, si corresponde, fecha del último descuento.
            </div>
          </div>

          {discounts.length === 0 && (
            <div className="alert alert-secondary">
              Aún no hay formas de descuento seleccionadas.
            </div>
          )}

          {typeof detailIndex === "number"
            ? (() => {
                const d = discounts[detailIndex];
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
                    requireDocument={requireDocument}
                  />
                );
              })()
            : discounts.map((d, idx) => (
                <DiscountCard
                  key={d.tempId}
                  discount={d}
                  onChange={(updated) => updateDiscount(d.tempId, updated)}
                  onRemove={removeDiscount}
                  autoFocus={isDetails && idx === 0}
                  requireDocument={requireDocument}
                />
              ))}

          <div className="mt-2">
            {!detailsComplete && (
              <div className="small text-danger mb-2">
                Complete los{requireDocument ? " comprobantes y" : ""} montos para poder continuar.
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
