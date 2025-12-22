<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

?>

<div class="user-panel">
    <h4 class="mb-3"><i class="fas fa-users"></i> <span lang="users"></span></h4>

    <div class="mb-3 d-flex gap-2">
        <button class="btn btn-success" id="addUserBtn"><i class="fa fa-plus"></i> <span lang="add_user"></span></button>
    </div>

    <div id="usersTableWrap">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th lang="id"></th>
                    <th lang="user_name"></th>
                    <th lang="role"></th>
                    <th lang="created_at"></th>
                    <th lang="status"></th>
                    <th lang="tabislem"></th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <!-- Filled by admin.js -->
            </tbody>
        </table>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="userModalTitle"></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="userForm">
                <input type="hidden" name="id" id="user_id">

                <div class="mb-3">
                    <label class="form-label" lang="user_name"></label>
                    <input type="text" class="form-control" name="username" id="user_username" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" lang="password"></label>
                    <input type="password" class="form-control" name="password" id="user_password" placeholder="(••••••)" >
                    <div class="form-text" lang="password_note"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" lang="role"></label>
                    <?php if (!empty($is_admin) && $is_admin): ?>
                    <select name="role" id="user_role" class="form-select">
                        <option value="admin">admin</option>
                        <option value="editor">editor</option>
                        <option value="user">user</option>
                    </select>
                    <?php else: ?>
                    <input type="hidden" name="role" id="user_role" value="user">
                    <div class="form-control-plaintext">(<?php echo htmlspecialchars($role ?? 'user'); ?>)</div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" lang="status"></label>
                    <select name="is_active" id="user_active" class="form-select">
                        <option value="1" lang="yes"></option>
                        <option value="0" lang="no"></option>
                    </select>
                </div>

            </form>
          </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" lang="cancel"></button>
                        <button type="button" class="btn btn-primary" id="saveUserBtn" lang="save_changes"></button>
                    </div>
        </div>
      </div>
    </div>

</div>
