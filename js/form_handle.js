// var c = document.getElementById("st_image_canvas");
// var ctx = c.getContext("2d");
// ctx.moveTo(0, 0);
// ctx.lineTo(320, 160);
// ctx.stroke();

// form saved by enter

document.getElementById("date").focus();

function resetStonePreview() {
  var fetchedImage = document.getElementById("fetched_image");
  var canvas = document.getElementById("st_image_canvas");
  if (fetchedImage) {
    fetchedImage.innerHTML = "";
  }
  if (canvas) {
    canvas.style.display = "none";
  }
}

function showStonePreview(html) {
  var fetchedImage = document.getElementById("fetched_image");
  var canvas = document.getElementById("st_image_canvas");
  if (fetchedImage) {
    fetchedImage.innerHTML = html;
  }
  if (canvas) {
    canvas.style.display = "none";
  }
}

function clearCstoneValidation(certi_no_div, date_div, weight_div, dimension_div, colour_div, stone_name_div, comments_div) {
  if (certi_no_div) {
    certi_no_div.classList.remove("has-error");
  }
  date_div.classList.remove("has-error");
  weight_div.classList.remove("has-error");
  dimension_div.classList.remove("has-error");
  colour_div.classList.remove("has-error");
  stone_name_div.classList.remove("has-error");
  comments_div.classList.remove("has-error");
}

function handleCstoneSaveResponse(xhr, certi_no_div, date_div, weight_div, dimension_div, colour_div, stone_name_div, comments_div) {
  var response;
  try {
    response = JSON.parse(xhr.responseText);
  } catch (error) {
    alert(xhr.responseText || "Unable to save record.");
    return;
  }

  if (response.status !== "success") {
    alert(response.message || "Unable to save record.");
    return;
  }

  let field_no = $("#field_copier_field").val();
  if (field_no == "") {
    form.reset();
  }
  var tokenField = document.getElementById("upload_token");
  if (tokenField) {
    tokenField.value = "";
  }

  $("#certi_no").val("");
  $("#assigned_certificate_no").text(response.certi_no);
  $("#assigned_certificate_result").css("display", "flex");
  var message = "Report saved successfully. Allotted Certificate No: " + response.certi_no;
  if (response.report_no) {
    message += " · Report No: " + response.report_no;
  }
  if (window.AppToast) {
    AppToast.success(message, 6500);
  } else {
    alert(message);
  }
  clearCstoneValidation(certi_no_div, date_div, weight_div, dimension_div, colour_div, stone_name_div, comments_div);
  document.getElementById("date").focus();
  resetStonePreview();
}

// Get all the input fields in the form
let formInputs = document.querySelectorAll("form input:not([type='hidden']), textarea, select");

// Add an event listener to each input field
for (let i = 0; i < formInputs.length; i++) {
  formInputs[i].addEventListener("keydown", function (event) {
    // If the user presses "Enter" key
    if (event.keyCode === 13) {
      // Prevent the default behavior of the form submitting
      event.preventDefault();
      // Move focus to the next input field
      let nextIndex = Array.prototype.indexOf.call(formInputs, this) + 1;
      if (nextIndex < formInputs.length) {
        formInputs[nextIndex].focus();
      } else {
        // If there are no more input fields, submit the form using AJAX
        let formData = new FormData(document.querySelector("form"));
        let xhr = new XMLHttpRequest();

        let form_date = document.form_stone.date.value;
        let form_weight = document.form_stone.weight.value;
        let form_dimension = document.form_stone.dimension.value;
        let form_colour = document.form_stone.colour.value;
        let form_stone_name = document.form_stone.stone_name.value;
        let form_comments = document.form_stone.comments.value;

        let certi_no_div = document.getElementById("error_class_certi_no");
        let date_div = document.getElementById("error_class_date");
        let weight_div = document.getElementById("error_class_weight");
        let dimension_div = document.getElementById("error_class_dimension");
        let colour_div = document.getElementById("error_class_colour");
        let stone_name_div = document.getElementById("error_class_stone_name");
        let comments_div = document.getElementById("error_class_comments");

        if (form_date == "") {
          alert("Date cannot be blank.");
          document.getElementById("date").focus();
          date_div.classList.add("has-error");
        } else if (form_weight == "") {
          alert("Weight cannot be blank.");
          document.getElementById("weight").focus();
          weight_div.classList.add("has-error");
        } else if (form_dimension == "") {
          alert("Dimensions cannot be blank.");
          document.getElementById("dimension").focus();
          dimension_div.classList.add("has-error");
        } else if (form_colour == "") {
          alert("Colour cannot be blank.");
          document.getElementById("colour").focus();
          colour_div.classList.add("has-error");
        } else if (form_stone_name == "") {
          alert("Stone name cannot be blank.");
          document.getElementById("stone_name").focus();
          stone_name_div.classList.add("has-error");
        } else if (form_comments == "") {
          alert("Comments cannot be blank.");
          document.getElementById("comments").focus();
          comments_div.classList.add("has-error");
        } else {
          formData.set("certi_no", "");
          xhr.open("POST", "process-form.php", true);
          xhr.onload = function () {
            handleCstoneSaveResponse(xhr, certi_no_div, date_div, weight_div, dimension_div, colour_div, stone_name_div, comments_div);
          };
          xhr.onerror = function () {
            alert("Unable to save record. Please check your connection and try again.");
          };
          xhr.send(formData);
        }
      }
    }
  });
}

// form saved by button

// Get the form and the submit button
let form = document.getElementById("cstone_feed");
let btn = document.getElementById("form_submit");

// Attach an event listener to the button
btn.addEventListener("click", function (event) {
  // Prevent the default form submission
  event.preventDefault();

  // Create a new FormData object and append the form data to it
  let formData = new FormData(form);

  // Create a new XMLHttpRequest object
  let xhr = new XMLHttpRequest();

  let form_date = document.form_stone.date.value;
  let form_weight = document.form_stone.weight.value;
  let form_dimension = document.form_stone.dimension.value;
  let form_colour = document.form_stone.colour.value;
  let form_stone_name = document.form_stone.stone_name.value;
  let form_comments = document.form_stone.comments.value;

  let certi_no_div = document.getElementById("error_class_certi_no");
  let date_div = document.getElementById("error_class_date");
  let weight_div = document.getElementById("error_class_weight");
  let dimension_div = document.getElementById("error_class_dimension");
  let colour_div = document.getElementById("error_class_colour");
  let stone_name_div = document.getElementById("error_class_stone_name");
  let comments_div = document.getElementById("error_class_comments");

  if (form_date == "") {
    alert("Date cannot be blank.");
    document.getElementById("date").focus();
    date_div.classList.add("has-error");
  } else if (form_weight == "") {
    alert("Weight cannot be blank.");
    document.getElementById("weight").focus();
    weight_div.classList.add("has-error");
  } else if (form_dimension == "") {
    alert("Dimensions cannot be blank.");
    document.getElementById("dimension").focus();
    dimension_div.classList.add("has-error");
  } else if (form_colour == "") {
    alert("Colour cannot be blank.");
    document.getElementById("colour").focus();
    colour_div.classList.add("has-error");
  } else if (form_stone_name == "") {
    alert("Stone name cannot be blank.");
    document.getElementById("stone_name").focus();
    stone_name_div.classList.add("has-error");
  } else if (form_comments == "") {
    alert("Comments cannot be blank.");
    document.getElementById("comments").focus();
    comments_div.classList.add("has-error");
  } else {
    formData.set("certi_no", "");
    xhr.open("POST", "process-form.php", true);
    xhr.onload = function () {
      handleCstoneSaveResponse(xhr, certi_no_div, date_div, weight_div, dimension_div, colour_div, stone_name_div, comments_div);
    };
      xhr.onerror = function () {
        alert("Unable to save record. Please check your connection and try again.");
      };
    xhr.send(formData);
  }
});

// weight unit adder

function updateInput(select) {
  var input = document.getElementById("weight");
  input.value += " " + select.value;
}

// stone image fetcher

function getCstoneUploadToken() {
  var tokenField = document.getElementById("upload_token");
  if (!tokenField) {
    return "";
  }
  if (!tokenField.value) {
    var randomPart = "";
    if (window.crypto && crypto.getRandomValues) {
      var values = new Uint32Array(2);
      crypto.getRandomValues(values);
      randomPart = values[0].toString(36) + values[1].toString(36);
    } else {
      randomPart = Math.random().toString(36).slice(2);
    }
    tokenField.value = "u" + Date.now().toString(36) + randomPart;
  }
  return tokenField.value;
}

function fetchStoneImageForCurrentCertificate() {
  var uploadToken = getCstoneUploadToken();
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "image_fetch.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  xhr.onreadystatechange = function () {
    if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
      showStonePreview(this.responseText);
    }
  };
  xhr.send("input=&upload_token=" + encodeURIComponent(uploadToken));
}

document.addEventListener("keydown", function (event) {
  if (event.keyCode === 119) {
    fetchStoneImageForCurrentCertificate();
  }
});

const fetch_button = document.getElementById("fetch_image");

fetch_button.addEventListener("click", function () {
  fetchStoneImageForCurrentCertificate();
});

// Direct image upload from folder

$(document).ready(function () {
  var $modal = $("#up_crop_modal");
  var $prev_modal = $("#up_from_folder");
  var fileInput = document.getElementById("upload_image");

  $("#upload_image").change(function (event) {
    var files = event.target.files;
    if (!files || files.length <= 0) {
      return;
    }

    var cert_no = document.getElementById("certi_no");
    var uploadData = new FormData();
    uploadData.append("image_file", files[0]);
    uploadData.append("certino", cert_no ? cert_no.value : "");
    uploadData.append("upload_token", getCstoneUploadToken());

    $.ajax({
      url: "upload.php",
      method: "POST",
      data: uploadData,
      processData: false,
      contentType: false,
      success: function (data) {
        $prev_modal.modal("hide");
        $modal.modal("hide");
        showStonePreview(data);
        fileInput.value = "";
      },
      error: function (xhr) {
        alert(xhr.responseText || "Unable to upload image.");
      },
    });
  });
});

///// Camera capture and crop script

const takePictureButton = document.getElementById("take-picture-button");
const cameraModal = $("#camera_modal");
const cropModal = $("#cam_crop_modal");
const cameraVideo = document.getElementById("video");
const cameraSelect = document.getElementById("camera_select");
const imageCam = document.getElementById("cam_snap_image");
const camPreview = document.getElementById("campreview");
const cropButton = $("#crop-button");
let cameraStream = null;
let cameraCropper = null;
let selectedCameraId = "";

function stopCamera() {
  if (cameraStream) {
    cameraStream.getTracks().forEach(function (track) {
      track.stop();
    });
    cameraStream = null;
  }
  cameraVideo.srcObject = null;
}

async function listCameras() {
  if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices || !cameraSelect) {
    return;
  }

  const devices = await navigator.mediaDevices.enumerateDevices();
  const videoDevices = devices.filter(function (device) {
    return device.kind === "videoinput";
  });

  cameraSelect.innerHTML = "";
  if (videoDevices.length === 0) {
    cameraSelect.innerHTML = '<option value="">No camera found</option>';
    cameraSelect.disabled = true;
    return;
  }

  videoDevices.forEach(function (device, index) {
    const option = document.createElement("option");
    option.value = device.deviceId;
    option.textContent = device.label || "Camera " + (index + 1);
    cameraSelect.appendChild(option);
  });

  cameraSelect.disabled = videoDevices.length === 1;
  if (selectedCameraId && videoDevices.some(function (device) { return device.deviceId === selectedCameraId; })) {
    cameraSelect.value = selectedCameraId;
  } else {
    selectedCameraId = cameraSelect.value;
  }
}

async function startCamera(deviceId) {
  stopCamera();

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    alert("Camera access needs Chrome or Edge running on HTTPS or localhost.");
    cameraModal.modal("hide");
    return;
  }

  try {
    const videoConstraints = deviceId
      ? { deviceId: { exact: deviceId } }
      : { facingMode: { ideal: "environment" } };

    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: videoConstraints,
      audio: false,
    });
    cameraVideo.srcObject = cameraStream;
    await cameraVideo.play();
    await listCameras();
  } catch (error) {
    console.error("Error accessing camera:", error);
    alert("Unable to open the camera. Please allow camera permission and try again.");
    cameraModal.modal("hide");
  }
}

function takePicture() {
  if (cameraVideo.readyState < 2 || !cameraVideo.videoWidth) {
    alert("The camera is still starting. Please wait a moment and try again.");
    return;
  }

  const canvas = document.createElement("canvas");
  canvas.width = cameraVideo.videoWidth;
  canvas.height = cameraVideo.videoHeight;
  canvas.getContext("2d").drawImage(cameraVideo, 0, 0, canvas.width, canvas.height);
  imageCam.src = canvas.toDataURL("image/jpeg", 0.92);

  stopCamera();
  cameraModal.modal("hide");
  cropModal.modal("show");
}

cropModal.on("shown.bs.modal", function () {
  if (cameraCropper) {
    cameraCropper.destroy();
  }
  cameraCropper = new Cropper(imageCam, {
    aspectRatio: 1,
    viewMode: 3,
    autoCropArea: 1,
    crop: function () {
      if (cameraCropper) {
        camPreview.src = cameraCropper
          .getCroppedCanvas({ width: 160, height: 160 })
          .toDataURL("image/jpeg", 0.85);
      }
    },
  });
});

cropModal.on("hidden.bs.modal", function () {
  if (cameraCropper) {
    cameraCropper.destroy();
    cameraCropper = null;
  }
  camPreview.removeAttribute("src");
});

function cropCameraImage() {
  if (!cameraCropper) {
    alert("Please capture an image before cropping.");
    return;
  }

  const croppedData = cameraCropper
    .getCroppedCanvas({ width: 800, height: 800 })
    .toDataURL("image/jpeg", 0.9);
  var cert_no = document.getElementById("certi_no");
  var certino = cert_no.value;
  $.ajax({
    type: "POST",
    url: "save_image.php",
    data: {
      croppedData: croppedData,
      certino: certino,
      upload_token: getCstoneUploadToken(),
    },
    success: function (response) {
      console.log(response);
      cropModal.modal("hide");
      showStonePreview(response);
    },
    error: function (xhr, status, error) {
      console.error(xhr.responseText);
    },
  });
}

// Add event listeners
cameraModal.on("shown.bs.modal", function () {
  startCamera(selectedCameraId);
});
cameraModal.on("hidden.bs.modal", stopCamera);
if (cameraSelect) {
  cameraSelect.addEventListener("change", function () {
    selectedCameraId = cameraSelect.value;
    if (selectedCameraId) {
      startCamera(selectedCameraId);
    }
  });
}
takePictureButton.addEventListener("click", takePicture);
cropButton.on("click", cropCameraImage);
