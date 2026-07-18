<?php
// user_management.php
// Portal Pengurusan Akaun Pengguna & Staff (Khas untuk Admin)

require_once 'config/db.php';

// Memastikan sesi dimulakan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sekatan peranan: Hanya Admin sahaja boleh mengurus pengguna
$role_check = $_SESSION['role'] ?? '';
if ($role_check !== 'admin') {
    http_response_code(403);
    $page_title = 'Akses Dihalang';
    require_once 'includes/header.php';
    echo '<div class="container-fluid px-4 py-5 text-center">
            <div class="card shadow-sm mx-auto p-5" style="max-width: 500px; border-radius: 16px;">
                <h1 style="color: #e74c3c;" class="display-4"><i class="bi bi-shield-slash-fill"></i></h1>
                <h3 class="fw-bold mt-3">Akses Dihalang!</h3>
                <p class="text-muted">Anda tidak mempunyai kebenaran untuk mengurus pengguna sistem ini.</p>
                <a href="index.php" class="btn btn-primary mt-3 py-2 px-4" style="background: #0f172a; border: none;">Kembali ke Dashboard</a>
            </div>
          </div>';
    require_once 'includes/footer.php';
    exit;
}

$error = '';
$users = [];
$hds_list = [];

try {
    $users = $pdo->query("SELECT u.*, h.name as hd_name FROM users u LEFT JOIN hds h ON u.hd_id = h.id ORDER BY u.role ASC, u.username ASC")->fetchAll(PDO::FETCH_ASSOC);
    $hds_list = $pdo->query("SELECT id, name FROM hds WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Gagal memuatkan senarai pengguna: ' . $e->getMessage();
}

$page_title = 'Pengurusan Pengguna | Moo Moo Supplies';
require_once 'includes/header.php';
?>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-people-fill me-2"></i><span data-lang="user_mgmt_page_title">User Management</span></h1>
                <p class="opacity-75 mb-0 fw-light" data-lang="user_mgmt_desc">Manage access credentials, roles, and status of staff and dealers</p>
            </div>
            <button class="btn btn-info fw-bold px-4" onclick="openAddModal()">
                <i class="bi bi-person-plus-fill me-1"></i> <span data-lang="user_mgmt_add_btn">Tambah Pengguna</span>
            </button>
        </div>
    </div>
</div>

<div class="container-fluid px-4">
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card main-card border-0 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-800 text-navy mb-0"><i class="bi bi-people me-2"></i><span data-lang="user_mgmt_list_title">Senarai Pengguna Hub</span></h5>
            <span class="badge bg-navy px-3 py-2 rounded-pill"><?= count($users) ?> <span data-lang="user_mgmt_active_users">Pengguna Aktif</span></span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="usersTable">
                <thead>
                    <tr class="text-secondary small fw-bold">
                        <th data-lang="user_mgmt_col_name">Nama Penuh</th>
                        <th data-lang="user_mgmt_col_username">Username (ID Log Masuk)</th>
                        <th data-lang="user_mgmt_col_role">Peranan (Role)</th>
                        <th data-lang="lbl_status">Status</th>
                        <th class="text-center" data-lang="lbl_action">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-1 mb-2"></i>
                                <p class="mb-0 fw-bold" data-lang="user_mgmt_empty">Tiada data pengguna ditemui.</p>
                            </td>
                        </tr>
                    <?php else: 
 foreach($users as $u): 
                            $status_badge = $u['is_active'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                            $status_text_lang = $u['is_active'] ? 'user_mgmt_active' : 'user_mgmt_inactive';
                            $status_text = $u['is_active'] ? 'Aktif' : 'Nyahaktif';
                            
                            $role_badge = 'bg-secondary-subtle text-secondary';
                            if ($u['role'] === 'admin') {
                                $role_badge = 'bg-danger-subtle text-danger';
                            } elseif (in_array($u['role'], ['staff', 'staff_jomcha'])) {
                                $role_badge = 'bg-primary-subtle text-primary';
                            } elseif ($u['role'] === 'dealer') {
                                $role_badge = 'bg-success-subtle text-success';
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($u['full_name']) ?></div>
                            </td>
                            <td><code class="fw-bold text-navy"><?= htmlspecialchars($u['username']) ?></code></td>
                            <td>
                                <span class="badge <?= $role_badge ?> rounded-pill px-3 py-1 fw-bold text-uppercase" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                                <?php if ($u['role'] === 'dealer' && !empty($u['hd_name'])): ?>
                                    <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                                        <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($u['hd_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (in_array($u['role'], ['staff', 'intern', 'pss_admin', 'staff_jomcha'])): ?>
                                    <?php if (!empty($u['allowed_view_modules'])): ?>
                                        <div class="small text-muted mt-1" style="font-size: 0.7rem; max-width: 220px;">
                                            <i class="bi bi-eye-fill me-1 text-info"></i>View: <span class="text-dark fw-bold"><?= str_replace(',', ', ', htmlspecialchars($u['allowed_view_modules'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($u['allowed_modules'])): ?>
                                        <div class="small text-muted mt-1" style="font-size: 0.7rem; max-width: 220px;">
                                            <i class="bi bi-pencil-fill me-1 text-success"></i>Edit: <span class="text-dark fw-bold"><?= str_replace(',', ', ', htmlspecialchars($u['allowed_modules'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $status_badge ?> rounded-pill px-3 py-1 fw-bold" style="font-size: 0.72rem;" data-lang="<?= $status_text_lang ?>">
                                    <?= $status_text ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm fw-bold px-3"
                                             onclick="openEditModal(<?= $u['id'] ?>)">
                                         <i class="bi bi-pencil-fill me-1"></i> <span data-lang="btn_edit">Edit</span>
                                     </button>
                                     <button type="button" class="btn btn-outline-danger btn-sm fw-bold px-3"
                                             onclick="deleteUser(<?= $u['id'] ?>)">
                                         <i class="bi bi-trash-fill me-1"></i> <span data-lang="btn_delete">Padam</span>
                                     </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; 
 endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Borang Pengguna -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <div class="modal-header bg-navy text-white py-3" style="background-color: #0f172a; border-top-left-radius: 18px; border-top-right-radius: 18px;">
                <h6 class="modal-title fw-800" id="modalTitle"><i class="bi bi-person-fill-gear me-2"></i>Borang Pengguna</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="userForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="user_id" id="formUserId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold" data-lang="user_mgmt_form_name">Nama Penuh *</label>
                        <input type="text" name="full_name" id="formFullName" class="form-control" placeholder="Cth: Sharifah Munirah" data-lang-placeholder="user_mgmt_placeholder_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold" data-lang="user_mgmt_form_username">ID Log Masuk (Username) *</label>
                        <input type="text" name="username" id="formUsername" class="form-control" placeholder="Cth: sya" data-lang-placeholder="user_mgmt_placeholder_username" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" data-lang="user_mgmt_form_password">Kata Laluan</label>
                        <input type="password" name="password" id="formPassword" class="form-control" placeholder="Isi kata laluan baharu" data-lang-placeholder="user_mgmt_placeholder_password">
                        <small class="text-muted" id="passwordHelp" data-lang="user_mgmt_pwd_help">Bagi pengguna sedia ada, biarkan kosong jika tidak mahu menukar kata laluan.</small>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" data-lang="user_mgmt_form_role">Peranan (Role) *</label>
                            <select name="role" id="formRole" class="form-select" required>
                                <option value="admin" data-lang="user_mgmt_role_admin">Admin (Akses Penuh)</option>
                                <option value="staff" data-lang="user_mgmt_role_staff">Staff (Operasi Gudang)</option>
                                <option value="staff_jomcha">Staff Jomcha</option>
                                <option value="pss_admin" data-lang="user_mgmt_role_pss">PSS Admin</option>
                                <option value="intern" data-lang="user_mgmt_role_intern">Intern</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" data-lang="user_mgmt_form_status">Status Akaun *</label>
                            <select name="is_active" id="formIsActive" class="form-select" required>
                                <option value="1" data-lang="user_mgmt_active">Aktif</option>
                                <option value="0" data-lang="user_mgmt_inactive">Nyahaktif</option>
                            </select>
                        </div>
                    </div>

                    <div id="permissionsDiv" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-info"><i class="bi bi-eye me-1"></i>Modul Boleh Dilihat (View Permissions)</label>
                            <div class="row row-cols-2 g-2 bg-light p-3 rounded border mb-3">
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-view-cb" type="checkbox" value="pss" id="view_pss" name="allowed_view_modules[]">
                                        <label class="form-check-label small" for="view_pss">PSS Delivery & Hub</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-view-cb" type="checkbox" value="daily_closing" id="view_daily_closing" name="allowed_view_modules[]">
                                        <label class="form-check-label small" for="view_daily_closing">Daily Closing Audit</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-view-cb" type="checkbox" value="stock_transfer" id="view_stock_transfer" name="allowed_view_modules[]">
                                        <label class="form-check-label small" for="view_stock_transfer">Stock Transfer</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-view-cb" type="checkbox" value="stock_take" id="view_stock_take" name="allowed_view_modules[]">
                                        <label class="form-check-label small" for="view_stock_take">Stock Take</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-view-cb" type="checkbox" value="product_management" id="view_product_management" name="allowed_view_modules[]">
                                        <label class="form-check-label small" for="view_product_management">Product Management</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-view-cb" type="checkbox" value="receiving" id="view_receiving" name="allowed_view_modules[]">
                                        <label class="form-check-label small" for="view_receiving">Inbound / Receiving</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-view-cb" type="checkbox" value="outbound_reconcile" id="view_outbound_reconcile" name="allowed_view_modules[]">
                                        <label class="form-check-label small" for="view_outbound_reconcile">Outbound & Reconcile</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-view-cb" type="checkbox" value="spoilage" id="view_spoilage" name="allowed_view_modules[]">
                                        <label class="form-check-label small" for="view_spoilage">Spoilage Report</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-view-cb" type="checkbox" value="user_management" id="view_user_management" name="allowed_view_modules[]">
                                        <label class="form-check-label small" for="view_user_management">User Management</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-success"><i class="bi bi-pencil me-1"></i>Modul Boleh Diedit (Write Permissions) *</label>
                            <div class="row row-cols-2 g-2 bg-light p-3 rounded border">
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-cb" type="checkbox" value="pss" id="perm_pss" name="allowed_modules[]">
                                        <label class="form-check-label small" for="perm_pss" data-lang="user_mgmt_perm_pss">PSS Delivery & Hub</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-cb" type="checkbox" value="daily_closing" id="perm_daily_closing" name="allowed_modules[]">
                                        <label class="form-check-label small" for="perm_daily_closing" data-lang="user_mgmt_perm_daily_closing">Daily Closing Audit</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-cb" type="checkbox" value="stock_transfer" id="perm_stock_transfer" name="allowed_modules[]">
                                        <label class="form-check-label small" for="perm_stock_transfer" data-lang="user_mgmt_perm_stock_transfer">Stock Transfer</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-cb" type="checkbox" value="stock_take" id="perm_stock_take" name="allowed_modules[]">
                                        <label class="form-check-label small" for="perm_stock_take" data-lang="user_mgmt_perm_stock_take">Stock Take</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-cb" type="checkbox" value="product_management" id="perm_product_management" name="allowed_modules[]">
                                        <label class="form-check-label small" for="perm_product_management" data-lang="user_mgmt_perm_product_management">Product Management</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-cb" type="checkbox" value="receiving" id="perm_receiving" name="allowed_modules[]">
                                        <label class="form-check-label small" for="perm_receiving" data-lang="user_mgmt_perm_receiving">Inbound / Receiving</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-cb" type="checkbox" value="outbound_reconcile" id="perm_outbound_reconcile" name="allowed_modules[]">
                                        <label class="form-check-label small" for="perm_outbound_reconcile" data-lang="user_mgmt_perm_outbound_reconcile">Outbound & Reconcile</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-cb" type="checkbox" value="spoilage" id="perm_spoilage" name="allowed_modules[]">
                                        <label class="form-check-label small" for="perm_spoilage" data-lang="user_mgmt_perm_spoilage">Spoilage Report</label>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input permission-cb" type="checkbox" value="user_management" id="perm_user_management" name="allowed_modules[]">
                                        <label class="form-check-label small" for="perm_user_management" data-lang="user_mgmt_perm_user_management">User Management</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3" style="border-bottom-left-radius: 18px; border-bottom-right-radius: 18px;">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal" data-lang="btn_cancel">Batal</button>
                    <button type="submit" class="btn btn-info fw-bold px-4" data-lang="user_mgmt_btn_save">Simpan Maklumat</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    const usersData = <?= json_encode($users) ?>;
    let userModal;

    $(document).ready(function() {
        userModal = new bootstrap.Modal(document.getElementById('userModal'));
        
        // Pemrosesan borang simpan/kemaskini
        $('#userForm').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                url: 'api/save_user.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    userModal.hide();
                    Swal.fire({
                        icon: 'success',
                        title: MMS_LANG.t('lbl_success') + '!',
                        text: response.success,
                        confirmButtonColor: '#0ea5e9'
                    }).then(() => {
                        window.location.reload();
                    });
                },
                error: function(xhr) {
                    let err = xhr.responseJSON ? xhr.responseJSON.error : 'Error processing request.';
                    Swal.fire({
                        icon: 'error',
                        title: MMS_LANG.t('lbl_error') + '!',
                        text: err,
                        confirmButtonColor: '#e74c3c'
                    });
                }
            });
        });
        
        $('#formRole').on('change', function() {
            const val = $(this).val();
            if (val === 'staff' || val === 'intern' || val === 'pss_admin' || val === 'staff_jomcha') {
                $('#permissionsDiv').show();
            } else {
                $('#permissionsDiv').hide();
            }
        });
    });

    function openAddModal() {
        $('#userForm')[0].reset();
        $('#formUserId').val('');
        $('#modalTitle').html('<i class="bi bi-person-plus-fill me-2"></i>' + MMS_LANG.t('user_mgmt_js_add_title'));
        $('#formUsername').prop('readonly', false);
        $('#formPassword').prop('required', true);
        $('#passwordHelp').hide();
        $('.permission-cb').prop('checked', false);
        $('.permission-view-cb').prop('checked', false);
        $('#formRole').val('staff').trigger('change');
        userModal.show();
    }

    function openEditModal(id) {
        const user = usersData.find(u => u.id == id);
        if (!user) return;

        $('#userForm')[0].reset();
        $('#formUserId').val(user.id);
        $('#formFullName').val(user.full_name);
        $('#formUsername').val(user.username).prop('readonly', true);
        $('#formRole').val(user.role).trigger('change');
        $('#formIsActive').val(user.is_active);
        $('#modalTitle').html('<i class="bi bi-person-fill-gear me-2"></i>' + MMS_LANG.t('user_mgmt_js_edit_title'));
        $('#formPassword').prop('required', false);
        $('#passwordHelp').show();
        
        // Set checkboxes
        $('.permission-cb').prop('checked', false);
        $('.permission-view-cb').prop('checked', false);
        
        if (user.role === 'staff' || user.role === 'intern' || user.role === 'pss_admin' || user.role === 'staff_jomcha') {
            if (user.allowed_modules) {
                const modules = user.allowed_modules.split(',');
                modules.forEach(m => {
                    $(`#perm_${m.trim()}`).prop('checked', true);
                });
            }
            if (user.allowed_view_modules) {
                const viewModules = user.allowed_view_modules.split(',');
                viewModules.forEach(m => {
                    $(`#view_${m.trim()}`).prop('checked', true);
                });
            }
        }
        userModal.show();
    }

    function deleteUser(id) {
        const user = usersData.find(u => u.id == id);
        if (!user) return;
        const username = user.username;

        Swal.fire({
            title: MMS_LANG.t('user_mgmt_js_delete_confirm_title'),
            text: `${MMS_LANG.t('user_mgmt_js_delete_confirm_desc')} '${username}'?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: MMS_LANG.t('user_mgmt_js_delete_confirm_btn'),
            cancelButtonText: MMS_LANG.t('btn_cancel')
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/delete_user.php',
                    type: 'POST',
                    data: { user_id: id },
                    dataType: 'json',
                    success: function(response) {
                        Swal.fire({
                            icon: 'success',
                            title: MMS_LANG.t('user_mgmt_js_deleted'),
                            text: response.success,
                            confirmButtonColor: '#0ea5e9'
                        }).then(() => {
                            window.location.reload();
                        });
                    },
                    error: function(xhr) {
                        let err = xhr.responseJSON ? xhr.responseJSON.error : MMS_LANG.t('user_mgmt_js_delete_fail');
                        Swal.fire({
                            icon: 'error',
                            title: MMS_LANG.t('lbl_error') + '!',
                            text: err,
                            confirmButtonColor: '#e74c3c'
                        });
                    }
                });
            }
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
