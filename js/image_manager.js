$(document).ready(function () {
  var $modal = $("#file_upload_progress");
  var $previewModal = $("#image_preview_modal");
  var $previewImage = $("#previewImage");
  var $previewTitle = $("#previewImageTitle");
  var $previewDownloadBtn = $("#previewDownloadBtn");
  var $previewDeleteBtn = $("#previewDeleteBtn");
  var $selectFile = $("#select_file");
  var $imageFiles = $("#image_files");
  var $deleteButton = $("#deleteSelectedBtn");
  var $selectedCount = $("#selected_count");
  var $imageCount = $("#image_count");
  var $search = $("#image_search");
  var previewFileName = "";
  var previewFileUrl = "";
  var currentPage = 1;
  var lastPage = 1;
  var searchTimer = null;

  function updateStats() {
    var $meta = $("#image_pagination_meta");
    var total = parseInt($meta.data("total"), 10);
    var from = parseInt($meta.data("from"), 10);
    var to = parseInt($meta.data("to"), 10);
    var selected = $imageFiles.find("input.image-item-checkbox:checked").length;

    if (isNaN(total)) {
      total = $imageFiles.find(".image-item").length;
    }

    if (isNaN(from) || isNaN(to) || total === 0) {
      $imageCount.text(total + (total === 1 ? " image" : " images"));
    } else {
      $imageCount.text("Showing " + from + "-" + to + " of " + total + (total === 1 ? " image" : " images"));
    }

    $selectedCount.text(selected + " selected");
    $deleteButton.prop("disabled", selected === 0);
    $imageFiles.find(".image-list-item").each(function () {
      $(this).toggleClass("is-selected", $(this).find("input.image-item-checkbox").is(":checked"));
    });
  }

  function deleteImages(imageNames, onSuccess, onComplete) {
    $.ajax({
      url: "file_delete.php",
      type: "POST",
      data: { images: imageNames },
      success: onSuccess,
      error: function () {
        alert("Unable to delete images. Please try again.");
      },
      complete: onComplete || function () {}
    });
  }

  function openPreview($item) {
    previewFileName = ($item.data("name") || "").toString();
    previewFileUrl = ($item.data("url") || "").toString();

    if (!previewFileName || !previewFileUrl) {
      return;
    }

    $previewTitle.html('<i class="fa fa-file-image-o"></i> ' + previewFileName);
    $previewImage.attr({ src: previewFileUrl, alt: previewFileName });
    $previewModal.modal("show");
  }

  function load_images(page) {
    currentPage = page || currentPage || 1;
    $imageFiles.html('<div class="image-empty">Loading images...</div>');
    $.ajax({
      url: "file_load.php",
      type: "POST",
      data: {
        page: currentPage,
        search: $search.val() || ""
      },
      success: function (response) {
        $imageFiles.html(response);
        var $meta = $("#image_pagination_meta");
        var returnedPage = parseInt($meta.data("page"), 10);
        var returnedLastPage = parseInt($meta.data("total-pages"), 10);

        if (!isNaN(returnedPage)) {
          currentPage = returnedPage;
        }

        if (!isNaN(returnedLastPage)) {
          lastPage = returnedLastPage;
        }

        updateStats();
      },
      error: function () {
        $imageFiles.html('<div class="image-empty">Unable to load images. Please refresh and try again.</div>');
        updateStats();
      }
    });
  }

  $("#image_delete_form").submit(function (e) {
    e.preventDefault();
    var selected_images = [];
    $imageFiles.find("input.image-item-checkbox:checked").each(function () {
      selected_images.push($(this).val());
    });

    if (selected_images.length <= 0) {
      alert("Please select at least one image to delete.");
      return;
    }

    if (selected_images.length > 50) {
      alert("You can delete only 50 images at a time. Selected image count is " + selected_images.length + ".");
      return;
    }

    AppConfirm.show("Delete selected image(s)?", {
      title: "Delete images",
      confirmText: "Delete",
      danger: true
    }).then(function (confirmed) {
      if (!confirmed) {
        return;
      }

      $deleteButton.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');
      deleteImages(selected_images, function () {
        load_images(currentPage);
      }, function () {
        $deleteButton.html('<i class="fa fa-trash"></i> Delete Selected');
        updateStats();
      });
    });
  });

  $("#checkAllBtn").on("click", function () {
    $imageFiles.find(".image-item:visible input.image-item-checkbox").prop("checked", true);
    updateStats();
  });

  $("#clearSelectionBtn").on("click", function () {
    $imageFiles.find("input.image-item-checkbox").prop("checked", false);
    updateStats();
  });

  $imageFiles.on("change", "input.image-item-checkbox", updateStats);

  $imageFiles.on("click", ".image-list-check", function (e) {
    e.stopPropagation();
  });

  $imageFiles.on("dblclick", ".image-list-item", function (e) {
    if ($(e.target).closest(".image-list-check").length) {
      return;
    }
    openPreview($(this));
  });

  $imageFiles.on("click", ".image-page-btn", function () {
    var page = parseInt($(this).data("page"), 10);

    if ($(this).prop("disabled") || isNaN(page) || page < 1 || page > lastPage || page === currentPage) {
      return;
    }

    load_images(page);
  });

  $previewDownloadBtn.on("click", function () {
    if (!previewFileUrl || !previewFileName) {
      return;
    }

    var link = document.createElement("a");
    link.href = previewFileUrl;
    link.download = previewFileName;
    link.target = "_blank";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });

  $previewDeleteBtn.on("click", function () {
    if (!previewFileName) {
      return;
    }

    AppConfirm.show("Delete " + previewFileName + "?", {
      title: "Delete image",
      confirmText: "Delete",
      danger: true
    }).then(function (confirmed) {
      if (!confirmed) {
        return;
      }

      $previewDeleteBtn.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');
      deleteImages([previewFileName], function () {
        $previewModal.modal("hide");
        load_images(currentPage);
      }, function () {
        $previewDeleteBtn.prop("disabled", false).html('<i class="fa fa-trash"></i> Delete');
      });
    });
  });

  $previewModal.on("hidden.bs.modal", function () {
    $previewImage.attr({ src: "", alt: "" });
    previewFileName = "";
    previewFileUrl = "";
    $previewDeleteBtn.prop("disabled", false).html('<i class="fa fa-trash"></i> Delete');
  });

  $search.on("input", function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
      load_images(1);
    }, 250);
  });

  $selectFile.on("change", function () {
    var files = this.files || [];
    var formData = new FormData();
    var error = "";

    if (files.length === 0) {
      return;
    }

    if (files.length > 100) {
      error = "<div class='alert alert-danger'>You cannot upload more than <strong>100</strong> images at a time. Selected: <strong>" + files.length + "</strong>.</div>";
    }

    for (var count = 0; count < files.length && error === ""; count++) {
      if (!["image/jpeg", "image/jpg"].includes(files[count].type)) {
        error += '<div class="alert alert-danger"><b>' + (count + 1) + "</b> selected file must be JPG/JPEG only.</div>";
      } else {
        formData.append("images[]", files[count]);
      }
    }

    $modal.modal("show");
    $("#progress_bar_process").css("width", "0%").text("0%");
    $("#progress_bar").hide();

    if (error !== "") {
      $("#uploaded_image").html(error);
      $selectFile.val("");
      return;
    }

    $("#uploaded_image").html("");
    $("#progress_bar").show();

    var request = new XMLHttpRequest();
    request.open("POST", "file_upload.php");

    request.upload.addEventListener("progress", function (event) {
      if (!event.lengthComputable) return;
      var percent = Math.round((event.loaded / event.total) * 100);
      $("#progress_bar_process").css("width", percent + "%").text(percent + "% completed");
    });

    request.addEventListener("load", function () {
      if (request.status >= 200 && request.status < 300) {
        $("#uploaded_image").html('<div class="alert alert-success">Images uploaded successfully.</div>');
        $selectFile.val("");
        load_images(1);
      } else {
        $("#uploaded_image").html('<div class="alert alert-danger">Upload failed. Please try again.</div>');
      }
    });

    request.addEventListener("error", function () {
      $("#uploaded_image").html('<div class="alert alert-danger">Upload failed. Please check the connection and try again.</div>');
    });

    request.send(formData);
  });

  load_images(1);
});
