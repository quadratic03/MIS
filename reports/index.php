<?php
require_once '../includes/config.php';

// Get database connection
$db = getDBConnection();

// Check if a report type is requested
$reportType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'vehicle_status';
$fromDate = isset($_GET['from_date']) ? sanitizeInput($_GET['from_date']) : date('Y-m-d', strtotime('-30 days'));
$toDate = isset($_GET['to_date']) ? sanitizeInput($_GET['to_date']) : date('Y-m-d');
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : 'all';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';

// Function to generate reports based on type
function generateReport($db, $reportType, $fromDate, $toDate, $category, $status) {
    $reportData = [];
    $reportTitle = '';
    $reportColumns = [];
    
    try {
        switch ($reportType) {
            case 'vehicle_status':
                $reportTitle = 'Vehicle Status Report';
                $reportColumns = ['Code', 'Name', 'Model', 'Category', 'Status', 'Location', 'Last Maintenance', 'Next Maintenance'];
                
                $query = "SELECT vehicle_code, name, model, category, status, current_location, 
                          last_maintenance, next_maintenance 
                          FROM vehicles";
                
                $conditions = [];
                $params = [];
                
                if ($category !== 'all') {
                    $conditions[] = "category = :category";
                    $params[':category'] = $category;
                }
                
                if ($status !== 'all') {
                    $conditions[] = "status = :status";
                    $params[':status'] = $status;
                }
                
                if (!empty($conditions)) {
                    $query .= " WHERE " . implode(' AND ', $conditions);
                }
                
                $query .= " ORDER BY category, status, vehicle_code";
                
                $stmt = $db->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'ammo_inventory':
                $reportTitle = 'Ammunition Inventory Report';
                $reportColumns = ['Code', 'Name', 'Category', 'Caliber', 'Quantity', 'Status', 'Location', 'Expiration Date'];
                
                $query = "SELECT ammo_code, name, category, caliber, quantity, status, 
                          storage_location, expiration_date 
                          FROM ammunition";
                
                $conditions = [];
                $params = [];
                
                if ($category !== 'all') {
                    $conditions[] = "category = :category";
                    $params[':category'] = $category;
                }
                
                if ($status !== 'all') {
                    $conditions[] = "status = :status";
                    $params[':status'] = $status;
                }
                
                if (!empty($conditions)) {
                    $query .= " WHERE " . implode(' AND ', $conditions);
                }
                
                $query .= " ORDER BY category, status, ammo_code";
                
                $stmt = $db->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'personnel_status':
                $reportTitle = 'Personnel Status Report';
                $reportColumns = ['Service #', 'Rank', 'Name', 'Unit', 'Position', 'Specialization', 'Status'];
                
                $query = "SELECT service_number, rank, CONCAT(first_name, ' ', last_name) as full_name, 
                          unit, position, specialization, status 
                          FROM personnel";
                
                $conditions = [];
                $params = [];
                
                if ($status !== 'all') {
                    $conditions[] = "status = :status";
                    $params[':status'] = $status;
                }
                
                if (!empty($conditions)) {
                    $query .= " WHERE " . implode(' AND ', $conditions);
                }
                
                $query .= " ORDER BY rank, last_name, first_name";
                
                $stmt = $db->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'maintenance_summary':
                $reportTitle = 'Maintenance Summary Report';
                $reportColumns = ['Vehicle', 'Type', 'Description', 'Technician', 'Start Date', 'End Date', 'Cost', 'Status'];
                
                $query = "SELECT v.vehicle_code, v.name AS vehicle_name, v.model AS vehicle_model, 
                          ml.maintenance_type, ml.description, 
                          CONCAT(p.rank, ' ', p.first_name, ' ', p.last_name) AS technician, 
                          ml.start_date, ml.end_date, ml.cost, ml.status
                          FROM maintenance_logs ml
                          LEFT JOIN vehicles v ON ml.vehicle_id = v.vehicle_id
                          LEFT JOIN personnel p ON ml.personnel_id = p.personnel_id
                          WHERE ml.start_date BETWEEN :from_date AND :to_date";
                
                $params = [
                    ':from_date' => $fromDate,
                    ':to_date' => $toDate
                ];
                
                if ($category !== 'all') {
                    $query .= " AND ml.maintenance_type = :maintenance_type";
                    $params[':maintenance_type'] = $category;
                }
                
                if ($status !== 'all') {
                    $query .= " AND ml.status = :status";
                    $params[':status'] = $status;
                }
                
                $query .= " ORDER BY ml.start_date DESC";
                
                $stmt = $db->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'inventory_transactions':
                $reportTitle = 'Inventory Transactions Report';
                $reportColumns = ['Date', 'Type', 'Item Type', 'Item Details', 'Personnel', 'From', 'To', 'Notes'];
                
                $query = "SELECT it.transaction_date, it.transaction_type, it.item_type,
                          CASE 
                            WHEN it.item_type = 'vehicle' THEN CONCAT(v.vehicle_code, ' - ', v.name)
                            WHEN it.item_type = 'ammunition' THEN CONCAT(a.ammo_code, ' - ', a.name)
                            ELSE CONCAT('ID: ', it.item_id)
                          END as item_details,
                          CONCAT(p.rank, ' ', p.first_name, ' ', p.last_name) as personnel,
                          it.from_location, it.to_location, it.notes
                          FROM inventory_transactions it
                          LEFT JOIN vehicles v ON it.item_type = 'vehicle' AND it.item_id = v.vehicle_id
                          LEFT JOIN ammunition a ON it.item_type = 'ammunition' AND it.item_id = a.ammo_id
                          LEFT JOIN personnel p ON it.personnel_id = p.personnel_id
                          WHERE it.transaction_date BETWEEN :from_date AND :to_date";
                
                $params = [
                    ':from_date' => $fromDate . ' 00:00:00',
                    ':to_date' => $toDate . ' 23:59:59'
                ];
                
                if ($category !== 'all') {
                    $query .= " AND it.item_type = :item_type";
                    $params[':item_type'] = $category;
                }
                
                if ($status !== 'all') {
                    $query .= " AND it.transaction_type = :transaction_type";
                    $params[':transaction_type'] = $status;
                }
                
                $query .= " ORDER BY it.transaction_date DESC";
                
                $stmt = $db->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            default:
                // Default to vehicle status report
                return generateReport($db, 'vehicle_status', $fromDate, $toDate, $category, $status);
        }
        
        return [
            'title' => $reportTitle,
            'columns' => $reportColumns,
            'data' => $reportData
        ];
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error: ' . $e->getMessage());
        return [
            'title' => 'Error Generating Report',
            'columns' => ['Error'],
            'data' => [['Error' => 'An error occurred while generating the report.']]
        ];
    }
}

// Get filter options based on report type
function getFilterOptions($db, $reportType) {
    $categoryOptions = [];
    $statusOptions = [];
    
    try {
        switch ($reportType) {
            case 'vehicle_status':
                // Category options
                $stmt = $db->query("SELECT DISTINCT category FROM vehicles ORDER BY category");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $categoryOptions[$row['category']] = ucfirst(str_replace('_', ' ', $row['category']));
                }
                
                // Status options
                $stmt = $db->query("SELECT DISTINCT status FROM vehicles ORDER BY status");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $statusOptions[$row['status']] = ucfirst(str_replace('_', ' ', $row['status']));
                }
                break;
                
            case 'ammo_inventory':
                // Category options
                $stmt = $db->query("SELECT DISTINCT category FROM ammunition ORDER BY category");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $categoryOptions[$row['category']] = ucfirst(str_replace('_', ' ', $row['category']));
                }
                
                // Status options
                $stmt = $db->query("SELECT DISTINCT status FROM ammunition ORDER BY status");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $statusOptions[$row['status']] = ucfirst(str_replace('_', ' ', $row['status']));
                }
                break;
                
            case 'personnel_status':
                // No category for personnel
                $categoryOptions = [];
                
                // Status options
                $stmt = $db->query("SELECT DISTINCT status FROM personnel ORDER BY status");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $statusOptions[$row['status']] = ucfirst(str_replace('_', ' ', $row['status']));
                }
                break;
                
            case 'maintenance_summary':
                // Maintenance type options
                $stmt = $db->query("SELECT DISTINCT maintenance_type FROM maintenance_logs ORDER BY maintenance_type");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $categoryOptions[$row['maintenance_type']] = ucfirst(str_replace('_', ' ', $row['maintenance_type']));
                }
                
                // Status options
                $stmt = $db->query("SELECT DISTINCT status FROM maintenance_logs ORDER BY status");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $statusOptions[$row['status']] = ucfirst(str_replace('_', ' ', $row['status']));
                }
                break;
                
            case 'inventory_transactions':
                // Item type options
                $categoryOptions = [
                    'vehicle' => 'Vehicle',
                    'ammunition' => 'Ammunition',
                    'equipment' => 'Equipment'
                ];
                
                // Transaction type options
                $stmt = $db->query("SELECT DISTINCT transaction_type FROM inventory_transactions ORDER BY transaction_type");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $statusOptions[$row['transaction_type']] = ucfirst(str_replace('_', ' ', $row['transaction_type']));
                }
                break;
        }
        
        return [
            'categoryOptions' => $categoryOptions,
            'statusOptions' => $statusOptions
        ];
    } catch (PDOException $e) {
        return [
            'categoryOptions' => [],
            'statusOptions' => []
        ];
    }
}

// Generate the report
$report = generateReport($db, $reportType, $fromDate, $toDate, $category, $status);
$filterOptions = getFilterOptions($db, $reportType);

// Include header file
require_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Heading -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">Reports & Analytics</h1>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
            
            <!-- Report Filter Form -->
            <div class="card shadow mb-4 non-printable">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Report Filters</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <!-- Report Type -->
                        <div class="col-md-4 mb-3">
                            <label for="type" class="form-label">Report Type</label>
                            <select class="form-select" id="type" name="type" onchange="this.form.submit()">
                                <option value="vehicle_status" <?php echo $reportType === 'vehicle_status' ? 'selected' : ''; ?>>Vehicle Status Report</option>
                                <option value="ammo_inventory" <?php echo $reportType === 'ammo_inventory' ? 'selected' : ''; ?>>Ammunition Inventory Report</option>
                                <option value="personnel_status" <?php echo $reportType === 'personnel_status' ? 'selected' : ''; ?>>Personnel Status Report</option>
                                <option value="maintenance_summary" <?php echo $reportType === 'maintenance_summary' ? 'selected' : ''; ?>>Maintenance Summary Report</option>
                                <option value="inventory_transactions" <?php echo $reportType === 'inventory_transactions' ? 'selected' : ''; ?>>Inventory Transactions Report</option>
                            </select>
                        </div>
                        
                        <?php if (in_array($reportType, ['maintenance_summary', 'inventory_transactions'])): ?>
                            <!-- Date Range -->
                            <div class="col-md-4 mb-3">
                                <label for="from_date" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="from_date" name="from_date" value="<?php echo $fromDate; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="to_date" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="to_date" name="to_date" value="<?php echo $toDate; ?>">
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($filterOptions['categoryOptions'])): ?>
                            <!-- Category Filter -->
                            <div class="col-md-4 mb-3">
                                <label for="category" class="form-label">
                                    <?php 
                                    $categoryLabel = 'Category';
                                    if ($reportType === 'maintenance_summary') {
                                        $categoryLabel = 'Maintenance Type';
                                    } elseif ($reportType === 'inventory_transactions') {
                                        $categoryLabel = 'Item Type';
                                    }
                                    echo $categoryLabel;
                                    ?>
                                </label>
                                <select class="form-select" id="category" name="category">
                                    <option value="all">All</option>
                                    <?php foreach ($filterOptions['categoryOptions'] as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo $category === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($filterOptions['statusOptions'])): ?>
                            <!-- Status Filter -->
                            <div class="col-md-4 mb-3">
                                <label for="status" class="form-label">
                                    <?php 
                                    $statusLabel = 'Status';
                                    if ($reportType === 'inventory_transactions') {
                                        $statusLabel = 'Transaction Type';
                                    }
                                    echo $statusLabel;
                                    ?>
                                </label>
                                <select class="form-select" id="status" name="status">
                                    <option value="all">All</option>
                                    <?php foreach ($filterOptions['statusOptions'] as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo $status === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="?type=<?php echo $reportType; ?>" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Reset Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Report Results -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo $report['title']; ?></h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="reportTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <?php foreach ($report['columns'] as $column): ?>
                                        <th><?php echo $column; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($report['data']) > 0): ?>
                                    <?php foreach ($report['data'] as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $key => $value): ?>
                                                <td>
                                                    <?php 
                                                    // Format dates
                                                    if (strpos($key, 'date') !== false && !empty($value)) {
                                                        echo date('M d, Y', strtotime($value));
                                                    }
                                                    // Format status with badges
                                                    elseif ($key === 'status') {
                                                        $statusClass = 'secondary';
                                                        
                                                        // Vehicle status classes
                                                        if (in_array($value, ['operational', 'available', 'active'])) {
                                                            $statusClass = 'success';
                                                        } elseif (in_array($value, ['maintenance', 'repair', 'scheduled', 'reserved'])) {
                                                            $statusClass = 'warning';
                                                        } elseif (in_array($value, ['decommissioned', 'depleted', 'expired', 'cancelled'])) {
                                                            $statusClass = 'danger';
                                                        } elseif (in_array($value, ['in_progress', 'deployed'])) {
                                                            $statusClass = 'primary';
                                                        } elseif (in_array($value, ['completed', 'leave'])) {
                                                            $statusClass = 'info';
                                                        }
                                                        
                                                        echo '<span class="badge badge-'.$statusClass.'">'.ucfirst(str_replace('_', ' ', $value)).'</span>';
                                                    }
                                                    // Format money
                                                    elseif ($key === 'cost' && !empty($value)) {
                                                        echo '$' . number_format($value, 2);
                                                    }
                                                    // Default formatting
                                                    else {
                                                        echo $value;
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo count($report['columns']); ?>" class="text-center">No data available for this report.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Report Summary -->
                    <div class="mt-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">Report Summary</h5>
                                <p class="mb-0">Total Records: <strong><?php echo count($report['data']); ?></strong></p>
                                
                                <?php if ($reportType === 'maintenance_summary' && !empty($report['data'])): ?>
                                    <?php 
                                    $totalCost = 0;
                                    $completedCount = 0;
                                    $inProgressCount = 0;
                                    $scheduledCount = 0;
                                    
                                    foreach ($report['data'] as $row) {
                                        if (!empty($row['cost'])) {
                                            $totalCost += floatval($row['cost']);
                                        }
                                        
                                        if ($row['status'] === 'completed') {
                                            $completedCount++;
                                        } elseif ($row['status'] === 'in_progress') {
                                            $inProgressCount++;
                                        } elseif ($row['status'] === 'scheduled') {
                                            $scheduledCount++;
                                        }
                                    }
                                    ?>
                                    <p class="mb-0">Total Cost: <strong>$<?php echo number_format($totalCost, 2); ?></strong></p>
                                    <p class="mb-0">Completed: <strong><?php echo $completedCount; ?></strong> | In Progress: <strong><?php echo $inProgressCount; ?></strong> | Scheduled: <strong><?php echo $scheduledCount; ?></strong></p>
                                <?php endif; ?>
                                
                                <?php if ($reportType === 'ammo_inventory' && !empty($report['data'])): ?>
                                    <?php 
                                    $totalQuantity = 0;
                                    $expiringCount = 0;
                                    $today = new DateTime();
                                    
                                    foreach ($report['data'] as $row) {
                                        $totalQuantity += intval($row['quantity']);
                                        
                                        if (!empty($row['expiration_date'])) {
                                            $expirationDate = new DateTime($row['expiration_date']);
                                            $diff = $today->diff($expirationDate);
                                            
                                            if ($diff->days <= 90 && $diff->invert === 0) {
                                                $expiringCount++;
                                            }
                                        }
                                    }
                                    ?>
                                    <p class="mb-0">Total Quantity: <strong><?php echo number_format($totalQuantity); ?></strong></p>
                                    <p class="mb-0">Expiring Soon (90 days): <strong><?php echo $expiringCount; ?></strong></p>
                                <?php endif; ?>
                                
                                <?php if ($reportType === 'vehicle_status' && !empty($report['data'])): ?>
                                    <?php 
                                    $operationalCount = 0;
                                    $maintenanceCount = 0;
                                    $repairCount = 0;
                                    $decommissionedCount = 0;
                                    
                                    foreach ($report['data'] as $row) {
                                        if ($row['status'] === 'operational') {
                                            $operationalCount++;
                                        } elseif ($row['status'] === 'maintenance') {
                                            $maintenanceCount++;
                                        } elseif ($row['status'] === 'repair') {
                                            $repairCount++;
                                        } elseif ($row['status'] === 'decommissioned') {
                                            $decommissionedCount++;
                                        }
                                    }
                                    ?>
                                    <p class="mb-0">Operational: <strong><?php echo $operationalCount; ?></strong> | Maintenance: <strong><?php echo $maintenanceCount; ?></strong> | Repair: <strong><?php echo $repairCount; ?></strong> | Decommissioned: <strong><?php echo $decommissionedCount; ?></strong></p>
                                <?php endif; ?>
                                
                                <p class="mt-2 mb-0"><small class="text-muted">Report generated on <?php echo date('F d, Y \a\t h:i A'); ?></small></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DataTables JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    const table = new DataTable('#reportTable', {
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf'
        ]
    });
});
</script>

<?php
require_once '../includes/footer.php';
?> 