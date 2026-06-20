import type { IncomeType } from "@/types/IncomeType";

interface Props {
  incomeTypes: IncomeType[];
  toggleIncomeType: (it: IncomeType) => void;
}

export default function IncomeTypeStep({
  incomeTypes,
  toggleIncomeType,
}: Props) {
  const isIncomeTypeExpired = (it: IncomeType) => {
    const today = new Date();
    const currentMonth = today.getMonth() + 1;
    const currentDay = today.getDate();

    if (typeof it.paymentMonth === "number") {
      if (it.paymentMonth < currentMonth) return true;
      if (
        it.paymentMonth === currentMonth &&
        typeof it.paymentDay === "number" &&
        it.paymentDay < currentDay
      )
        return true;
    }
    return false;
  };
  const visibleIncomeTypes = incomeTypes.filter(
    (it) => !isIncomeTypeExpired(it),
  );

  const isSelected = (_incomeTypeId: number) => false; // Placeholder, implement selection logic as needed
  return (
    <div>
      <h2>Tipo de ingreso</h2>
      <div className="row gy-3 mb-4">
        {visibleIncomeTypes.map((it) => {
          const selected = isSelected(it.id);
          return (
            <div className="col-12 col-md-6 col-xl-4 col-xxl-3" key={it.id}>
              <button
                type="button"
                className={`card btn h-100 w-100 text-start p-3 ${selected ? "border-primary bg-primary-subtle" : ""}`}
                onClick={() => toggleIncomeType(it)}
                style={{ minHeight: 140 }}
              >
                <div className="card-body">
                  <div className="fw-semibold fs-6">{it.name}</div>
                  <div className="small text-muted">{it.description}</div>
                  {it.isPeriodic && (
                    <div className="badge bg-primary text-dark mt-2">
                      Periódico
                    </div>
                  )}
                </div>
              </button>
            </div>
          );
        })}
      </div>
    </div>
  );
}
