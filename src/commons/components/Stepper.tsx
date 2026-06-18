interface StepperProps {
  current: number;
  total: number;
  title?: string;
}

export default function Stepper({current, total, title}: StepperProps) {
  const percent = Math.round((current / total) * 100);

  return (
    <div className="mb-4">
      <div className="d-flex justify-content-between align-items-center mb-2">
        <div>Paso {current} de {total}</div>
        {title && <div className="fw-semibold">{title}</div>}
      </div>

      <div className="progress" style={{height: 10}}>
        <div className="progress-bar" role="progressbar" style={{width: `${percent}%`}} aria-valuenow={percent} aria-valuemin={0} aria-valuemax={100}></div>
      </div>
    </div>
  )
}
