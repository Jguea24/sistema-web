document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-bs-toggle='tooltip']").forEach((node) => {
    if (window.bootstrap) {
      new bootstrap.Tooltip(node);
    }
  });

  document.querySelectorAll(".js-confirm").forEach((button) => {
    button.addEventListener("click", (event) => {
      const text = button.getAttribute("data-confirm") || "Confirmar accion?";
      if (!window.confirm(text)) {
        event.preventDefault();
      }
    });
  });

  // Mueve botones de accion del footer al header (arriba derecha) en modales.
  document.querySelectorAll(".modal").forEach((modal) => {
    const header = modal.querySelector(".modal-header");
    const footer = modal.querySelector(".modal-footer");
    if (!header || !footer) return;

    // Si el header ya tiene acciones (distintas al boton cerrar), no duplicar.
    const headerActions = header.querySelectorAll("button:not(.btn-close), a.btn");
    if (headerActions.length > 0) return;

    const footerButtons = Array.from(footer.querySelectorAll("button, a.btn"));
    if (footerButtons.length === 0) return;

    const actionsWrap = document.createElement("div");
    actionsWrap.className = "d-flex align-items-center gap-2 ms-auto modal-header-actions";

    footerButtons.forEach((btn) => {
      const clone = btn.cloneNode(true);

      // Si el boton era submit dentro de un form sin atributo form, vincularlo.
      if (
        clone.tagName === "BUTTON" &&
        clone.getAttribute("type") === "submit" &&
        !clone.getAttribute("form")
      ) {
        const parentForm = btn.closest("form");
        if (parentForm) {
          if (!parentForm.id) {
            parentForm.id = `autoForm_${Math.random().toString(36).slice(2, 9)}`;
          }
          clone.setAttribute("form", parentForm.id);
        }
      }

      actionsWrap.appendChild(clone);
    });

    const closeButton = header.querySelector(".btn-close");
    if (closeButton) {
      header.insertBefore(actionsWrap, closeButton);
    } else {
      header.appendChild(actionsWrap);
    }

    footer.classList.add("d-none");
  });
});
