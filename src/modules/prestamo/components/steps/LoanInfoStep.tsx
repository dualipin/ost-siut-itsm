import { WORKER_PROFILES } from "../../constants/profiles";
import type { WorkerType } from "../../types/loan.types";

interface BaseLoanData {
  workerType?: WorkerType;
  interestRate?: number;
}

interface Props<T extends BaseLoanData> {
  draft: T;
  onChange: (patch: { workerType: WorkerType; interestRate: number }) => void;
}

export default function LoanInfoStep<T extends BaseLoanData>({
  draft,
  onChange,
}: Props<T>) {
  return (
	<div>
	  <div className="mb-3">
		<label className="form-label fw-medium mb-3 h5">
		  Tipo de trabajador solicitante
		</label>

		<div className="row gx-3 gy-3">
		  {WORKER_PROFILES.map((profile) => {
			const selected = draft.workerType === profile.key;

			return (
			  <div key={profile.key} className="col-12 col-md-6">
				<button
				  type="button"
				  className={`btn w-100 h-100 p-3 text-start ${
					selected ? "btn-primary" : "btn-outline-primary"
				  }`}
				  aria-pressed={selected}
				  onClick={() =>
					onChange({
					  workerType: profile.key,
					  interestRate: profile.interestRate,
					})
				  }
				>
				  <div className="d-flex flex-column gap-1">
					<span className="fw-semibold fs-6">{profile.label}</span>
					<span className="small">
					  Tasa: {profile.interestRate}%
					</span>
				  </div>
				</button>
			  </div>
			);
		  })}
		</div>
	  </div>
	</div>
  );
}


