<?php

declare(strict_types=1);

/** Logótipo da marca (Nuno Teixeira / Nuvipama). Substitua por ficheiro local em assets/img se preferir. */
$brand_logo_url = 'https://medianuvipama.ximo.pt/conteudos/26.jpg';

session_start();
if (empty($_SESSION['csrf_seguros'])) {
    $_SESSION['csrf_seguros'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nuno Teixeira — Condomínio &amp; gestão</title>
  <link rel="preconnect" href="https://medianuvipama.ximo.pt" crossorigin>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <header class="top-header">
    <div class="top-header__inner">
      <a class="brand" href="#" data-tab-go="imobiliario" aria-label="Nuno Teixeira — início">
        <img
          class="brand__logo"
          src="<?= htmlspecialchars($brand_logo_url, ENT_QUOTES, 'UTF-8') ?>"
          alt="NT — Nuno Teixeira by Nuvipama"
          width="140"
          height="52"
          decoding="async"
          fetchpriority="high"
        />
        <span class="brand__text">
          <span class="brand__title">Nuno Teixeira</span>
          <span class="brand__sub">mediação imobiliária</span>
        </span>
      </a>
      <nav class="tabs" role="group" aria-label="Navegação principal">
        <button type="button" role="tab" data-tab="imobiliario" aria-selected="true">Início</button>
        <a
          class="tabs__pill-link"
          href="https://nuvipamaimoveis.pt/"
          target="_blank"
          rel="noopener noreferrer"
        >Imobiliária</a>
        <button type="button" role="tab" data-tab="condominio" aria-selected="false">Área do condomínio</button>
        <button type="button" role="tab" data-tab="seguros" aria-selected="false">Seguros</button>
      </nav>
    </div>
  </header>

  <main class="main">
    <section id="panel-imobiliario" class="panel panel--imob active" data-panel="imobiliario" role="tabpanel">
      <div class="hero">
        <div class="hero__inner">
          <h1 class="hero__title">Gestão inteligente e <span class="hero__accent">transparente</span></h1>
          <p class="hero__lead">A solução moderna para o seu condomínio. Menos burocracia, mais eficiência e contas sempre claras.</p>
          <div class="hero__actions">
            <button type="button" class="btn btn--hero" data-tab-go="seguros">Solicitar seguro <span aria-hidden="true">→</span></button>
            <a class="btn btn--ghost" href="https://nuvipamaimoveis.pt/" target="_blank" rel="noopener noreferrer">Ver imóveis</a>
          </div>
        </div>
      </div>

      <div class="wrap">
        <section class="features" aria-labelledby="features-title">
          <h2 id="features-title" class="features__title">Porque escolher a nossa gestão?</h2>
          <p class="features__subtitle">Tecnologia e experiência ao serviço do seu condomínio.</p>
          <ul class="feature-grid">
            <li class="feature-card">
              <span class="feature-card__icon feature-card__icon--green" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="1.6"/></svg>
              </span>
              <h3 class="feature-card__h">Transparência</h3>
              <p class="feature-card__p">Documentação e contas acessíveis de forma clara e organizada.</p>
            </li>
            <li class="feature-card">
              <span class="feature-card__icon feature-card__icon--navy" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M13 10V3L4 14h7v7l9-11h-7z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
              </span>
              <h3 class="feature-card__h">Eficiência</h3>
              <p class="feature-card__p">Menos papelada, mais tempo para o que realmente importa.</p>
            </li>
            <li class="feature-card">
              <span class="feature-card__icon feature-card__icon--green" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" stroke="currentColor" stroke-width="1.6"/></svg>
              </span>
              <h3 class="feature-card__h">Digital</h3>
              <p class="feature-card__p">Consulte informação do condomínio quando precisar, em qualquer dispositivo.</p>
            </li>
            <li class="feature-card">
              <span class="feature-card__icon feature-card__icon--navy" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="1.6"/></svg>
              </span>
              <h3 class="feature-card__h">Suporte</h3>
              <p class="feature-card__p">Equipa disponível para esclarecer dúvidas sobre seguros e condomínio.</p>
            </li>
          </ul>
        </section>
      </div>
    </section>

    <section id="panel-condominio" class="panel" data-panel="condominio" role="tabpanel">
      <div class="wrap wrap--section">
        <header class="section-head">
          <span class="section-head__badge section-head__badge--navy" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z" stroke="currentColor" stroke-width="1.6"/></svg>
          </span>
          <div>
            <h2 class="section-head__title">Área do condomínio</h2>
            <p class="section-head__lead">Indique o NIF do condomínio e a senha de acesso. Por privacidade, os dados não ficam guardados: ao sair, atualizar ou voltar a esta página, terá de preencher novamente.</p>
          </div>
        </header>

        <div class="card card--condo">
          <form id="form-condominio" class="form-grid form-grid--condo">
            <label>
              NIF do condómino
              <input type="text" id="nif" name="nif" inputmode="numeric" autocomplete="off" placeholder="Ex.: 501234567" required maxlength="9">
            </label>
            <label>
              Senha
              <input type="password" id="senha" name="senha" autocomplete="off" required maxlength="64" placeholder="Digite a senha">
            </label>
            <div class="form-actions">
              <button type="submit" class="btn btn--primary">Consultar documento</button>
            </div>
          </form>
          <div class="doc-preview" id="doc-preview">
            <div class="doc-preview__toolbar" id="doc-toolbar" hidden>
              <span class="doc-preview__toolbar-title">Resultado da consulta</span>
              <button type="button" class="doc-preview__close" id="btn-doc-limpar" aria-label="Fechar e limpar pesquisa" title="Fechar e limpar (Esc)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
              </button>
            </div>
            <div class="doc-preview__content">
              <p id="doc-placeholder" class="doc-placeholder">O documento aparecerá aqui após consultar. Se sair ou atualizar a página, esta área é limpa.</p>
              <iframe id="doc-frame" title="Documento do condomínio" hidden></iframe>
            </div>
            <p id="doc-open-wrap" class="doc-open-wrap" hidden>
              <a id="doc-open-tab" href="#" target="_blank" rel="noopener">Abrir documento numa nova janela</a>
            </p>
          </div>
        </div>
      </div>
    </section>

    <section id="panel-seguros" class="panel" data-panel="seguros" role="tabpanel">
      <div class="wrap wrap--section">
        <header class="section-head">
          <span class="section-head__badge section-head__badge--green" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke="currentColor" stroke-width="1.6"/></svg>
          </span>
          <div>
            <h2 class="section-head__title">Seguros</h2>
            <p class="section-head__lead">Solicite uma proposta personalizada para o seu condomínio.</p>
          </div>
        </header>

        <p class="intro-box">Preencha o formulário abaixo e a nossa equipa entrará em contacto consigo com uma proposta adequada à sua necessidade.</p>

        <div class="seguros-layout">
          <div class="card card--form">
            <form id="form-seguros" class="form-grid">
              <label>
                Nome completo *
                <input type="text" name="nome" required maxlength="200" autocomplete="name" placeholder="Ex.: João Silva">
              </label>
              <label>
                E-mail *
                <input type="email" name="email" required maxlength="200" autocomplete="email" placeholder="seu@email.com">
              </label>
              <label>
                Telefone *
                <input type="tel" name="telefone" required maxlength="40" autocomplete="tel" placeholder="+351 912 345 678">
              </label>
              <label>
                Endereço do condomínio <span class="hint">(opcional)</span>
                <input type="text" name="endereco_condominio" maxlength="300" autocomplete="street-address" placeholder="Morada, localidade">
              </label>
              <label>
                Número da unidade <span class="hint">(opcional)</span>
                <input type="text" name="numero_unidade" maxlength="80" placeholder="Ex.: 3.º Esq.">
              </label>
              <label>
                Descreva a necessidade *
                <textarea name="necessidade" required maxlength="4000" placeholder="Tipo de seguro, urgência, número de frações, etc."></textarea>
              </label>
              <div class="form-actions">
                <button type="submit" class="btn btn--primary btn--wide">Enviar pedido</button>
              </div>
            </form>
            <div id="msg-seguros" class="msg" aria-live="polite"></div>
          </div>

          <aside class="seguros-aside">
            <div class="side-card">
              <h3 class="side-card__title">Porque nos escolher?</h3>
              <ul class="check-list">
                <li>Experiência em gestão de condomínios</li>
                <li>Equipa profissional e dedicada</li>
                <li>Transparência nas contas e documentos</li>
                <li>Resposta atempada aos seus pedidos</li>
              </ul>
            </div>
            <div class="side-card side-card--contact">
              <h3 class="side-card__title">Contacto direto</h3>
              <dl class="contact-dl">
                <dt>E-mail</dt>
                <dd><a href="mailto:condominio@nuvipama.pt">condominio@nuvipama.pt</a></dd>
                <dt>Horário</dt>
                <dd>Seg–Sex: 9h–18h</dd>
              </dl>
            </div>
          </aside>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="wrap">
      <p>Em caso de dúvida: <a href="mailto:condominio@nuvipama.pt">condominio@nuvipama.pt</a></p>
    </div>
  </footer>

  <script src="assets/js/app.js?v=20260427" defer></script>
</body>
</html>
