import { useState } from "react";
import Stepper from "@/commons/components/Stepper.tsx";
import LoanInfoStep from "../../prestamo/components/steps/LoanInfoStep";
import type { WorkerType } from "../../prestamo/types/loan.types";

interface LoanSimulationDraft {
  workerType?: WorkerType;
  interestRate?: number;
}

export default function LoanSimulatorWizard() {
  const [draft, setDraft] = useState<LoanSimulationDraft>({});

  return (
    <div>
      <Stepper current={1} total={1} title="Simulador de préstamo" />

      <div className="card border-0 shadow-sm rounded-4 mt-4">
        <div className="card-body">
          <LoanInfoStep<LoanSimulationDraft>
            draft={draft}
            onChange={(patch) => {
              const nextDraft: LoanSimulationDraft = {
                ...draft,
                workerType: patch.workerType as WorkerType,
                interestRate: patch.interestRate,
              };
              setDraft(nextDraft);
            }}
          />
        </div>
      </div>
    </div>
  );
}
