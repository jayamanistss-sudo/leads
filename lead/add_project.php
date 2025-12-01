<?php
require_once '../config.php';

if (!isLoggedIn() || getUserType() !== 'lead') {
    redirect('login.php');
}

$message = '';
$user_id = getUserId();

// Fetch clients added by this lead user
$clients = $conn->query("SELECT id, client_name, company FROM clients WHERE added_by = $user_id ORDER BY client_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = sanitize($_POST['project_name']);
    $client_id = (int)$_POST['client_id'];
    $description = sanitize($_POST['description']);
    $start_date = sanitize($_POST['start_date']);
    $end_date = sanitize($_POST['end_date']);
    $budget = floatval($_POST['budget']);
    $status = sanitize($_POST['status']);
    
    // Verify that the client belongs to this lead user
    $check_sql = "SELECT id FROM clients WHERE id = ? AND added_by = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $client_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $sql = "INSERT INTO projects (project_name, client_id, description, start_date, end_date, budget, status, added_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssdsi", $project_name, $client_id, $description, $start_date, $end_date, $budget, $status, $user_id);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> Project added successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
            
            // Log activity
            $action = 'add_project';
            $project_id = $conn->insert_id;
            $log_sql = "INSERT INTO activity_logs (user_id, action, module, record_id, description) 
                        VALUES (?, ?, 'projects', ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_description = "Added project: $project_name";
            $log_stmt->bind_param("isis", $user_id, $action, $project_id, $log_description);
            $log_stmt->execute();
        } else {
            $message = '<div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> Error adding project. Please try again.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
        }
    } else {
        $message = '<div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> Invalid client selected. You can only add projects for your own clients.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    }
}

include 'header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-0">Add New Project</h2>
            <p class="text-muted">Create a project for your clients</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="projects.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Projects
            </a>
        </div>
    </div>
    
    <?php echo $message; ?>
    
    <?php if ($clients->num_rows == 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> You need to add at least one client before creating a project.
            <a href="add_client.php" class="btn btn-sm btn-warning ms-2">Add Client Now</a>
        </div>
    <?php else: ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Project Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Project Name *</label>
                            <input type="text" class="form-control" name="project_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select Client *</label>
                            <select class="form-select" name="client_id" required>
                                <option value="">Choose a client...</option>
                                <?php while ($client = $clients->fetch_assoc()): ?>
                                <option value="<?php echo $client['id']; ?>">
                                    <?php echo htmlspecialchars($client['client_name']); ?>
                                    <?php if ($client['company']): ?>
                                        - <?php echo htmlspecialchars($client['company']); ?>
                                    <?php endif; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="form-text text-muted">
                                Don't see your client? <a href="add_client.php">Add a new client</a>
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4" placeholder="Enter project description..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Budget ($)</label>
                                <input type="number" class="form-control" name="budget" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="planning">Planning</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Project
                            </button>
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-lightbulb"></i> Guidelines</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> Projects must be associated with a client
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> You can only create projects for clients you've added
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> Set realistic start and end dates
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> Update project status as it progresses
                        </li>
                    </ul>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <h6>Project Status Guide:</h6>
                        <small class="text-muted">
                            <strong>Planning:</strong> Initial phase<br>
                            <strong>In Progress:</strong> Active work<br>
                            <strong>Completed:</strong> Finished<br>
                            <strong>On Hold:</strong> Temporarily paused<br>
                            <strong>Cancelled:</strong> Project terminated
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>