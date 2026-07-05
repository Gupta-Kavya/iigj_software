$(document).ready(function () {
    function showMasterToast(type, message) {
        if (window.AppToast && typeof AppToast[type] === "function") {
            AppToast[type](message);
            return;
        }

        if (type === "error") {
            window.alert(message);
        }
    }

    function setRowBusy($row, busy, mode) {
        var $buttons = $row.find(".master-btn-save, .master-btn-delete");
        $buttons.prop("disabled", busy);

        if (mode === "save") {
            var $save = $row.find(".master-btn-save");
            if (busy) {
                $save.data("original-html", $save.html());
                $save.html('<i class="fa fa-spinner fa-spin"></i>');
            } else {
                $save.html($save.data("original-html") || '<i class="fa fa-save"></i>');
            }
        }

        if (mode === "delete") {
            var $delete = $row.find(".master-btn-delete");
            if (busy) {
                $delete.data("original-html", $delete.html());
                $delete.html('<i class="fa fa-spinner fa-spin"></i>');
            } else {
                $delete.html($delete.data("original-html") || '<i class="fa fa-trash"></i>');
            }
        }
    }

    function getDataTable($table) {
        if ($.fn.DataTable && $.fn.dataTable && $.fn.dataTable.isDataTable($table.get(0))) {
            return $table.DataTable();
        }

        return null;
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function getMasterRowSearchText($row) {
        var parts = [];

        $row.find("td").each(function () {
            var $cell = $(this);

            $cell.contents().filter(function () {
                return this.nodeType === 3;
            }).each(function () {
                var text = $.trim(this.nodeValue);
                if (text) {
                    parts.push(text);
                }
            });

            $cell.find("input, textarea, select").each(function () {
                var $field = $(this);
                var value = $field.is("select")
                    ? $field.find("option:selected").text()
                    : $field.val();

                if (value) {
                    parts.push(value);
                }
            });
        });

        return $.trim(parts.join(" ").replace(/\s+/g, " "));
    }

    function refreshMasterRowCache(row) {
        var $row = $(row);
        var searchText = getMasterRowSearchText($row);
        var $firstCell = $row.children("td").first();

        if (!$firstCell.length) {
            return;
        }

        $firstCell.find(".master-search-cache").remove();
        if (searchText) {
            $firstCell.append(
                '<span class="master-search-cache" aria-hidden="true">' +
                escapeHtml(searchText) +
                "</span>"
            );
        }
    }

    function addFallbackSearch($table) {
        if ($table.data("masterFallbackSearchReady")) {
            return;
        }

        $table.data("masterFallbackSearchReady", true);

        var $box = $(
            '<div class="master-local-search">' +
            '<label>Search entries</label>' +
            '<input type="search" class="form-control" placeholder="Search entries...">' +
            "</div>"
        );

        $table.closest(".master-table-wrap").before($box);

        $box.find("input").on("input", function () {
            var query = $.trim($(this).val()).toLowerCase();
            var visibleCount = 0;

            $table.find("tbody tr").each(function () {
                var rowText = getMasterRowSearchText($(this)).toLowerCase();
                var matched = !query || rowText.indexOf(query) !== -1;
                $(this).toggle(matched);
                if (matched) {
                    visibleCount++;
                }
            });

            $table.toggleClass("master-has-filter", !!query);
            $table.next(".master-filter-empty").remove();

            if (query && visibleCount === 0) {
                $table.after('<div class="master-filter-empty">No matching entries found.</div>');
            }
        });
    }

    $(".master-table").each(function () {
        var table = this;
        var $table = $(table);

        if ($table.find("tbody tr").length === 0) {
            return;
        }

        $table.find("tbody tr").each(function () {
            refreshMasterRowCache(this);
        });

        if ($.fn.DataTable && $.fn.dataTable) {
            if ($.fn.dataTable.isDataTable(table)) {
                return;
            }

            var dataTable = $table.DataTable({
                pageLength: 25,
                order: [],
                columnDefs: [
                    {
                        targets: -1,
                        orderable: false
                    }
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search entries..."
                }
            });

            $table.on("change blur", "input, textarea, select", function () {
                var row = $(this).closest("tr").get(0);
                refreshMasterRowCache(row);
                dataTable.row(row).invalidate("dom");
                dataTable.draw(false);
            });
        } else {
            addFallbackSearch($table);
        }
    });

    $(document).on("submit", ".master-table form", function (event) {
        event.preventDefault();

        var form = this;
        var $form = $(form);
        var $row = $('[form="' + form.id + '"]').first().closest("tr");
        var $table = $row.closest("table");
        var dataTable = getDataTable($table);

        if (!$row.length) {
            form.submit();
            return;
        }

        setRowBusy($row, true, "save");

        $.ajax({
            url: $form.attr("action"),
            type: ($form.attr("method") || "POST").toUpperCase(),
            dataType: "json",
            data: $form.serialize(),
            headers: {
                Accept: "application/json"
            },
            success: function (response) {
                if (response && response.status === "success") {
                    refreshMasterRowCache($row);
                    if (dataTable) {
                        dataTable.row($row).invalidate("dom").draw(false);
                    }
                    showMasterToast("success", response.message || "Master entry updated successfully.");
                } else {
                    showMasterToast("error", (response && response.message) || "Unable to update master data.");
                }
            },
            error: function () {
                showMasterToast("error", "Unable to update master data. Please try again.");
            },
            complete: function () {
                setRowBusy($row, false, "save");
            }
        });
    });

    $(document).on("click", ".master-btn-delete", function () {
        var $button = $(this);
        var url = $button.data("delete-url");
        if (!url) {
            return;
        }

        function deleteRow() {
            var $row = $button.closest("tr");
            var $table = $row.closest("table");
            var dataTable = getDataTable($table);

            setRowBusy($row, true, "delete");

            $.ajax({
                url: url,
                type: "POST",
                dataType: "json",
                headers: {
                    Accept: "application/json"
                },
                success: function (response) {
                    if (response && response.status === "success") {
                        if (dataTable) {
                            dataTable.row($row).remove().draw(false);
                        } else {
                            $row.fadeOut(180, function () {
                                $(this).remove();
                            });
                        }

                        showMasterToast("success", response.message || "Master entry deleted successfully.");
                    } else {
                        setRowBusy($row, false, "delete");
                        showMasterToast("error", (response && response.message) || "Unable to delete master data.");
                    }
                },
                error: function () {
                    setRowBusy($row, false, "delete");
                    showMasterToast("error", "Unable to delete master data. Please try again.");
                }
            });
        }

        if (window.AppConfirm && typeof AppConfirm.show === "function") {
            AppConfirm.show("Delete this master entry?", {
                title: "Delete entry",
                confirmText: "Delete",
                danger: true
            }).then(function (confirmed) {
                if (confirmed) {
                    deleteRow();
                }
            });
            return;
        }

        if (window.confirm("Delete this master entry?")) {
            deleteRow();
        }
    });
});
