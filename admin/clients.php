<?php
require_once '../config.php';
if (!isLoggedIn() || getUserType() !== 'admin') {
    redirect('login.php');
}
$message = '';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="clients_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID','Client Name','Email','Phone','Company','Address','City','State','Country','Status','Created At']);
    $clients = $conn->query("SELECT * FROM clients ORDER BY created_at DESC");
    while ($client = $clients->fetch_assoc()) {
        fputcsv($output, [
            $client['id'],
            $client['client_name'],
            $client['email'],
            $client['phone'],
            $client['company'],
            $client['address'],
            $client['city'],
            $client['state'],
            $client['country'],
            $client['status'],
            $client['created_at']
        ]);
    }
    fclose($output);
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'import') {
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == 0) {
        $file = $_FILES['import_file']['tmp_name'];
        $handle = fopen($file,'r');
        $row = 0;
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row++;
            if ($row == 1) continue;
            $client_name = sanitize($data[1]);
            $email = sanitize($data[2]);
            $phone = sanitize($data[3]);
            $company = sanitize($data[4]);
            $address = sanitize($data[5]);
            $city = sanitize($data[6]);
            $state = sanitize($data[7]);
            $country = sanitize($data[8]);
            $status = sanitize($data[9]);
            $added_by = (int)$_SESSION['user_id'];
            if (!empty($client_name)) {
                $check_sql = "SELECT id FROM clients WHERE email = ? OR (client_name = ? AND phone = ?)";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("sss",$email,$client_name,$phone);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $skipped++;
                } else {
                    $sql = "INSERT INTO clients (client_name,email,phone,company,address,city,state,country,added_by,status)
                            VALUES (?,?,?,?,?,?,?,?,?,?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssssssss",$client_name,$email,$phone,$company,$address,$city,$state,$country,$added_by,$status);
                    if ($stmt->execute()) $imported++;
                    else $errors++;
                }
            }
        }
        fclose($handle);
        $message = '<div class="alert alert-success">Import completed! Imported: '.$imported.' | Skipped: '.$skipped.' | Errors: '.$errors.'</div>';
    } else {
        $message = '<div class="alert alert-danger">Error uploading file.</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $client_name = sanitize($_POST['client_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $company = sanitize($_POST['company']);
            $address = sanitize($_POST['address']);
            $city = sanitize($_POST['city']);
            $state = sanitize($_POST['state']);
            $country = sanitize($_POST['country']);
            $added_by = (int)$_SESSION['user_id'];
            $status = sanitize($_POST['status']);
            $check_sql = "SELECT id FROM clients WHERE email = ? OR (client_name = ? AND phone = ?)";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("sss",$email,$client_name,$phone);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $message = '<div class="alert alert-warning">Duplicate client found. Entry skipped.</div>';
            } else {
                $sql = "INSERT INTO clients (client_name,email,phone,company,address,city,state,country,added_by,status)
                        VALUES (?,?,?,?,?,?,?,?,?,?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssss",$client_name,$email,$phone,$company,$address,$city,$state,$country,$added_by,$status);
                if ($stmt->execute()) $message = '<div class="alert alert-success">Client added successfully!</div>';
                else $message = '<div class="alert alert-danger">Error adding client.</div>';
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $conn->query("DELETE FROM clients WHERE id = $id");
            $message = '<div class="alert alert-success">Client deleted successfully!</div>';
        }
    }
}

$clients = $conn->query("SELECT c.*,u.name AS added_name FROM clients c LEFT JOIN users u ON c.added_by=u.id ORDER BY c.created_at DESC");
include 'header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6"><h2>Manage Clients</h2></div>
        <div class="col-md-6 text-end">
            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importClientModal"><i class="fas fa-file-import"></i> Import</button>
            <a href="?export=csv" class="btn btn-info me-2"><i class="fas fa-file-export"></i> Export CSV</a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal"><i class="fas fa-plus"></i> Add New Client</button>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Company</th>
                            <th>City</th><th>Country</th><th>Status</th><th>Added By</th><th>Created</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $Sno =0;while ($client = $clients->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo ++$Sno; ?></td>
                            <td><?php echo htmlspecialchars($client['client_name']); ?></td>
                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                            <td><?php echo htmlspecialchars($client['phone']); ?></td>
                            <td><?php echo htmlspecialchars($client['company']); ?></td>
                            <td><?php echo htmlspecialchars($client['city']); ?></td>
                            <td><?php echo htmlspecialchars($client['country']); ?></td>
                            <td><span class="badge bg-<?php echo $client['status']==='active'?'success':'danger'; ?>"><?php echo ucfirst($client['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($client['added_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo formatDate($client['created_at']); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewClient(<?php echo $client['id']; ?>)"><i class="fas fa-eye"></i></button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirmDelete();">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $client['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5>Add New Client</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Client Name *</label><input type="text" class="form-control" name="client_name" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" class="form-control" name="phone"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Company</label><input type="text" class="form-control" name="company"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">City</label><input type="text" class="form-control" name="city"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">State</label><input type="text" class="form-control" name="state"></div>
                    </div>
                    <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Country</label><input type="text" class="form-control" name="country"></div></div>
                    <div class="mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="3"></textarea></div>
                    <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Client</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Import Client Modal -->
<div class="modal fade" id="importClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5>Import Clients</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="import">
                    <div class="mb-3"><label class="form-label">Upload CSV File</label><input type="file" class="form-control" name="import_file" accept=".csv" required>
                        <div class="form-text">CSV format: ID, Client Name, Email, Phone, Company, Address, City, State, Country, Status</div>
                    </div>
                    <div class="alert alert-info"><strong>Note:</strong> First row should contain headers and will be skipped.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Import</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function viewClient(id) {
    var modal = new bootstrap.Modal(document.getElementById('viewClientModal'));
    modal.show();
    fetch('get_client_details.php?id=' + id)
        .then(response => response.text())
        .then(data => { document.getElementById('clientDetailsContent').innerHTML = data; })
        .catch(() => { document.getElementById('clientDetailsContent').innerHTML = '<div class="alert alert-danger">Error loading client details.</div>'; });
}
function confirmDelete() { return confirm('Are you sure you want to delete this client?'); }
</script>

<!-- View Client Details Modal -->
<div class="modal fade" id="viewClientModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header"><h5>Client Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="clientDetailsContent"><div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div></div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
