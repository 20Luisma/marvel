# ADR-024 — Amazon Web Services: Auditoría FinOps y Security Hardening

**Fecha:** 17 de Febrero de 2026  
**Estado:** ✅ Implementada  
**Categoría:** Infraestructura / FinOps / Seguridad  
**Impacto:** Reducción de superficie de ataque, optimización de almacenamiento, gobernanza cloud

---

## Contexto

Como parte de la estrategia de **Alta Disponibilidad Multi-Cloud**, se ha desplegado una réplica del microservicio **Heatmap** en AWS (`eu-west-3`, París). El servicio corre sobre una instancia EC2 `t3.micro` con Docker.

Tras el despliegue inicial, se ha realizado una auditoría similar a la de GCP (ADR-022) para garantizar que el nodo de failover sea tan seguro y eficiente como el nodo primario.

---

## Auditoría realizada

### Inventario previo (AWS Console)

| Recurso | Estado antes | Problema |
|---------|-------------|----------|
| Instancia EC2 (`t3.micro`) | RUNNING | ✅ Correcto (Free Tier eligible) |
| Volumen EBS (8 GB, `gp2`) | SSD General Purpose | 🟠 Obsoleto: `gp3` es un 20% más barato y rinde mejor |
| Security Group `marvel-ec2-sg` | Puertos: 22, 80, 443, 8080, 3389 | 🔴 Demasiados puertos abiertos al mundo |
| Elastic IP | 1 asignada | 🟡 Coste si la instancia se detiene (~$0.005/h) |
| Etiquetado (Tagging) | Ninguno | 🔴 Falta de gobernanza y control de costes |

---

## Decisiones

### Decisión 1: Migración de Almacenamiento a `gp3`

**Contexto**: AWS introdujo `gp3` como evolución de `gp2`. Permite desacoplar el rendimiento (IOPS) del tamaño y es consistentemente más barato.

**Acción**: Se ha modificado el tipo de volumen de `gp2` a `gp3` mediante una operación "in-flight" (sin tiempo de inactividad).

**Beneficio**:
- Ahorro directo del 20% en costes de almacenamiento mensual.
- Rendimiento base de 3,000 IOPS garantizado (frente a los 100 IOPS de un volumen pequeño en `gp2`).

### Decisión 2: Hardening del Security Group (Firewall)

**Reglas eliminadas**:

| Puerto | Protocolo | Razón |
|--------|-----------|-------|
| 3389 | RDP | Riesgo crítico (Windows Remote Desktop) en una máquina Linux. |
| 80 | HTTP | El microservicio no escucha en el puerto estándar. |
| 443 | HTTPS | TLS se gestiona a nivel de aplicación o no se requiere para failover interno. |

**Reglas conservadas**:

- **8080 (TCP)**: Acceso al microservicio Heatmap desde la App PHP.
- **22 (TCP)**: Acceso administrativo vía SSH.

### Decisión 3: Etiquetado de Recursos (Gobernanza)

Se han aplicado las siguientes etiquetas a todos los recursos (EC2, EBS, Network Interfaces):

- `Project`: `CleanMarvel`
- `Environment`: `Failover-Cloud`
- `Owner`: `TFM-Luisma`
- `CostCenter`: `Engineering-Academic`

**Justificación**: Permite desglosar el coste en el AWS Billing Dashboard y evita "recursos huérfanos" que generen gastos inesperados.

### Decisión 4: Uso de IPv4 Efímera frente a Elastic IP

**Decisión**: No reservar una Elastic IP (IP estática).
**Justificación**: En AWS Free Tier, las Elastic IPs son gratuitas mientras están asociadas a una instancia en ejecución, pero generan cargos si la instancia se apaga. Como el sistema de failover de la App PHP (`FailoverHeatmapApiClient`) es configurable vía `.env`, es preferible usar la IP pública efímera y actualizarla en el despliegue, evitando costes residuales.

---

## Estado final del nodo AWS

### Recursos activos

```
AWS Cloud (eu-west-3)
├── EC2 Instance: marvel-ec2-dev (t3.micro, Free Tier)
│   ├── Storage: 8 GB EBS (gp3) ✅
│   └── Networking: Security Group (22, 8080) ✅
└── Governance: Resource Tagging habilitado ✅
```

### Tabla de Costes (AWS)

| Concepto | Coste gp2 (Antes) | Coste gp3 (Después) |
|----------|-------------------|---------------------|
| Compute (t3.micro) | $0.00 (Free Tier) | $0.00 (Free Tier) |
| Almacenamiento | $0.80/mes | $0.64/mes ✅ |
| Snapshots | $0.00 | $0.00 |
| **Total Estimado** | **$0.80/mes** | **$0.64/mes** |

---

## Consecuencias

- **Resiliencia Documentada**: Ambos nodos (GCP y AWS) siguen los mismos estándares de calidad.
- **FinOps**: Ahorro del 20% en almacenamiento mediante `gp3`.
- **Seguridad**: Superficie de ataque minimizada.
- **Conformidad**: El proyecto cumple con las mejores prácticas de AWS para despliegues escalables.

---

## Referencias

- [AWS Free Tier Details](https://aws.amazon.com/free/)
- [EBS gp3 vs gp2 Comparison](https://aws.amazon.com/ebs/general-purpose/)
- [AWS Security Best Practices](https://docs.aws.amazon.com/whitepapers/latest/aws-security-best-practices/security-best-practices.html)
