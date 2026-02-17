# ADR-023: Implementación de Failover Multi-Cloud para Microservicios Críticos

## Estado
Propuesto

## Contexto
El microservicio Heatmap, aunque no es crítico para el funcionamiento básico de la aplicación, es una pieza clave de la analítica del proyecto. Actualmente reside exclusivamente en Google Cloud Platform (GCP). Una caída del proveedor o de la región dejaría al sistema sin capacidad de registro de interacciones.

## Decisión
Implementar un mecanismo de **Failover Activo-Pasivo** utilizando dos proveedores de nube distintos y regiones geográficamente separadas:

1.  **Primario (GCP)**: `us-east1-b` (Carolina del Sur, USA).
2.  **Secundario (AWS)**: `eu-west-3` (París, Francia).

### Detalles Técnicos
- Se ha replicado el microservicio Heatmap (Python/Flask + Docker) en una instancia EC2 (`t3.micro`) de AWS.
- Se ha implementado el patrón **Failover** en el cliente PHP mediante el nuevo `FailoverHeatmapApiClient`.
- El cliente intentará siempre el nodo primario (GCP). Si el código de respuesta es de error (5xx) o hay un timeout, reintentará automáticamente en el nodo secundario (AWS).

## Consecuencias
- **Alta Disponibilidad**: El sistema puede tolerar la caída completa de una región o un proveedor de nube.
- **Sincronización**: Al usar bases de datos locales (SQLite) en cada nodo, los datos de clics durante el periodo de failover residirán en AWS. Se requerirá un proceso de merge manual de los archivos `heatmap.db` si se desea consolidar la analítica tras una caída prolongada (aceptable dado el bajo volumen de datos).
- **Latencia**: En caso de failover, la latencia aumentará ligeramente debido al reintento, pero el usuario no percibirá fallo.

## Comparativa Multi-Cloud
| Característica | Google Cloud (Primario) | Amazon Web Services (Secundario) |
|---|---|---|
| **Región** | us-east1 (USA) | eu-west-3 (París) |
| **Instancia** | e2-micro | t3.micro |
| **Coste** | $0.00 (Free Tier) | $0.00 (Free Tier / Saldo) |
| **Estrategia** | Siempre activo | Fallback automático |
