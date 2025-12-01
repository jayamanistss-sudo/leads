<?php
require_once '../config.php';

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirect('login.php');
}

// Fetch statistics
$stats = [];

// Total Leads
$result = $conn->query("SELECT COUNT(*) as count FROM leads");
$stats['total_leads'] = $result->fetch_assoc()['count'];

// Total Clients
$result = $conn->query("SELECT COUNT(*) as count FROM clients");
$stats['total_clients'] = $result->fetch_assoc()['count'];

// Total Projects
$result = $conn->query("SELECT COUNT(*) as count FROM projects");
$stats['total_projects'] = $result->fetch_assoc()['count'];

// Active Staff
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND user_type != 'client'");
$stats['total_staff'] = $result->fetch_assoc()['count'];

// Recent Leads
$recent_leads = $conn->query("SELECT l.*, ls.source_name, u.name as assigned_name 
                              FROM leads l 
                              LEFT JOIN lead_sources ls ON l.source_id = ls.id
                              LEFT JOIN users u ON l.assigned_to = u.id
                              ORDER BY l.created_at DESC LIMIT 5");

// Lead Status Distribution
$lead_status = $conn->query("SELECT status, COUNT(*) as count FROM leads GROUP BY status");

include 'header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">Dashboard</h2>
            <p class="text-muted">Welcome back, <?php echo $_SESSION['user_name']; ?>!</p>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Total Leads</p>
                            <h3 class="mb-0"><?php echo $stats['total_leads']; ?></h3>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-user-tag"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Total Clients</p>
                            <h3 class="mb-0"><?php echo $stats['total_clients']; ?></h3>
                        </div>
                        <div class="stat-icon bg-success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Total Projects</p>
                            <h3 class="mb-0"><?php echo $stats['total_projects']; ?></h3>
                        </div>
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Active Staff</p>
                            <h3 class="mb-0"><?php echo $stats['total_staff']; ?></h3>
                        </div>
                        <div class="stat-icon bg-info">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Leads -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Recent Leads</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($lead = $recent_leads->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($lead['lead_name']); ?></td>
                                    <td><?php echo htmlspecialchars($lead['email']); ?></td>
                                    <td><?php echo htmlspecialchars($lead['source_name'] ?? 'N/A'); ?></td>
                                    <td><span class="badge bg-<?php echo getStatusColor($lead['status']); ?>"><?php echo ucfirst($lead['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($lead['assigned_name'] ?? 'Unassigned'); ?></td>
                                    <td><?php echo formatDate($lead['created_at']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lead Status Chart -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Lead Status Overview</h5>
                </div>
                <div class="card-body">
                    <?php while ($status = $lead_status->fetch_assoc()): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span><?php echo ucfirst($status['status']); ?></span>
                            <span class="fw-bold"><?php echo $status['count']; ?></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-<?php echo getStatusColor($status['status']); ?>" 
                                 style="width: <?php echo ($status['count'] / $stats['total_leads']) * 100; ?>%"></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

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