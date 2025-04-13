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

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Get report filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales';

// Get sales report data
if ($report_type == 'sales') {
    $sales_report = $db->prepare("
        SELECT 
            DATE_FORMAT(order_date, '%Y-%m-%d') AS date,
            COUNT(*) AS order_count,
            SUM(total_amount) AS total_sales,
            AVG(total_amount) AS avg_order_value
        FROM orders
        WHERE order_date BETWEEN ? AND ?
        AND status = 'delivered'
        GROUP BY DATE_FORMAT(order_date, '%Y-%m-%d')
        ORDER BY date ASC
    ");
    $sales_report->execute([$start_date, $end_date]);
    $sales_data = $sales_report->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $total_orders = 0;
    $total_sales = 0;
    foreach ($sales_data as $row) {
        $total_orders += $row['order_count'];
        $total_sales += $row['total_sales'];
    }
    $avg_order_value = $total_orders > 0 ? $total_sales / $total_orders : 0;
}

// Get product performance report data
if ($report_type == 'products') {
    $product_report = $db->prepare("
        SELECT 
            p.product_id,
            p.name,
            p.category,
            SUM(oi.quantity) AS total_quantity,
            SUM(oi.quantity * oi.price) AS total_revenue,
            COUNT(DISTINCT oi.order_id) AS order_count
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status = 'delivered'
        GROUP BY p.product_id
        ORDER BY total_revenue DESC
        LIMIT 50
    ");
    $product_report->execute([$start_date, $end_date]);
    $product_data = $product_report->fetchAll(PDO::FETCH_ASSOC);
}

// Get customer report data
if ($report_type == 'customers') {
    $customer_report = $db->prepare("
        SELECT 
            u.user_id,
            u.firstname,
            u.lastname,
            u.email,
            COUNT(o.order_id) AS order_count,
            SUM(o.total_amount) AS total_spent,
            MAX(o.order_date) AS last_order_date
        FROM users u
        JOIN orders o ON u.user_id = o.user_id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status = 'delivered'
        GROUP BY u.user_id
        ORDER BY total_spent DESC
        LIMIT 50
    ");
    $customer_report->execute([$start_date, $end_date]);
    $customer_data = $customer_report->fetchAll(PDO::FETCH_ASSOC);
}

// Get monthly sales data for chart
$monthly_sales = $db->query("
    SELECT 
        DATE_FORMAT(order_date, '%Y-%m') AS month,
        COUNT(*) AS order_count,
        SUM(total_amount) AS total_sales
    FROM orders
    WHERE status = 'delivered'
    GROUP BY DATE_FORMAT(order_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Urban Trends Apparel - Reports</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js for reports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            border-bottom: 3px solid var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .tab:hover:not(.active) {
            border-bottom: 3px solid #ddd;
        }
        
        /* Filters */
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
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 0.9rem;
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
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            border-top: 4px solid var(--primary-color);
        }
        
        .stat-card.warning {
            border-top-color: var(--warning-color);
        }
        
        .stat-card.danger {
            border-top-color: var(--danger-color);
        }
        
        .stat-card.success {
            border-top-color: var(--success-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .stat-card h3 i {
            margin-right: 8px;
        }
        
        .stat-card p {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--dark-color);
        }
        
        .stat-card .stat-change {
            font-size: 0.8rem;
            color: #4CAF50;
            display: flex;
            align-items: center;
            margin-top: 5px;
        }
        
        .stat-card .stat-change.negative {
            color: #F44336;
        }
        
        /* Charts */
        .chart-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
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
        
        .status.success {
            background-color: #D4EDDA;
            color: #155724;
        }
        
        .status.warning {
            background-color: #FFF3CD;
            color: #856404;
        }
        
        /* Responsive Styles */
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
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div class="admin-sidebar-header">
            <h2><i class="fas fa-crown"></i> <span>Admin Panel</span></h2>
        </div>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="products.php"><i class="fas fa-tshirt"></i> <span>Products</span></a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-bag"></i> <span>Orders</span></a></li>
            <li><a href="customers.php"><i class="fas fa-users"></i> <span>Customers</span></a></li>
            <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="admin-main">
        <div class="admin-header">
            <h2><i class="fas fa-chart-bar"></i> Reports</h2>
            <div class="admin-actions">
                <a href="../index.php"><i class="fas fa-home"></i> View Site</a>
                <a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Report Tabs -->
        <div class="tabs">
            <div class="tab <?php echo $report_type == 'sales' ? 'active' : ''; ?>" onclick="switchReportType('sales')">
                <i class="fas fa-shopping-bag"></i> Sales
            </div>
            <div class="tab <?php echo $report_type == 'products' ? 'active' : ''; ?>" onclick="switchReportType('products')">
                <i class="fas fa-tshirt"></i> Product Performance
            </div>
            <div class="tab <?php echo $report_type == 'customers' ? 'active' : ''; ?>" onclick="switchReportType('customers')">
                <i class="fas fa-users"></i> Customer Insights
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters">
            <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
            
            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" class="form-control" 
                       value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" class="form-control" 
                       value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group" style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </form>

        <!-- Sales Report -->
        <?php if ($report_type == 'sales'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><i class="fas fa-shopping-bag"></i> Total Orders</h3>
                    <p><?php echo number_format($total_orders); ?></p>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> 12% from last period
                    </div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-peso-sign"></i> Total Sales</h3>
                    <p>₱<?php echo number_format($total_sales, 2); ?></p>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> 8% from last period
                    </div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-receipt"></i> Avg. Order Value</h3>
                    <p>₱<?php echo number_format($avg_order_value, 2); ?></p>
                    <div class="stat-change negative">
                        <i class="fas fa-arrow-down"></i> 2% from last period
                    </div>
                </div>
            </div>

            <div class="chart-container">
                <h3><i class="fas fa-chart-line"></i> Monthly Sales Trend</h3>
                <canvas id="monthlySalesChart" height="300"></canvas>
            </div>

            <div class="table-container">
                <h3><i class="fas fa-calendar-day"></i> Daily Sales Report (<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Orders</th>
                            <th>Total Sales</th>
                            <th>Avg. Order Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales_data as $row): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo $row['order_count']; ?></td>
                                <td>₱<?php echo number_format($row['total_sales'], 2); ?></td>
                                <td>₱<?php echo number_format($row['avg_order_value'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sales_data)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">No sales data for the selected period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Product Performance Report -->
        <?php if ($report_type == 'products'): ?>
            <div class="chart-container">
                <h3><i class="fas fa-chart-pie"></i> Product Performance Overview</h3>
                <canvas id="productPerformanceChart" height="300"></canvas>
            </div>

            <div class="table-container">
                <h3><i class="fas fa-tshirt"></i> Top Performing Products (<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Quantity Sold</th>
                            <th>Total Revenue</th>
                            <th>Orders</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($product_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $row['category'])); ?></td>
                                <td><?php echo $row['total_quantity']; ?></td>
                                <td>₱<?php echo number_format($row['total_revenue'], 2); ?></td>
                                <td><?php echo $row['order_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($product_data)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No product data for the selected period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Customer Insights Report -->
        <?php if ($report_type == 'customers'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><i class="fas fa-users"></i> Total Customers</h3>
                    <p><?php echo count($customer_data); ?></p>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> 5% from last period
                    </div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-shopping-bag"></i> Total Orders</h3>
                    <p><?php echo array_sum(array_column($customer_data, 'order_count')); ?></p>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> 12% from last period
                    </div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-peso-sign"></i> Total Revenue</h3>
                    <p>₱<?php echo number_format(array_sum(array_column($customer_data, 'total_spent')), 2); ?></p>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> 8% from last period
                    </div>
                </div>
            </div>

            <div class="table-container">
                <h3><i class="fas fa-users"></i> Top Customers (<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Last Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customer_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo $row['order_count']; ?></td>
                                <td>₱<?php echo number_format($row['total_spent'], 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($row['last_order_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($customer_data)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No customer data for the selected period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Switch report type
        function switchReportType(type) {
            const url = new URL(window.location.href);
            url.searchParams.set('report_type', type);
            window.location.href = url.toString();
        }
        
        // Initialize charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($report_type == 'sales'): ?>
                // Monthly Sales Chart
                const monthlyCtx = document.getElementById('monthlySalesChart').getContext('2d');
                const monthlySalesChart = new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($monthly_sales, 'month')); ?>,
                        datasets: [{
                            label: 'Sales (₱)',
                            data: <?php echo json_encode(array_column($monthly_sales, 'total_sales')); ?>,
                            backgroundColor: 'rgba(67, 97, 238, 0.7)',
                            borderColor: 'rgba(67, 97, 238, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Sales (₱)'
                                }
                            }
                        }
                    }
                });
            <?php elseif ($report_type == 'products'): ?>
                // Product Performance Chart (Top 10 products by revenue)
                const productCtx = document.getElementById('productPerformanceChart').getContext('2d');
                const productPerformanceChart = new Chart(productCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_column(array_slice($product_data, 0, 10), 'name')); ?>,
                        datasets: [{
                            label: 'Revenue (₱)',
                            data: <?php echo json_encode(array_column(array_slice($product_data, 0, 10), 'total_revenue')); ?>,
                            backgroundColor: [
                                'rgba(67, 97, 238, 0.7)',
                                'rgba(76, 201, 240, 0.7)',
                                'rgba(103, 114, 229, 0.7)',
                                'rgba(72, 199, 142, 0.7)',
                                'rgba(249, 65, 68, 0.7)',
                                'rgba(248, 150, 30, 0.7)',
                                'rgba(247, 37, 133, 0.7)',
                                'rgba(114, 9, 183, 0.7)',
                                'rgba(58, 134, 255, 0.7)',
                                'rgba(6, 214, 160, 0.7)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'right',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ₱${value.toFixed(2)} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>