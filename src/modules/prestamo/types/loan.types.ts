export type WorkerType =
  | "agremiado_ahorrador"
  | "agremiado_no_ahorrador"
  | "no_agremiado_ahorrador"
  | "no_agremiado_no_ahorrador";

export interface DiscountConfiguration {
  tempId: string;
  incomeTypeId?: number;
  incomeTypeName?: string;
  amount?: number;
  isPeriodic?: boolean;
  lastDiscountDate?: string;
  supportingDocument?: File | null;
}

export interface LoanApplicationDraft {
  requestedAmount?: number;
  workerId: number;
  workerType?: WorkerType;
  interestRate?: number;
  discounts: DiscountConfiguration[];
}

