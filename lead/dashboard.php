<?php
require_once '../config.php';

if (!isLoggedIn() || getUserType() !== 'lead') {
    redirect('login.php');
}

$user_id = getUserId();

// Fetch statistics for this lead user
$stats = [];

// My Clients
$result = $conn->query("SELECT COUNT(*) as count FROM clients WHERE added_by = $user_id");
$stats['my_clients'] = $result->fetch_assoc()['count'];

// My Projects
$result = $conn->query("SELECT COUNT(*) as count FROM projects WHERE added_by = $user_id");
$stats['my_projects'] = $result->fetch_assoc()['count'];

// Assigned Leads
$result = $conn->query("SELECT COUNT(*) as count FROM leads WHERE assigned_to = $user_id");
$stats['assigned_leads'] = $result->fetch_assoc()['count'];

// Recent Clients
$recent_clients = $conn->query("SELECT * FROM clients WHERE added_by = $user_id ORDER BY created_at DESC LIMIT 5");

// Recent Projects
$recent_projects = $conn->query("SELECT p.*, c.client_name 
                                 FROM projects p 
                                 LEFT JOIN clients c ON p.client_id = c.id
                                 WHERE p.added_by = $user_id 
                                 ORDER BY p.created_at DESC LIMIT 5");

include 'header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">My Dashboard</h2>
            <p class="text-muted">Welcome back, <?php echo $_SESSION['user_name']; ?>!</p>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">My Clients</p>
                            <h3 class="mb-0"><?php echo $stats['my_clients']; ?></h3>
                        </div>
                        <div class="stat-icon bg-success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">My Projects</p>
                            <h3 class="mb-0"><?php echo $stats['my_projects']; ?></h3>
                        </div>
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Assigned Leads</p>
                            <h3 class="mb-0"><?php echo $stats['assigned_leads']; ?></h3>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-user-tag"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">Quick Actions</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="add_client.php" class="btn btn-success">
                            <i class="fas fa-user-plus"></i> Add Client
                        </a>
                        <a href="add_project.php" class="btn btn-warning">
                            <i class="fas fa-folder-plus"></i> Add Project
                        </a>
                        <a href="clients.php" class="btn btn-info">
                            <i class="fas fa-users"></i> View All Clients
                        </a>
                        <a href="projects.php" class="btn btn-primary">
                            <i class="fas fa-project-diagram"></i> View All Projects
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Clients -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Clients</h5>
                    <a href="clients.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Date Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($client = $recent_clients->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($client['client_name']); ?></td>
                                    <td><?php echo htmlspecialchars($client['email']); ?></td>
                                    <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                    <td><?php echo formatDate($client['created_at']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Projects -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Projects</h5>
                    <a href="projects.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Project Name</th>
                                    <th>Client</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($project = $recent_projects->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                    <td><?php echo htmlspecialchars($project['client_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getProjectStatusColor($project['status']); ?>">
                                            <?php echo ucfirst($project['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($project['created_at']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
function getProjectStatusColor($status) {
    $colors = [
        'planning' => 'secondary',
        'in_progress' => 'primary',
        'completed' => 'success',
        'on_hold' => 'warning',
        'cancelled' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

include 'footer.php';
?>