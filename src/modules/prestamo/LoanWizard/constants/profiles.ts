import type {WorkerType} from '../types/loan.types';

export type WorkerProfile = {
  key: WorkerType;
  label: string;
  interestRate: number;
};

export const WORKER_PROFILES: WorkerProfile[] = [
  {key: 'agremiado_ahorrador', label: 'Agremiado y ahorrador', interestRate: 6},
  {key: 'agremiado_no_ahorrador', label: 'Agremiado y no ahorrador', interestRate: 7.5},
  {key: 'no_agremiado_ahorrador', label: 'No agremiado y ahorrador', interestRate: 8},
  {key: 'no_agremiado_no_ahorrador', label: 'No agremiado y no ahorrador', interestRate: 9.5},
];

export function getProfileLabel(key?: WorkerType | ''): string {
  if (!key) return '';
  const p = WORKER_PROFILES.find(x => x.key === key);
  return p ? p.label : String(key);
}
