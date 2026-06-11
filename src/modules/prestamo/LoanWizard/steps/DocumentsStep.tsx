import type {LoanApplicationDraft} from '../types/loan.types';

interface Props {
  draft: LoanApplicationDraft;
}

export default function DocumentsStep({draft}: Props) {
  const missing = draft.discounts.filter(d => !d.supportingDocument);

  return (
    <div>
      <h5>Documentos cargados</h5>
      <ul className="list-group mb-3">
        {draft.discounts.map(d => (
          <li key={d.tempId} className="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <div className="fw-semibold">{d.incomeTypeName ?? 'Sin tipo'}</div>
              <div className="small">Monto: ${d.amount ?? 0}</div>
            </div>
            <div>
              {d.supportingDocument ? <span className="text-success">✓ {d.supportingDocument.name}</span> : <span className="text-warning">⚠ Falta comprobante</span>}
            </div>
          </li>
        ))}
      </ul>

      {missing.length > 0 && <div className="alert alert-danger">Falta comprobante para {missing.map(m => m.incomeTypeName ?? 'uno de los descuentos').join(', ')}. No puede continuar hasta corregirlo.</div>}
    </div>
  )
}
