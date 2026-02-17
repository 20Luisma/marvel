---
title: "Clean Marvel Album: Guía Técnica de la Ciudad de Héroes"
author: "Martín Pallante Cardeo"
subject: "TFM Ingenieria de Software"
keywords: [Clean Architecture, PHP, Marvel, TFM]
---

# Clean Marvel Album: Guía Técnica de la Ciudad de Héroes

## Prólogo: La ciudad que parecía un álbum

La primera vez que alguien entra en Clean Marvel Album, cree que está viendo una aplicación para gestionar héroes y álbumes. Y sí, lo es. Pero por debajo de esa apariencia vive una ciudad completa, una ciudad organizada con la disciplina de S.H.I.E.L.D., la creatividad de Tony Stark y la memoria de una biblioteca cósmica.

Esa ciudad no se construyó para presumir complejidad, sino para resolver un problema sencillo: cómo crear algo que siga funcionando cuando crece, cuando cambian las reglas, cuando se cae un servicio, cuando aparece una nueva idea o una nueva amenaza. En otras palabras, cómo construir algo que no se rompa cada vez que el mundo cambia.

En este libro no vas a encontrar jerga pesada. Vas a encontrar escenas. Decisiones humanas. Conflictos reales. Y una pregunta constante: si el universo Marvel tuviera que organizar su información, proteger su base principal y coordinar equipos inteligentes, ¿cómo lo haría?

La respuesta es esta historia.

## Capítulo 1: El mapa de la ciudad

Imagina una ciudad dividida en cuatro distritos. En el centro está el distrito más importante: donde viven las reglas del mundo. Allí no hay carteles luminosos, ni pantallas, ni botones. Allí viven las leyes. Qué es un héroe. Qué es un álbum. Qué cosas son válidas y cuáles no.

Ese centro es el Dominio.

Rodeándolo está el distrito de coordinación: el lugar donde los agentes reciben misiones, juntan información y deciden el orden de las acciones. Nadie aquí inventa las reglas de fondo; aquí se ejecutan planes. Ese es el distrito de Aplicación.

Más hacia afuera está el distrito de infraestructura. Garajes, carreteras, antenas, bases de datos, archivos, conexiones con otros cuarteles. Es el lugar de los medios y las herramientas.

Y en la frontera de la ciudad está la zona de presentación: puertas, pantallas, formularios, rutas web. Es la cara visible del sistema, la plaza pública.

La clave de esta ciudad es que cada distrito sabe cuál es su trabajo. El centro no depende de las fronteras. Las leyes del mundo no cambian porque cambió el color de un botón o porque un proveedor externo se cayó. Es como en los Vengadores: la misión no depende de la marca del quinjet.

Cuando esto se respeta, la ciudad respira. Cuando no, todo se mezcla y cada pequeño cambio se convierte en un desastre.

## Capítulo 2: Héroes, álbumes y reglas que no negocian

Hablemos del corazón: héroes y álbumes.

Un héroe no es un trozo de texto suelto en una pantalla. Tiene identidad, historia y coherencia. Un álbum tampoco es solo una caja donde meter nombres; es un espacio con significado, con relaciones y límites.

En Clean Marvel Album, pensar en dominio es pensar como Nick Fury en una sala de crisis: antes de mover piezas, entiende quién es quién y qué reglas no se pueden romper.

Si una regla dice que un héroe necesita identidad válida para existir, esa regla vive en el centro y no se negocia. Si un álbum debe mantener consistencia en su contenido, esa consistencia no depende de si hoy guardas en JSON o mañana en MySQL. La ley del mundo permanece.

Por eso esta arquitectura trata al dominio como el Sanctum Sanctorum: un lugar protegido, lejos del ruido de internet, de formularios y de detalles de infraestructura. Allí solo importan las verdades del negocio.

## Capítulo 3: Dos memorias para una misma historia

Toda ciudad necesita memoria. Pero en este universo hay dos tipos de memoria, como si existieran dos archivos históricos en paralelo.

El primero es ligero y cercano: archivos JSON. Ideal para moverse rápido en local, para aprender, para prototipar, para levantar el sistema sin pedir permisos al universo entero.

El segundo es robusto para producción: base de datos MySQL. Más estable para hosting, más preparada para escenarios reales.

Clean Marvel Album no obliga a elegir uno y olvidar el otro. Hace algo más inteligente: intenta usar la memoria robusta cuando está disponible y, si esa puerta falla, cae de forma transparente a la memoria ligera.

Piensa en esto como Wakanda y una base temporal de campaña. Si el centro principal está operativo, trabajas allí. Si una tormenta corta comunicaciones, el equipo no se paraliza: activa plan de continuidad y sigue funcionando.

Esta decisión no suena épica, pero salva sistemas. Porque lo importante no es presumir infraestructura, sino mantener la misión viva.

## Capítulo 4: Tres equipos especiales fuera de la torre

La ciudad principal no lo hace todo. Sería mala idea que una sola base controlara combate, investigación y archivo cósmico al mismo tiempo. Por eso hay equipos especializados.

El primer equipo es como una división creativa de Stark Industries: recibe una misión narrativa y devuelve cómics generados con IA. Es el servicio dedicado a generación.

El segundo equipo es una biblioteca táctica inteligente. Cuando alguien pregunta por héroes, no responde a ciegas: busca conocimiento relevante, lo recupera y construye una respuesta contextual. Es el equipo RAG, la biblioteca que piensa.

El tercero se dedica a observar el comportamiento del campo de batalla: dónde hacen clic los usuarios, qué zonas reciben atención, cómo se mueve la gente por la interfaz. Es el servicio heatmap, más cercano a un centro de inteligencia operativa.

En términos Marvel, no metes a Doctor Strange a conducir tráfico ni a Spider-Man a custodiar archivos cósmicos. Cada escuadrón tiene misión propia.

La aplicación principal actúa como coordinadora. Pide apoyo, envía contexto, recibe resultados y sigue la historia. Esa separación permite evolucionar cada equipo sin romper a los demás.

## Capítulo 5: Cuando el universo cambia de entorno

Una misión en el cuartel de entrenamiento no es igual que una misión en plena ciudad. Por eso Clean Marvel Album reconoce entornos distintos: local, hosting, staging.

En local, los servicios viven cerca, con puertos internos y flujo rápido de prueba. En hosting, cambian direcciones y condiciones. En staging, se ensaya antes del gran despliegue.

La inteligencia para resolver a dónde llamar en cada momento funciona como un sistema de navegación de Jarvis: mismo piloto, rutas distintas según contexto.

Esto evita uno de los errores más caros del desarrollo: hardcodear rutas como si el mundo nunca fuera a cambiar. En Marvel, los portales se abren y cierran. En software, los endpoints también.

## Capítulo 6: Proteger la Torre Stark

Ahora llegamos a la parte que separa una demo bonita de un sistema serio: seguridad.

Imagina la Torre Stark en mitad de Manhattan. No la proteges con una sola puerta y una contraseña débil. La proteges por capas.

Primera capa: puertas y cristales reforzados. En web, eso son cabeceras de seguridad, cookies seguras, políticas que reducen ataques básicos.

Segunda capa: control de acceso. No todos entran a laboratorios internos. Hay autenticación, sesión con tiempo de vida, verificación de contexto, vigilancia de intentos fallidos.

Tercera capa: protección contra suplantaciones. Si alguien intenta enviar una orden fingiendo ser un usuario legítimo, el sistema pide prueba de autenticidad, como credenciales de misión válidas.

Cuarta capa: firewall de comportamiento. Si un payload llega con patrones sospechosos, se bloquea antes de tocar zonas sensibles.

Quinta capa: trazas y bitácora de incidentes. Cada evento importante se registra para investigar con claridad.

Y una capa adicional muy concreta: llave móvil para endpoints sensibles. Es como acceso biométrico para puertas de alto riesgo.

La filosofía es simple: no confíes en una única barrera. Si una cae, otra responde.

## Capítulo 7: Firmas internas y mensajeros confiables

Cuando la base central habla con equipos externos, no basta con enviar mensajes. Hay que demostrar que el mensaje es auténtico.

Por eso aparece la firma HMAC: una especie de sello de S.H.I.E.L.D. en cada comunicación interna. Si el sello no coincide, el mensaje no se considera confiable.

Es una idea muy humana: no importa solo qué dice una orden, importa quién la firma.

En este proyecto, esas firmas y cabeceras internas convierten una simple llamada HTTP en una comunicación verificable. No es paranoia. Es higiene operativa en un entorno distribuido.

## Capítulo 8: El día que un servicio se cae

Toda ciudad moderna vive esta escena tarde o temprano: un proveedor externo falla.

La pregunta no es si pasará. La pregunta es cómo reaccionas.

Si cada petición insiste ciegamente en llamar a un servicio caído, saturas el sistema, quemas tiempo y empeoras la experiencia del usuario. Es como enviar quinjets uno tras otro a un portal inestable.

Clean Marvel Album usa una estrategia parecida a un protocolo de emergencia. Si detecta fallos repetidos, cierra temporalmente la puerta a ese servicio. Espera. Reintenta con cuidado. Si ve recuperación, reabre gradualmente.

Ese mecanismo evita cascadas de error y mantiene la calma en momentos críticos. Es liderazgo técnico disfrazado de resiliencia.

## Capítulo 9: El hilo rojo de una investigación

Piensa en una incidencia en producción. Un usuario reporta un fallo al comparar héroes. La petición salió de la app principal, pasó por un servicio, saltó a otro y terminó en error.

Sin correlación, cada log parece una escena aislada. Con `trace_id`, todas esas escenas se convierten en una sola película.

Ese identificador viaja de extremo a extremo como un hilo rojo entre mundos. Permite reconstruir qué pasó, cuándo, en qué ruta y en qué servicio. Para soporte técnico, es oro puro. Para ingeniería, es paz mental.

En Marvel sería como seguir la misma señal de energía desde la Torre Stark hasta una base remota de S.H.I.E.L.D. No importa cuántas estaciones atraviese: sabes que es la misma misión.

## Capítulo 10: Mensajes entre héroes dentro de la base

No toda coordinación ocurre por internet. Dentro de la app también pasan cosas importantes cuando se crea un héroe o se actualiza un álbum.

En lugar de acoplar todo directamente, Clean Marvel Album usa eventos internos en memoria. Es una forma elegante de decir: “esto ocurrió” y dejar que los equipos interesados reaccionen.

Si un evento de héroe creado dispara notificaciones, esa reacción está separada del acto de creación. La misión principal no necesita conocer todos los efectos secundarios.

En términos narrativos, es como el cuartel central emitiendo una alerta: cada unidad que deba actuar lo hace, sin que el comandante tenga que llamar una por una.

## Capítulo 11: De controladores obesos a líderes que delegan

En los primeros años de muchos proyectos, hay controladores que quieren hacerlo todo. Reciben petición, validan, buscan datos, llaman servicios externos, gestionan errores y casi preparan café.

Eso funciona hasta que deja de funcionar.

La evolución natural de este proyecto fue adelgazar esos controladores y mover la orquestación real a casos de uso claros. El controlador quedó como puerta de entrada HTTP. La lógica de misión quedó en una capa dedicada.

Es el paso de un líder agotado a un equipo bien organizado. El resultado: código más legible, más testeable y más fácil de reutilizar.

## Capítulo 12: Cambiar de armadura sin cambiar de misión

Durante mucho tiempo, la generación de cómics estaba atada a un proveedor concreto. Era como diseñar la estrategia de los Vengadores alrededor de una sola armadura de Iron Man.

El proyecto dio un salto importante: separó el contrato de generación de la implementación específica. Así, la aplicación pide “genera cómic” sin casarse con un único proveedor.

Hoy puede usar una implementación actual. Mañana, otra distinta. La misión no cambia.

Esto no es moda de arquitectura. Es libertad futura comprada a tiempo.

## Capítulo 13: Portadas de álbum y almacenamiento con futuro

Subir una portada parece algo pequeño, pero suele esconder acoplamientos peligrosos. Si toda la lógica depende del disco local, migrar a almacenamiento en nube duele.

Clean Marvel Album abstrae ese almacenamiento como si fuera un contrato logístico. La aplicación dice “guarda esta portada y dame su ruta pública”. No pregunta si fue en disco local o en un bucket remoto.

Es como pedirle a logística de los Vengadores que entregue equipo en campo: importa el resultado, no el camión exacto.

Ese detalle abre la puerta a evolución cloud sin reescribir la lógica de negocio.

## Capítulo 14: La biblioteca inteligente de héroes

La parte RAG del proyecto puede explicarse como una biblioteca viva del universo Marvel.

Cuando alguien hace una pregunta, no responde improvisando. Primero busca conocimiento relevante. Recupera contexto útil. Luego construye respuesta con ese contexto en mente.

Eso hace que la respuesta sea menos genérica y más anclada al conocimiento real del sistema.

Además, el proyecto evolucionó hacia una memoria vectorial más potente para búsquedas semánticas rápidas, sin perder un plan de respaldo con datos locales. Es como tener la gran biblioteca de Asgard conectada, pero conservar archivos estratégicos en la Tierra por si hay tormenta cósmica.

La lección es elegante: inteligencia sin resiliencia es frágil; resiliencia sin inteligencia es limitada. Aquí conviven ambas.

## Capítulo 15: La fábrica automática de trajes de Iron Man

Ahora imagina una línea de ensamblaje automática para trajes. Cada nueva pieza pasa por escáneres, pruebas, validaciones de vuelo y simulaciones de combate. Si algo falla, ese traje no sale de fábrica.

Eso es CI/CD en este proyecto.

Cada cambio pasa por una cadena de comprobaciones: pruebas, análisis, revisiones de seguridad, validaciones de flujo crítico. Hay un “filtro quirúrgico” que decide si una versión está realmente lista para producción.

No se trata de automatizar por moda. Se trata de evitar que un error pequeño llegue a la gente real.

Además, hay vigilancia recurrente en producción. Como drones de patrulla nocturna, revisan que la ciudad siga sana incluso días después del despliegue.

## Capítulo 16: El centinela que no duerme

Una ciudad segura no depende solo de revisiones manuales. Necesita centinelas automáticos.

En Clean Marvel Album, la seguridad se vigila en dos frentes: dependencias externas y endurecimiento del servidor. Si aparece una vulnerabilidad en la cadena de suministro, salta alerta. Si el entorno expone algo que no debería, se detecta.

Es un enfoque más maduro que “revisar cuando haya tiempo”. Aquí la vigilancia forma parte del sistema.

Como diría Fury: la amenaza más peligrosa es la que te pilla confiado.

## Capítulo 17: Escalar sin perder el alma

Un proyecto académico suele morir cuando termina la entrega. Este proyecto eligió otra ruta: dejar una base viva y extensible.

La modularización del arranque, la separación de responsabilidades, los contratos claros y la observabilidad no son adornos. Son decisiones para que el sistema pueda crecer sin convertirse en una maraña.

Incluso las nuevas capacidades, como recomendaciones de películas por similitud, se integran respetando la estructura general. No entran a martillazos. Entran como nuevos héroes que entienden las reglas de la casa.

## Capítulo 18: Lo que realmente enseña este proyecto

Si este libro tuviera que resumirse en una sola imagen, sería esta: una gran mesa de coordinación en la Torre Stark.

En esa mesa, cada equipo sabe su rol. El conocimiento importante no depende de una pantalla bonita. La seguridad no se improvisa al final. Los servicios externos son aliados, no puntos ciegos. Los fallos se esperan y se gestionan. Los cambios se validan antes de salir. Y cuando algo pasa, hay trazas para entenderlo.

Eso es Clean Marvel Album en esencia.

No es un catálogo de tecnologías. Es una forma de pensar sistemas complejos con claridad humana.

Y quizá esa sea la lección más útil para cualquiera que construya software: no necesitas sonar complejo para resolver cosas complejas. Necesitas diseñar como si mañana fueras a heredar tu propio universo.

## Epílogo: Después de los créditos

Toda historia Marvel deja una escena postcréditos. Esta también.

La ciudad está operativa. Los héroes coordinan bien. La torre resiste. La fábrica de trajes produce con control. La biblioteca inteligente responde. Los centinelas vigilan.

Pero el universo sigue en movimiento. Llegarán nuevas amenazas, nuevos equipos, nuevas reglas de juego. Y eso no da miedo cuando la base está bien construida.

Porque al final, la arquitectura limpia no es una obsesión técnica. Es una forma de cuidar el futuro.

Y cuidar el futuro, en cualquier universo, siempre ha sido trabajo de héroes.
