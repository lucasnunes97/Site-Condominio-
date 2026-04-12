(function () {
  const tabButtons = document.querySelectorAll("[data-tab]");
  const panels = document.querySelectorAll("[data-panel]");

  function activateTab(id) {
    if (!id) {
      return;
    }
    tabButtons.forEach((btn) => {
      const sel = btn.getAttribute("data-tab") === id;
      btn.setAttribute("aria-selected", sel ? "true" : "false");
    });
    panels.forEach((p) => {
      p.classList.toggle("active", p.getAttribute("data-panel") === id);
    });
    try {
      history.replaceState(null, "", "#" + id);
    } catch (e) {
      /* ignore */
    }
  }

  tabButtons.forEach((btn) => {
    btn.addEventListener("click", () => activateTab(btn.getAttribute("data-tab")));
  });

  document.querySelectorAll("[data-tab-go]").forEach((el) => {
    el.addEventListener("click", (e) => {
      const id = el.getAttribute("data-tab-go");
      if (!id) {
        return;
      }
      e.preventDefault();
      activateTab(id);
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  });

  const hash = (location.hash || "").replace("#", "");
  if (hash && document.querySelector('[data-panel="' + hash + '"]')) {
    activateTab(hash);
  }

  const formSeguros = document.getElementById("form-seguros");
  const msgSeguros = document.getElementById("msg-seguros");

  async function loadCsrf() {
    const r = await fetch("csrf.php", { credentials: "same-origin" });
    const j = await r.json();
    return j.csrf || "";
  }

  if (formSeguros) {
    let csrfToken = "";
    loadCsrf()
      .then((t) => {
        csrfToken = t;
      })
      .catch(() => {});

    formSeguros.addEventListener("submit", async (e) => {
      e.preventDefault();
      msgSeguros.textContent = "";
      msgSeguros.className = "msg";

      if (!csrfToken) {
        try {
          csrfToken = await loadCsrf();
        } catch (err) {
          msgSeguros.textContent = "Erro ao preparar o formulário. Atualize a página.";
          msgSeguros.classList.add("err");
          return;
        }
      }

      const fd = new FormData(formSeguros);
      fd.set("csrf", csrfToken);

      try {
        const res = await fetch("contato.php", {
          method: "POST",
          body: fd,
          credentials: "same-origin",
        });
        const j = await res.json();
        if (j.ok) {
          msgSeguros.textContent = j.message || "Enviado com sucesso.";
          msgSeguros.classList.add("ok");
          formSeguros.reset();
          csrfToken = await loadCsrf();
        } else {
          msgSeguros.textContent = j.error || "Não foi possível enviar.";
          msgSeguros.classList.add("err");
        }
      } catch (err) {
        msgSeguros.textContent = "Erro de rede. Tente novamente.";
        msgSeguros.classList.add("err");
      }
    });
  }

  const formCondo = document.getElementById("form-condominio");
  const iframeDoc = document.getElementById("doc-frame");
  const docPlaceholder = document.getElementById("doc-placeholder");
  const docOpenWrap = document.getElementById("doc-open-wrap");
  const docOpenTab = document.getElementById("doc-open-tab");
  const docToolbar = document.getElementById("doc-toolbar");
  const btnDocLimpar = document.getElementById("btn-doc-limpar");
  const panelCondo = document.getElementById("panel-condominio");

  function limparConsultaCondominio() {
    if (iframeDoc) {
      iframeDoc.src = "about:blank";
      iframeDoc.hidden = true;
    }
    if (docPlaceholder) {
      docPlaceholder.hidden = false;
    }
    if (docOpenWrap) {
      docOpenWrap.hidden = true;
    }
    if (docOpenTab) {
      docOpenTab.href = "#";
    }
    if (docToolbar) {
      docToolbar.hidden = true;
    }
    if (formCondo) {
      formCondo.reset();
    }
    if (btnDocLimpar) {
      btnDocLimpar.blur();
    }
  }

  /** Marca saída da página para, ao regressar (nova carga ou histórico), limpar NIF/ano e iframe. */
  const CONDO_LEAVE_KEY = "condo_leave_reset_v1";

  window.addEventListener("pagehide", () => {
    try {
      sessionStorage.setItem(CONDO_LEAVE_KEY, "1");
    } catch (e) {
      /* ignore */
    }
  });

  window.addEventListener("pageshow", (e) => {
    if (e.persisted) {
      limparConsultaCondominio();
    }
  });

  try {
    if (sessionStorage.getItem(CONDO_LEAVE_KEY) === "1") {
      limparConsultaCondominio();
      sessionStorage.removeItem(CONDO_LEAVE_KEY);
    }
  } catch (e) {
    /* ignore */
  }

  if (formCondo && iframeDoc) {
    formCondo.addEventListener("submit", (e) => {
      e.preventDefault();
      const nif = (document.getElementById("nif") || {}).value || "";
      const ano = (document.getElementById("ano") || {}).value || "";
      const q = new URLSearchParams({ nif: nif.trim(), ano: ano });
      const url = "documento.php?" + q.toString();
      if (docToolbar) {
        docToolbar.hidden = false;
      }
      iframeDoc.src = url;
      if (docOpenTab) {
        docOpenTab.href = url;
      }
      if (docPlaceholder) {
        docPlaceholder.hidden = true;
      }
      iframeDoc.hidden = false;
      if (docOpenWrap) {
        docOpenWrap.hidden = false;
      }
    });
  }

  if (btnDocLimpar) {
    btnDocLimpar.addEventListener("click", () => {
      limparConsultaCondominio();
    });
  }

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") {
      return;
    }
    if (!panelCondo || !panelCondo.classList.contains("active")) {
      return;
    }
    if (!docToolbar || docToolbar.hidden) {
      return;
    }
    e.preventDefault();
    limparConsultaCondominio();
  });
})();
