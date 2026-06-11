import { createRoot } from "react-dom/client";
import App from "./main.tsx";
import Prestamo from "./modules/prestamo";

const container = document.getElementById("prestamo-root");
if (container) {
  const user = container.dataset.user ? JSON.parse(container.dataset.user) : null;
  const root = createRoot(container);
  root.render(
    <App>
      <Prestamo user={user} />
    </App>
  );


}

