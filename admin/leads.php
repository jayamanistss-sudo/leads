<?php
require_once '../config.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirect('login.php');
}

$message = '';

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filter_client = isset($_GET['filter_client']) ? (int)$_GET['filter_client'] : 0;
    $filter_project = isset($_GET['filter_project']) ? (int)$_GET['filter_project'] : 0;
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

    $sql = "SELECT l.*, ls.source_name, u.name as assigned_name, c.client_name, p.project_name
            FROM leads l
            LEFT JOIN lead_sources ls ON l.source_id = ls.id
            LEFT JOIN users u ON l.assigned_to = u.id
            LEFT JOIN clients c ON l.client_id = c.id
            LEFT JOIN projects p ON l.project_id = p.id WHERE 1=1";
    
    if ($filter_client > 0) {
        $sql .= " AND l.client_id = $filter_client";
    }
    if ($filter_project > 0) {
        $sql .= " AND l.project_id = $filter_project";
    }
    if (!empty($search)) {
        $sql .= " AND (l.lead_name LIKE '%$search%' OR l.email LIKE '%$search%' OR l.phone LIKE '%$search%' OR l.company LIKE '%$search%')";
    }
    $sql .= " ORDER BY l.created_at DESC";

    $result = $conn->query($sql);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $sheet->setCellValue('A1', 'ID');
    $sheet->setCellValue('B1', 'Lead Name');
    $sheet->setCellValue('C1', 'Email');
    $sheet->setCellValue('D1', 'Phone');
    $sheet->setCellValue('E1', 'Company');
    $sheet->setCellValue('F1', 'Source');
    $sheet->setCellValue('G1', 'Status');
    $sheet->setCellValue('H1', 'Assigned To');
    $sheet->setCellValue('I1', 'Client');
    $sheet->setCellValue('J1', 'Project');
    $sheet->setCellValue('K1', 'Notes');
    $sheet->setCellValue('L1', 'Created At');

    $row = 2;
    while ($lead = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $lead['id']);
        $sheet->setCellValue('B' . $row, $lead['lead_name']);
        $sheet->setCellValue('C' . $row, $lead['email']);
        $sheet->setCellValue('D' . $row, $lead['phone']);
        $sheet->setCellValue('E' . $row, $lead['company']);
        $sheet->setCellValue('F' . $row, $lead['source_name']);
        $sheet->setCellValue('G' . $row, $lead['status']);
        $sheet->setCellValue('H' . $row, $lead['assigned_name']);
        $sheet->setCellValue('I' . $row, $lead['client_name']);
        $sheet->setCellValue('J' . $row, $lead['project_name']);
        $sheet->setCellValue('K' . $row, $lead['notes']);
        $sheet->setCellValue('L' . $row, $lead['created_at']);
        $row++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="leads_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'import') {
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == 0) {
        require_once '../vendor/autoload.php';
        
        $file = $_FILES['import_file']['tmp_name'];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        
        $imported = 0;
        $errors = 0;
        $skipped = 0;
        
        foreach ($worksheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $cells = [];
            foreach ($cellIterator as $cell) {
                $cells[] = $cell->getValue();
            }
            
            $lead_name = sanitize($cells[1]);
            $email = sanitize($cells[2]);
            $phone = sanitize($cells[3]);
            $company = sanitize($cells[4]);
            $source_name = sanitize($cells[5]);
            $status = sanitize($cells[6]);
            $assigned_name = sanitize($cells[7]);
            $project_name = sanitize($cells[9]);
            $notes = sanitize($cells[10]);
            
            if (!empty($lead_name) && !empty($email)) {
                $check_sql = "SELECT id FROM leads WHERE email = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $skipped++;
                } else {
                    $source_id = null;
                    if (!empty($source_name)) {
                        $source_query = $conn->query("SELECT id FROM lead_sources WHERE source_name = '$source_name' LIMIT 1");
                        if ($source_query->num_rows > 0) {
                            $source_id = $source_query->fetch_assoc()['id'];
                        }
                    }
                    
                    $assigned_to = null;
                    if (!empty($assigned_name)) {
                        $user_query = $conn->query("SELECT id FROM users WHERE name = '$assigned_name' LIMIT 1");
                        if ($user_query->num_rows > 0) {
                            $assigned_to = $user_query->fetch_assoc()['id'];
                        }
                    }
                    
                    $project_id = null;
                    $client_id = null;
                    if (!empty($project_name)) {
                        $project_query = $conn->query("SELECT id, client_id FROM projects WHERE project_name = '$project_name' LIMIT 1");
                        if ($project_query->num_rows > 0) {
                            $project_data = $project_query->fetch_assoc();
                            $project_id = $project_data['id'];
                            $client_id = $project_data['client_id'];
                        }
                    }
                    
                    $sql = "INSERT INTO leads (lead_name, email, phone, company, source_id, assigned_to, status, notes, client_id, project_id) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssiissii", $lead_name, $email, $phone, $company, $source_id, $assigned_to, $status, $notes, $client_id, $project_id);
                    
                    if ($stmt->execute()) {
                        $imported++;
                    } else {
                        $errors++;
                    }
                }
            }
        }
        
        $message = '<div class="alert alert-success">Import completed! Imported: ' . $imported . ' | Skipped (Duplicates): ' . $skipped . ' | Errors: ' . $errors . '</div>';
    } else {
        $message = '<div class="alert alert-danger">Error uploading file.</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $lead_name = sanitize($_POST['lead_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $company = sanitize($_POST['company']);
            $source_id = (int)$_POST['source_id'];
            $assigned_to = (int)$_POST['assigned_to'];
            $status = sanitize($_POST['status']);
            $notes = sanitize($_POST['notes']);
            $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;
            
            $sql = "SELECT client_id FROM projects WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $client_id = null;
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $client_id = $row['client_id'];
            }
            
            $sql = "INSERT INTO leads (lead_name, email, phone, company, source_id, assigned_to, status, notes, client_id, project_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssiissii", $lead_name, $email, $phone, $company, $source_id, $assigned_to, $status, $notes, $client_id, $project_id);

            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Lead added successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Error adding lead.</div>';
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $conn->query("DELETE FROM leads WHERE id = $id");
            $message = '<div class="alert alert-success">Lead deleted successfully!</div>';
        } elseif ($_POST['action'] === 'push_to_client') {
            $lead_ids = $_POST['lead_ids'];
            $client_id = (int)$_POST['push_client_id'];
            
            if (!empty($lead_ids) && $client_id > 0) {
                $ids = implode(',', array_map('intval', $lead_ids));
                $conn->query("UPDATE leads SET client_id = $client_id WHERE id IN ($ids)");
                $message = '<div class="alert alert-success">' . count($lead_ids) . ' leads pushed to client successfully!</div>';
            }
        }
    }
}

$filter_client = isset($_GET['filter_client']) ? (int)$_GET['filter_client'] : 0;
$filter_project = isset($_GET['filter_project']) ? (int)$_GET['filter_project'] : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$sql = "SELECT l.*, ls.source_name, u.name as assigned_name, c.client_name, p.project_name
        FROM leads l
        LEFT JOIN lead_sources ls ON l.source_id = ls.id
        LEFT JOIN users u ON l.assigned_to = u.id
        LEFT JOIN clients c ON l.client_id = c.id
        LEFT JOIN projects p ON l.project_id = p.id WHERE 1=1";

if ($filter_client > 0) {
    $sql .= " AND l.client_id = $filter_client";
}
if ($filter_project > 0) {
    $sql .= " AND l.project_id = $filter_project";
}
if (!empty($search)) {
    $sql .= " AND (l.lead_name LIKE '%$search%' OR l.email LIKE '%$search%' OR l.phone LIKE '%$search%' OR l.company LIKE '%$search%')";
}
$sql .= " ORDER BY l.created_at DESC";

$leads = $conn->query($sql);
$sources = $conn->query("SELECT * FROM lead_sources WHERE status = 'active'");
$staff = $conn->query("SELECT id, name FROM users WHERE user_type IN ('admin', 'lead') AND status = 'active'");
$clients_list = $conn->query("SELECT id, client_name FROM clients ");
$projects_list = $conn->query("SELECT id, project_name FROM projects ");

include 'header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2 class="mb-0">Manage Leads</h2>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importLeadModal">
                <i class="fas fa-file-import"></i> Import
            </button>
            <button class="btn btn-info me-2" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button class="btn btn-warning me-2" id="pushToClientBtn" style="display:none;">
                <i class="fas fa-share"></i> Push to Client
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeadModal">
                <i class="fas fa-plus"></i> Add New Lead
            </button>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, Email, Phone...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter by Client</label>
                    <select class="form-select" name="filter_client">
                        <option value="0">All Clients</option>
                        <?php
                        $clients_list->data_seek(0);
                        while ($client = $clients_list->fetch_assoc()):
                        ?>
                            <option value="<?php echo $client['id']; ?>" <?php echo $filter_client == $client['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['client_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter by Project</label>
                    <select class="form-select" name="filter_project">
                        <option value="0">All Projects</option>
                        <?php
                        $projects_list->data_seek(0);
                        while ($project = $projects_list->fetch_assoc()):
                        ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo $filter_project == $project['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['project_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="leads.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                            </th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Company</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($lead = $leads->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="lead-checkbox" value="<?php echo $lead['id']; ?>" onchange="updatePushButton()">
                                </td>
                                <td><?php echo $lead['id']; ?></td>
                                <td><?php echo htmlspecialchars($lead['lead_name']); ?></td>
                                <td><?php echo htmlspecialchars($lead['email']); ?></td>
                                <td><?php echo htmlspecialchars($lead['phone']); ?></td>
                                <td><?php echo htmlspecialchars($lead['company']); ?></td>
                                <td><?php echo htmlspecialchars($lead['source_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusColor($lead['status']); ?>">
                                        <?php echo ucfirst($lead['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($lead['assigned_name'] ?? 'Unassigned'); ?></td>
                                <td><?php echo htmlspecialchars($lead['client_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($lead['project_name'] ?? '-'); ?></td>
                                <td><?php echo formatDate($lead['created_at']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewLead(<?php echo $lead['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirmDelete();">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $lead['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

<div class="modal fade" id="addLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Lead Name *</label>
                            <input type="text" class="form-control" name="lead_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company</label>
                            <input type="text" class="form-control" name="company">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Project *</label>
                            <select class="form-select" name="project_id" required>
                                <option value="">Select Project</option>
                                <?php
                                $projects = $conn->query("SELECT id, project_name FROM projects");
                                while ($project = $projects->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $project['id']; ?>">
                                        <?php echo $project['project_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Source *</label>
                            <select class="form-select" name="source_id" required>
                                <option value="">Select Source</option>
                                <?php
                                $sources->data_seek(0);
                                while ($source = $sources->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $source['id']; ?>"><?php echo $source['source_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assign To *</label>
                            <select class="form-select" name="assigned_to" required>
                                <option value="">Select Staff</option>
                                <?php
                                $staff->data_seek(0);
                                while ($s = $staff->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo $s['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="qualified">Qualified</option>
                            <option value="proposal">Proposal</option>
                            <option value="negotiation">Negotiation</option>
                            <option value="won">Won</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Lead</button>
                    <!---SAVE And push to client--->
                    <button type="submit" formaction="push_to_client.php" class="btn btn-success">Save & Push to Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="importLeadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Leads</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="import">
                    <div class="mb-3">
                        <label class="form-label">Upload Excel File</label>
                        <input type="file" class="form-control" name="import_file" accept=".xlsx,.xls" required>
                        <div class="form-text">Excel format: ID, Lead Name, Email, Phone, Company, Source, Status, Assigned To, Client, Project, Notes</div>
                    </div>
                    <div class="alert alert-info">
                        <strong>Note:</strong> The first row should contain headers and will be skipped during import.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="pushToClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Push Leads to Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="pushToClientForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="push_to_client">
                    <div id="selectedLeadsInput"></div>
                    <div class="mb-3">
                        <label class="form-label">Select Client *</label>
                        <select class="form-select" name="push_client_id" required>
                            <option value="">Select Client</option>
                            <?php
                            $clients_list->data_seek(0);
                            while ($client = $clients_list->fetch_assoc()):
                            ?>
                                <option value="<?php echo $client['id']; ?>">
                                    <?php echo htmlspecialchars($client['client_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <span id="selectedCount">0</span> lead(s) selected
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Push to Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.lead-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    updatePushButton();
}

function updatePushButton() {
    const checkboxes = document.querySelectorAll('.lead-checkbox:checked');
    const pushBtn = document.getElementById('pushToClientBtn');
    
    if (checkboxes.length > 0) {
        pushBtn.style.display = 'inline-block';
    } else {
        pushBtn.style.display = 'none';
    }
}

document.getElementById('pushToClientBtn').addEventListener('click', function() {
    const checkboxes = document.querySelectorAll('.lead-checkbox:checked');
    const selectedLeadsInput = document.getElementById('selectedLeadsInput');
    const selectedCount = document.getElementById('selectedCount');
    
    selectedLeadsInput.innerHTML = '';
    checkboxes.forEach(checkbox => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'lead_ids[]';
        input.value = checkbox.value;
        selectedLeadsInput.appendChild(input);
    });
    
    selectedCount.textContent = checkboxes.length;
    
    const modal = new bootstrap.Modal(document.getElementById('pushToClientModal'));
    modal.show();
});

function exportToExcel() {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('export', 'excel');
    window.location.href = '?' + urlParams.toString();
}

function viewLead(id) {
    alert('View lead details for ID: ' + id);
}

function confirmDelete() {
    return confirm('Are you sure you want to delete this lead?');
}
</script>

<?php
function getStatusColor($status) {
    $colors = [
        'new' => 'primary',
        'contacted' => 'info',
        'qualified' => 'success',
        'proposal' => 'warning',
        'negotiation' => 'secondary',
        'won' => 'success',
        'lost' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

include 'footer.php';
?>