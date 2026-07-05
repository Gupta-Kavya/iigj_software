<?php require_once 'master_data_helper.php'; ?>
<?php include 'assets/navbar.php'; ?>
<link rel="stylesheet" href="//cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

<div id="page-wrapper">
    <div class="container-fluid master-page">
        <div class="master-header">
            <h1><i class="fa fa-database"></i> Colour Master</h1>
            <p>Edit colour values used in certificate dropdowns and forms.</p>
        </div>

        <div class="master-card">
            <div class="master-card-head">
                <h3>Saved colours</h3>
                <p>Update a value and click save, or delete entries you no longer need.</p>
            </div>
            <div class="master-table-wrap">
                <table class="table master-table" id="colour_master_table">
                    <thead>
                        <tr>
                            <th>Colour</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        include("db_connect.php");
                        $userId = auth_current_user_id();
                        $rows = master_fetch_rows($conn, 'sm_master_colour', ['colour'], $userId, ['colour'], 'colour');
                        $hasRows = !empty($rows);
                        if ($hasRows) {
                            foreach ($rows as $row) {
                                $id = (int) $row['id'];
                                $colour = htmlspecialchars($row['colour'], ENT_QUOTES, 'UTF-8');
                                $formId = 'colour_master_form_' . $id;
                                $canManage = master_can_manage_owner($userId, (int) $row['user_id']);
                                ?>
                                <tr>
                                    <td>
                                        <form id="<?php echo $formId; ?>" method="POST" action="master_edit.php?key=<?php echo $id; ?>&redirect_to=colour-master-menu.php"></form>
                                        <input form="<?php echo $formId; ?>" type="hidden" name="table_name" value="sm_master_colour">
                                        <input form="<?php echo $formId; ?>" type="hidden" name="col_name" value="colour">
                                        <input form="<?php echo $formId; ?>" type="text" name="colour" class="form-control" value="<?php echo $colour; ?>" <?php echo $canManage ? '' : 'readonly'; ?>>
                                    </td>
                                    <td>
                                        <div class="master-row-actions">
                                            <button form="<?php echo $formId; ?>" class="btn master-btn-icon master-btn-save" type="submit" title="<?php echo $canManage ? 'Save' : 'Shared default'; ?>" <?php echo $canManage ? '' : 'disabled'; ?>><i class="fa fa-save"></i></button>
                                            <button type="button" class="btn master-btn-icon master-btn-delete" title="<?php echo $canManage ? 'Delete' : 'Shared default'; ?>" <?php echo $canManage ? '' : 'disabled'; ?>
                                                data-delete-url="master_delete.php?key=<?php echo $id; ?>&table_name=sm_master_colour&redirect_to=colour-master-menu.php">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        $conn->close();
                        ?>
                    </tbody>
                </table>
                <?php if (!$hasRows): ?>
                    <div class="master-empty">No colour master entries yet. Add values from Add Master Details.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="//cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<?php include 'assets/footer.php'; ?>
