<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_admin_roles(['admin', 'terapeuta', 'recepcion']);

$totalPacientes = (int) $pdo->query('SELECT COUNT(*) FROM pacientes')->fetchColumn();
$totalSolicitudesWeb = (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn();
$citasHoyStmt = $pdo->prepare('SELECT COUNT(*) FROM citas_clinicas WHERE fecha = CURDATE()');
$citasHoyStmt->execute();
$citasHoy = (int) $citasHoyStmt->fetchColumn();
$pagosPendientes = (int) $pdo->query("SELECT COUNT(*) FROM pagos WHERE estado = 'pendiente'")->fetchColumn();
$pagosPagados = (int) $pdo->query("SELECT COUNT(*) FROM pagos WHERE estado = 'pagado'")->fetchColumn();
$ingresoMes = (float) $pdo->query(
    "SELECT COALESCE(SUM(monto), 0)
     FROM pagos
     WHERE estado = 'pagado'
       AND YEAR(COALESCE(fecha_pago, creado_en)) = YEAR(CURDATE())
       AND MONTH(COALESCE(fecha_pago, creado_en)) = MONTH(CURDATE())"
)->fetchColumn();
$recordatoriosPendientes = (int) $pdo->query("SELECT COUNT(*) FROM recordatorios_whatsapp WHERE estado = 'pendiente'")->fetchColumn();

$citasEstadoRows = $pdo->query(
    'SELECT estado, COUNT(*) AS total FROM citas_clinicas GROUP BY estado'
)->fetchAll();
$estadoBase = [
    'programada' => 0,
    'confirmada' => 0,
    'atendida' => 0,
    'cancelada' => 0,
];
foreach ($citasEstadoRows as $estadoRow) {
    $estado = (string) ($estadoRow['estado'] ?? '');
    $total = (int) ($estadoRow['total'] ?? 0);
    if (!array_key_exists($estado, $estadoBase)) {
        $estadoBase[$estado] = 0;
    }
    $estadoBase[$estado] = $total;
}

$serviciosRows = $pdo->query(
    "SELECT CASE WHEN TRIM(COALESCE(servicio, '')) = '' THEN 'Sin servicio' ELSE servicio END AS servicio,
            COUNT(*) AS total
     FROM citas_clinicas
     GROUP BY servicio
     ORDER BY total DESC
     LIMIT 6"
)->fetchAll();

$citasSemanaRows = $pdo->query(
    "SELECT fecha, COUNT(*) AS total
     FROM citas_clinicas
     WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
     GROUP BY fecha"
)->fetchAll();
$semanaBase = [];
for ($i = 6; $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime('-' . $i . ' day'));
    $semanaBase[$fecha] = 0;
}
foreach ($citasSemanaRows as $semanaRow) {
    $fecha = (string) ($semanaRow['fecha'] ?? '');
    if (array_key_exists($fecha, $semanaBase)) {
        $semanaBase[$fecha] = (int) ($semanaRow['total'] ?? 0);
    }
}
$semanaLabels = [];
foreach (array_keys($semanaBase) as $fecha) {
    $semanaLabels[] = date('d/m', strtotime($fecha));
}
$semanaValues = array_values($semanaBase);

$estadoLabels = array_map(
    static fn (string $estado): string => ucfirst($estado),
    array_keys($estadoBase)
);
$estadoValues = array_values($estadoBase);

$servicioLabels = [];
$servicioValues = [];
foreach ($serviciosRows as $servicioRow) {
    $servicioLabels[] = (string) ($servicioRow['servicio'] ?? 'Sin servicio');
    $servicioValues[] = (int) ($servicioRow['total'] ?? 0);
}
if (!$servicioLabels) {
    $servicioLabels = ['Sin datos'];
    $servicioValues = [0];
}

$citasActivas = (int) ($estadoBase['programada'] ?? 0) + (int) ($estadoBase['confirmada'] ?? 0);

$proximasCitas = $pdo->query(
    "SELECT cc.fecha, cc.hora, cc.servicio, cc.profesional, p.nombres, p.apellidos
     FROM citas_clinicas cc
     INNER JOIN pacientes p ON p.id = cc.paciente_id
     WHERE CONCAT(cc.fecha, ' ', cc.hora) >= NOW()
     ORDER BY cc.fecha ASC, cc.hora ASC
     LIMIT 8"
)->fetchAll();

admin_header('Dashboard', 'dashboard', 'Vista general del consultorio.');
?>
<div class="row g-3 mb-4 dashboard-kpi">
  <div class="col-6 col-xl-2">
    <div class="admin-stat kpi-card kpi-1 p-3 h-100">
      <div class="label">Pacientes</div>
      <div class="value"><?= $totalPacientes ?></div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="admin-stat kpi-card kpi-2 p-3 h-100">
      <div class="label">Citas Hoy</div>
      <div class="value"><?= $citasHoy ?></div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="admin-stat kpi-card kpi-3 p-3 h-100">
      <div class="label">Citas Activas</div>
      <div class="value"><?= $citasActivas ?></div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="admin-stat kpi-card kpi-4 p-3 h-100">
      <div class="label">Pagos Pendientes</div>
      <div class="value"><?= $pagosPendientes ?></div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="admin-stat kpi-card kpi-5 p-3 h-100">
      <div class="label">Pagos Pagados</div>
      <div class="value"><?= $pagosPagados ?></div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="admin-stat kpi-card kpi-6 p-3 h-100">
      <div class="label">Ingreso Mes (USD)</div>
      <div class="value"><?= number_format($ingresoMes, 2) ?></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="admin-stat kpi-card kpi-7 p-3 h-100">
      <div class="label">Solicitudes Web</div>
      <div class="value"><?= $totalSolicitudesWeb ?></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="admin-stat kpi-card kpi-8 p-3 h-100">
      <div class="label">Recordatorios Pendientes</div>
      <div class="value"><?= $recordatoriosPendientes ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-xl-5">
    <section class="admin-card dashboard-chart-card p-3 p-lg-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">Distribucion por estado</h2>
        <span class="small text-secondary">Citas clinicas</span>
      </div>
      <div class="dashboard-chart-wrap">
        <canvas id="chartEstado"></canvas>
      </div>
    </section>
  </div>
  <div class="col-xl-7">
    <section class="admin-card dashboard-chart-card p-3 p-lg-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">Servicios mas solicitados</h2>
        <span class="small text-secondary">Top 6</span>
      </div>
      <div class="dashboard-chart-wrap">
        <canvas id="chartServicios"></canvas>
      </div>
    </section>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12">
    <section class="admin-card dashboard-chart-card p-3 p-lg-4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">Citas ultimos 7 dias</h2>
        <span class="small text-secondary">Tendencia diaria</span>
      </div>
      <div class="dashboard-chart-wrap dashboard-chart-wrap-lg">
        <canvas id="chartSemana"></canvas>
      </div>
    </section>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <section class="admin-card p-3 p-lg-4">
      <h2 class="h5 mb-3">Proximas citas</h2>
      <?php if (!$proximasCitas): ?>
      <p class="text-secondary mb-0">No hay citas programadas.</p>
      <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($proximasCitas as $cita): ?>
        <div class="list-group-item px-0">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <div class="fw-semibold"><?= esc($cita['nombres'] . ' ' . $cita['apellidos']) ?></div>
              <small class="text-secondary">
                <?= esc($cita['servicio']) ?> | <?= esc($cita['profesional']) ?>
              </small>
            </div>
            <span class="badge badge-soft">
              <?= esc((string) $cita['fecha']) ?> <?= esc(substr((string) $cita['hora'], 0, 5)) ?>
            </span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') {
    return;
  }

  const estadoLabels = <?= json_encode($estadoLabels, JSON_UNESCAPED_UNICODE) ?>;
  const estadoValues = <?= json_encode($estadoValues, JSON_UNESCAPED_UNICODE) ?>;
  const servicioLabels = <?= json_encode($servicioLabels, JSON_UNESCAPED_UNICODE) ?>;
  const servicioValues = <?= json_encode($servicioValues, JSON_UNESCAPED_UNICODE) ?>;
  const semanaLabels = <?= json_encode($semanaLabels, JSON_UNESCAPED_UNICODE) ?>;
  const semanaValues = <?= json_encode($semanaValues, JSON_UNESCAPED_UNICODE) ?>;

  const baseOptions = {
    plugins: {
      legend: {
        labels: {
          color: '#bfd0e6',
          boxWidth: 12,
          usePointStyle: true,
          pointStyle: 'circle'
        }
      }
    }
  };

  const estadoCanvas = document.getElementById('chartEstado');
  if (estadoCanvas) {
    new Chart(estadoCanvas, {
      type: 'doughnut',
      data: {
        labels: estadoLabels,
        datasets: [{
          data: estadoValues,
          backgroundColor: ['#23c55e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'],
          borderColor: '#0f2034',
          borderWidth: 2
        }]
      },
      options: {
        ...baseOptions,
        maintainAspectRatio: false,
        cutout: '62%'
      }
    });
  }

  const serviciosCanvas = document.getElementById('chartServicios');
  if (serviciosCanvas) {
    new Chart(serviciosCanvas, {
      type: 'bar',
      data: {
        labels: servicioLabels,
        datasets: [{
          data: servicioValues,
          borderRadius: 8,
          backgroundColor: ['#3b82f6', '#14b8a6', '#f59e0b', '#ef4444', '#8b5cf6', '#0ea5e9']
        }]
      },
      options: {
        ...baseOptions,
        indexAxis: 'y',
        maintainAspectRatio: false,
        scales: {
          x: {
            beginAtZero: true,
            ticks: { color: '#9fb4cc', precision: 0 },
            grid: { color: 'rgba(159, 180, 204, 0.15)' }
          },
          y: {
            ticks: { color: '#c9d7e8' },
            grid: { display: false }
          }
        }
      }
    });
  }

  const semanaCanvas = document.getElementById('chartSemana');
  if (semanaCanvas) {
    new Chart(semanaCanvas, {
      type: 'line',
      data: {
        labels: semanaLabels,
        datasets: [{
          label: 'Citas',
          data: semanaValues,
          borderColor: '#22d3ee',
          backgroundColor: 'rgba(34, 211, 238, 0.2)',
          fill: true,
          borderWidth: 2.5,
          tension: 0.35,
          pointRadius: 3,
          pointBackgroundColor: '#22d3ee'
        }]
      },
      options: {
        ...baseOptions,
        maintainAspectRatio: false,
        scales: {
          x: {
            ticks: { color: '#9fb4cc' },
            grid: { color: 'rgba(159, 180, 204, 0.15)' }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#9fb4cc', precision: 0 },
            grid: { color: 'rgba(159, 180, 204, 0.15)' }
          }
        }
      }
    });
  }
});
</script>
<?php admin_footer(); ?>
