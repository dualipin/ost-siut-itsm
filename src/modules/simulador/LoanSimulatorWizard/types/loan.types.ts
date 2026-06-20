export interface DiscountItem {
  monto: number;
  tipoId: number;
  cantidad: number;
  fechaPago?: string;
}

export interface SimulationRequest {
  tasaInteresMensual: number;
  fechaOtorgamiento: string;
  descuentos: DiscountItem[];
  montoPrestamo: number;
  mesesPagar: number;
  diasAdicionales: number;
}

export interface SimulationResponse {
  corridaPrestaciones: any[];
  resumenAnual: Record<string, number>;
  formasPago: any[];
  corrida: any[];
  corridasPorTipo: any[];
  resumen: {
    montoTotal: number;
    interesTotal: number;
    pagoTotal: number;
  };
  montoPrestamo: number;
  mesesPagar: number;
  diasAdicionales: number;
  tasaInteresMensual: number;
  fechaOtorgamiento: string;
}
