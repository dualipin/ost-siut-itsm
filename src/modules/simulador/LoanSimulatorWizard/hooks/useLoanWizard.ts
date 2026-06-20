import { useState, useMemo, useCallback } from "react";
import { useQuery } from "@tanstack/react-query";
import { getIncomeTypes } from "@/commons/api/incomeType";
import type { DiscountConfiguration } from "@/types/DiscountConfiguration";
import type { IncomeType } from "@/types/IncomeType";
import type { WorkerType } from "@/modules/prestamo/types/loan.types";

export interface LoanSimulationDraft {
  workerType?: WorkerType;
  interestRate?: number;
  fechaOtorgamiento: string;
  descuentos: DiscountConfiguration[];
}

export function obtenerOpcionesFechasPago(cat: Partial<IncomeType>, fechaOtorgamiento: string) {
  if (!cat || !cat.isPeriodic) return [];

  const frecuencia = Number(cat.frequencyDays) || 30;
  const diaPago = Number(cat.paymentDay) || 15;
  const inicio = new Date(fechaOtorgamiento + "T00:00:00");
  
  // Fecha límite operativa para opciones de pago periódico: 15 de noviembre
  const fechaMaxima = new Date(inicio.getFullYear(), 10, 15);

  if (inicio > fechaMaxima) return [];

  const opciones = [];
  const cursor = new Date(inicio.getFullYear(), inicio.getMonth(), 1);
  let guard = 0;

  while (cursor <= fechaMaxima && guard < 36) {
    const year = cursor.getFullYear();
    const monthIndex = cursor.getMonth();
    const totalDiasMes = new Date(year, monthIndex + 1, 0).getDate();

    const fraccionMensual = frecuencia / totalDiasMes;
    const vecesEnMes = fraccionMensual > 0 ? Math.max(1, Math.round(1 / fraccionMensual)) : 1;
    const diaBase = Math.min(Math.max(1, diaPago), totalDiasMes);
    const saltoDias = Math.max(1, Math.round(totalDiasMes / vecesEnMes));

    for (let i = 0; i < vecesEnMes; i++) {
      const diaCandidato = diaBase + (i * saltoDias);
      if (diaCandidato > totalDiasMes) break;

      const fechaPago = new Date(year, monthIndex, diaCandidato);
      const diffMs = fechaPago.getTime() - inicio.getTime();
      const diffDays = Math.floor(diffMs / 86400000);

      // Tolerancia: quincena con al menos 10 días de diferencia respecto al inicio
      if (diffDays < 10 || fechaPago > fechaMaxima) continue;

      const y = fechaPago.getFullYear();
      const m = String(fechaPago.getMonth() + 1).padStart(2, "0");
      const d = String(fechaPago.getDate()).padStart(2, "0");

      opciones.push({
        value: `${y}-${m}-${d}`,
        label: fechaPago.toLocaleDateString("es-MX"),
      });
    }

    cursor.setDate(1);
    cursor.setMonth(cursor.getMonth() + 1);
    guard++;
  }

  return opciones;
}

export function calcularPrimeraQuincena(fechaOtorgamiento: string) {
  if (!fechaOtorgamiento) return "";
  const fecha = new Date(fechaOtorgamiento + "T00:00:00");
  const dia = fecha.getDate();
  const mes = fecha.getMonth();
  const ano = fecha.getFullYear();
  const tolerancia = 10;

  const dia15 = new Date(ano, mes, 15);
  const ultimoDia = new Date(ano, mes + 1, 0);

  if (dia < 15) {
    const diasHasta15 = Math.floor((dia15.getTime() - fecha.getTime()) / 86400000);
    if (diasHasta15 >= tolerancia) {
      return dia15.toLocaleDateString("es-MX");
    }
    return ultimoDia.toLocaleDateString("es-MX");
  } else {
    const diasHastaUltimoDia = Math.floor((ultimoDia.getTime() - fecha.getTime()) / 86400000);
    if (diasHastaUltimoDia >= tolerancia) {
      return ultimoDia.toLocaleDateString("es-MX");
    }
    const nextMonth = new Date(ano, mes + 1, 15);
    return nextMonth.toLocaleDateString("es-MX");
  }
}

export function calcularFechaFin(cat: Partial<IncomeType>, cantidad: number, fechaOtorgamiento: string) {
  if (!cat || !cat.isPeriodic || !(Number(cantidad) > 0)) return "-";

  const numCuotas = Math.max(1, Number(cantidad));
  const opciones = obtenerOpcionesFechasPago(cat, fechaOtorgamiento);
  if (opciones.length === 0) return "-";

  const idx = Math.min(opciones.length, numCuotas) - 1;
  return new Date(opciones[idx].value + "T00:00:00").toLocaleDateString("es-MX");
}

export function useLoanWizard() {
  const { data: incomeTypes = [] } = useQuery<IncomeType[]>({
    queryKey: ["incomeTypes"],
    queryFn: getIncomeTypes,
  });

  const getTodayStr = () => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
  };

  const [draft, setDraft] = useState<LoanSimulationDraft>({
    descuentos: [],
    fechaOtorgamiento: getTodayStr(),
  });

  const addDiscount = useCallback((d: Partial<DiscountConfiguration>) => {
    const tempId = String(Date.now()) + Math.random().toString(36).slice(2, 6);
    setDraft((prev) => {
      // Find the first available income type that isn't selected yet
      const selectedIds = prev.descuentos.map(x => x.incomeTypeId);
      const available = incomeTypes.find(c => c.isActive && !selectedIds.includes(c.id));
      if (!available && d.incomeTypeId === undefined) {
        return prev;
      }
      
      const incomeTypeToUse = d.incomeTypeId 
        ? incomeTypes.find(c => c.id === d.incomeTypeId) 
        : available;

      if (!incomeTypeToUse) return prev;

      let defaultFields: Partial<DiscountConfiguration> = {};
      if (incomeTypeToUse.isPeriodic) {
        const opciones = obtenerOpcionesFechasPago(incomeTypeToUse, prev.fechaOtorgamiento);
        defaultFields = {
          lastDiscountDate: opciones.length > 0 ? opciones[0].value : "",
          amount: 0,
        };
      }

      const newDiscount: DiscountConfiguration = {
        tempId,
        incomeTypeId: incomeTypeToUse.id,
        incomeTypeName: incomeTypeToUse.name,
        isPeriodic: incomeTypeToUse.isPeriodic,
        ...defaultFields,
        ...d,
      } as DiscountConfiguration;

      return {
        ...prev,
        descuentos: [...prev.descuentos, newDiscount],
      };
    });
  }, [incomeTypes]);

  const updateDiscount = useCallback(
    (tempId: string, patch: Partial<DiscountConfiguration>) => {
      setDraft((prev) => {
        const updated = prev.descuentos.map((d) => {
          if (d.tempId !== tempId) return d;
          
          const newD = { ...d, ...patch };

          // If the type changed, update details
          if (patch.incomeTypeId !== undefined && patch.incomeTypeId !== d.incomeTypeId) {
            const cat = incomeTypes.find(c => c.id === patch.incomeTypeId);
            if (cat) {
              newD.incomeTypeName = cat.name;
              newD.isPeriodic = cat.isPeriodic;
              if (cat.isPeriodic) {
                const opciones = obtenerOpcionesFechasPago(cat, prev.fechaOtorgamiento);
                newD.lastDiscountDate = opciones.length > 0 ? opciones[0].value : "";
              } else {
                delete newD.lastDiscountDate;
              }
            }
          }

          // If the lastDiscountDate changed, recalculate quantity based on its index
          if (patch.lastDiscountDate !== undefined && newD.isPeriodic) {
            const cat = incomeTypes.find(c => c.id === newD.incomeTypeId);
            if (cat) {
              const opciones = obtenerOpcionesFechasPago(cat, prev.fechaOtorgamiento);
              const idx = opciones.findIndex(op => op.value === patch.lastDiscountDate);
              if (idx >= 0) {
                newD.cantidad = idx + 1;
              }
            }
          }

          return newD;
        });

        return {
          ...prev,
          descuentos: updated,
        };
      });
    },
    [incomeTypes]
  );

  const removeDiscount = useCallback((tempId: string) => {
    setDraft((prev) => ({
      ...prev,
      descuentos: prev.descuentos.filter((d) => d.tempId !== tempId),
    }));
  }, []);

  const totalSolicitado = useMemo(() => {
    return draft.descuentos.reduce((s, d) => s + (Number(d.amount) || 0), 0);
  }, [draft.descuentos]);

  const plazoDiasEstimado = useMemo(() => {
    let maxDias = 0;
    draft.descuentos.forEach((d) => {
      const tipoId = Number(d.incomeTypeId);
      const cat = incomeTypes.find(c => c.id === tipoId);
      if (!cat) return;

      if (cat.isPeriodic) {
        const base = new Date(draft.fechaOtorgamiento + "T00:00:00");
        const opciones = obtenerOpcionesFechasPago(cat, draft.fechaOtorgamiento);
        const fechaSeleccionada = d.lastDiscountDate || (opciones[0] ? opciones[0].value : "");
        if (!fechaSeleccionada) return;

        const target = new Date(fechaSeleccionada + "T00:00:00");
        const diff = Math.max(0, Math.floor((target.getTime() - base.getTime()) / 86400000));
        maxDias = Math.max(maxDias, diff);
        return;
      }

      const mes = Number(cat.paymentMonth) || 12;
      const dia = Number(cat.paymentDay) || 1;
      const base = new Date(draft.fechaOtorgamiento + "T00:00:00");
      let target = new Date(base.getFullYear(), mes - 1, dia);
      if (target <= base) target = new Date(base.getFullYear() + 1, mes - 1, dia);
      const diff = Math.max(0, Math.floor((target.getTime() - base.getTime()) / 86400000));
      maxDias = Math.max(maxDias, diff);
    });
    return maxDias;
  }, [draft.descuentos, draft.fechaOtorgamiento, incomeTypes]);

  const mesesEstimados = useMemo(() => Math.floor(plazoDiasEstimado / 30), [plazoDiasEstimado]);
  const diasEstimados = useMemo(() => plazoDiasEstimado % 30, [plazoDiasEstimado]);

  const formatCurrency = (v: number) => {
    return "$" + Number(v || 0).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  };

  const localizeDate = (dateStr: string) => {
    if (!dateStr) return "";
    const p = String(dateStr).split("-");
    if (p.length !== 3) return dateStr;
    return `${p[2]}/${p[1]}/${p[0]}`;
  };

  return {
    draft,
    setDraft,
    incomeTypes,
    addDiscount,
    updateDiscount,
    removeDiscount,
    totalSolicitado,
    plazoDiasEstimado,
    mesesEstimados,
    diasEstimados,
    formatCurrency,
    localizeDate,
  } as const;
}
