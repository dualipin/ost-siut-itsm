import {useCallback, useState, useMemo} from 'react';
import type {LoanApplicationDraft, DiscountConfiguration} from '../../types/loan.types';

export function useLoanWizard(initial?: Partial<LoanApplicationDraft>) {
  const [draft, setDraft] = useState<LoanApplicationDraft>({
    // requestedAmount is calculated from discounts; do not rely on stored value
    workerId: initial?.workerId ?? 0,
    interestRate: initial?.interestRate,
    discounts: initial?.discounts ?? [],
  });

  const computedRequestedAmount = useMemo(() => {
    return draft.discounts.reduce((s, d) => s + (d.amount ?? 0), 0);
  }, [draft.discounts]);

  const nov30Violation = useMemo(() => {
    const hasPeriodic = draft.discounts.some(d => d.isPeriodic);
    if (!hasPeriodic) return false;
    const now = new Date();
    const nov30 = new Date(now.getFullYear(), 10, 30, 23, 59, 59, 999);
    return now > nov30;
  }, [draft.discounts]);

  const nov30ViolationMessage = nov30Violation ? 'No es posible elegir un préstamo después del 30 de noviembre del año en curso para formas de descuento periódicas.' : '';

  const setBasicInfo = useCallback((patch: Partial<LoanApplicationDraft>) => {
    setDraft(d => ({...d, ...patch}));
  }, []);

  const addDiscount = useCallback((d: Partial<DiscountConfiguration>) => {
    const tempId = String(Date.now()) + Math.random().toString(36).slice(2,6);
    setDraft(prev => ({...prev, discounts: [...prev.discounts, {...d, tempId} as DiscountConfiguration]}));
  }, []);

  const updateDiscount = useCallback((tempId: string, patch: Partial<DiscountConfiguration>) => {
    setDraft(prev => ({...prev, discounts: prev.discounts.map(d => d.tempId === tempId ? {...d, ...patch} : d)}));
  }, []);

  const removeDiscount = useCallback((tempId: string) => {
    setDraft(prev => ({...prev, discounts: prev.discounts.filter(d => d.tempId !== tempId)}));
  }, []);

  // validateStep ahora soporta pasos dinámicos: se deben pasar la cantidad de pasos dinámicos
  const validateStep = useCallback((step: number, dynamicCount: number) => {
    // Paso 1: info básica
    if (step === 1) {
      return !!(draft.workerId && (draft.interestRate !== undefined && draft.interestRate !== null));
    }

    // Paso 2: selección de tipos
    if (step === 2) {
      return !!(draft.discounts && draft.discounts.length > 0);
    }

    // Pasos dinámicos: 3 .. (2 + dynamicCount)
    const dynamicStart = 3;
    const dynamicEnd = 2 + dynamicCount;
    if (step >= dynamicStart && step <= dynamicEnd) {
      const idx = step - dynamicStart;
      const d = draft.discounts[idx];
      if (!d) return false;
      if (!d.incomeTypeId || !d.amount || d.amount <= 0) return false;
      if (d.isPeriodic && !d.lastDiscountDate) return false;
      if (!d.supportingDocument) return false;
      return true;
    }

    // Documents step
    if (step === dynamicEnd + 1) {
      return true;
    }

    // Resumen u otros
    return true;
  }, [draft]);

  return {
    draft,
    computedRequestedAmount,
    nov30Violation,
    nov30ViolationMessage,
    setBasicInfo,
    addDiscount,
    updateDiscount,
    removeDiscount,
    setDraft,
    validateStep,
  } as const;
}
