<?php
require_once __DIR__ . '/../auth.php';
auth_require_login();
require_once __DIR__ . '/../db_connect.php';
$navbarUserId = auth_current_user_id();
$navbarRoleStmt = $conn->prepare("SELECT role FROM sm_users WHERE id = ? LIMIT 1");
if ($navbarRoleStmt) {
    $navbarRoleStmt->bind_param('i', $navbarUserId);
    $navbarRoleStmt->execute();
    $navbarRole = $navbarRoleStmt->get_result()->fetch_assoc();
    $navbarRoleStmt->close();
    $_SESSION['user_role'] = $navbarRole['role'] ?? 'user';
}
$navbarCanUseAnyCertificate = true;
$navbarCanUseStone = true;
$navbarCanUseDiamond = true;
$navbarCanUseJewellery = true;
$navbarCanUseRudraksha = true;
$currentPage = basename($_SERVER['PHP_SELF']);
$masterPages = ['add-master.php', 'stone-master-menu.php', 'shape-cut-master-menu.php', 'ri-master-menu.php', 'magni-master-menu.php', 'colour-master-menu.php', 'colour-report-type-master.php'];
if (auth_is_super_admin()) {
    $masterPages[] = 'rate-condition-master.php';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Startmin - Bootstrap Admin Theme</title>

    <!-- Bootstrap Core CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../css/bootstrap.min.css" rel="stylesheet">

    <!-- MetisMenu CSS -->
    <link href="../css/metisMenu.min.css" rel="stylesheet">

    <!-- Timeline CSS -->
    <link href="../css/timeline.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="../css/startmin.css" rel="stylesheet">
    <link href="../css/common-theme.css" rel="stylesheet">
    <link href="../css/app-toast.css" rel="stylesheet">
    <link href="../css/master-pages.css" rel="stylesheet">

    <!-- Custom Fonts -->
    <link href="../css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <script src="../js/jquery.min.js"></script>
    <script src="../js/app-toast.js"></script>
    <link href="../css/cropper.min.css" rel="stylesheet" />
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/respond.js/1.4.2/respond.min.js"></script>
        <![endif]-->
</head>

<body>
    <script>
    (function () {
        try {
            if (window.matchMedia("(min-width: 768px)").matches && window.localStorage.getItem("smartlinkSidebarCollapsed") === "1") {
                document.body.className += (document.body.className ? " " : "") + "app-sidebar-collapsed";
            }
        } catch (error) {}
    })();
    </script>

    <div id="wrapper">

        <!-- Navigation -->
        <nav class="navbar navbar-inverse navbar-fixed-top app-navbar" role="navigation">
            <div class="navbar-header">
                <a class="navbar-brand app-brand" href="index.php"><i class="fa fa-diamond"></i> SMARTLINK SOFT</a>
            </div>

            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>

            <ul class="nav navbar-nav navbar-left navbar-top-links app-top-context">
                <li>
                    <button type="button" class="app-sidebar-toggle" id="appSidebarToggle" aria-label="Hide sidebar" aria-expanded="true" title="Hide sidebar">
                        <i class="fa fa-angle-left" aria-hidden="true"></i>
                    </button>
                </li>
                <li><a href="index.php"><i class="fa fa-home fa-fw"></i> Laboratory Panel</a></li>
            </ul>

            <ul class="nav navbar-right navbar-top-links">
                <li class="dropdown navbar-inverse app-alert-dropdown">
                    <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                        <i class="fa fa-bell fa-fw"></i> <b class="caret"></b>
                    </a>
                    <ul class="dropdown-menu dropdown-alerts">
                        <li>
                            <a href="#">
                                <div>
                                    <i class="fa fa-comment fa-fw"></i> New Comment
                                    <span class="pull-right text-muted small">4 minutes ago</span>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <div>
                                    <i class="fa fa-twitter fa-fw"></i> 3 New Followers
                                    <span class="pull-right text-muted small">12 minutes ago</span>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <div>
                                    <i class="fa fa-envelope fa-fw"></i> Message Sent
                                    <span class="pull-right text-muted small">4 minutes ago</span>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <div>
                                    <i class="fa fa-tasks fa-fw"></i> New Task
                                    <span class="pull-right text-muted small">4 minutes ago</span>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <div>
                                    <i class="fa fa-upload fa-fw"></i> Server Rebooted
                                    <span class="pull-right text-muted small">4 minutes ago</span>
                                </div>
                            </a>
                        </li>
                        <li class="divider"></li>
                        <li>
                            <a class="text-center" href="#">
                                <strong>See All Alerts</strong>
                                <i class="fa fa-angle-right"></i>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="dropdown app-user-menu">
                    <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                        <i class="fa fa-user fa-fw"></i> <?php echo htmlspecialchars(auth_current_user_name()); ?> <b class="caret"></b>
                    </a>
                    <ul class="dropdown-menu dropdown-user">
                        <li><a href="profile.php"><i class="fa fa-user fa-fw"></i> My Profile</a>
                        </li>
                        <?php if (auth_is_super_admin()): ?>
                        <li><a href="super-admin.php"><i class="fa fa-shield fa-fw"></i> Super Admin</a></li>
                        <?php endif; ?>
                        <!-- <li><a href="y"><i class="fa fa-gear fa-fw"></i> Settings</a>
                        </li> -->
                        <li class="divider"></li>
                        <li><a href="logout.php"><i class="fa fa-sign-out fa-fw"></i> Logout</a>
                        </li>
                    </ul>
                </li>
            </ul>
            <!-- /.navbar-top-links -->



            <div class="navbar-default sidebar app-sidebar" role="navigation" >
                <div class="sidebar-nav navbar-collapse" >
                    <ul class="nav" id="side-menu">

                    <!-- <li>
                            <div class="software_owner_logo app-sidebar-logo" style = "text-decoration:none;">
                                <img src="assets/software_name.jpg" alt="" width="100%">
                            </div>
                        </li> -->

                        <li>
                            <a href="index.php" class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>"><i class="fa fa-dashboard fa-fw"></i> Dashboard</a>
                        </li>

                        <?php if ($navbarCanUseAnyCertificate): ?>
                        <li>
                            <a href="../pages/agreement.php" class="<?php echo in_array($currentPage, ['agreement.php', 'agreement-print.php'], true) ? 'active' : ''; ?>"><i class="fa fa-file-text-o fa-fw"></i> Stone Agreement</a>
                        </li>
                        <?php endif; ?>

                        <?php if (auth_is_super_admin()): ?>
                        <li>
                            <a href="super-admin.php" class="<?php echo in_array($currentPage, ['super-admin.php', 'super-admin-user.php'], true) ? 'active' : ''; ?>"><i class="fa fa-shield fa-fw"></i> Super Admin</a>
                        </li>
                        <?php endif; ?>

                        <?php if ($navbarCanUseStone): ?>
                        <li>
                            <a href="../pages/c_stone.php" class="<?php echo $currentPage === 'c_stone.php' ? 'active' : ''; ?>"><i class="fa fa-diamond fa-fw"></i> Colour Stone
                                Feeding</a>
                        </li>
                        <?php endif; ?>

                        <?php if ($navbarCanUseDiamond): ?>
                        <li>
                            <a href="../pages/diamond.php" class="<?php echo $currentPage === 'diamond.php' ? 'active' : ''; ?>"><i class="fa fa-diamond fa-fw"></i> Diamond
                                Feeding</a>
                        </li>
                        <?php endif; ?>

                        <?php if ($navbarCanUseJewellery): ?>
                        <li>
                            <a href="../pages/jewellery.php" class="<?php echo $currentPage === 'jewellery.php' ? 'active' : ''; ?>"><i class="fa fa-diamond fa-fw"></i> Jewellery
                                Feeding</a>
                        </li>
                        <?php endif; ?>

                        <?php if ($navbarCanUseRudraksha): ?>
                        <li>
                            <a href="../pages/rudraksha.php" class="<?php echo $currentPage === 'rudraksha.php' ? 'active' : ''; ?>"><i class="fa fa-circle-o fa-fw"></i> Rudraksha
                                Feeding</a>
                        </li>
                        <?php endif; ?>

                        <?php if ($navbarCanUseAnyCertificate): ?>
                        <li>
                            <a href="../pages/image_manager.php" class="<?php echo $currentPage === 'image_manager.php' ? 'active' : ''; ?>"><i class="fa fa-picture-o fa-fw"></i> Image
                                Manager</a>
                        </li>

                        <li>
                            <a href="../pages/report_generate.php" class="<?php echo $currentPage === 'report_generate.php' || $currentPage === 'atm_report_generate.php' ? 'active' : ''; ?>"><i class="fa fa-file-pdf-o fa-fw"></i>
                                Generate Reports</a>
                        </li>
                        <?php endif; ?>

                        <!-- <li>
                            <a href="#" class=""><i class="fa fa-id-card-o fa-fw"></i> Generate Long Stickers</a>
                        </li>

                        <li>
                            <a href="#" class=""><i class="fa fa-credit-card fa-fw"></i> Back Side Generation</a>
                        </li> -->

                        <?php if ($navbarCanUseAnyCertificate): ?>
                        <li>
                            <a href="edit-report.php" class="<?php echo $currentPage === 'edit-report.php' ? 'active' : ''; ?>"><i class="fa fa-edit fa-fw"></i> Edit Reports</a>
                        </li>

                        <li>
                            <a href="../pages/settings.php" class="<?php echo in_array($currentPage, ['settings.php', 'backPrintSettings.php', 'a4Settings.php', 'postcardSettings.php'], true) ? 'active' : ''; ?>"><i class="fa fa-cog fa-fw"></i> Settings</a>
                        </li>
                        <?php endif; ?>

                        <li>
                            <a href="../pages/apiSettings.php" class="<?php echo $currentPage === 'apiSettings.php' ? 'active' : ''; ?>"><i class="fa fa-key fa-fw"></i> API &amp; Verification</a>
                        </li>
                        
                         <li>
    <a href="#mastermenu" data-toggle="collapse" aria-expanded="<?php echo in_array($currentPage, $masterPages, true) ? 'true' : 'false'; ?>" class="<?php echo in_array($currentPage, $masterPages, true) ? 'active' : ''; ?>">
        <i class="fa fa-database fa-fw"></i> Master Menu <span class="fa arrow"></span>
    </a>
    <ul class="nav nav-second-level collapse <?php echo in_array($currentPage, $masterPages, true) ? 'in' : ''; ?>" id="mastermenu">
        <li>
            <a href="add-master.php" class="<?php echo $currentPage === 'add-master.php' ? 'active' : ''; ?>">Add Master Details</a>
        </li>
        <li>
            <a href="colour-report-type-master.php" class="<?php echo $currentPage === 'colour-report-type-master.php' ? 'active' : ''; ?>">Colour Report Types</a>
        </li>
        <?php if (auth_is_super_admin()): ?>
            <li>
                <a href="rate-condition-master.php" class="<?php echo $currentPage === 'rate-condition-master.php' ? 'active' : ''; ?>">Rate Condition Master</a>
            </li>
        <?php endif; ?>
        <li>
            <a href="#editmastermenu" data-toggle="collapse" aria-expanded="false">Edit Master Menu <span class="fa arrow"></span></a>
            <ul class="nav nav-third-level collapse" id="editmastermenu">
                <li>
                    <a href="stone-master-menu.php" class="<?php echo $currentPage === 'stone-master-menu.php' ? 'active' : ''; ?>">Stone Name</a>
                </li>
                <li>
                    <a href="shape-cut-master-menu.php" class="<?php echo $currentPage === 'shape-cut-master-menu.php' ? 'active' : ''; ?>">Shape / Cut</a>
                </li>
                <li>
                    <a href="ri-master-menu.php" class="<?php echo $currentPage === 'ri-master-menu.php' ? 'active' : ''; ?>">Refractive Index</a>
                </li>
                <li>
                    <a href="magni-master-menu.php" class="<?php echo $currentPage === 'magni-master-menu.php' ? 'active' : ''; ?>">Magnification</a>
                </li>
                <li>
                    <a href="colour-master-menu.php" class="<?php echo $currentPage === 'colour-master-menu.php' ? 'active' : ''; ?>">Colour</a>
                </li>
            </ul>
        </li>
    </ul>
</li>


                </div>
         
            </div>
        </nav>
        <button type="button" class="app-sidebar-reveal" id="appSidebarReveal" aria-label="Show sidebar" title="Show sidebar">
            <i class="fa fa-angle-right" aria-hidden="true"></i>
        </button>
