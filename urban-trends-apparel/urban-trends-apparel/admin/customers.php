<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'urban_trends');

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit;
}

class CustomerManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getAllCustomers($status = null, $search = null) {
        try {
            $query = "SELECT u.*, COUNT(o.order_id) as order_count 
                     FROM users u
                     LEFT JOIN orders o ON u.user_id = o.user_id
                     WHERE u.is_admin = 0";
            
            $conditions = [];
            $params = [];
            
            if ($status) {
                $conditions[] = "u.status = ?";
                $params[] = $status;
            }
            
            if ($search) {
                $conditions[] = "(u.email LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if (!empty($conditions)) {
                $query .= " AND " . implode(" AND ", $conditions);
            }
            
            $query .= " GROUP BY u.user_id ORDER BY u.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting customers: " . $e->getMessage());
            return [];
        }
    }
    
    public function getCustomerById($customer_id) {
        try {
            $stmt = $this->db->prepare("SELECT u.*, 
                                       (SELECT COUNT(*) FROM orders WHERE user_id = u.user_id) as order_count,
                                       (SELECT SUM(total_amount) FROM orders WHERE user_id = u.user_id AND status = 'delivered') as total_spent
                                       FROM users u 
                                       WHERE u.user_id = ? AND u.is_admin = 0");
            $stmt->execute([$customer_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting customer: " . $e->getMessage());
            return null;
        }
    }
    
    public function updateCustomerStatus($customer_id, $status) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE user_id = ? AND is_admin = 0");
            $stmt->execute([$status, $customer_id]);
            return true;
        } catch(PDOException $e) {
            error_log("Error updating customer status: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteCustomer($customer_id) {
        try {
            // First check if customer has orders
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
            $stmt->execute([$customer_id]);
            $orderCount = $stmt->fetchColumn();
            
            if ($orderCount > 0) {
                return false;
            }
            
            $stmt = $this->db->prepare("DELETE FROM users WHERE user_id = ? AND is_admin = 0");
            $stmt->execute([$customer_id]);
            return true;
        } catch(PDOException $e) {
            error_log("Error deleting customer: " . $e->getMessage());
            return false;
        }
    }
    
    public function getStatuses() {
        return ['active', 'inactive', 'banned'];
    }
}

$customerManager = new CustomerManager($db);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update customer status
    if (isset($_POST['update_status'])) {
        $customer_id = $_POST['customer_id'];
        $status = $_POST['status'];
        
        if ($customerManager->updateCustomerStatus($customer_id, $status)) {
            $_SESSION['success_message'] = "Customer status updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update customer status.";
        }
        
        header("Location: customers.php" . (isset($_GET['id']) ? '?id='.$_GET['id'] : ''));
        exit;
    }
}

// Handle customer deletion
if (isset($_GET['delete_customer'])) {
    $customer_id = $_GET['delete_customer'];
    
    if ($customerManager->deleteCustomer($customer_id)) {
        $_SESSION['success_message'] = "Customer deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to delete customer. Customer may have existing orders.";
    }
    header("Location: customers.php");
    exit;
}

// Get filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : null;
$search_filter = isset($_GET['search']) ? $_GET['search'] : null;

// Get all customers with filters
$customers = $customerManager->getAllCustomers($status_filter, $search_filter);

// Get customer details if viewing single customer
$customer = null;
if (isset($_GET['id'])) {
    $customer = $customerManager->getCustomerById($_GET['id']);
    if (!$customer) {
        $_SESSION['error_message'] = "Customer not found.";
        header("Location: customers.php");
        exit;
    }
}

// Get all statuses
$statuses = $customerManager->getStatuses();

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urban Trends Apparel - Customer Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --success-color: #4cc9f0;
            --dark-color: #2b2d42;
            --light-color: #f8f9fa;
            --sidebar-width: 250px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .admin-sidebar {
            width: var(--sidebar-width);
            background-color: var(--dark-color);
            color: white;
            padding: 20px 0;
            height: 100vh;
            position: fixed;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            z-index: 100;
        }
        
        .admin-sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .admin-sidebar-header h2 {
            display: flex;
            align-items: center;
            font-size: 1.2rem;
        }
        
        .admin-sidebar-header h2 i {
            margin-right: 10px;
            color: var(--accent-color);
        }
        
        .admin-sidebar ul {
            list-style: none;
        }
        
        .admin-sidebar ul li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .admin-sidebar ul li a:hover, 
        .admin-sidebar ul li a.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
            border-left: 3px solid var(--accent-color);
        }
        
        .admin-sidebar ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content Styles */
        .admin-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .admin-header h2 {
            color: var(--dark-color);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .admin-header h2 i {
            margin-right: 10px;
        }
        
        .admin-actions a {
            color: var(--dark-color);
            text-decoration: none;
            margin-left: 15px;
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }
        
        .admin-actions a:hover {
            color: var(--danger-color);
        }
        
        .admin-actions a i {
            margin-right: 5px;
        }
        
        /* Tables */
        .table-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            overflow-x: auto;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #f8f9fa;
            color: #555;
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status.active {
            background-color: #D4EDDA;
            color: #155724;
        }
        
        .status.inactive {
            background-color: #FFF3CD;
            color: #856404;
        }
        
        .status.banned {
            background-color: #F8D7DA;
            color: #721C24;
        }
        
        /* Buttons */
        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
        }
        
        .btn i {
            margin-right: 5px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #d63384;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #3aa8d1;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        /* Customer Details */
        .customer-details {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .customer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .customer-header h3 {
            color: var(--dark-color);
        }
        
        .customer-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .customer-meta-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }
        
        .customer-meta-item h4 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .customer-meta-item p {
            font-weight: 500;
            color: var(--dark-color);
        }
        
        
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .admin-sidebar-header h2 span,
            .admin-sidebar ul li a span {
                display: none;
            }
            
            .admin-sidebar ul li a {
                justify-content: center;
                padding: 12px 0;
            }
            
            .admin-sidebar ul li a i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .admin-main {
                margin-left: 70px;
            }
            
            .customer-meta {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .admin-sidebar {
                width: 100%;
                height: auto;
                position: static;
                display: flex;
                flex-direction: column;
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .admin-sidebar-header {
                display: none;
            }
            
            .admin-sidebar ul {
                display: flex;
                overflow-x: auto;
            }
            
            .admin-sidebar ul li {
                flex: 0 0 auto;
            }
            
            .admin-sidebar ul li a {
                padding: 10px 15px;
                border-left: none;
                border-bottom: 3px solid transparent;
            }
            
            .admin-sidebar ul li a:hover, 
            .admin-sidebar ul li a.active {
                border-left: none;
                border-bottom: 3px solid var(--accent-color);
            }
            
            .customer-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    
    <div class="admin-sidebar">
        <div class="admin-sidebar-header">
            <h2><i class="fas fa-crown"></i> <span>Admin Panel</span></h2>
        </div>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="products.php"><i class="fas fa-tshirt"></i> <span>Products</span></a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-bag"></i> <span>Orders</span></a></li>
            <li><a href="customers.php" class="active"><i class="fas fa-users"></i> <span>Customers</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
        </ul>
    </div>

    <div class="admin-main">
        <div class="admin-header">
            <h2><i class="fas fa-users"></i> <?php echo isset($customer) ? "Customer Details" : "Customer Management"; ?></h2>
            <div class="admin-actions">
                <a href="../index.php"><i class="fas fa-home"></i> View Site</a>
                <a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($customer)): ?>
            
            <div class="customer-details">
                <div class="customer-header">
                    <h3>Customer Information</h3>
                    <div>
                        <span class="status <?php echo $customer['status'] ?? 'active'; ?>">
                            <?php echo ucfirst($customer['status'] ?? 'active'); ?>
                        </span>
                    </div>
                </div>
                
                <div class="customer-meta">
                    <div class="customer-meta-item">
                        <h4>Personal Information</h4>
                        <p><?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?></p>
                        <p><?php echo htmlspecialchars($customer['email']); ?></p>
                        <p><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></p>
                        <p>Joined: <?php echo date('M d, Y', strtotime($customer['created_at'])); ?></p>
                    </div>
                    
                    <div class="customer-meta-item">
                        <h4>Address</h4>
                        <p><?php echo nl2br(htmlspecialchars($customer['address'] ?? 'Not provided')); ?></p>
                    </div>
                    
                    <div class="customer-meta-item">
                        <h4>Order Statistics</h4>
                        <p><strong>Total Orders:</strong> <?php echo $customer['order_count']; ?></p>
                        <p><strong>Total Spent:</strong> $<?php echo number_format($customer['total_spent'] ?? 0, 2); ?></p>
                    </div>
                </div>
                
                <!-- Status Update Form -->
                <div class="status-update" style="margin-top: 30px;">
                    <h4>Update Customer Status</h4>
                    <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                        <input type="hidden" name="customer_id" value="<?php echo $customer['user_id']; ?>">
                        <select name="status" class="form-control" style="flex: 1; max-width: 200px;">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo ($customer['status'] ?? 'active') == $status ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </form>
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <a href="customers.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Customers
                    </a>
                    <a href="customers.php?delete_customer=<?php echo $customer['user_id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this customer? This cannot be undone.');">
                        <i class="fas fa-trash"></i> Delete Customer
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Customer List View -->
            <div class="filters">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control" onchange="applyFilters()">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $status_filter == $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_filter); ?>" 
                           class="form-control" placeholder="Search customers..." oninput="applyFilters()">
                </div>
                
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="resetFilters()" style="height: 38px;">
                        <i class="fas fa-sync-alt"></i> Reset
                    </button>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Joined</th>
                            <th>Orders</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                <td><?php echo $customer['order_count']; ?></td>
                                <td>
                                    <span class="status <?php echo $customer['status'] ?? 'active'; ?>">
                                        <?php echo ucfirst($customer['status'] ?? 'active'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="customers.php?id=<?php echo $customer['user_id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="customers.php?delete_customer=<?php echo $customer['user_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this customer?');">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Apply filters
        function applyFilters() {
            const status = document.getElementById('status').value;
            const search = document.getElementById('search').value;
            
            let url = 'customers.php?';
            
            if (status) url += `status=${status}&`;
            if (search) url += `search=${encodeURIComponent(search)}&`;
            
            window.location.href = url.slice(0, -1); // Remove last & or ?
        }
        
        // Reset filters
        function resetFilters() {
            window.location.href = 'customers.php';
        }
    </script>
</body>
</html>