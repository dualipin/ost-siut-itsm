import { api } from "@/libs/axios";
import type { SimulationRequest, SimulationResponse } from "../types/loan.types";

export const simulateLoan = async (data: SimulationRequest): Promise<SimulationResponse> => {
  const response = await api.post<SimulationResponse>("/loan/simulate", {
    ...data,
    output: "json",
  });
  return response.data;
};

export const fetchLoanSimulationPdf = async (data: SimulationRequest): Promise<Blob> => {
  const response = await api.post<Blob>(
    "/loan/simulate",
    {
      ...data,
      output: "pdf",
    },
    {
      responseType: "blob",
    },
  );
  return response.data;
};

