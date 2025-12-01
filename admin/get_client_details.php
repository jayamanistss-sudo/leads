<?php
require_once '../config.php';

if (!isLoggedIn() || !isset($_GET['id'])) {
    exit('Unauthorized');
}

$client_id = (int)$_GET['id'];

// Fetch client details
$client_query = $conn->prepare("SELECT c.*, u.name AS added_name FROM clients c LEFT JOIN users u ON c.added_by = u.id WHERE c.id = ?");
$client_query->bind_param("i", $client_id);
$client_query->execute();
$client = $client_query->get_result()->fetch_assoc();

if (!$client) {
    exit('<div class="alert alert-danger">Client not found.</div>');
}

// Fetch projects for this client
$projects_query = $conn->prepare("SELECT p.*, 
    (SELECT COUNT(*) FROM leads l WHERE l.project_id = p.id) as lead_count 
    FROM projects p WHERE p.client_id = ? ORDER BY p.created_at DESC");
$projects_query->bind_param("i", $client_id);
$projects_query->execute();
$projects = $projects_query->get_result();

// Calculate total leads across all projects
$total_leads_query = $conn->prepare("SELECT COUNT(*) as total FROM leads l INNER JOIN projects p ON l.project_id = p.id WHERE p.client_id = ?");
$total_leads_query->bind_param("i", $client_id);
$total_leads_query->execute();
$total_leads = $total_leads_query->get_result()->fetch_assoc()['total'];
?>

<div class="row">
    <!-- Client Information -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-user"></i> Client Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td><?php echo htmlspecialchars($client['client_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td><?php echo htmlspecialchars($client['email']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Phone:</strong></td>
                        <td><?php echo htmlspecialchars($client['phone']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Company:</strong></td>
                        <td><?php echo htmlspecialchars($client['company']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>City:</strong></td>
                        <td><?php echo htmlspecialchars($client['city']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>State:</strong></td>
                        <td><?php echo htmlspecialchars($client['state']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Country:</strong></td>
                        <td><?php echo htmlspecialchars($client['country']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <span class="badge bg-<?php echo $client['status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($client['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Added By:</strong></td>
                        <td><?php echo htmlspecialchars($client['added_name'] ?? 'Unknown'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Created:</strong></td>
                        <td><?php echo date('M d, Y', strtotime($client['created_at'])); ?></td>
                    </tr>
                </table>
                <?php if (!empty($client['address'])): ?>
                <hr>
                <strong>Address:</strong>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($client['address'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Summary Statistics -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Summary</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-primary mb-0"><?php echo $projects->num_rows; ?></h3>
                        <small class="text-muted">Total Projects</small>
                    </div>
                    <div class="col-6">
                        <h3 class="text-success mb-0"><?php echo $total_leads; ?></h3>
                        <small class="text-muted">Total Leads</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Projects and Leads -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-project-diagram"></i> Projects & Leads</h6>
            </div>
            <div class="card-body">
                <?php if ($projects->num_rows > 0): ?>
                    <div class="accordion" id="projectsAccordion">
                        <?php 
                        $project_index = 0;
                        while ($project = $projects->fetch_assoc()): 
                            $project_index++;
                            
                            // Fetch leads for this project
                            $leads_query = $conn->prepare("SELECT l.*, u.name as assigned_name FROM leads l LEFT JOIN users u ON l.assigned_to = u.id WHERE l.project_id = ? ORDER BY l.created_at DESC");
                            $leads_query->bind_param("i", $project['id']);
                            $leads_query->execute();
                            $leads = $leads_query->get_result();
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo $project_index; ?>">
                                <button class="accordion-button <?php echo $project_index > 1 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $project_index; ?>" aria-expanded="<?php echo $project_index === 1 ? 'true' : 'false'; ?>">
                                    <div class="d-flex justify-content-between w-100 me-2">
                                        <span>
                                            <strong><?php echo htmlspecialchars($project['project_name']); ?></strong>
                                            <span class="badge bg-<?php echo $project['status'] === 'active' ? 'success' : ($project['status'] === 'completed' ? 'primary' : 'secondary'); ?> ms-2">
                                                <?php echo ucfirst($project['status']); ?>
                                            </span>
                                        </span>
                                        <span class="badge bg-info"><?php echo $project['lead_count']; ?> Leads</span>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $project_index; ?>" class="accordion-collapse collapse <?php echo $project_index === 1 ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $project_index; ?>">
                                <div class="accordion-body">
                                    <!-- Project Details -->
                                    <div class="mb-3">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small class="text-muted">Start Date:</small>
                                                <p><?php echo $project['start_date'] ? date('M d, Y', strtotime($project['start_date'])) : 'N/A'; ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted">End Date:</small>
                                                <p><?php echo $project['end_date'] ? date('M d, Y', strtotime($project['end_date'])) : 'N/A'; ?></p>
                                            </div>
                                        </div>
                                        <?php if (!empty($project['description'])): ?>
                                        <small class="text-muted">Description:</small>
                                        <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <hr>
                                    
                                    <!-- Leads Table -->
                                    <h6 class="mb-3"><i class="fas fa-users"></i> Leads for this Project</h6>
                                    <?php if ($leads->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Lead Name</th>
                                                    <th>Email</th>
                                                    <th>Phone</th>
                                                    <th>Status</th>
                                                    <th>Source</th>
                                                    <th>Assigned To</th>
                                                    <th>Created</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($lead = $leads->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($lead['lead_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($lead['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($lead['phone']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $lead['status'] === 'new' ? 'primary' : 
                                                                ($lead['status'] === 'contacted' ? 'info' : 
                                                                ($lead['status'] === 'qualified' ? 'warning' : 
                                                                ($lead['status'] === 'converted' ? 'success' : 'secondary'))); 
                                                        ?>">
                                                            <?php echo ucfirst($lead['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($lead['source'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($lead['assigned_name'] ?? 'Unassigned'); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($lead['created_at'])); ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-info-circle"></i> No leads generated for this project yet.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle"></i> No projects found for this client.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>