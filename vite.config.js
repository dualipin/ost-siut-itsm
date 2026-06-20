import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import vue from "@vitejs/plugin-vue";
import path from "path";

export default defineConfig({
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "src"),
    },
  },
  plugins: [react(), vue()],
  build: {
    manifest: true,
    outDir: "public/build",
    rolldownOptions: {
      input: {
        prestamo: "src/prestamo.tsx",
        simulador: "src/simulador.tsx",
      },
    },
  },
  publicDir: false,
  server: {
    strictPort: true,
    port: 5173,
  },
});
