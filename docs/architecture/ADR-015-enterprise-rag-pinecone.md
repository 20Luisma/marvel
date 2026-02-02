# ADR-015 – Implementación de RAG Enterprise con Pinecone (Vector Database)

## Estado
Aceptado

## Contexto
El sistema RAG inicial (ADR-005) utilizaba un almacenamiento basado en archivos JSON locales tanto para el conocimiento como para los embeddings de los héroes. Si bien esta solución es funcional para volúmenes de datos pequeños y entornos de desarrollo, presenta limitaciones de escalabilidad, rendimiento en búsquedas semánticas y desacoplamiento de datos en entornos de producción (hosting compartido). Se identificó la necesidad de evolucionar hacia una arquitectura de nivel empresarial (Enterprise RAG).

## Decisión
Adoptar **Pinecone** como base de datos vectorial gestionada (Vector Database as a Service) para el motor de conocimiento del Agente Marvel.

### Puntos clave de la implementación:
1.  **Arquitectura Híbrida**: Se mantiene el sistema JSON como *fallback* (resiliencia) pero se prioriza Pinecone en producción.
2.  **Motor Vectorial**: Uso de embeddings de OpenAI (`text-embedding-3-small`) con 1536 dimensiones.
3.  **Métrica de Similitud**: Implementación de búsqueda por similitud de coseno (*Cosine Similarity*).
4.  **Desacoplamiento Cloud**: El conocimiento técnico del proyecto reside en la nube de Pinecone, permitiendo que tanto el entorno local como el de staging/producción consulten la misma fuente de verdad optimizada.
5.  **Estrategia de Sincronización**: Creación de herramientas CLI para la ingesta y vectorización de datos desde los archivos maestros de conocimiento.

## Justificación
- **Escalabilidad**: Las bases de datos vectoriales están optimizadas para realizar búsquedas en milisegundos incluso con millones de registros.
- **Profesionalización**: Alinea el proyecto con los estándares actuales de la industria en el desarrollo de aplicaciones de IA generativa.
- **Rendimiento**: Libera al microservicio `rag-service` de la carga de procesar similitudes vectoriales en memoria PHP, delegando el cálculo pesado a una infraestructura especializada.
- **Consistencia**: Garantiza que todos los nodos del sistema (Staging, Producción, Local) utilicen el mismo índice vectorial actualizado.

## Consecuencias
### Positivas
- Reducción significativa de la latencia en las respuestas del Agente Marvel.
- Mayor precisión semántica al utilizar índices optimizados.
- Mejora la narrativa del TFM al demostrar la capacidad de integrar servicios Cloud nativos de IA.
### Negativas
- Dependencia de un servicio externo (Pinecone API).
- Requiere gestión de nuevas variables de entorno y API Keys.

## Resiliencia (Fallback)
En caso de fallo de red o error en la API de Pinecone, el sistema conmuta automáticamente al motor `MarvelAgentKnowledgeBase` (JSON local), garantizando que el servicio nunca se interrumpa.
