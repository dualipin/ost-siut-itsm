import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
    plugins: [react()],
    build: {
        manifest: true,
        outDir: "public/build",
        rolldownOptions: {
            input: "resources/js/main.tsx"
        },
    },
    server: {
        strictPort: true,
        port: 5173,
        origin: "http://localhost:5173",
    }
});