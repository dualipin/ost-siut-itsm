import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { getIncomeTypes } from "@/commons/api/incomeType";
import Stepper from "./components/Stepper";
import LoanInfoStep from "./steps/LoanInfoStep";
import PaymentSourcesStep from "./steps/PaymentSourcesStep";
import DocumentsStep from "./steps/DocumentsStep";
import ReviewStep from "./steps/ReviewStep";
import { useLoanWizard } from "./hooks/useLoanWizard";
import type { LoanApplicationDraft } from "./types/loan.types";
import type { User } from "@/types/User";

export default function LoanWizard({ user }: { user: User }) {
  const { data: incomeTypes = [] } = useQuery({
    queryKey: ["incomeTypes"],
    queryFn: getIncomeTypes,
  });

  const {
    draft,
    computedRequestedAmount,
    nov30Violation,
    nov30ViolationMessage,
    setBasicInfo,
    addDiscount,
    updateDiscount,
    removeDiscount,
    setDraft,
    validateStep,
  } = useLoanWizard({
    workerId: user.id,
  });

  const [step, setStep] = useState(1);

  useEffect(() => {
    // Autoguardado local simple: ayuda a usuarios lentos y es fácil de integrar
    try {
      localStorage.setItem("loan-draft", JSON.stringify(draft));
    } catch (e) {
      // ignore
    }
  }, [draft]);

  useEffect(() => {
    // Cargar borrador local si existe
    try {
      const raw = localStorage.getItem("loan-draft");
      if (raw) {
        const parsed = JSON.parse(raw) as LoanApplicationDraft;
        setDraft(parsed as any);
      }
    } catch (e) {
      // ignore
    }
  }, [setDraft]);

  const dynamicCount = draft.discounts.length;

  const titles = useMemo(() => {
    const base = ["Datos", "Seleccionar descuentos"];
    const details = draft.discounts.map(
      (d) => `Detalle: ${d.incomeTypeName ?? "Descuento"}`,
    );
    return [...base, ...details, "Documentos", "Resumen"];
  }, [draft.discounts]);

  const totalSteps = titles.length;

  const canNext = useMemo(() => {
    if (nov30Violation) return false;
    return validateStep(step, dynamicCount);
  }, [step, dynamicCount, validateStep, nov30Violation]);

  function next() {
    if (!validateStep(step, dynamicCount) || nov30Violation) return;
    setStep((s) => Math.min(totalSteps, s + 1));
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function prev() {
    setStep((s) => Math.max(1, s - 1));
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  async function submit() {
    if (nov30Violation) {
      alert(nov30ViolationMessage);
      return;
    }

    // Aquí se haría el POST final: /api/loan-applications/{loanId}/submit
    // Por ahora enviamos a consola y limpiamos borrador local.
    const payload = { ...draft, requestedAmount: computedRequestedAmount };
    console.log("Submitting draft", payload);
    try {
      localStorage.removeItem("loan-draft");
    } catch (e) {}
    alert("Solicitud enviada (demo).");
  }

  useEffect(() => {
    // Si cambian las formas de descuento, ajustamos el paso actual para que no quede fuera de rango
    if (step > totalSteps) {
      setStep(totalSteps);
    }
  }, [draft.discounts, step, totalSteps]);

  return (
    <div>
      <Stepper current={step} total={totalSteps} title={titles[step - 1]} />

      <div className="card">
        <div className="card-body">
          {step === 1 && (
            <LoanInfoStep draft={draft} onChange={(p) => setBasicInfo(p)} />
          )}

          {step === 2 && (
            <PaymentSourcesStep
              draft={draft}
              incomeTypes={incomeTypes}
              addDiscount={(d) => addDiscount(d)}
              updateDiscount={updateDiscount}
              removeDiscount={removeDiscount}
              isDetails={false}
            />
          )}

          {/* Pasos dinámicos: uno por cada descuento seleccionado */}
          {step >= 3 && step <= 2 + dynamicCount && (
            <PaymentSourcesStep
              draft={draft}
              incomeTypes={incomeTypes}
              addDiscount={(d) => addDiscount(d)}
              updateDiscount={updateDiscount}
              removeDiscount={removeDiscount}
              isDetails={true}
              detailIndex={step - 3}
            />
          )}

          {/* Documents */}
          {step === 3 + dynamicCount && <DocumentsStep draft={draft} />}

          {/* Review */}
          {step === 4 + dynamicCount && (
            <ReviewStep
              draft={draft}
              computedRequestedAmount={computedRequestedAmount}
            />
          )}
        </div>
      </div>

      {nov30Violation && (
        <div className="mt-3 alert alert-danger">{nov30ViolationMessage}</div>
      )}

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
              disabled={!canNext}
            >
              Siguiente
            </button>
          )}
          {step === totalSteps && (
            <button
              type="button"
              className="btn btn-success btn-lg"
              onClick={submit}
            >
              Enviar solicitud
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
