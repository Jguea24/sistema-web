<?php

declare(strict_types=1);

/**
 * @return array<int, array{at:DateTime,type:string}>
 */
function build_default_reminder_slots(DateTime $appointmentAt, DateTime $now, bool $includeImmediate = false): array
{
    $slots = [];

    if ($includeImmediate && $appointmentAt > $now) {
        $slots[] = [
            'at' => (clone $now)->modify('+1 minute'),
            'type' => 'confirmacion_inmediata',
        ];
    }

    $candidate24h = (clone $appointmentAt)->modify('-24 hours');
    $candidate2h = (clone $appointmentAt)->modify('-2 hours');

    if ($candidate24h > $now) {
        $slots[] = ['at' => $candidate24h, 'type' => 'recordatorio_24h'];
    }

    if ($candidate2h > $now) {
        $slots[] = ['at' => $candidate2h, 'type' => 'recordatorio_2h'];
    }

    // Si la cita es cercana y ya pasaron -24h/-2h, crear un recordatorio util cercano.
    if ($appointmentAt > $now && $candidate24h <= $now && $candidate2h <= $now) {
        $fallback = (clone $appointmentAt)->modify('-15 minutes');
        if ($fallback <= $now) {
            $fallback = (clone $now)->modify('+1 minute');
        }
        $slots[] = ['at' => $fallback, 'type' => 'recordatorio_cercano'];
    }

    return $slots;
}

function build_whatsapp_message_for_slot(array $cita, DateTime $appointmentAt, string $slotType): string
{
    $nombre = trim((string) ($cita['nombres'] ?? 'Paciente'));
    $servicio = (string) ($cita['servicio'] ?? 'Consulta');
    $profesional = (string) ($cita['profesional'] ?? 'Profesional');
    $modalidad = (string) ($cita['modalidad'] ?? 'presencial');
    $fecha = $appointmentAt->format('d/m/Y');
    $hora = $appointmentAt->format('H:i');

    if ($slotType === 'confirmacion_inmediata') {
        return "Hola {$nombre}, tu cita en PsicoBienestar ha sido confirmada.\n"
            . "Servicio: {$servicio}\n"
            . "Profesional: {$profesional}\n"
            . "Fecha: {$fecha}\n"
            . "Hora: {$hora}\n"
            . "Modalidad: {$modalidad}\n"
            . "Si necesitas reprogramar, responde a este mensaje.";
    }

    $titulo = 'Recordatorio de cita';
    if ($slotType === 'recordatorio_24h') {
        $titulo = 'Recordatorio 24 horas antes';
    } elseif ($slotType === 'recordatorio_2h') {
        $titulo = 'Recordatorio 2 horas antes';
    } elseif ($slotType === 'recordatorio_cercano') {
        $titulo = 'Recordatorio cercano';
    }

    return "{$titulo} - PsicoBienestar\n"
        . "Hola {$nombre}, te esperamos en tu cita.\n"
        . "Servicio: {$servicio}\n"
        . "Profesional: {$profesional}\n"
        . "Fecha: {$fecha}\n"
        . "Hora: {$hora}\n"
        . "Modalidad: {$modalidad}\n"
        . "Si necesitas reprogramar, responde a este mensaje.";
}

/**
 * Crea recordatorios para una cita concreta.
 */
function create_reminders_for_cita(
    PDO $pdo,
    int $citaId,
    bool $skipIfExists = true,
    bool $includeImmediateConfirmation = false
): int {
    $existingTypes = [];
    if ($skipIfExists) {
        $existsStmt = $pdo->prepare('SELECT tipo FROM recordatorios_whatsapp WHERE cita_clinica_id = ?');
        $existsStmt->execute([$citaId]);
        $existingRows = $existsStmt->fetchAll();
        foreach ($existingRows as $existingRow) {
            $existingType = trim((string) ($existingRow['tipo'] ?? ''));
            if ($existingType !== '') {
                $existingTypes[] = $existingType;
            }
        }
    }

    $stmt = $pdo->prepare(
        "SELECT cc.id, cc.fecha, cc.hora, cc.servicio, cc.profesional, cc.modalidad, cc.estado,
                p.id AS paciente_id, p.nombres, p.apellidos, p.telefono
         FROM citas_clinicas cc
         INNER JOIN pacientes p ON p.id = cc.paciente_id
         WHERE cc.id = ?
         LIMIT 1"
    );
    $stmt->execute([$citaId]);
    $row = $stmt->fetch();

    if (!$row) {
        return 0;
    }

    $estado = (string) $row['estado'];
    if (!in_array($estado, ['programada', 'confirmada'], true)) {
        return 0;
    }

    $telefono = trim((string) $row['telefono']);
    if ($telefono === '') {
        return 0;
    }

    $appointmentAt = new DateTime((string) $row['fecha'] . ' ' . (string) $row['hora']);
    $now = new DateTime();
    $slots = build_default_reminder_slots($appointmentAt, $now, $includeImmediateConfirmation);
    if (!$slots) {
        return 0;
    }

    $insert = $pdo->prepare(
        'INSERT INTO recordatorios_whatsapp (cita_clinica_id, paciente_id, tipo, telefono_destino, mensaje, programado_para, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $created = 0;
    foreach ($slots as $slot) {
        $slotType = (string) $slot['type'];
        if ($skipIfExists && in_array($slotType, $existingTypes, true)) {
            continue;
        }
        /** @var DateTime $slotAt */
        $slotAt = $slot['at'];
        $mensaje = build_whatsapp_message_for_slot($row, $appointmentAt, $slotType);

        $insert->execute([
            (int) $row['id'],
            (int) $row['paciente_id'],
            $slotType,
            $telefono,
            $mensaje,
            $slotAt->format('Y-m-d H:i:s'),
            'pendiente',
        ]);
        $existingTypes[] = $slotType;
        $created++;
    }

    return $created;
}

/**
 * @return array{citas:int,recordatorios:int}
 */
function backfill_missing_reminders(PDO $pdo, int $limit = 100): array
{
    $stmt = $pdo->prepare(
        "SELECT cc.id
         FROM citas_clinicas cc
         INNER JOIN pacientes p ON p.id = cc.paciente_id
         WHERE CONCAT(cc.fecha, ' ', cc.hora) > NOW()
           AND cc.estado IN ('programada', 'confirmada')
           AND TRIM(COALESCE(p.telefono, '')) <> ''
         ORDER BY cc.fecha ASC, cc.hora ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $citas = $stmt->fetchAll();

    $citasEvaluadas = 0;
    $recordatoriosCreados = 0;
    foreach ($citas as $cita) {
        $citasEvaluadas++;
        $recordatoriosCreados += create_reminders_for_cita($pdo, (int) $cita['id'], true, false);
    }

    return [
        'citas' => $citasEvaluadas,
        'recordatorios' => $recordatoriosCreados,
    ];
}
