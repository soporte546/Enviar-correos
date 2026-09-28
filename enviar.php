<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

// ============================================================
// CARGA DE VARIABLES DESDE .env (sin dependencias extra)
// ============================================================
function cargarEnv(string $ruta): void
{
    if (!file_exists($ruta)) {
        exit("ERROR: No se encontró el archivo .env en: $ruta\n" .
             "Crea el archivo .env con tus credenciales (guiate de .env.example)\n");
    }

    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || strpos($linea, '#') === 0) continue;
        if (strpos($linea, '=') === false) continue;

        list($clave, $valor) = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);

        if (strlen($valor) >= 2) {
            $primera = $valor[0];
            $ultima  = substr($valor, -1);
            if (($primera === '"' && $ultima === '"') || ($primera === "'" && $ultima === "'")) {
                $valor = substr($valor, 1, -1);
            }
        }
        $_ENV[$clave] = $valor;
    }
}

function env(string $clave, string $porDefecto = ''): string
{
    return isset($_ENV[$clave]) ? $_ENV[$clave] : $porDefecto;
}

// ============================================================
// NUEVO: LEE UN ARCHIVO DE CORREOS (uno por línea, ignora #)
// ============================================================
function leerCorreosDesdeArchivo(string $ruta): array
{
    if (!file_exists($ruta)) {
        return [];
    }
    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $correos = array_map('trim', $lineas);
    $correos = array_filter($correos, function ($l) {
        return $l !== '' && strpos($l, '#') !== 0;
    });
    return array_values(array_unique($correos)); // quita duplicados
}

cargarEnv(__DIR__ . '/.env');

// ============ CONFIGURACIÓN SMTP (desde .env) ============
 $SMTP_HOST    = env('MAIL_HOST', 'smtp.gmail.com');
 $SMTP_PORT    = (int) env('MAIL_PORT', '587');
 $SMTP_USER    = env('MAIL_USERNAME');
 $SMTP_PASS    = env('MAIL_PASSWORD');
 $SMTP_ENC     = env('MAIL_ENCRYPTION', 'tls');
 $FROM_ADDRESS = env('MAIL_FROM_ADDRESS', $SMTP_USER);
 $FROM_NAME    = env('MAIL_FROM_NAME', 'Soporte Arlo Capital');

if ($SMTP_USER === '' || $SMTP_PASS === '') {
    exit("ERROR: Falta MAIL_USERNAME o MAIL_PASSWORD en el archivo .env\n");
}

// ============ DATOS DEL CORREO ============
 $asunto  = 'Validacion de Inventario por Usuario';
 $rutaPDF = __DIR__ . '/Instructivo.pdf'; // ← nombre real de tu PDF

// ============ DESTINATARIOS PRINCIPALES (TO) ============
 $destinatarios = [
    // 'persona1@empresa.com',
];

if (file_exists(__DIR__ . '/correos.txt')) {
    $destinatarios = leerCorreosDesdeArchivo(__DIR__ . '/correos.txt');
}

if (empty($destinatarios)) {
    exit("No hay destinatarios. Agrégalos al array o crea correos.txt\n");
}

// ============================================================
// NUEVO: COPIAS (CC) desde CC_correo.txt
// ============================================================
 $rutaCC          = __DIR__ . '/CC_correo.txt';
 $ccDestinatarios = leerCorreosDesdeArchivo($rutaCC);

if (empty($ccDestinatarios)) {
    echo "⚠ Aviso: No se encontró (o está vacío) CC_correo.txt — los correos saldrán sin copia.\n\n";
} else {
    // Si alguien ya es destinatario principal, no lo duplicamos como CC
    $ccDestinatarios = array_values(array_filter($ccDestinatarios, function ($cc) use ($destinatarios) {
        return !in_array($cc, $destinatarios, true);
    }));
    echo "Se enviará copia (CC) a: " . implode(', ', $ccDestinatarios) . "\n\n";
}

// ============ CUERPO DEL CORREO ============
 $cuerpo = <<<HTML
<html>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.5;">

<p>Buenos días compañero, como lo mencionamos en el correo gral., para poder sacar los datos de tu equipo, el proceso será el siguiente:</p>

<p><strong>1.-</strong> Entrar a la liga de Drive y descargar los 2 archivos, <strong>Ejecutar.bat</strong> e <strong>Inventario-Laptop-v6.ps1</strong>:<br>
<a href="https://drive.google.com/drive/folders/17ECiUljsWK84R5cWKkwp-CFDKTxjNV8S?usp=sharing">Carpeta de Drive</a></p>

<p><strong>2.-</strong> Con doble click abrir el archivo de <strong>Ejecutar</strong>, saldrá una pantalla de permisos, darle en <strong>Sí</strong>. Cuando termine el proceso, se va a generar un archivo en el escritorio de Block de Notas con un nombre similar a <strong>Inventario LAPTOP-******</strong>; en su contenido está la info que necesitamos. Ya pueden cerrar las ventanas negras en este punto.</p>

<p><strong>3.-</strong> Con el archivo generado en el escritorio, lo abres y con esa información llenas el formulario.</p>

<p><strong>4.- Llenado del formulario:</strong><br>
<a href="https://docs.google.com/forms/d/e/1FAIpQLSfmtQop_R9csipZB9My1nER9mRhutEpQz0sp374NierIuuGdA/viewform?usp=dialog">Abrir formulario</a></p>

<p>De igual manera adjuntamos un instructivo en PDF para más información. Cualquier cosa estamos pendientes a este correo o, para más agilidad, por WhatsApp conmigo o con Omar. Gracias por tu apoyo.</p>

<p>Adicional a este correo, para mantener un ritmo de trabajo y no mezclar información, en este punto mantendremos separados los datos de <strong>celulares</strong> en otro formulario para organizar mejor la información. Una vez concluyamos con los equipos, les haremos llegar el otro formulario, más corto.</p>

<p>Saludos!!</p>
</body>
</html>
HTML;

// ============ ENVÍO ============
 $total = count($destinatarios);
 $exitosos = [];
 $fallos = [];

echo "Enviando a $total destinatarios...\n\n";

foreach ($destinatarios as $i => $email) {
    echo "[" . ($i + 1) . "/$total] $email ... ";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USER;
        $mail->Password   = $SMTP_PASS;
        $mail->SMTPSecure = ($SMTP_ENC === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($FROM_ADDRESS, $FROM_NAME);
        $mail->addAddress($email);

        // ===== NUEVO: copia (CC) a todos los del archivo CC_correo.txt =====
        foreach ($ccDestinatarios as $cc) {
            $mail->addCC($cc);
        }

        if (file_exists($rutaPDF)) {
            $mail->addAttachment($rutaPDF);
        } else {
            echo "\n  ⚠ ADVERTENCIA: no se encontró el PDF en: $rutaPDF\n";
        }

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo;
        $mail->AltBody = "Para ver este correo correctamente usa un cliente compatible con HTML.";

        $mail->send();
        echo "OK\n";
        $exitosos[] = $email;
    } catch (Exception $e) {
        echo "FALLO ({$mail->ErrorInfo})\n";
        $fallos[$email] = $mail->ErrorInfo;
    }

    // Pausa entre correos para que Gmail no bloquee el envío
    if ($i < $total - 1) {
        sleep(3);
    }
}

// ============ RESUMEN ============
echo "\n========== RESUMEN ==========\n";
echo "Enviados correctamente: " . count($exitosos) . "\n";
echo "Copias (CC) en cada correo: " . count($ccDestinatarios) . "\n";
echo "Fallidos: " . count($fallos) . "\n";
foreach ($fallos as $email => $err) {
    echo "  - $email → $err\n";
}