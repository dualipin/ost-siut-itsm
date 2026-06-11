export type IncomeType = {
    id: number;
    name: string;
    description: string;
    isPeriodic: boolean;
    isActive: boolean;
    frequencyDays?: number;
    paymentMonth?: number;
    paymentDay?: number;
}