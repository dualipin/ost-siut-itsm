import { useEffect, useState } from "react";
import type { SimulationRequest, SimulationResponse } from "../types/loan.types";
import { simulateLoan, fetchLoanSimulationPdf } from "../api/loanSimulate";

interface Props {
  requestData: SimulationRequest;
  formatCurrency: (v: number) => string;
  localizeDate: (d: string) => string;
}

export default function ReviewStep({ requestData, formatCurrency, localizeDate }: Props) {
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
      setError("No se pudo realizar el cálculo de la simulación. Por favor intente de nuevo.");
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
        <p className="mt-3 text-muted">Realizando corrida financiera y calculando intereses...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="alert alert-danger my-4 p-4 text-center">
        <i className="bi bi-exclamation-triangle-fill fs-2 d-block mb-3"></i>
        <h5 className="alert-heading">Error de Cálculo</h5>
        <p>{error}</p>
        <button className="btn btn-outline-danger mt-3" onClick={performCalculation}>
          Intentar de nuevo
        </button>
      </div>
    );
  }

  if (!response) {
    return null;
  }

  return (
    <div className="animate-fade-in">
      <div className="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
          <h4 className="fw-semibold text-primary mb-1">Resultados de la Simulación</h4>
          <p className="text-muted small mb-0">Corrida financiera estimada para el préstamo.</p>
        </div>
        <button
          type="button"
          className="btn btn-outline-danger d-flex align-items-center gap-2"
          onClick={handleDownloadPdf}
          disabled={pdfDownloading}
        >
          {pdfDownloading ? (
            <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
          ) : (
            <i className="bi bi-file-pdf"></i>
          )}
          Descargar PDF
        </button>
      </div>



      {/* Formas de descuento */}
      {response.formasPago && response.formasPago.length > 0 && (
        <div className="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
          <div className="card-header bg-primary text-white py-3">
            <h5 className="card-title mb-0 fs-6 fw-semibold">Formas de descuento aplicadas</h5>
          </div>
          <div className="table-responsive">
            <table className="table table-striped table-hover align-middle mb-0 text-sm">
              <thead className="table-light">
                <tr>
                  <th>Prestación</th>
                  <th>Tipo</th>
                  <th>Detalle</th>
                  <th className="text-end">Monto</th>
                </tr>
              </thead>
              <tbody>
                {response.formasPago.map((forma, idx) => (
                  <tr key={idx}>
                    <td className="fw-medium">{forma.nombre}</td>
                    <td>
                      <span className={`badge ${forma.tipo === "periodico" ? "bg-info text-dark" : "bg-secondary"}`}>
                        {forma.tipo === "periodico" ? "Periódico" : "No periódico"}
                      </span>
                    </td>
                    <td>
                      {forma.tipo === "periodico"
                        ? `Cada ${forma.frecuenciaDias} días, ${forma.cantidad} periodo(s) ${
                            forma.diaTentativo ? `(Día tentativo: ${forma.diaTentativo})` : ""
                          }`
                        : `Pago tentativo: ${localizeDate(forma.fechaPago)}`}
                    </td>
                    <td className="text-end fw-bold">{formatCurrency(forma.monto)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Resumen Anual & Corrida Prestaciones */}
      <div className="row g-4 mb-4">
        {/* Resumen Anual */}
        {response.resumenAnual && Object.keys(response.resumenAnual).length > 0 && (
          <div className="col-12 col-lg-5">
            <div className="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
              <div className="card-header bg-info text-white py-3">
                <h5 className="card-title mb-0 fs-6 fw-semibold">Resumen Anual de Pagos con Prestaciones</h5>
              </div>
              <ul className="list-group list-group-flush">
                {Object.entries(response.resumenAnual).map(([prestacion, monto]) => (
                  <li key={prestacion} className="list-group-item d-flex justify-content-between align-items-center py-3">
                    <span className="fw-medium text-dark">{prestacion}</span>
                    <span className="badge bg-primary rounded-pill px-3 py-2 fs-7">{formatCurrency(monto)}</span>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        )}

        {/* Corrida de Prestaciones */}
        {response.corridaPrestaciones && response.corridaPrestaciones.length > 0 && (
          <div className="col-12 col-lg-7">
            <div className="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
              <div className="card-header bg-success text-white py-3">
                <h5 className="card-title mb-0 fs-6 fw-semibold">Corrida financiera de prestaciones</h5>
              </div>
              <div className="table-responsive">
                <table className="table table-striped table-hover align-middle mb-0 text-sm">
                  <thead className="table-light">
                    <tr>
                      <th>Prestación</th>
                      <th>Tipo</th>
                      <th className="text-center">Periodo</th>
                      <th className="text-center">Fecha</th>
                      <th className="text-end">Monto</th>
                    </tr>
                  </thead>
                  <tbody>
                    {response.corridaPrestaciones.map((row, idx) => (
                      <tr key={idx}>
                        <td className="fw-medium">{row.prestacion}</td>
                        <td>{row.tipo === "periodico" ? "Periódico" : "No periódico"}</td>
                        <td className="text-center">{row.periodo}</td>
                        <td className="text-center">{localizeDate(row.fecha)}</td>
                        <td className="text-end fw-semibold text-success">{formatCurrency(row.monto)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Corridas por tipo detalladas */}
      {response.corridasPorTipo && response.corridasPorTipo.map((grupo: any, idx: number) => (
        <div key={idx} className="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
          <div className="card-header bg-light py-3 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center">
            <div>
              <h5 className="mb-0 fs-6 fw-bold text-dark">Detalle: {grupo.prestacion}</h5>
              <span className="text-muted small">
                {grupo.metodo} | Tipo: {grupo.tipo === "periodico" ? "Periódico" : "No periódico"}
              </span>
            </div>
            <span className="badge bg-dark mt-2 mt-sm-0 px-3 py-2">
              Monto Base: {formatCurrency(grupo.montoBase)}
            </span>
          </div>
          <div className="table-responsive">
            <table className="table table-striped table-hover align-middle mb-0 text-xs">
              <thead className="table-light">
                <tr>
                  <th className="text-center">Periodo</th>
                  <th className="text-end">Pago Capital</th>
                  <th className="text-end">Interés</th>
                  <th className="text-end">Pago</th>
                  <th className="text-end">Saldo Final</th>
                  <th className="text-center">Fecha Pago</th>
                </tr>
              </thead>
              <tbody>
                {grupo.corrida.map((row: any) => (
                  <tr key={row.periodo}>
                    <td className="text-center fw-semibold">{row.periodo}</td>
                    <td className="text-end">{formatCurrency(row.capital)}</td>
                    <td className="text-end text-muted">{formatCurrency(row.interes)}</td>
                    <td className="text-end fw-semibold text-primary">{formatCurrency(row.pago)}</td>
                    <td className="text-end text-danger">{formatCurrency(row.saldo)}</td>
                    <td className="text-center text-muted">{localizeDate(row.fecha)}</td>
                  </tr>
                ))}
              </tbody>
              <tfoot className="table-group-divider fw-bold">
                <tr>
                  <td colSpan={2} className="text-center">Totales</td>
                  <td className="text-end">{formatCurrency(grupo.resumen.interesTotal)}</td>
                  <td className="text-end text-primary">{formatCurrency(grupo.resumen.pagoTotal)}</td>
                  <td className="text-end text-danger">{formatCurrency(grupo.resumen.saldoFinal)}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      ))}

      {/* Main Amortization Table */}
      <div className="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div className="card-header bg-dark text-white py-3">
          <h5 className="card-title mb-0 fs-6 fw-semibold">Tabla de corrida quincenal (General)</h5>
        </div>
        <div className="table-responsive">
          <table className="table table-striped table-hover align-middle mb-0 text-sm">
            <thead className="table-dark">
              <tr>
                <th className="text-center">Quincena</th>
                <th className="text-end">Pago Capital (Costo Fijo)</th>
                <th className="text-end">Interés Quincenal</th>
                <th className="text-end">Pago Quincenal</th>
                <th className="text-end">Saldo Final Quincena</th>
                <th className="text-center">Fecha Pago</th>
              </tr>
            </thead>
            <tbody>
              {response.corrida.map((row) => (
                <tr key={row.quincena}>
                  <td className="text-center fw-bold">{row.quincena}</td>
                  <td className="text-end">{formatCurrency(row.capital)}</td>
                  <td className="text-end text-muted">{formatCurrency(row.interes)}</td>
                  <td className="text-end text-primary fw-bold">{formatCurrency(row.pago)}</td>
                  <td className="text-end text-danger">{formatCurrency(row.saldo)}</td>
                  <td className="text-center text-muted">{localizeDate(row.fecha)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div className="card-footer bg-light p-4 text-end border-top">
          <h5 className="fw-bold mb-0 text-dark">
            Monto final estimado a pagar:{" "}
            <span className="text-primary fs-4">{formatCurrency(response.resumen.pagoTotal)}</span>
          </h5>
        </div>
      </div>

      {/* Resumen General Card */}
      <div className="card border-0 bg-light shadow-sm rounded-4 mb-4">
        <div className="card-body p-4">
          <div className="row g-3 text-center text-sm-start">
            <div className="col-6 col-md-3">
              <span className="text-muted small d-block mb-1">Monto del Préstamo</span>
              <span className="h5 fw-bold text-dark">{formatCurrency(response.montoPrestamo)}</span>
            </div>
            <div className="col-6 col-md-3">
              <span className="text-muted small d-block mb-1">Fecha Otorgamiento</span>
              <span className="h5 fw-bold text-dark">{localizeDate(response.fechaOtorgamiento)}</span>
            </div>
            <div className="col-6 col-md-3">
              <span className="text-muted small d-block mb-1">Plazo Estimado</span>
              <span className="h5 fw-bold text-dark">
                {response.mesesPagar} Meses {response.diasAdicionales > 0 && `+ ${response.diasAdicionales} días`}
              </span>
            </div>
            <div className="col-6 col-md-3">
              <span className="text-muted small d-block mb-1">Tasa Mensual</span>
              <span className="h5 fw-bold text-primary">{response.tasaInteresMensual}%</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
