import { api } from "@/libs/axios";
import type { LoanReviewResponse, LoanStatus } from "@/modules/prestamo/LoanReview/types/loan.type";

interface GetLoansParams {
  search?: string;
  status?: LoanStatus;
  fromDate?: string;
  toDate?: string;
  order?: "fecha" | "folio";
  dir?: "asc" | "desc";
}

export async function getLoans(
  params: GetLoansParams = {},
): Promise<LoanReviewResponse> {
  const query = new URLSearchParams();

  if (params.search) query.set("search", params.search);
  if (params.status && params.status !== "todos")
    query.set("status", params.status);
  if (params.fromDate) query.set("fecha_desde", params.fromDate);
  if (params.toDate) query.set("fecha_hasta", params.toDate);
  if (params.order) query.set("order", params.order);
  if (params.dir) query.set("dir", params.dir);

  const qs = query.toString();
  const response = await api.get<LoanReviewResponse>(
    `/loan/review${qs ? `?${qs}` : ""}`,
  );
  return response.data;
}
