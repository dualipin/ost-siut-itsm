import { createRoot } from "react-dom/client";
import App from "./app.tsx";
import Prestamo from "./modules/prestamo";
import LoanReview from "./modules/prestamo/LoanReview/LoanReview.tsx";

const container = document.getElementById("prestamo-root");
if (container) {
  const user = container.dataset.user
    ? JSON.parse(container.dataset.user)
    : null;
  const root = createRoot(container);
  root.render(
    <App>
      <Prestamo user={user} />
    </App>,
  );
}

const loanReviewContainer = document.getElementById("loan-review-root");
if (loanReviewContainer) {
  const root = createRoot(loanReviewContainer);
  root.render(
    <App>
      <LoanReview />
    </App>,
  );
}
