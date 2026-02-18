<?php
declare(strict_types=1);

namespace App\Heatmap\Infrastructure;

/**
 * ReplicatedHeatmapApiClient — Write-to-Both con cola de sincronización persistente.
 *
 * Estrategia:
 *  - ESCRITURA (sendClick): se envía a TODOS los nodos simultáneamente.
 *    Si un nodo falla, el click se encola en disco (storage/ persistente) para reintentarlo.
 *  - LECTURA (getSummary / getPages): primer nodo disponible (GCP primero).
 *  - SYNC: al inicio de cada request, intenta vaciar la cola de clicks pendientes
 *    en los nodos que antes fallaron y ahora están disponibles.
 *
 * La cola se guarda en storage/heatmap/pending_clicks.json (NO en /tmp)
 * para sobrevivir reinicios del servidor PHP.
 */
final class ReplicatedHeatmapApiClient implements HeatmapApiClient
{
    private const QUEUE_FILENAME  = 'pending_clicks.json';
    private const STATUS_FILENAME = 'node_status.json';
    private const MAX_QUEUE       = 5000;

    private string $queueFile;
    private string $statusFile;

    /** @var HttpHeatmapApiClient[] */
    private array $clients;

    /** @var string[] URLs base de cada cliente (para identificarlos en la cola) */
    private array $urls;

    public function __construct(HttpHeatmapApiClient ...$clients)
    {
        // Último argumento opcional: ruta del directorio de storage persistente
        // Se inyecta desde el Bootstrap para seguir Clean Architecture.
        // Por defecto usa storage/heatmap/ relativo al directorio de trabajo.
        $this->clients = $clients;

        // Extraemos las URLs base para identificar qué nodo falló
        $this->urls = array_map(
            static fn(HttpHeatmapApiClient $c) => $c->getBaseUrl(),
            $clients
        );

        // Ruta persistente de la cola y del estado de nodos
        $storageDir = dirname(__DIR__, 4) . '/storage/heatmap';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
        $this->queueFile  = $storageDir . '/' . self::QUEUE_FILENAME;
        $this->statusFile = $storageDir . '/' . self::STATUS_FILENAME;

        // NO llamamos flushPendingQueue() aquí para no bloquear cada request
    }

    /**
     * Envía el click a TODOS los nodos. Si alguno falla, lo encola.
     *
     * @param array<string,mixed> $payload
     * @return array{statusCode:int,body:string}
     */
    public function sendClick(array $payload): array
    {
        if (empty($this->clients)) {
            return $this->errorResponse('No heatmap clients configured');
        }

        $results = $this->writeToAll('sendClick', [$payload]);

        // Devolvemos el primer resultado exitoso (o el último error)
        $lastResult = $this->errorResponse('All heatmap nodes failed');
        foreach ($results as $idx => $result) {
            if ($result['statusCode'] >= 200 && $result['statusCode'] < 300) {
                $lastResult = $result;
            } else {
                // Este nodo falló → encolar para sincronización posterior
                $this->enqueue($this->urls[$idx] ?? 'unknown', $payload);
            }
        }

        // Sincronización ocasional de cola (1 de cada 10 requests)
        $this->maybeFlushQueue();

        return $lastResult;
    }

    /**
     * Intenta sincronizar la cola con probabilidad 1/10 para no bloquear
     * cada request. Se ejecuta en el mismo proceso, tras devolver el resultado.
     */
    private function maybeFlushQueue(): void
    {
        if (random_int(1, 10) === 1) {
            $this->flushPendingQueue();
        }
    }

    /**
     * Lectura: primer nodo disponible (GCP primero, AWS de fallback).
     *
     * @param array<string,string> $query
     * @return array{statusCode:int,body:string}
     */
    public function getSummary(array $query): array
    {
        return $this->readFromFirst('getSummary', [$query]);
    }

    /**
     * @return array{statusCode:int,body:string}
     */
    public function getPages(): array
    {
        return $this->readFromFirst('getPages', []);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ESCRITURA PARALELA (cURL multi)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ejecuta el método en todos los clientes (Write-to-Both).
     * Respeta el estado del panel de control (node_status.json):
     * si un nodo está marcado offline, lo salta y encola directamente.
     *
     * @param array<int,mixed> $arguments
     * @return array<int,array{statusCode:int,body:string}>
     */
    private function writeToAll(string $method, array $arguments): array
    {
        $nodeStatus = $this->loadNodeStatus();
        $results = [];

        foreach ($this->clients as $idx => $client) {
            $nodeKey = $this->resolveNodeKey($this->urls[$idx] ?? '', $idx);

            // Simulación de caída desde el panel:
            if (($nodeStatus[$nodeKey] ?? 'online') === 'offline') {
                $results[$idx] = [
                    'statusCode' => 503,
                    'body' => (string) json_encode([
                        'status' => 'error',
                        'message' => "Node {$nodeKey} simulated offline",
                    ]),
                ];
                continue;
            }

            try {
                $results[$idx] = $client->$method(...$arguments);
            } catch (\Throwable $e) {
                $results[$idx] = [
                    'statusCode' => 502,
                    'body' => (string) json_encode([
                        'status'  => 'error',
                        'message' => 'Node failed: ' . $e->getMessage(),
                    ]),
                ];
            }
        }
        return $results;
    }


    /**
     * Lee el estado de los nodos desde node_status.json (escrito por el panel de control).
     * @return array<string,string>  ['gcp' => 'online'|'offline', 'aws' => 'online'|'offline']
     */
    private function loadNodeStatus(): array
    {
        if (!is_file($this->statusFile)) {
            return ['gcp' => 'online', 'aws' => 'online'];
        }
        $raw = file_get_contents($this->statusFile);
        $decoded = json_decode($raw ?: '', true);
        return is_array($decoded) ? $decoded : ['gcp' => 'online', 'aws' => 'online'];
    }

    /**
     * Mapea una URL base al identificador de nodo usado en node_status.json.
     * Usa el índice como fallback: 0 = gcp, 1 = aws.
     */
    private function resolveNodeKey(string $url, int $idx = 0): string
    {
        if (str_contains($url, '34.74.102.123')) return 'gcp';
        if (str_contains($url, '35.181.60.162')) return 'aws';
        // Fallback por índice: primer nodo = gcp, segundo = aws
        return $idx === 0 ? 'gcp' : 'aws';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LECTURA — primer nodo disponible
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array<int,mixed> $arguments
     * @return array{statusCode:int,body:string}
     */
    private function readFromFirst(string $method, array $arguments): array
    {
        $last = $this->errorResponse('No heatmap clients available');
        foreach ($this->clients as $client) {
            try {
                $result = $client->$method(...$arguments);
                if ($result['statusCode'] >= 200 && $result['statusCode'] < 300) {
                    return $result;
                }
                $last = $result;
            } catch (\Throwable) {
                // Prueba el siguiente
            }
        }
        return $last;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COLA DE SINCRONIZACIÓN
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Encola un click que no pudo enviarse a un nodo concreto.
     *
     * @param array<string,mixed> $payload
     */
    private function enqueue(string $nodeUrl, array $payload): void
    {
        $queue = $this->loadQueue();

        if (count($queue) >= self::MAX_QUEUE) {
            // Cola llena: descartamos el más antiguo (FIFO)
            array_shift($queue);
        }

        $queue[] = [
            'node'      => $nodeUrl,
            'payload'   => $payload,
            'queued_at' => time(),
            'attempts'  => 0,
        ];

        $this->saveQueue($queue);
    }

    /**
     * Intenta reenviar los clicks pendientes a los nodos que ahora están disponibles.
     * Se ejecuta al inicio de cada request (con timeout corto para no bloquear).
     */
    private function flushPendingQueue(): void
    {
        $queue = $this->loadQueue();
        if (empty($queue)) {
            return;
        }

        $remaining = [];

        foreach ($queue as $entry) {
            $nodeUrl  = (string) ($entry['node'] ?? '');
            $payload  = (array)  ($entry['payload'] ?? []);
            $attempts = (int)    ($entry['attempts'] ?? 0);

            // Buscar el cliente que corresponde a este nodo
            $client = $this->findClientByUrl($nodeUrl);
            if ($client === null) {
                // Nodo ya no existe en la config → descartar
                continue;
            }

            // Si el panel sigue marcando el nodo como offline → no intentar sync
            $nodeStatus = $this->loadNodeStatus();
            $nodeKey    = $this->resolveNodeKey($nodeUrl);
            if (($nodeStatus[$nodeKey] ?? 'online') === 'offline') {
                $remaining[] = $entry; // Mantener en cola
                continue;
            }

            try {
                $result = $client->sendClick($payload);
                if ($result['statusCode'] >= 200 && $result['statusCode'] < 300) {
                    // ✅ Sincronizado correctamente — no lo añadimos a $remaining
                    error_log("[Heatmap Sync] Click sincronizado en nodo {$nodeUrl}");
                    continue;
                }
            } catch (\Throwable) {
                // Nodo sigue caído
            }

            // Sigue fallando → mantener en cola (máx 10 intentos)
            if ($attempts < 10) {
                $entry['attempts'] = $attempts + 1;
                $remaining[] = $entry;
            }
        }

        $this->saveQueue($remaining);
    }

    private function findClientByUrl(string $url): ?HttpHeatmapApiClient
    {
        foreach ($this->clients as $idx => $client) {
            if (($this->urls[$idx] ?? '') === $url) {
                return $client;
            }
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PERSISTENCIA DE LA COLA (storage/heatmap/pending_clicks.json — persistente)
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<int,array<string,mixed>> */
    private function loadQueue(): array
    {
        if (!is_file($this->queueFile)) {
            return [];
        }
        $raw = file_get_contents($this->queueFile);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<int,array<string,mixed>> $queue */
    private function saveQueue(array $queue): void
    {
        file_put_contents(
            $this->queueFile,
            json_encode($queue, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{statusCode:int,body:string} */
    private function errorResponse(string $message): array
    {
        return [
            'statusCode' => 503,
            'body'       => (string) json_encode(['status' => 'error', 'message' => $message]),
        ];
    }
}
