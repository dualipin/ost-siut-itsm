import type { LoanStatus } from "@/modules/prestamo/LoanReview/types/loan.type.ts";

interface LoanStatusOption {
  value: LoanStatus;
  label: string;
}

export const LOAN_STATUS_OPTIONS: LoanStatusOption[] = [
  { value: "todos", label: "Todos" },
  { value: "borrador", label: "Borrador" },
  { value: "solicitado", label: "Solicitado" },
  { value: "en_espera", label: "En espera" },
  { value: "aprobado", label: "Aprobado" },
  { value: "rechazado", label: "Rechazado" },
  { value: "activo", label: "Activo" },
  { value: "liquidado", label: "Liquidado" },
  { value: "reestructurado", label: "Reestructurado" },
];
