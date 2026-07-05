<?php include "assets/navbar.php"; ?>
<?php
// Define the target directory and file name
$targetDir = ""; // Ensure this directory exists and is writable
$targetFile = $targetDir . "2.jpg";
$uploadOk = 1;
$errorMsg = "";

// Check if the file was uploaded
if (isset($_FILES["image"])) {
    $imageFileType = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    
    // Check if the file is an image
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if ($check !== false) {
        $errorMsg .= "File is an image - " . $check["mime"] . ".<br>";
        $uploadOk = 1;
    } else {
        $errorMsg .= "File is not an image.<br>";
        $uploadOk = 0;
    }
    
    // Check file size (5MB limit in this case)
    if ($_FILES["image"]["size"] > 5000000) {
        $errorMsg .= "Sorry, your file is too large.<br>";
        $uploadOk = 0;
    }
    
    // Allow certain file formats
    if ($imageFileType != "jpg" && $imageFileType != "jpeg" && $imageFileType != "png" && $imageFileType != "gif") {
        $errorMsg .= "Sorry, only JPG, JPEG, PNG & GIF files are allowed.<br>";
        $uploadOk = 0;
    }
    
    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        $errorMsg .= "Sorry, your file was not uploaded.<br>";
    } else {
        // Try to upload file
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            $errorMsg .= "The file " . htmlspecialchars(basename($_FILES["image"]["name"])) . " has been uploaded as 2.jpg.<br>";
        } else {
            $errorMsg .= "Sorry, there was an error uploading your file.<br>";
        }
    }
}
?>

<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <h1 class="page-header"><i class="fa fa-cog fa-fw"></i> REPORT SETTINGS</h1>
            </div>
        </div>

        <div class="row">
            <ul class="nav nav-tabs">
                <li role="presentation" ><a href="card-builder.php">Card Builder</a></li>
                <li role="presentation" ><a href="tableDataSettings.php">Table Data</a></li>
                <li role="presentation" class="active"><a href="backImageSetting.php">Back Image</a></li>
            </ul>

            <div class="panel" style="margin-top:10px;">
                <div class="panel-body">
              
                <?php
                    if (!empty($errorMsg)) {
                        echo '<div class="alert alert-info">' . $errorMsg . '</div>';
                    }
                    ?>
                    <div class="col-md-6">
                        <form action="" method="post" enctype="multipart/form-data" class="form-horizontal">
                            <div class="form-group">
                                <label for="image" class="col-sm-3 control-label">Choose Image</label>
                                <div class="col-sm-9">
                                    <input type="file" name="image" id="image" accept="image/*" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="col-sm-offset-3 col-sm-9">
                                    <button type="submit" class="btn btn-primary">Upload Image</button>
                                </div>
                            </div>
                        </form>
                    </div>





            </div>
        </div>
    </div>
    </div>  
</div>

<?php include "assets/footer.php"; ?>

