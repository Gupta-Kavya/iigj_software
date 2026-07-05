<?php require_once 'master_data_helper.php'; ?>
<?php include 'assets/navbar.php'; ?>
<link rel="stylesheet" href="//cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

<div id="page-wrapper">
    <div class="container-fluid master-page">
        <div class="master-header">
            <h1><i class="fa fa-database"></i> Stone Name Master</h1>
            <p>Edit stone names and species/group values used across certificate forms.</p>
        </div>

        <div class="master-card">
            <div class="master-card-head">
                <h3>Saved stone names</h3>
                <p>Update a row and save, or delete entries you no longer need.</p>
            </div>
            <div class="master-table-wrap">
                <table class="table master-table" id="stone_master_table">
                    <thead>
                        <tr>
                            <th>Stone Name</th>
                            <th>Species / Group</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        include("db_connect.php");
                        $userId = auth_current_user_id();
                        $rows = master_fetch_rows($conn, 'sm_master_stone_name', ['stone_name', 'group'], $userId, ['stone_name', 'group'], 'stone_name');
                        $hasRows = !empty($rows);
                        if ($hasRows) {
                            foreach ($rows as $row) {
                                $id = (int) $row['id'];
                                $stoneName = htmlspecialchars($row['stone_name'], ENT_QUOTES, 'UTF-8');
                                $group = htmlspecialchars($row['group'], ENT_QUOTES, 'UTF-8');
                                $formId = 'stone_master_form_' . $id;
                                $canManage = master_can_manage_owner($userId, (int) $row['user_id']);
                                ?>
                                <tr>
                                    <td>
                                        <form id="<?php echo $formId; ?>" method="POST" action="master_edit.php?key=<?php echo $id; ?>&redirect_to=stone-master-menu.php"></form>
                                        <input form="<?php echo $formId; ?>" type="hidden" name="table_name" value="sm_master_stone_name">
                                        <input form="<?php echo $formId; ?>" type="hidden" name="col_name" value="stone_name">
                                        <input form="<?php echo $formId; ?>" type="text" name="stone_name" class="form-control" value="<?php echo $stoneName; ?>" <?php echo $canManage ? '' : 'readonly'; ?>>
                                    </td>
                                    <td>
                                        <input form="<?php echo $formId; ?>" type="text" name="group" class="form-control" value="<?php echo $group; ?>" <?php echo $canManage ? '' : 'readonly'; ?>>
                                    </td>
                                    <td>
                                        <div class="master-row-actions">
                                            <button form="<?php echo $formId; ?>" class="btn master-btn-icon master-btn-save" type="submit" title="<?php echo $canManage ? 'Save' : 'Shared default'; ?>" <?php echo $canManage ? '' : 'disabled'; ?>><i class="fa fa-save"></i></button>
                                            <button type="button" class="btn master-btn-icon master-btn-delete" title="<?php echo $canManage ? 'Delete' : 'Shared default'; ?>"
                                                <?php echo $canManage ? '' : 'disabled'; ?>
                                                data-delete-url="master_delete.php?key=<?php echo $id; ?>&table_name=sm_master_stone_name&redirect_to=stone-master-menu.php">
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
                    <div class="master-empty">No stone name entries yet. Add values from Add Master Details.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="//cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<?php include 'assets/footer.php'; ?>
