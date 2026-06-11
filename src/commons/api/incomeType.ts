import {api} from "@/libs/axios.ts";
import type {IncomeType} from "@/types/IncomeType.ts";


export const getIncomeTypes = async (): Promise<IncomeType[]> => {
    try {
        const response = await api.get("/loan/income-type.php");
        return response.data;
    } catch (error) {
        console.error("Error fetching income types:", error);
        throw error;
    }
}