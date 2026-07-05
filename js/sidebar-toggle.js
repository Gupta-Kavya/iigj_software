(function () {
    "use strict";

    var storageKey = "smartlinkSidebarCollapsed";
    var body = document.body;
    var toggle = document.getElementById("appSidebarToggle");
    var reveal = document.getElementById("appSidebarReveal");
    var desktopQuery = window.matchMedia("(min-width: 768px)");

    function storedCollapsed() {
        try {
            return window.localStorage.getItem(storageKey) === "1";
        } catch (error) {
            return false;
        }
    }

    function remember(collapsed) {
        try {
            window.localStorage.setItem(storageKey, collapsed ? "1" : "0");
        } catch (error) {
            // The animation still works when browser storage is unavailable.
        }
    }

    function updateControls(collapsed) {
        if (toggle) {
            toggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
            toggle.setAttribute("aria-label", collapsed ? "Show sidebar" : "Hide sidebar");
            toggle.setAttribute("title", collapsed ? "Show sidebar" : "Hide sidebar");
            var icon = toggle.querySelector("i");
            if (icon) icon.className = collapsed ? "fa fa-angle-right" : "fa fa-angle-left";
        }
        if (reveal) reveal.setAttribute("aria-hidden", collapsed ? "false" : "true");
    }

    function applyState(collapsed, persist) {
        if (!desktopQuery.matches) {
            body.classList.remove("app-sidebar-collapsed");
            updateControls(false);
            return;
        }
        body.classList.toggle("app-sidebar-collapsed", collapsed);
        updateControls(collapsed);
        if (persist) remember(collapsed);
        window.setTimeout(function () {
            window.dispatchEvent(new Event("resize"));
        }, 330);
    }

    if (toggle) {
        toggle.addEventListener("click", function () {
            applyState(!body.classList.contains("app-sidebar-collapsed"), true);
        });
    }
    if (reveal) {
        reveal.addEventListener("click", function () {
            applyState(false, true);
        });
    }

    function handleViewportChange() {
        applyState(desktopQuery.matches && storedCollapsed(), false);
    }
    if (desktopQuery.addEventListener) {
        desktopQuery.addEventListener("change", handleViewportChange);
    } else {
        desktopQuery.addListener(handleViewportChange);
    }
    handleViewportChange();
})();
