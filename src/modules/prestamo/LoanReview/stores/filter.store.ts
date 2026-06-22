import { create } from "zustand";
import type { LoanStatus } from "../types/loan.type";

type FiltersState = {
  search: string;
  status?: LoanStatus;
  fromDate: string;
  toDate: string;
  orderBy: "fecha" | "folio";
  orderDir: "asc" | "desc";
};

export const useFilters = create<FiltersState>(() => ({
  search: "",
  status: undefined,
  fromDate: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)
    .toISOString()
    .split("T")[0],
  toDate: new Date().toISOString().split("T")[0],
  orderBy: "fecha",
  orderDir: "desc",
}));
