import { useCallback, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { getIncomeTypes } from "@/commons/api/incomeType";
import Stepper from "@/commons/components/Stepper.tsx";
import LoanInfoStep from "../../prestamo/components/steps/LoanInfoStep";
import PaymentSourcesStep from "../../prestamo/components/steps/PaymentSourcesStep";
import type { WorkerType } from "../../prestamo/types/loan.types";
import type { DiscountConfiguration } from "@/types/DiscountConfiguration";

interface LoanSimulationDraft {
  workerType?: WorkerType;
  interestRate?: number;
  discounts: DiscountConfiguration[];
}

export default function LoanSimulatorWizard() {
  const { data: incomeTypes = [] } = useQuery({
    queryKey: ["incomeTypes"],
    queryFn: getIncomeTypes,
  });

  const [draft, setDraft] = useState<LoanSimulationDraft>({
    discounts: [],
  });

  const [step, setStep] = useState(1);

  const addDiscount = useCallback((d: Partial<DiscountConfiguration>) => {
    const tempId =
      String(Date.now()) + Math.random().toString(36).slice(2, 6);
    setDraft((prev) => ({
      ...prev,
      discounts: [
        ...prev.discounts,
        { ...d, tempId } as DiscountConfiguration,
      ],
    }));
  }, []);

  const updateDiscount = useCallback(
    (tempId: string, patch: Partial<DiscountConfiguration>) => {
      setDraft((prev) => ({
        ...prev,
        discounts: prev.discounts.map((d) =>
          d.tempId === tempId ? { ...d, ...patch } : d,
        ),
      }));
    },
    [],
  );

  const removeDiscount = useCallback((tempId: string) => {
    setDraft((prev) => ({
      ...prev,
      discounts: prev.discounts.filter((d) => d.tempId !== tempId),
    }));
  }, []);

  const discountStepCount = draft.discounts.length;
  const baseSteps = 2; // loan info + discount selection
  const totalSteps = baseSteps + discountStepCount + 1; // +1 for review

  const stepTitle = useMemo(() => {
    if (step === 1) return "Datos del solicitante";
    if (step === 2) return "Seleccionar descuentos";
    if (step >= 3 && step <= 2 + discountStepCount) return `Detalle: ${draft.discounts[step - 3]?.incomeTypeName ?? "Descuento"}`;
    if (step === 3 + discountStepCount) return "Resumen";
    return "";
  }, [step, discountStepCount, draft.discounts]);

  function next() {
    setStep((s) => Math.min(totalSteps, s + 1));
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function prev() {
    setStep((s) => Math.max(1, s - 1));
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  return (
    <div>
      <Stepper current={step} total={Math.max(1, totalSteps)} title={stepTitle} />

      <div className="card border-0 shadow-sm rounded-4 mt-4">
        <div className="card-body">
          {step === 1 && (
            <LoanInfoStep<LoanSimulationDraft>
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

          {step === 2 && (
            <PaymentSourcesStep
              discounts={draft.discounts}
              incomeTypes={incomeTypes}
              addDiscount={addDiscount}
              updateDiscount={updateDiscount}
              removeDiscount={removeDiscount}
              isDetails={false}
              requireDocument={false}
            />
          )}

          {step >= 3 && step <= 2 + discountStepCount && (
            <PaymentSourcesStep
              discounts={draft.discounts}
              incomeTypes={incomeTypes}
              addDiscount={addDiscount}
              updateDiscount={updateDiscount}
              removeDiscount={removeDiscount}
              isDetails={true}
              detailIndex={step - 3}
              requireDocument={false}
            />
          )}

          {step === 3 + discountStepCount && (
            <div>
              <div className="mb-3">
                <div className="fw-semibold h5">Resumen de la simulación</div>
              </div>

              <table className="table table-bordered">
                <tbody>
                  <tr>
                    <th>Tasa de interés</th>
                    <td>{draft.interestRate != null ? `${draft.interestRate}%` : "—"}</td>
                  </tr>
                  <tr>
                    <th>Formas de descuento</th>
                    <td>{discountStepCount}</td>
                  </tr>
                </tbody>
              </table>

              {discountStepCount > 0 && (
                <>
                  <div className="fw-semibold mb-2">Detalle de descuentos</div>
                  <table className="table table-bordered">
                    <thead>
                      <tr>
                        <th>Tipo</th>
                        <th>Monto</th>
                      </tr>
                    </thead>
                    <tbody>
                      {draft.discounts.map((d) => (
                        <tr key={d.tempId}>
                          <td>{d.incomeTypeName ?? "—"}</td>
                          <td>{d.amount != null ? `$${d.amount}` : "—"}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="d-flex justify-content-between align-items-center mt-3">
        <div>
          <button
            type="button"
            className="btn btn-outline-secondary btn-lg"
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
              className="btn btn-primary btn-lg"
              onClick={next}
            >
              Siguiente
            </button>
          )}
          {step === totalSteps && step > 1 && (
            <button
              type="button"
              className="btn btn-success btn-lg"
            >
              Simular
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
