export type WorkerType =
  | "agremiado_ahorrador"
  | "agremiado_no_ahorrador"
  | "no_agremiado_ahorrador"
  | "no_agremiado_no_ahorrador";

import type { DiscountConfiguration } from "@/types/DiscountConfiguration";
export type { DiscountConfiguration };

export interface LoanApplicationDraft {
  requestedAmount?: number;
  workerId: number;
  workerType?: WorkerType;
  interestRate?: number;
  discounts: DiscountConfiguration[];
}

