<?php

declare(strict_types=1);

$pageTitle = 'Marvel Agent — Asistente Técnico';
$additionalStyles = [
    '/assets/css/seccion.css',
    '/assets/css/agentia.css',
];
$activeTopAction = $activeTopAction ?? 'marvel-agent';

require_once __DIR__ . '/../layouts/header.php';
?>

<header class="app-hero app-hero--tech seccion-hero">
  <div class="app-hero__inner">
    <div class="space-y-3 max-w-3xl">
      <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
      <p class="app-hero__meta text-base text-slate-300">
        Asistente técnico del proyecto Clean Marvel Album: arquitectura, microservicios, calidad y documentación.
      </p>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main id="main-content" tabindex="-1" role="main" class="agentia-main">
  <div class="agentia-layout">
    <aside class="agentia-card agentia-help">
      <header class="agentia-card__header">
        <p class="agentia-card__eyebrow">Guía rápida</p>
        <h3 class="agentia-card__title">¿Qué puedo preguntarle a Marvel Agent?</h3>
      </header>
      <ul class="agentia-list">
        <li>
          <strong>RAG flow:</strong> “Descríbeme el flujo del microservicio RAG y cómo arma el prompt final.”
        </li>
        <li>
          <strong>OpenAI gateway:</strong> “¿Qué hace el endpoint /v1/chat y qué variables de entorno usa?”
        </li>
        <li>
          <strong>CI/CD:</strong> “Resúmeme los jobs de calidad y despliegue del proyecto.”
        </li>
        <li>
          <strong>Calidad y métricas:</strong> “¿Qué monitorea SonarCloud y cómo interpretarlo?”
        </li>
        <li>
          <strong>Heatmap:</strong> “¿Cómo funciona el tracking de clics y qué datos guarda?”
        </li>
        <li>
          <strong>KB y embeddings:</strong> “¿Cómo se generan los embeddings y se refresca la knowledge base?”
        </li>
      </ul>
    </aside>

    <section class="agentia-card agentia-chat">
      <header class="agentia-card__header">
        <div>
          <p class="agentia-card__eyebrow">Panel de chat</p>
          <h2 class="agentia-card__title">Marvel Agent – Technical Chat</h2>
          <p class="agentia-card__meta">status: online</p>
        </div>
      </header>

      <div id="agent-chat-messages" class="agent-chat-messages">
        <div class="agent-message agent-message--bot">
          <p>Listo para responder dudas técnicas sobre arquitectura, microservicios y calidad del proyecto.</p>
        </div>
      </div>

      <form id="agent-chat-form" class="agent-chat-form">
        <?= csrf_field() ?>
        <input
          type="text"
          id="agentia-input"
          name="question"
          placeholder="Escribe tu pregunta técnica…"
          autocomplete="off"
          aria-label="Pregunta para Marvel Agent"
        />
        <button type="submit" class="agent-chat-submit">Enviar</button>
      </form>
    </section>
  </div>
</main>

<?php
$scripts = $scripts ?? [];
$scripts[] = '/assets/js/agentia.js';
require_once __DIR__ . '/../layouts/footer.php';
?>
