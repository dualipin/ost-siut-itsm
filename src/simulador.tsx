import { createRoot } from "react-dom/client";
import App from "@/main.tsx";
import Simulador from "@/modules/simulador";

const container = document.getElementById("simulador-root");

if (container) {
  const root = createRoot(container);

  root.render(
    <App>
      <Simulador />
    </App>,
  );
}
