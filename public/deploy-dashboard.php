<?php
/**
 * 🛰️ SENTINEL COMMAND v8.0 | ULTI-SIDEBAR VERSION
 * Diseño Lateral Profesional - Optimizado para monitorización de procesos.
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE); 
session_start();
set_time_limit(0);

// Cargar .env si existe para evitar credenciales hardcodeadas
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with($line, '#')) continue;
        list($key, $value) = explode('=', $line, 2) + [null, null];
        if ($key) {
            putenv(trim($key) . "=" . trim($value));
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$ssh_user = getenv('DEPLOY_SSH_USER') ?: "";
$ssh_host = getenv('DEPLOY_SSH_HOST') ?: "";
$ssh_port = getenv('DEPLOY_SSH_PORT') ?: "";
$ssh_pass = getenv('DEPLOY_SSH_PASS') ?: "";

if (empty($ssh_user) || empty($ssh_pass)) {
    die("Error: Credenciales de despliegue no configuradas. Por favor, añada DEPLOY_SSH_USER y DEPLOY_SSH_PASS al archivo .env");
}


// SEGURIDAD: Si no hay una sesión activa o una IP permitida, podrías bloquear el acceso aquí.
// Por ahora, al menos eliminamos las claves del código fuente público.


if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $backup = $_GET['backup'] ?? '';
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    $script_path = strpos($action, 'rollback-') === 0 
        ? realpath(__DIR__ . '/../bin/rollback.sh') 
        : realpath(__DIR__ . '/../bin/deploy-hostinger.sh');
    $target = str_replace('rollback-', '', $action);
    $command = "bash \"$script_path\" $target \"$backup\" 2>&1";

    $handle = popen($command, 'r');
    if ($handle) {
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line) {
                echo "data: " . json_encode($line) . "\n\n";
                @ob_flush(); flush();
            }
        }
        pclose($handle);
    }
    echo "data: [DONE]\n\n";
    exit;
}

function getBackups($entorno, $u, $h, $p, $pass) {
    try {
        $base = ($entorno === 'prod') ? "iamasterbigschool" : "clean-marvel-staging";
        $cmd = "sshpass -p '$pass' ssh -p '$p' -q -o StrictHostKeyChecking=no $u@$h 'ls -t domains/contenido.creawebes.com/public_html/$base/deploy_backups/backup_*.zip 2>/dev/null | head -n 12'";
        $out = @shell_exec($cmd);
        if (!$out) return [];
        return array_filter(array_map('basename', explode("\n", trim($out))));
    } catch (Exception $e) { return []; }
}

$back_staging = getBackups('staging', $ssh_user, $ssh_host, $ssh_port, $ssh_pass);
$back_prod = getBackups('prod', $ssh_user, $ssh_host, $ssh_port, $ssh_pass);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sentinel v8 | Command Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { 
            --bg: #020617; 
            --sidebar: #0b0f1a; 
            --card: #111827; 
            --border: rgba(255,255,255,0.06); 
            --primary: #38bdf8; 
            --secondary: #818cf8;
            --text: #f1f5f9; 
            --muted: #64748b; 
            --success: #10b981; 
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body { 
            background: var(--bg); 
            color: var(--text); 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* --- NAVBAR --- */
        .nav { 
            height: 60px;
            padding: 0 1.5rem; 
            border-bottom: 1px solid var(--border); 
            display:flex; 
            justify-content:space-between; 
            align-items:center; 
            background: rgba(2,6,23,0.9); 
            backdrop-filter:blur(10px); 
            z-index: 100;
        }
        .logo { font-weight:800; font-size:1rem; letter-spacing:-0.02em; display:flex; align-items:center; gap:8px; }
        .logo span { color: var(--primary); }
        .live-status { font-size:0.65rem; color:var(--success); font-weight:800; display:flex; align-items:center; gap:6px; letter-spacing: 0.1em; }

        /* --- LAYOUT --- */
        .layout { display: flex; flex: 1; overflow: hidden; }

        /* --- SIDEBAR --- */
        .sidebar { 
            width: 360px; 
            background: var(--sidebar); 
            border-right: 1px solid var(--border); 
            padding: 1.5rem; 
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .card { 
            background: var(--card); 
            border: 1px solid var(--border); 
            border-radius: 12px; 
            padding: 1.25rem; 
            transition: 0.3s;
        }
        .card:hover { border-color: rgba(255,255,255,0.1); }
        
        .tag { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em; margin-bottom: 0.25rem; display: block; }
        .card-title { font-size: 1rem; font-weight: 800; margin-bottom: 1rem; display: flex; align-items:center; gap: 8px; }
        .card-title .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--primary); }

        .btn { 
            width:100%; height:44px; border-radius:8px; font-weight:700; font-size:0.8rem; 
            cursor:pointer; border:none; transition:0.2s; display:flex; align-items:center; 
            justify-content:center; gap:8px; font-family:inherit; text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .btn-p { background: var(--primary); color: #000; margin-bottom: 0.75rem; }
        .btn-p:hover { background: #7dd3fc; transform: translateY(-1px); }
        .btn-s { background: var(--secondary); color: #fff; margin-bottom: 0.75rem; }
        .btn-s:hover { background: #a5b4fc; transform: translateY(-1px); }

        .links-row { display: flex; gap: 8px; margin-bottom: 1.5rem; }
        .links-row a { 
            flex: 1; text-decoration: none; background: rgba(255,255,255,0.03); 
            border: 1px solid var(--border); border-radius: 6px; color: var(--muted);
            font-size: 0.65rem; font-weight: 700; display: flex; align-items: center; 
            justify-content: center; height: 30px; transition: 0.2s;
        }
        .links-row a:hover { color: #fff; background: rgba(255,255,255,0.08); border-color: var(--primary); }

        .rollback-well { 
            background: rgba(0,0,0,0.3); border-radius:10px; padding: 1rem; border:1px solid var(--border); 
        }
        .well-label { font-size: 0.6rem; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem; display: block; }
        select { 
            width:100%; height:34px; background: #020617; border:1px solid var(--border); 
            border-radius:6px; color:#fff; padding:0 0.5rem; font-family:'JetBrains Mono'; 
            font-size:0.7rem; margin-bottom:0.75rem; outline: none;
        }
        .btn-rbk { background:transparent; color: #ef4444; border:1px solid rgba(239, 68, 68, 0.2); height:32px; font-size:0.7rem; font-weight: 800; }
        .btn-rbk:hover { background:rgba(239, 68, 68, 0.1); border-color:#ef4444; }

        /* --- MAIN CONTENT (LOGS) --- */
        .main-content { flex: 1; display: flex; flex-direction: column; background: var(--bg); position: relative; }
        
        .term-header { 
            padding: 0.75rem 1.5rem; 
            background: #0b0f1a; 
            border-bottom: 1px solid var(--border); 
            display: flex; justify-content: space-between; align-items: center; 
        }
        .term-title { font-family: 'JetBrains Mono'; font-size: 0.7rem; color: var(--muted); font-weight: 700; letter-spacing: 0.1em; }
        .btn-clear { background: transparent; border: 1px solid var(--border); color: var(--muted); font-size: 0.6rem; font-weight: 700; padding: 4px 12px; border-radius: 4px; cursor: pointer; transition: 0.2s; }
        .btn-clear:hover { color: #fff; border-color: var(--muted); }

        #log { 
            flex: 1; overflow-y: auto; padding: 1.5rem; font-family: 'JetBrains Mono', monospace; 
            font-size: 0.85rem; line-height: 1.6; color: rgba(255,255,255,0.7); 
            scroll-behavior: smooth;
        }
        
        /* Log Highlights */
        .log-line { margin-bottom: 4px; border-left: 2px solid transparent; padding-left: 10px; }
        .log-info { color: var(--primary); font-weight: 600; border-left-color: var(--primary); background: rgba(56, 189, 248, 0.05); padding: 8px 10px; margin: 10px 0; border-radius: 0 4px 4px 0; }
        .log-success { color: var(--success); font-weight: 800; border-left-color: var(--success); background: rgba(16, 185, 129, 0.05); padding: 8px 10px; margin: 10px 0; border-radius: 0 4px 4px 0; }
        .log-meta { color: var(--muted); opacity: 0.5; font-size: 0.75rem; margin-top: 20px; border-top: 1px dashed var(--border); padding-top: 10px; }

        /* Progress Bar Overlay */
        .progress-container { height: 4px; background: #020617; width: 100%; position: sticky; bottom: 0; }
        .progress-bar { height: 100%; background: var(--primary); width: 0%; transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 0 20px var(--primary); }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
    </style>
</head>
<body>

    <nav class="nav">
        <div class="logo">SENTINEL <span>COMMAND CENTER</span></div>
        <div class="live-status">● SYSTEM STABLE & ONLINE</div>
    </nav>

    <div class="layout">
        <aside class="sidebar">
            
            <!-- PRODUCTION NODE -->
            <div class="card">
                <span class="tag">Main Node</span>
                <div class="card-title"><div class="dot"></div> Production Hub</div>
                <button class="btn btn-p" onclick="run('prod')">Launch Deploy</button>
                
                <div class="links-row">
                    <a href="https://iamasterbigschool.contenido.creawebes.com" target="_blank">🌐 App</a>
                    <a href="https://iamasterbigschool.contenido.creawebes.com/presentation/tfm-presentation.html" target="_blank">📊 TFM</a>
                </div>

                <div class="rollback-well">
                    <span class="well-label">Historical Rollback</span>
                    <select id="sel-prod">
                        <option value="">Select Version...</option>
                        <?php foreach($back_prod as $b) echo "<option value='$b'>$b</option>"; ?>
                    </select>
                    <button class="btn btn-rbk" onclick="run('rollback-prod', 'sel-prod')">Restore Point</button>
                </div>
            </div>

            <!-- STAGING NODE -->
            <div class="card">
                <span class="tag">Mirror Node</span>
                <div class="card-title"><div class="dot" style="background:var(--secondary)"></div> Staging Mirror</div>
                <button class="btn btn-s" onclick="run('staging')">Sync Staging</button>

                <div class="links-row">
                    <a href="https://staging.contenido.creawebes.com" target="_blank">🌐 App</a>
                    <a href="https://staging.contenido.creawebes.com/presentation/tfm-presentation.html" target="_blank">📊 TFM</a>
                </div>

                <div class="rollback-well">
                    <span class="well-label">Historical Rollback</span>
                    <select id="sel-staging">
                        <option value="">Select Version...</option>
                        <?php foreach($back_staging as $b) echo "<option value='$b'>$b</option>"; ?>
                    </select>
                    <button class="btn btn-rbk" onclick="run('rollback-staging', 'sel-staging')">Restore Point</button>
                </div>
            </div>

        </aside>

        <main class="main-content">
            <div class="term-header">
                <div class="term-title">REAL-TIME TRANSMISSION STREAM</div>
                <button class="btn-clear" onclick="clearLogs()">CLEAR SESSION</button>
            </div>
            
            <div id="log">Awaiting command input...</div>
            
            <div class="progress-container">
                <div id="p-bar" class="progress-bar"></div>
            </div>
        </main>
    </div>

    <script>
        function updateProgress(p) { document.getElementById('p-bar').style.width = p + '%'; }
        function clearLogs() { 
            document.getElementById('log').innerHTML = 'Session cleared. Ready.';
            updateProgress(0);
        }

        function run(act, selId = null) {
            const log = document.getElementById('log');
            const btns = document.querySelectorAll('.btn');
            const version = selId ? document.getElementById(selId).value : '';

            if (act.includes('rollback') && !version) {
                alert('Please select a restoration point first.');
                return;
            }
            
            log.innerHTML += `<div class='log-meta'>NEW SESSION: ${act.toUpperCase()} [${new Date().toLocaleTimeString()}]</div>`;
            log.innerHTML += `<div class='log-info'>ESTABLISHING SECURE CONNECTION...</div>`;
            
            updateProgress(5);
            btns.forEach(b => b.disabled = true);

            const es = new EventSource(`?action=${act}&backup=${encodeURIComponent(version)}`);
            
            es.onmessage = e => {
                if (e.data === '[DONE]') {
                    es.close();
                    btns.forEach(b => b.disabled = false);
                    log.innerHTML += "<div class='log-success'>[SUCCESS] OPERATION FINALIZED SUCCESSFULLY.</div>";
                    updateProgress(100);
                    
                    // Actualización silenciosa de los desplegables
                    setTimeout(() => {
                        const parser = new DOMParser();
                        fetch(location.href).then(r => r.text()).then(html => {
                            const doc = parser.parseFromString(html, 'text/html');
                            document.getElementById('sel-prod').innerHTML = doc.getElementById('sel-prod').innerHTML;
                            document.getElementById('sel-staging').innerHTML = doc.getElementById('sel-staging').innerHTML;
                        });
                    }, 2000);
                    return;
                }

                let line = JSON.parse(e.data);
                
                // Progress Logic
                if(line.includes('[1/3]') || line.includes('Snapshot') || line.includes('Buscando')) updateProgress(20);
                if(line.includes('[2/3]') || line.includes('Sincronizando') || line.includes('Descomprimiendo')) updateProgress(50);
                if(line.includes('[3/3]') || line.includes('Microservicios') || line.includes('Integridad')) updateProgress(85);

                // Styling logic
                let cls = "log-line";
                if(line.includes('EXITOSAMENTE') || line.includes('FINALIZADO')) cls = "log-success";
                if(line.includes('INITIATING') || line.includes('Creando')) cls = "log-info";
                
                log.innerHTML += `<div class='${cls}'>${line}</div>`;
                log.scrollTop = log.scrollHeight;
            };

            es.onerror = () => { 
                es.close(); 
                btns.forEach(b => b.disabled = false); 
                updateProgress(0);
                log.innerHTML += `<div style='color:#ef4444; font-weight:800; padding:10px;'>[FATAL ERROR] CONNECTION LOST.</div>`;
            };
        }
    </script>
</body>
</html>
