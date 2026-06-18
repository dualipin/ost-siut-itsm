import type {LoanApplicationDraft} from '../../types/loan.types';
import {getProfileLabel} from '../../constants/profiles';

interface Props {
  draft: LoanApplicationDraft;
  computedRequestedAmount: number;
}

export default function ReviewStep({draft, computedRequestedAmount}: Props) {
  // const totalDiscount = draft.discounts.reduce((s, d) => s + (d.amount ?? 0), 0);

  return (
    <div>
      <h5>Resumen de su solicitud</h5>

      <div className="mb-3 text-center">
        <div className="fw-semibold fs-5">Monto solicitado:</div>
        <div className="fs-5">${computedRequestedAmount.toFixed(2)}</div>
      </div>

      <div className="mb-3">
        <div className="fw-semibold">Tipo de trabajador solicitante:</div>
        <div>{getProfileLabel(draft.workerType ?? '')}</div>
      </div>

      <div className="mb-3">
        <div className="fw-semibold">Descuentos configurados</div>
        {draft.discounts.map(d => (
          <div key={d.tempId} className="border rounded p-2 mb-2">
            <div className="fw-semibold">{d.incomeTypeName}</div>
            <div>Monto: ${d.amount ?? 0}</div>
            {d.isPeriodic && d.lastDiscountDate && <div>Último descuento: {d.lastDiscountDate}</div>}
          </div>
        ))}
      </div>
    </div>
  )
}
