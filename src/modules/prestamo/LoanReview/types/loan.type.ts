export type LoanStatus =
  | "todos"
  | "borrador"
  | "solicitado"
  | "en_espera"
  | "aprobado"
  | "rechazado"
  | "activo"
  | "liquidado"
  | "reestructurado";

export interface Loan {
  loan_id: number;
  folio: string;
  status: string;
  requested_amount: number;
  applied_interest_rate: number;
  term_fortnights: number | null;
  application_date: string;
  requires_restructuring: number;
  original_loan_id: number | null;
  days_elapsed: number;
  borrower_name: string;
  borrower_email: string;
  borrower_department: string | null;
  pending_docs: number;
  total_configs: number;
  status_label: string;
  status_badge: string;
  is_restructuring: boolean;
  application_date_label: string;
  requested_amount_label: string;
}

export interface LoanReviewResponse {
  data: Loan[];
  summary: Record<string, number>;
}
