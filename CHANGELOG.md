# [0.1.0](https://github.com/20Luisma/marvel/compare/v1.0.11...v0.1.0) (2026-01-31)


### Bug Fixes

* asegurar que los datos de GitHub se actualicen sin caché ([b25e6a3](https://github.com/20Luisma/marvel/commit/b25e6a3bcf748aae8f841829901079ddae109539))
* **ci:** serve static assets for real E2E albums flow ([079b4f1](https://github.com/20Luisma/marvel/commit/079b4f19c6c18a17b9758a56aef8e13fd90a2ff7))
* corregir bucle infinito en el observer de la presentación que causaba bloqueo ([e55c7b1](https://github.com/20Luisma/marvel/commit/e55c7b14c5aa9f06e4014d0aa51e8cca21cc2275))
* corregir numeración de diapositivas y eliminar monitor de APIs duplicado ([10461cc](https://github.com/20Luisma/marvel/commit/10461cc001a544b8319c4f98af1f710453b79293))
* ensure marvel-agent always uses /rag/agent endpoint ([2a77043](https://github.com/20Luisma/marvel/commit/2a770435e127f0c177b9cc4e74fd785406779619))
* force UI refresh and bypass cache for single mobile view ([3a97d40](https://github.com/20Luisma/marvel/commit/3a97d401f32fe09e51ce81bd2b7aaf2018152a79))
* forzar recarga de imagen de Flash mediante cache busting ([db4755d](https://github.com/20Luisma/marvel/commit/db4755d0d11d5652db2de46db7c8b7592b6c8f90))
* load .env and use correct staging RAG URL in marvel-agent API ([880cde8](https://github.com/20Luisma/marvel/commit/880cde823fadd72a64fde2f8b2913e2839103171))
* make sonar metrics env reading more robust ([98b6d51](https://github.com/20Luisma/marvel/commit/98b6d51f9384d1f7ca7a21b0b577297369783f10))
* move presentation to public/presentation for web accessibility ([badeebd](https://github.com/20Luisma/marvel/commit/badeebd7a3268e23f29028330dc149100decd175))
* optimizar carga de datos de GitHub y corregir valores por defecto ([43808c8](https://github.com/20Luisma/marvel/commit/43808c8287db5739727da832534027b6e2d51879))
* optimizar script de la presentación para evitar bloqueos y corregir rutas de imágenes ([0922b40](https://github.com/20Luisma/marvel/commit/0922b40ffc1752bf7f8969999a2815a61ac62f57))
* renumber slides and update total count in presentation ([d5b9fa8](https://github.com/20Luisma/marvel/commit/d5b9fa808c1f4a89f4777aac703b8a8ed16bc8b1))
* resolve 404 on production by adding root proxy and fixing document root routing ([4f5e079](https://github.com/20Luisma/marvel/commit/4f5e079537420159b2131bb62472f36a0061d69d))
* restaurar contenido detallado de las diapositivas y corregir estructura ([b5d2a97](https://github.com/20Luisma/marvel/commit/b5d2a97d0c8169e2069d8109195f56181edf85d6))
* speed up deploy by excluding app screenshots ([45ae455](https://github.com/20Luisma/marvel/commit/45ae455df9e5d4fafbdf11a21a2a0ed254f00379))


### Features

* add integration and E2E tests for hero creation and album flow ([d92e0c8](https://github.com/20Luisma/marvel/commit/d92e0c88ecc479f8624b6df91f19cce060ec2ec8))
* add Mirroring Strategy slide to TFM presentation ([e6d28c6](https://github.com/20Luisma/marvel/commit/e6d28c67fada52352205b3c39dba929de98c0a4e))
* agregar gráfico de línea de tiempo dinámico a la slide Proyecto Vivo ([07a1871](https://github.com/20Luisma/marvel/commit/07a1871caaf9922ccfae1829d83ec5644389625e))
* añadir animación del velocista (Quicksilver) en la línea de tiempo de la slide 13 ([f178d0f](https://github.com/20Luisma/marvel/commit/f178d0f35d633ebe3e4603a55fd6484d85576f49))
* configure direct subdomains and deploy workflow for staging ([a7ccdda](https://github.com/20Luisma/marvel/commit/a7ccdda6df58da0c9b4f553e17914aab3060acc5))
* dockerizar app principal, orquestar ecosistema y destacar Heatmap (Python/GCP) en la presentación ([0070f27](https://github.com/20Luisma/marvel/commit/0070f27ed12eb73298272dd83f915c985cbdbdca))
* enable direct push deploy for staging ([ac42f59](https://github.com/20Luisma/marvel/commit/ac42f5974468ac12baeb1fc4ccaf652541e8b36c))
* implement automatic semantic release workflow ([39f77ad](https://github.com/20Luisma/marvel/commit/39f77ad6a3ef03568e92055a31314a28539ddd2a))
* implement professional engineering workflow and documentation ([267c05f](https://github.com/20Luisma/marvel/commit/267c05fe39c1870e4891f198fb1cc9eb692bfb55))


### Reverts

* Revert "test: demo de flujo profesional CI/CD" ([62f4b06](https://github.com/20Luisma/marvel/commit/62f4b06546348cc6d12f2503b9f9f797255a6b19))
* Revert "ci: include test branches in staging deployment" ([dc622e2](https://github.com/20Luisma/marvel/commit/dc622e206cf5a94b1253bd1bbbbc9f98d414f847))



## [1.0.11](https://github.com/20Luisma/marvel/compare/v1.0.10...v1.0.11) (2026-01-25)


### Bug Fixes

* actualizar timestamp del video para forzar redeploy ([3fe6e3d](https://github.com/20Luisma/marvel/commit/3fe6e3d4c463ed485b8b9665541d09df82e2586e))
* force css refresh in presentation ([b1b6243](https://github.com/20Luisma/marvel/commit/b1b6243cec4538bcda1a2344c47d8b77a2c3f2a3))
* mejorar detección de versiones desde GitHub API ([84b98ae](https://github.com/20Luisma/marvel/commit/84b98ae244a0854bb9f7005a044ad9817a7f75bb))


### Features

* add reset demo data functionality ([67a2755](https://github.com/20Luisma/marvel/commit/67a27552a2a0e61a57def75f9e1d27a3c0d6710b))
* agregar datos dinámicos reales desde GitHub API en slide 14 ([cce13ab](https://github.com/20Luisma/marvel/commit/cce13ab8e429b29909eed47d831cb3190ead78fb))
* register RESTAURADO activity when resetting demo data ([3c9adab](https://github.com/20Luisma/marvel/commit/3c9adab9c4064897e6424f9cbe56db287e9b1a97))



## [1.0.10](https://github.com/20Luisma/marvel/compare/v1.0.9...v1.0.10) (2026-01-24)



## [1.0.9](https://github.com/20Luisma/marvel/compare/v1.0.8...v1.0.9) (2025-12-14)


### Features

* **security:** harden auth, CSRF and credential management ([69e8081](https://github.com/20Luisma/marvel/commit/69e808188b679e62cc470178689da7ab7ad746c2))



## [1.0.8](https://github.com/20Luisma/marvel/compare/v1.0.7...v1.0.8) (2025-12-08)



