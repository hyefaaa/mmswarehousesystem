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
    echo '<div style="font-family: sans-serif; text-align: center; padding: 100px 20px;">
            <h1 style="color: #e74c3c;">🚫 Akses Dihalang!</h1>
            <p>Anda tidak mempunyai kebenaran untuk mengurus pengguna sistem ini.</p>
            <a href="index.php" style="color: #3498db; font-weight: bold; text-decoration: none;">Kembali ke Dashboard</a>
          </div>';
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
                <h1 class="fw-800 mb-1"><i class="bi bi-people-fill me-2"></i>User Management</h1>
                <p class="opacity-75 mb-0 fw-light">Manage access credentials, roles, and status of staff and dealers</p>
            </div>
            <button class="btn btn-info fw-bold px-4" onclick="openAddModal()">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna
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
            <h5 class="fw-800 text-navy mb-0"><i class="bi bi-people me-2"></i>Senarai Pengguna Hub</h5>
            <span class="badge bg-navy px-3 py-2 rounded-pill"><?= count($users) ?> Pengguna Aktif</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="usersTable">
                <thead>
                    <tr class="text-secondary small fw-bold">
                        <th>Nama Penuh</th>
                        <th>Username (ID Log Masuk)</th>
                        <th>Peranan (Role)</th>
                        <th>Status</th>
                        <th class="text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-1 mb-2"></i>
                                <p class="mb-0 fw-bold">Tiada data pengguna ditemui.</p>
                            </td>
                        </tr>
                    <?php else: 
 foreach($users as $u): 
                            $status_badge = $u['is_active'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                            $status_text = $u['is_active'] ? 'Aktif' : 'Nyahaktif';
                            
                            $role_badge = 'bg-secondary-subtle text-secondary';
                            if ($u['role'] === 'admin') {
                                $role_badge = 'bg-danger-subtle text-danger';
                            } elseif ($u['role'] === 'staff') {
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
                            </td>
                            <td>
                                <span class="badge <?= $status_badge ?> rounded-pill px-3 py-1 fw-bold" style="font-size: 0.72rem;">
                                    <?= $status_text ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm fw-bold px-3"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                                        <i class="bi bi-pencil-fill me-1"></i> Edit
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm fw-bold px-3"
                                            onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">
                                        <i class="bi bi-trash-fill me-1"></i> Padam
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
                        <label class="form-label fw-bold">Nama Penuh *</label>
                        <input type="text" name="full_name" id="formFullName" class="form-control" placeholder="Cth: Sharifah Munirah" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">ID Log Masuk (Username) *</label>
                        <input type="text" name="username" id="formUsername" class="form-control" placeholder="Cth: sya" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Kata Laluan</label>
                        <input type="password" name="password" id="formPassword" class="form-control" placeholder="Isi kata laluan baharu">
                        <small class="text-muted" id="passwordHelp">Bagi pengguna sedia ada, biarkan kosong jika tidak mahu menukar kata laluan.</small>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Peranan (Role) *</label>
                            <select name="role" id="formRole" class="form-select" required>
                                <option value="admin">Admin (Akses Penuh)</option>
                                <option value="staff">Staff (Operasi Gudang)</option>
                                <option value="dealer">Dealer (Kontraktor Hub)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Status Akaun *</label>
                            <select name="is_active" id="formIsActive" class="form-select" required>
                                <option value="1">Aktif</option>
                                <option value="0">Nyahaktif</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3" id="hdSelectionDiv" style="display: none;">
                        <label class="form-label fw-bold">Hub Dealer (HD) Asosiasi *</label>
                        <select name="hd_id" id="formHdId" class="form-select">
                            <option value="">-- Pilih HD --</option>
                            <?php foreach ($hds_list as $hd): ?>
                                <option value="<?= $hd['id'] ?>"><?= htmlspecialchars($hd['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3" style="border-bottom-left-radius: 18px; border-bottom-right-radius: 18px;">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info fw-bold px-4">Simpan Maklumat</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
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
                        title: 'Berjaya!',
                        text: response.success,
                        confirmButtonColor: '#0ea5e9'
                    }).then(() => {
                        window.location.reload();
                    });
                },
                error: function(xhr) {
                    let err = xhr.responseJSON ? xhr.responseJSON.error : 'Ralat pemrosesan.';
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: err,
                        confirmButtonColor: '#e74c3c'
                    });
                }
            });
        // Form role change handler
        $('#formRole').on('change', function() {
            if ($(this).val() === 'dealer') {
                $('#hdSelectionDiv').show();
                $('#formHdId').prop('required', true);
            } else {
                $('#hdSelectionDiv').hide();
                $('#formHdId').prop('required', false).val('');
            }
        });
    });

    function openAddModal() {
        $('#userForm')[0].reset();
        $('#formUserId').val('');
        $('#modalTitle').html('<i class="bi bi-person-plus-fill me-2"></i>Daftar Pengguna Baharu');
        $('#formUsername').prop('readonly', false);
        $('#formPassword').prop('required', true);
        $('#passwordHelp').hide();
        $('#formRole').val('dealer').trigger('change');
        userModal.show();
    }

    function openEditModal(user) {
        $('#userForm')[0].reset();
        $('#formUserId').val(user.id);
        $('#formFullName').val(user.full_name);
        $('#formUsername').val(user.username).prop('readonly', true);
        $('#formRole').val(user.role).trigger('change');
        $('#formHdId').val(user.hd_id);
        $('#formIsActive').val(user.is_active);
        $('#modalTitle').html('<i class="bi bi-person-fill-gear me-2"></i>Kemaskini Maklumat Pengguna');
        $('#formPassword').prop('required', false);
        $('#passwordHelp').show();
        userModal.show();
    }

    function deleteUser(id, username) {
        Swal.fire({
            title: 'Anda Pasti?',
            text: `Adakah anda ingin memadam akaun '${username}' secara kekal?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Padam!',
            cancelButtonText: 'Batal'
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
                            title: 'Dipadam!',
                            text: response.success,
                            confirmButtonColor: '#0ea5e9'
                        }).then(() => {
                            window.location.reload();
                        });
                    },
                    error: function(xhr) {
                        let err = xhr.responseJSON ? xhr.responseJSON.error : 'Gagal memadam pengguna.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
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
