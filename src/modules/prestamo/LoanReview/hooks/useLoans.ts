import { useQuery } from "@tanstack/react-query";
import { useFilters } from "@/modules/prestamo/LoanReview/stores/filter.store";
import { getLoans } from "@/modules/prestamo/LoanReview/api/getLoans";
import type { LoanReviewResponse } from "@/modules/prestamo/LoanReview/types/loan.type";

export function useLoans() {
  const search = useFilters((s) => s.search);
  const status = useFilters((s) => s.status);
  const fromDate = useFilters((s) => s.fromDate);
  const toDate = useFilters((s) => s.toDate);
  const orderBy = useFilters((s) => s.orderBy);
  const orderDir = useFilters((s) => s.orderDir);

  return useQuery<LoanReviewResponse>({
    queryKey: ["loans-review", { search, status, fromDate, toDate, orderBy, orderDir }],
    queryFn: () => getLoans({ search, status, fromDate, toDate, order: orderBy, dir: orderDir }),
  });
}
