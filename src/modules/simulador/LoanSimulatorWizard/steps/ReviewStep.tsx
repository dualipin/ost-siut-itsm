import { useEffect, useState } from "react";
import type {
  SimulationRequest,
  SimulationResponse,
} from "../types/loan.types";
import { simulateLoan, fetchLoanSimulationPdf } from "../api/loanSimulate";

interface Props {
  requestData: SimulationRequest;
  formatCurrency: (v: number) => string;
  localizeDate: (d: string) => string;
}

export default function ReviewStep({
  requestData,
  formatCurrency,
  localizeDate,
}: Props) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [response, setResponse] = useState<SimulationResponse | null>(null);
  const [pdfDownloading, setPdfDownloading] = useState(false);

  const performCalculation = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await simulateLoan(requestData);
      setResponse(res);
    } catch (err) {
      console.error(err);
      setError(
        "No se pudo realizar el cálculo de la simulación. Por favor intente de nuevo.",
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    performCalculation();
  }, [requestData]);

  const handleDownloadPdf = async () => {
    setPdfDownloading(true);
    try {
      const pdfBlob = await fetchLoanSimulationPdf(requestData);
      const url = window.URL.createObjectURL(pdfBlob);
      const link = document.createElement("a");
      link.href = url;
      link.setAttribute("download", `simulacion_prestamo_${Date.now()}.pdf`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (err) {
      console.error(err);
      alert("Error al descargar el PDF de la simulación.");
    } finally {
      setPdfDownloading(false);
    }
  };

  if (loading) {
    return (
      <div className="text-center py-5">
        <div className="spinner-border text-primary" role="status">
          <span className="visually-hidden">Cargando simulación...</span>
        </div>
        <p className="mt-3 text-muted">
          Realizando corrida financiera y calculando intereses...
        </p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="alert alert-danger my-4 p-4 text-center">
        <i className="bi bi-exclamation-triangle-fill fs-2 d-block mb-3"></i>
        <h5 className="alert-heading">Error de Cálculo</h5>
        <p>{error}</p>
        <button
          className="btn btn-outline-danger mt-3"
          onClick={performCalculation}
        >
          Intentar de nuevo
        </button>
      </div>
    );
  }

  if (!response) {
    return null;
  }

  return (
    <div>
      {/* Encabezado */}
      <div className="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h3 className="mb-1">Simulación del préstamo</h3>
          <p className="text-muted mb-0">
            Revise el resultado antes de continuar.
          </p>
        </div>

        <button
          type="button"
          className="btn btn-outline-secondary"
          onClick={handleDownloadPdf}
          disabled={pdfDownloading}
        >
          {pdfDownloading ? (
            <>
              <span className="spinner-border spinner-border-sm me-2" />
              Generando...
            </>
          ) : (
            <>
              <i className="bi bi-file-pdf me-2" />
              Descargar PDF
            </>
          )}
        </button>
      </div>

      {/* Resumen */}
      <div className="row g-3 mb-4">
        <div className="col-md-3">
          <div className="card">
            <div className="card-body">
              <div className="small text-muted">Monto solicitado</div>
              <div className="fs-5 fw-semibold">
                {formatCurrency(response.montoPrestamo)}
              </div>
            </div>
          </div>
        </div>

        <div className="col-md-3">
          <div className="card">
            <div className="card-body">
              <div className="small text-muted">Total a pagar</div>
              <div className="fs-5 fw-semibold">
                {formatCurrency(response.resumen.pagoTotal)}
              </div>
            </div>
          </div>
        </div>

        <div className="col-md-3">
          <div className="card">
            <div className="card-body">
              <div className="small text-muted">Plazo</div>
              <div className="fs-5 fw-semibold">
                {response.mesesPagar} meses
              </div>
            </div>
          </div>
        </div>

        <div className="col-md-3">
          <div className="card">
            <div className="card-body">
              <div className="small text-muted">Tasa mensual</div>
              <div className="fs-5 fw-semibold">
                {response.tasaInteresMensual}%
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Tabla principal */}
      <div className="card mb-4">
        <div className="card-header">
          <strong>Corrida general</strong>
        </div>

        <div className="table-responsive">
          <table className="table align-middle mb-0">
            <thead>
              <tr>
                <th>Periodo</th>
                <th className="text-end">Capital</th>
                <th className="text-end">Interés</th>
                <th className="text-end">Pago</th>
                <th className="text-end">Saldo</th>
                <th>Fecha</th>
              </tr>
            </thead>

            <tbody>
              {response.corrida.map((row) => (
                <tr key={row.quincena}>
                  <td>{row.quincena}</td>
                  <td className="text-end">{formatCurrency(row.capital)}</td>
                  <td className="text-end">{formatCurrency(row.interes)}</td>
                  <td className="text-end fw-semibold">
                    {formatCurrency(row.pago)}
                  </td>
                  <td className="text-end">{formatCurrency(row.saldo)}</td>
                  <td>{localizeDate(row.fecha)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Formas de pago */}
      {response.formasPago?.length > 0 && (
        <div className="card mb-4">
          <div className="card-header">
            <strong>Formas de descuento</strong>
          </div>

          <div className="table-responsive">
            <table className="table align-middle mb-0">
              <thead>
                <tr>
                  <th>Prestación</th>
                  <th>Último descuento</th>
                  <th className="text-end">Monto</th>
                </tr>
              </thead>

              <tbody>
                {response.formasPago.map((forma, idx) => (
                  <tr key={idx}>
                    <td>{forma.nombre}</td>
                    <td>
                      {forma.tipo === "periodico"
                        ? `Cada ${forma.frecuenciaDias} días`
                        : localizeDate(forma.fechaPago)}
                    </td>
                    <td className="text-end">{formatCurrency(forma.monto)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Detalles */}
      {response.corridasPorTipo?.length > 0 && (
        <div className="accordion mb-4">
          {response.corridasPorTipo.map((grupo: any, idx: number) => (
            <div key={idx} className="accordion-item">
              <h2 className="accordion-header">
                <button
                  className="accordion-button collapsed"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target={`#detalle-${idx}`}
                >
                  {grupo.prestacion}
                </button>
              </h2>

              <div
                id={`detalle-${idx}`}
                className="accordion-collapse collapse"
              >
                <div className="accordion-body">
                  <div className="table-responsive">
                    <table className="table">
                      <thead>
                        <tr>
                          <th>Periodo</th>
                          <th className="text-end">Capital</th>
                          <th className="text-end">Interés</th>
                          <th className="text-end">Pago</th>
                          <th className="text-end">Saldo</th>
                          <th>Fecha</th>
                        </tr>
                      </thead>

                      <tbody>
                        {grupo.corrida.map((row: any) => (
                          <tr key={row.periodo}>
                            <td>{row.periodo}</td>
                            <td className="text-end">
                              {formatCurrency(row.capital)}
                            </td>
                            <td className="text-end">
                              {formatCurrency(row.interes)}
                            </td>
                            <td className="text-end">
                              {formatCurrency(row.pago)}
                            </td>
                            <td className="text-end">
                              {formatCurrency(row.saldo)}
                            </td>
                            <td>{localizeDate(row.fecha)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
