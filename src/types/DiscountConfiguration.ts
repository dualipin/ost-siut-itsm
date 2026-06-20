export interface DiscountConfiguration {
  tempId: string;
  incomeTypeId?: number;
  incomeTypeName?: string;
  amount?: number;
  isPeriodic?: boolean;
  lastDiscountDate?: string;
  supportingDocument?: File | null;
}
