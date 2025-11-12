<?php
// views/panel-coderabbit.php
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-14 days'));
$to   = $_GET['to']   ?? date('Y-m-d');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$endpoint = sprintf(
    '%s://%s/api/coderabbit-report.php?%s',
    $scheme,
    $host,
    http_build_query(['from' => $from, 'to' => $to])
);

// Llama al backend local
$ctx = stream_context_create(["http" => ["timeout" => 15]]);
$json = @file_get_contents($endpoint, false, $ctx);
$data = $json ? json_decode($json, true) : null;

function md_to_html($s)
{
    if (!is_string($s)) return '';
    // conversión mínima para demo
    $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
    $s = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $s);
    $s = preg_replace('/\n- /', '<br>• ', $s);
    $s = nl2br($s);
    return $s;
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>CodeRabbit – Reporte On-Demand</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: #0b0f14;
            color: #e6edf3;
            margin: 0
        }

        .wrap {
            max-width: 980px;
            margin: 40px auto;
            padding: 0 16px
        }

        h1 {
            font-size: 26px;
            margin: 0 0 8px
        }

        .sub {
            opacity: .8;
            margin-bottom: 24px
        }

        .row {
            display: grid;
            gap: 16px
        }

        .card {
            background: #11161c;
            border: 1px solid #1f2730;
            border-radius: 14px;
            padding: 16px
        }

        .tag {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 999px;
            border: 1px solid #2a3542;
            display: inline-block;
            margin-right: 8px
        }

        .muted {
            opacity: .8
        }

        .err {
            background: #1d0f12;
            border-color: #5a1f28
        }

        a {
            color: #7ab7ff;
            text-decoration: none
        }

        .head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 0
        }

        input,
        button {
            background: #0b0f14;
            border: 1px solid #2a3542;
            color: #e6edf3;
            border-radius: 10px;
            padding: 8px 10px
        }

        button {
            cursor: pointer
        }

        .group {
            font-weight: 700;
            margin-bottom: 6px
        }
    </style>
</head>

<body>
    <div class="wrap">
        <h1>CodeRabbit – Reporte On-Demand</h1>
        <div class="sub">Rango: <span class="tag"><?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?></span></div>

        <form class="head" method="get">
            <div>
                <label>Desde: <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
                <label>Hasta: <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
            </div>
            <button type="submit">Actualizar</button>
        </form>

        <?php if (!$data || isset($data['error'])): ?>
            <div class="card err">
                <strong>Sin datos del API.</strong>
                <div class="muted">
                    <?= htmlspecialchars($data['error'] ?? 'Error desconocido') ?><br>
                    <?php if (isset($data['body'])) echo '<small>' . htmlspecialchars(json_encode($data['body'])) . '</small>'; ?>
                </div>
                <div class="muted">Revisa que <code>CODERABBIT_API_KEY</code> esté configurada y tu plan tenga acceso a Reports.</div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($data as $block): ?>
                    <div class="card">
                        <div class="group"><?= htmlspecialchars($block['group'] ?? 'Report') ?></div>
                        <div><?= md_to_html($block['report'] ?? '') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="muted" style="margin-top:18px">
                Fuente: API <code>report.generate</code> de CodeRabbit (Pro).
                Verifica PRs activos en tu organización para obtener contenido del informe.
            </p>
        <?php endif; ?>
    </div>
</body>

</html>
