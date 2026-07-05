<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Card Design</title>
  <?php

$positions = json_decode(file_get_contents('positions.json'), true);
  ?>
  <style>
    .page {
      display: flex;
      flex-wrap: wrap;
      width: 100%;
      box-sizing: border-box;
      margin: 20px; /* Optional: adds margin around the whole page */
    }

    .card {
      width: 321.25984252px;
      height: 204.09448819px;
      position: relative;
      border: 1px solid #000;
      margin: 30px;

      box-sizing: border-box;
      page-break-inside: avoid; /* Avoid breaking inside cards */
      break-inside: avoid; /* Avoid breaking inside cards */
    }

    .card::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 0;
      width: 100%;
      border-bottom: 1px dashed #000;
    }

    .bck-image {
      width: 100%;
      height: 100%;
      position: absolute;
      top: 0;
      left: 0;
      z-index: -1;
    }

    .report-data {
      position: absolute;
      top: <?php echo $positions['table']['top']; ?>px;
      left: <?php echo $positions['table']['left']; ?>px;
      font-size: 8px;
      width:<?php echo $positions['table']['width']; ?>px;
      height:<?php echo $positions['table']['height']; ?>px;
      font-size:<?php echo $positions['table']['fontSize']; ?>px;
      display :<?php echo $positions['table']['display']; ?>; 
    }

    .qr-code {
      position: absolute;
      width: <?php echo $positions['qrcode']['width']; ?>px;
      height: <?php echo $positions['qrcode']['width']; ?>px;
      top: <?php echo $positions['qrcode']['top']; ?>px;
      right: <?php echo $positions['qrcode']['right']; ?>px;
      display :<?php echo $positions['qrcode']['display']; ?>; 
    }

    .stone-image {
      position: absolute;
      width: <?php echo $positions['gemstone']['width']; ?>px;
      height: <?php echo $positions['gemstone']['height']; ?>px;
      top: <?php echo $positions['gemstone']['top']; ?>px;
      right: <?php echo $positions['gemstone']['right']; ?>px;
      display :<?php echo $positions['gemstone']['display']; ?>; 
    }

    @media print {
      body * {
        visibility: hidden; /* Hide all elements by default */
      }

      .page, .page * {
        visibility: visible; /* Only show elements within the .page class */
      }

      .page {
        position: absolute;
        left: 0;
        top: 0;
      }

      @page {
        margin: 0; /* Remove margins for printing */
      }

      /* Hide elements like page title and URL */
      .page-title, .page-url {
        display: none; /* Hide these elements */
      }
    }
  </style>
</head>
<body>

  <div class="page">
    <?php
    require_once("db_connect.php");
    require_once("auth.php");
    require_once("atm_config.php");

    $settingsFile = 'settings.json';
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
    } else {
        // Handle the error if the file does not exist
        die("Settings file not found.");
    }

    $from = $_POST["from"];
    $to = $_POST["to"];

    $user_id = auth_current_user_id();
    $hasFormUserId = atm_table_has_column($conn, 'sm_form_data', 'user_id');
    $scopeSql = user_branch_scope_sql($conn, $user_id, 'user_id');
    $stmt = $hasFormUserId
        ? $conn->prepare("SELECT * FROM sm_form_data WHERE {$scopeSql} AND certi_no >= ? and certi_no <= ?")
        : $conn->prepare("SELECT * FROM sm_form_data WHERE certi_no >= ? and certi_no <= ?");
    if ($hasFormUserId) {
        $stmt->bind_param('ii', $from, $to);
    } else {
        $stmt->bind_param('ii', $from, $to);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
    ?>
        <div class="card">
          <img src="2.jpg" alt="" class="bck-image" />
          <div class="report-data">
            <table>
            <tr style = "display : <?php echo $settings['reportNo']; ?>">
            <td>Report No</td>
            <td>: <?php echo htmlspecialchars($row["report_no"]); ?></td>
        </tr>
              <tr style = "display : <?php echo $settings['weight']; ?>">
                <td>Weight</td>
                <td>: <?php echo $row["stone_wt"]; ?></td>
              </tr>
              <tr style = "display : <?php echo $settings['shapeCut']; ?>">
                <td>Shape / Cut</td>
                <td>: <?php echo $row["shape_cut"]; ?></td>
              </tr>
              <tr style = "display : <?php echo $settings['dimension']; ?>">
                <td>Dimension</td>
                <td>: <?php echo $row["dimension"]; ?></td>
              </tr>
              <tr style = "display : <?php echo $settings['colour']; ?>">
                <td>Colour</td>
                <td>: <?php echo $row["color"]; ?></td>
              </tr>
              <tr style = "display : <?php echo $settings['refractiveIndex']; ?>">
                <td>Refractive Index</td>
                <td>: <?php echo $row["ref_index"]; ?></td>
              </tr>
              <tr style = "display : <?php echo $settings['specificGravity']; ?>">
                <td>Specific Gravity</td>
                <td>: <?php echo $row["spe_gravit"]; ?></td>
              </tr>
              <tr style = "display : <?php echo $settings['speciesGroup']; ?>">
                <td>Species / Group</td>
                <td>: <?php echo $row["spe_group"]; ?></td>
              </tr>
              <tr style = "display : <?php echo $settings['remarks']; ?>">
                <td>Remarks</td>
                <td>: <?php echo $row["comment"]; ?></td>
              </tr>
            </table>
          </div>

          <div class="stone-img">
            <img src="assets/st_images/<?php echo $row["certi_no"]; ?>.JPG" alt="" class="stone-image" />
          </div>

          <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://rtrlu.com/index.php?certi-no=<?php echo $row["certi_no"]; ?>" alt="" class="qr-code" />
        </div>
    <?php
      }
    } else {
      echo "We cannot find any record for the values.";
    }
    ?>
  </div>

  <script>
    window.onload = function() {
      window.print();
    };
  </script>
</body>
</html>
