export type User = {
    id: number;
    name: string;
    email: string;
    role: "lider" | "agremiado" | 'administrador' | 'no_agremiado' | "finanzas"
}