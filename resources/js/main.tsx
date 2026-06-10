import { createRoot } from "react-dom/client";
import Alert from "./components/Alert";

const container = document.getElementById("app-react-root");

if (container) {
    const root = createRoot(container);
    root.render(<Alert message="This is a Message" type="success" />);
}