<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Lead Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-width: 250px;
        }

        body {
            background-color: #f5f6fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            padding: 20px 0;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar .logo {
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 24px;
            font-weight: bold;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 25px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar .nav-link.active {
            background-color: white;
            color: var(--primary-color);
            font-weight: 600;
        }

        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
        }

        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            border-radius: 10px;
        }

        .stat-card {
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            color: white;
            font-size: 24px;
        }

        .card {
            border-radius: 10px;
        }

        .card-header {
            border-bottom: 2px solid #f0f0f0;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-chart-line"></i> LMS
        </div>

        <nav class="nav flex-column mt-4">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : ''; ?>" href="clients.php">
                <i class="fas fa-users"></i> Clients
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : ''; ?>" href="projects.php">
                <i class="fas fa-project-diagram"></i> Projects
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'leads.php' ? 'active' : ''; ?>" href="leads.php">
                <i class="fas fa-user-tag"></i> Leads
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'staff.php' ? 'active' : ''; ?>" href="staff.php">
                <i class="fas fa-user-tie"></i> Staff
            </a>

            <div class="mt-3 px-3">
                <small class="text-white-50">SETTINGS</small>
            </div>

            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'app_settings.php' ? 'active' : ''; ?>" href="app_settings.php">
                <i class="fas fa-cog"></i> App Settings
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'roles.php' ? 'active' : ''; ?>" href="roles.php">
                <i class="fas fa-user-shield"></i> Roles
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'permissions.php' ? 'active' : ''; ?>" href="permissions.php">
                <i class="fas fa-key"></i> Permissions
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sources.php' ? 'active' : ''; ?>" href="sources.php">
                <i class="fas fa-stream"></i> Lead Sources
            </a>

            <a class="nav-link mt-4" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted">Welcome,</span>
                    <strong><?php echo $_SESSION['user_name']; ?></strong>
                </div>
                <div>
                    <span class="badge bg-primary">Admin Panel</span>
                </div>
            </div>
        </div>