<?php
require_once '../config.php';

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirect('login.php');
}

$message = '';
$user_id = getUserId();

$clients = $conn->query("SELECT id, client_name, company FROM clients WHERE added_by = $user_id ORDER BY client_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['action']) && $_POST['action'] === "delete") {
        $pid = (int)$_POST['project_id'];
        $conn->query("DELETE FROM projects WHERE id = $pid AND added_by = $user_id");
        header("Location: add_project.php");
        exit;
    }

    $project_name = sanitize($_POST['project_name']);
    $client_id = (int)$_POST['client_id'];
    $description = sanitize($_POST['description']);
    $start_date = sanitize($_POST['start_date']);
    $end_date = sanitize($_POST['end_date']);
    $budget = floatval($_POST['budget']);
    $status = sanitize($_POST['status']);

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
                            Project added successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
        } else {
            $message = '<div class="alert alert-danger alert-dismissible fade show">
                            Error adding project.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
        }

    } else {
        $message = '<div class="alert alert-danger alert-dismissible fade show">
                        Invalid client selected.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    }
}

$projects = $conn->query("
    SELECT p.*, c.client_name 
    FROM projects p
    LEFT JOIN clients c ON p.client_id = c.id
    WHERE p.added_by = $user_id
    ORDER BY p.created_at DESC
");

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
            You need to add at least one client before creating a project.
            <a href="add_client.php" class="btn btn-sm btn-warning ms-2">Add Client</a>
        </div>

    <?php else: ?>

    <div class="row">
        <div class="col-md-12">

            <div class="card border-0 shadow-sm mb-4">
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
                            <label class="form-label">Client *</label>
                            <select class="form-select" name="client_id" required>
                                <option value="">Choose a client</option>
                                <?php while ($client = $clients->fetch_assoc()): ?>
                                <option value="<?php echo $client['id']; ?>">
                                    <?php echo htmlspecialchars($client['client_name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4"></textarea>
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

                        <div class="row" style="display:none;">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Budget</label>
                                <input type="number" class="form-control" name="budget" step="0.01" min="0">
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

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Project
                        </button>

                    </form>

                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Your Projects</h5>
                </div>
                <div class="card-body p-0">

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Client</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Status</th>
                                    <th style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php while ($p = $projects->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['project_name']); ?></td>
                                    <td><?php echo htmlspecialchars($p['client_name']); ?></td>
                                    <td><?php echo $p['start_date']; ?></td>
                                    <td><?php echo $p['end_date']; ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($p['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">

                                            <a href="view_project.php?id=<?php echo $p['id']; ?>" 
                                               class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <form method="POST" onsubmit="return confirm('Delete this project?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="project_id" value="<?php echo $p['id']; ?>">
                                                <button class="btn btn-outline-danger">
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
    </div>

    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>
