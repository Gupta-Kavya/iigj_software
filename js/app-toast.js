(function (window, document) {
    "use strict";

    var DEFAULT_DURATION = 4200;
    var container = null;

    function ensureContainer() {
        if (!container) {
            container = document.createElement("div");
            container.id = "app-toast-stack";
            container.className = "app-toast-stack";
            container.setAttribute("aria-live", "polite");
            container.setAttribute("aria-atomic", "false");
            document.body.appendChild(container);
        }
        return container;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function inferType(message) {
        var text = String(message || "").toLowerCase();

        if (/success|saved|updated|deleted|uploaded|created|complete/.test(text)) {
            return "success";
        }
        if (/unable|error|fail|invalid|cannot|can't|blank|required|not available|please select|please fill|please enter/.test(text)) {
            return "error";
        }
        if (/warning|wait|allow|permission/.test(text)) {
            return "warning";
        }
        return "info";
    }

    function show(message, options) {
        options = options || {};

        if (message === undefined || message === null) {
            message = "";
        }

        var type = options.type || inferType(message);
        var duration = options.duration !== undefined ? options.duration : DEFAULT_DURATION;
        var stack = ensureContainer();
        var toast = document.createElement("div");

        toast.className = "app-toast app-toast--" + type;
        toast.innerHTML =
            '<span class="app-toast-icon" aria-hidden="true"></span>' +
            '<span class="app-toast-message">' + escapeHtml(message) + "</span>" +
            '<button type="button" class="app-toast-close" aria-label="Dismiss">&times;</button>';

        stack.appendChild(toast);

        window.requestAnimationFrame(function () {
            toast.classList.add("is-visible");
        });

        var timer = null;

        function dismiss() {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }

            toast.classList.remove("is-visible");
            toast.classList.add("is-leaving");

            window.setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 200);
        }

        toast.querySelector(".app-toast-close").addEventListener("click", dismiss);

        if (duration > 0) {
            timer = window.setTimeout(dismiss, duration);
        }

        return { dismiss: dismiss };
    }

    var AppToast = {
        show: show,
        success: function (message, duration) {
            return show(message, { type: "success", duration: duration });
        },
        error: function (message, duration) {
            return show(message, { type: "error", duration: duration });
        },
        warning: function (message, duration) {
            return show(message, { type: "warning", duration: duration });
        },
        info: function (message, duration) {
            return show(message, { type: "info", duration: duration });
        }
    };

    window.AppToast = AppToast;
    window.alert = function (message) {
        AppToast.show(message);
    };

    var confirmRoot = null;
    var confirmKeyHandler = null;
    var confirmResolver = null;

    function ensureConfirmRoot() {
        if (confirmRoot) {
            return confirmRoot;
        }

        confirmRoot = document.createElement("div");
        confirmRoot.id = "app-confirm-root";
        confirmRoot.className = "app-confirm-root";
        confirmRoot.innerHTML =
            '<div class="app-confirm-backdrop" data-app-confirm-dismiss="true"></div>' +
            '<div class="app-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="app-confirm-title">' +
            '<div class="app-confirm-header">' +
            '<span class="app-confirm-icon" aria-hidden="true"></span>' +
            '<h4 class="app-confirm-title" id="app-confirm-title"></h4>' +
            "</div>" +
            '<div class="app-confirm-body"></div>' +
            '<div class="app-confirm-footer">' +
            '<button type="button" class="btn btn-default app-confirm-cancel">Cancel</button>' +
            '<button type="button" class="btn btn-primary app-confirm-ok">Confirm</button>' +
            "</div>" +
            "</div>";
        document.body.appendChild(confirmRoot);
        return confirmRoot;
    }

    function closeConfirm(result) {
        if (!confirmRoot || !confirmResolver) {
            return;
        }

        confirmRoot.classList.remove("is-open");
        document.body.classList.remove("app-confirm-open");

        if (confirmKeyHandler) {
            document.removeEventListener("keydown", confirmKeyHandler);
            confirmKeyHandler = null;
        }

        var resolver = confirmResolver;
        confirmResolver = null;
        resolver(!!result);
    }

    function confirmShow(message, options) {
        options = options || {};

        if (confirmResolver) {
            closeConfirm(false);
        }

        var root = ensureConfirmRoot();
        var title = options.title || "Confirm action";
        var confirmText = options.confirmText || "Confirm";
        var cancelText = options.cancelText || "Cancel";
        var danger = !!options.danger;
        var okBtn = root.querySelector(".app-confirm-ok");
        var cancelBtn = root.querySelector(".app-confirm-cancel");

        root.querySelector(".app-confirm-title").textContent = title;
        root.querySelector(".app-confirm-body").textContent = String(message || "");
        okBtn.textContent = confirmText;
        cancelBtn.textContent = cancelText;

        okBtn.className = "btn app-confirm-ok " + (danger ? "btn-danger" : "btn-primary");
        root.classList.toggle("app-confirm--danger", danger);

        return new Promise(function (resolve) {
            confirmResolver = resolve;

            function onOk() {
                closeConfirm(true);
            }

            function onCancel() {
                closeConfirm(false);
            }

            okBtn.onclick = onOk;
            cancelBtn.onclick = onCancel;
            root.querySelector(".app-confirm-backdrop").onclick = onCancel;

            confirmKeyHandler = function (event) {
                if (event.key === "Escape") {
                    onCancel();
                }
            };
            document.addEventListener("keydown", confirmKeyHandler);

            document.body.classList.add("app-confirm-open");
            root.classList.add("is-open");
            window.requestAnimationFrame(function () {
                (danger ? okBtn : cancelBtn).focus();
            });
        });
    }

    var AppConfirm = {
        show: confirmShow,
        ask: function (message, onConfirm, onCancel, options) {
            confirmShow(message, options).then(function (confirmed) {
                if (confirmed && typeof onConfirm === "function") {
                    onConfirm();
                } else if (!confirmed && typeof onCancel === "function") {
                    onCancel();
                }
            });
        }
    };

    window.AppConfirm = AppConfirm;
})(window, document);
