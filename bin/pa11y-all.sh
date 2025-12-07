#!/bin/bash

echo "🔎 Analizando accesibilidad del Clean Marvel Album..."

pa11y http://localhost:8080/ &&
pa11y http://localhost:8080/albums &&
pa11y http://localhost:8080/heroes &&
pa11y http://localhost:8080/comic &&
pa11y http://localhost:8080/oficial-marvel &&
pa11y http://localhost:8080/readme &&
pa11y http://localhost:8080/sonar &&
pa11y http://localhost:8080/sentry &&
pa11y http://localhost:8080/panel-github &&
pa11y http://localhost:8080/seccion &&
pa11y http://localhost:8080/movies &&
pa11y http://localhost:8080/index.php

echo "✔ Análisis completo terminado (WCAG 2.1 AA)"
