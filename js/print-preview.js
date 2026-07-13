(function () {
    "use strict";

    var pageWrap = document.getElementById("print_preview_page_wrap");
    var zoomValue = document.getElementById("print_preview_zoom_value");
    var zoom = 1;

    function setZoom(next) {
        zoom = Math.max(0.35, Math.min(1.7, next));
        if (pageWrap) pageWrap.style.zoom = zoom;
        if (zoomValue) zoomValue.textContent = Math.round(zoom * 100) + "%";
    }

    function fitWidth(maxZoom) {
        var documentBox = document.querySelector(".print-preview-document");
        var paper = document.querySelector(".preview-paper");
        if (!documentBox || !paper) return;
        var available = Math.max(280, documentBox.clientWidth - 48);
        var width = paper.offsetWidth || paper.getBoundingClientRect().width || available;
        maxZoom = typeof maxZoom === "number" && maxZoom > 0 ? maxZoom : 1.25;
        setZoom(Math.min(maxZoom, available / width));
    }

    document.addEventListener("click", function (event) {
        var control = event.target.closest("[data-preview-zoom]");
        if (!control) return;
        var action = control.getAttribute("data-preview-zoom");
        if (action === "in") setZoom(zoom + 0.1);
        if (action === "out") setZoom(zoom - 0.1);
        if (action === "fit") fitWidth();
    });

    window.addEventListener("load", function () {
        var mode = document.body ? document.body.getAttribute("data-preview-default") : "";
        var maxZoom = parseFloat(document.body ? document.body.getAttribute("data-preview-max-zoom") : "");
        if (mode === "fit") {
            window.setTimeout(function () {
                fitWidth(maxZoom);
            }, 0);
        }
    });

    window.PrintPreview = {
        setZoom: setZoom,
        fitWidth: fitWidth
    };
})();
