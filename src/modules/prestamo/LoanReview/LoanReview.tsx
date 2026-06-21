import Filters from "@/modules/prestamo/LoanReview/components/Filters.tsx";
import { useFilters } from "./stores/filter.store";

export default function LoanReview() {
  function onFilter() {
    // Implementar lógica de filtrado aquí
    console.log("Filtrando con los siguientes criterios:");
    console.log("Búsqueda:", useFilters.getState().search);
    console.log("Estatus:", useFilters.getState().status);
    console.log("Desde:", useFilters.getState().fromDate);
    console.log("Hasta:", useFilters.getState().toDate);
  }

  return (
    <>
      <Filters onFilter={onFilter} />
    </>
  );
}
