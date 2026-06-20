import { useMemo, useState } from "react";
import Stepper from "@/commons/components/Stepper.tsx";
import LoanInfoStep from "../../prestamo/components/steps/LoanInfoStep";
import PaymentSourcesStep from "../../prestamo/components/steps/PaymentSourcesStep";
import type { WorkerType } from "../../prestamo/types/loan.types";
import { useLoanWizard, obtenerOpcionesFechasPago } from "./hooks/useLoanWizard";
import ReviewStep from "./steps/ReviewStep";
import type { SimulationRequest } from "./types/loan.types";

function formatFriendlyDate(dateStr: string) {
  if (!dateStr) return "";
  const date = new Date(dateStr + "T00:00:00");
  return date.toLocaleDateString("es-MX", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

export default function LoanSimulatorWizard() {
  const {
    draft,
    setDraft,
    incomeTypes,
    addDiscount,
    updateDiscount,
    removeDiscount,
    totalSolicitado,
    mesesEstimados,
    diasEstimados,
    formatCurrency,
    localizeDate,
  } = useLoanWizard();

  const [step, setStep] = useState(1);

  const discountStepCount = draft.descuentos.length;
  const baseSteps = 3; // Step 1: Start date, Step 2: Worker type, Step 3: Discount categories
  const totalSteps = baseSteps + discountStepCount + 1; // +1 for review

  const canNext = useMemo(() => {
    if (step === 1) {
      return !!draft.fechaOtorgamiento;
    }
    if (step === 2) {
      return !!(draft.workerType && draft.interestRate != null);
    }
    if (step === 3) {
      return draft.descuentos.length > 0;
    }
    if (step >= 4 && step <= 3 + discountStepCount) {
      const idx = step - 4;
      const d = draft.descuentos[idx];
      if (!d) return false;
      if (!d.amount || d.amount <= 0) return false;
      if (d.isPeriodic && !d.lastDiscountDate) return false;
      return true;
    }
    return true;
  }, [step, draft, discountStepCount]);

  const stepTitle = useMemo(() => {
    if (step === 1) return "Fecha de inicio";
    if (step === 2) return "Perfil del trabajador";
    if (step === 3) return "Seleccionar descuentos";
    if (step >= 4 && step <= 3 + discountStepCount) {
      return `Detalle: ${draft.descuentos[step - 4]?.incomeTypeName ?? "Descuento"}`;
    }
    if (step === 4 + discountStepCount) return "Resumen y Simulación";
    return "";
  }, [step, discountStepCount, draft.descuentos]);

  function next() {
    setStep((s) => Math.min(totalSteps, s + 1));
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function prev() {
    setStep((s) => Math.max(1, s - 1));
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  const requestData = useMemo<SimulationRequest>(() => {
    return {
      tasaInteresMensual: draft.interestRate || 0,
      fechaOtorgamiento: draft.fechaOtorgamiento,
      montoPrestamo: totalSolicitado,
      mesesPagar: mesesEstimados,
      diasAdicionales: diasEstimados,
      descuentos: draft.descuentos.map((d) => ({
        monto: d.amount || 0,
        tipoId: d.incomeTypeId || 0,
        cantidad: d.isPeriodic
          ? (() => {
              const cat = incomeTypes.find((c) => c.id === d.incomeTypeId);
              if (!cat) return 1;
              const opciones = obtenerOpcionesFechasPago(cat, draft.fechaOtorgamiento);
              const idx = opciones.findIndex((op) => op.value === d.lastDiscountDate);
              return idx >= 0 ? idx + 1 : 1;
            })()
          : 1,
        fechaPago: d.lastDiscountDate,
      })),
    };
  }, [draft, totalSolicitado, mesesEstimados, diasEstimados, incomeTypes]);

  return (
    <div>
      <Stepper
        current={step}
        total={Math.max(1, totalSteps)}
        title={stepTitle}
      />

      <div className="card border-0 shadow-sm rounded-4 mt-4">
        <div className="card-body p-4">
          {step === 1 && (
            <div className="text-center py-4">
              <div className="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle p-3 mb-3">
                <i className="bi bi-calendar-event fs-2"></i>
              </div>
              <h4 className="fw-bold text-dark mb-2">Fecha de inicio del préstamo</h4>
              <p className="text-muted mb-4 mx-auto" style={{ maxWidth: 500 }}>
                Selecciona la fecha en la que se te entregará el préstamo. Las fechas de tus pagos se calcularán a partir de este día.
              </p>
              <div className="mx-auto" style={{ maxWidth: 300 }}>
                <input
                  type="date"
                  className="form-control form-control-lg rounded-3 border-primary shadow-sm text-center"
                  value={draft.fechaOtorgamiento}
                  onChange={(e) => setDraft((prev) => ({ ...prev, fechaOtorgamiento: e.target.value }))}
                  required
                />
              </div>
              {draft.fechaOtorgamiento && (
                <div className="mt-4 animate-fade-in">
                  <span className="text-muted small d-block">Fecha seleccionada</span>
                  <span className="badge bg-primary text-white px-3 py-2 rounded-pill fs-6 mt-1">
                    {formatFriendlyDate(draft.fechaOtorgamiento)}
                  </span>
                </div>
              )}
            </div>
          )}

          {step === 2 && (
            <LoanInfoStep
              draft={draft}
              onChange={(patch) => {
                setDraft((prev) => ({
                  ...prev,
                  workerType: patch.workerType as WorkerType,
                  interestRate: patch.interestRate,
                }));
              }}
            />
          )}

          {step === 3 && (
            <PaymentSourcesStep
              discounts={draft.descuentos}
              incomeTypes={incomeTypes}
              addDiscount={addDiscount}
              updateDiscount={updateDiscount}
              removeDiscount={removeDiscount}
              isDetails={false}
              requireDocument={false}
            />
          )}

          {step >= 4 && step <= 3 + discountStepCount && (
            <PaymentSourcesStep
              discounts={draft.descuentos}
              incomeTypes={incomeTypes}
              addDiscount={addDiscount}
              updateDiscount={updateDiscount}
              removeDiscount={removeDiscount}
              isDetails={true}
              detailIndex={step - 4}
              requireDocument={false}
            />
          )}

          {step === 4 + discountStepCount && (
            <ReviewStep
              requestData={requestData}
              formatCurrency={formatCurrency}
              localizeDate={localizeDate}
            />
          )}
        </div>
      </div>

      {/* Floating Summary Bar for early steps */}
      {step < 4 + discountStepCount && (
        <div className="card border-0 shadow-sm rounded-4 mt-3 bg-light">
          <div className="card-body py-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <div>
              <span className="text-muted small d-block">Monto Estimado</span>
              <span className="h4 fw-bold text-primary mb-0">{formatCurrency(totalSolicitado)}</span>
            </div>
            <div className="text-center text-sm-end">
              <span className="text-muted small d-block">Plazo Estimado</span>
              <span className="fw-semibold text-dark">
                {mesesEstimados} mes(es) {diasEstimados > 0 && `+ ${diasEstimados} día(s)`}
              </span>
            </div>
          </div>
        </div>
      )}

      <div className="d-flex justify-content-between align-items-center mt-4">
        <div>
          <button
            type="button"
            className="btn btn-outline-secondary btn-lg rounded-3px px-4"
            onClick={prev}
            disabled={step === 1}
          >
            Anterior
          </button>
        </div>

        <div>
          {step < totalSteps && (
            <button
              type="button"
              className="btn btn-primary btn-lg rounded-3px px-4"
              onClick={next}
              disabled={!canNext}
            >
              Siguiente
            </button>
          )}
        </div>
      </div>
    </div>
  );
}


