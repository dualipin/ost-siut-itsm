import type { User } from "@/types/User";
import LoanWizard from "./LoanWizard/LoanWizard";

export default function Prestamo({ user }: { user?: User }) {
  return (
    <div>
      {user && <LoanWizard user={user} />}
      {!user && (
        <div className="alert alert-warning" role="alert">
          No se ha podido cargar la información del usuario. Por favor, intente
          nuevamente más tarde.
        </div>
      )}
    </div>
  );
}
