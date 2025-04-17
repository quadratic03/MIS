<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

// Get database connection
$db = getDBConnection();

// Check if database update is needed for user status field
$needsDatabaseUpdate = false;
if (isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    try {
        $checkColumn = $db->query("SHOW COLUMNS FROM users LIKE 'status'");
        if ($checkColumn->rowCount() === 0) {
            $needsDatabaseUpdate = true;
        }
    } catch (PDOException $e) {
        // Ignore error
    }
}

// Get dashboard statistics
try {
    // Get vehicle counts
    $vehicleStmt = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'operational' THEN 1 ELSE 0 END) as operational,
        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
        SUM(CASE WHEN status = 'repair' THEN 1 ELSE 0 END) as repair,
        SUM(CASE WHEN status = 'decommissioned' THEN 1 ELSE 0 END) as decommissioned
    FROM vehicles");
    $vehicleData = $vehicleStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get ammunition counts
    $ammoStmt = $db->query("SELECT 
        COUNT(*) as total,
        SUM(quantity) as total_quantity,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
        SUM(CASE WHEN status = 'depleted' THEN 1 ELSE 0 END) as depleted,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock
    FROM ammunition");
    $ammoData = $ammoStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get personnel counts
    $personnelStmt = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as on_leave,
        SUM(CASE WHEN status = 'training' THEN 1 ELSE 0 END) as training,
        SUM(CASE WHEN status = 'deployed' THEN 1 ELSE 0 END) as deployed,
        SUM(CASE WHEN status = 'retired' THEN 1 ELSE 0 END) as retired
    FROM personnel");
    $personnelData = $personnelStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get critical inventory (low ammo, vehicles in maintenance, etc.)
    $criticalStmt = $db->query("SELECT 
        (SELECT COUNT(*) FROM ammunition WHERE quantity <= reorder_level) +
        (SELECT COUNT(*) FROM vehicles WHERE status IN ('maintenance', 'repair')) as critical_count
    ");
    $criticalData = $criticalStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activities
    $recentStmt = $db->query("SELECT * FROM inventory_transactions ORDER BY transaction_date DESC LIMIT 5");
    $recentActivities = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    $vehicleData = $ammoData = $personnelData = $criticalData = ['total' => 0];
    $recentActivities = [];
}
?>

<!-- Dashboard Content -->
<div class="container-fluid">
    <?php if ($needsDatabaseUpdate): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
        <strong>Database Update Required!</strong> The system needs to update the database structure to support user status management.
        <a href="database/add_user_status.php" class="btn btn-sm btn-warning ms-3">Update Database Now</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-4">Dashboard</h2>
            
            <!-- Overview Cards -->
            <div class="row">
                <!-- Total Vehicles Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Vehicles</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $vehicleData['total']; ?></div>
                                    <div class="mt-2 text-xs">
                                        <span class="text-success mr-2">
                                            <?php echo $vehicleData['operational']; ?> Operational
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-truck-moving fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Ammunition Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Ammunition</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($ammoData['total_quantity']); ?></div>
                                    <div class="mt-2 text-xs">
                                        <span class="text-danger mr-2">
                                            <?php echo $ammoData['low_stock']; ?> Low Stock
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-bomb fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Personnel Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Active Personnel</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $personnelData['active']; ?></div>
                                    <div class="mt-2 text-xs">
                                        <span class="text-primary mr-2">
                                            <?php echo $personnelData['total']; ?> Total Personnel
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Critical Inventory Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Critical Inventory</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $criticalData['critical_count']; ?></div>
                                    <div class="mt-2 text-xs">
                                        <span class="text-danger mr-2">
                                            Needs Attention
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Categories Grid -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Asset Categories</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Combat Vehicles Category -->
                                <div class="col-lg-3 col-md-6 mb-4">
                                    <div class="card bg-success text-white shadow">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col-auto mr-3">
                                                    <i class="fas fa-tank fa-2x"></i>
                                                </div>
                                                <div class="col">
                                                    <div class="font-weight-bold">Combat Vehicles</div>
                                                    <div class="text-white-50 small">2 Units</div>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-xs">
                                                <div class="bg-white text-success px-2 py-1 rounded">
                                                    <i class="fas fa-check-circle mr-1"></i> All Operational
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Transport Vehicles Category -->
                                <div class="col-lg-3 col-md-6 mb-4">
                                    <div class="card bg-primary text-white shadow">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col-auto mr-3">
                                                    <i class="fas fa-truck-moving fa-2x"></i>
                                                </div>
                                                <div class="col">
                                                    <div class="font-weight-bold">Transport Vehicles</div>
                                                    <div class="text-white-50 small">1 Unit</div>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-xs">
                                                <div class="bg-white text-primary px-2 py-1 rounded">
                                                    <i class="fas fa-check-circle mr-1"></i> All Operational
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Artillery Category -->
                                <div class="col-lg-3 col-md-6 mb-4">
                                    <div class="card bg-warning text-white shadow">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col-auto mr-3">
                                                    <i class="fas fa-rocket fa-2x"></i>
                                                </div>
                                                <div class="col">
                                                    <div class="font-weight-bold">Artillery Units</div>
                                                    <div class="text-white-50 small">0 Units</div>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-xs">
                                                <div class="bg-white text-warning px-2 py-1 rounded">
                                                    <i class="fas fa-exclamation-circle mr-1"></i> No Data
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Infantry Equipment Category -->
                                <div class="col-lg-3 col-md-6 mb-4">
                                    <div class="card bg-info text-white shadow">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col-auto mr-3">
                                                    <i class="fas fa-shield-alt fa-2x"></i>
                                                </div>
                                                <div class="col">
                                                    <div class="font-weight-bold">Infantry Equipment</div>
                                                    <div class="text-white-50 small">4 Types</div>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-xs">
                                                <div class="bg-white text-info px-2 py-1 rounded">
                                                    <i class="fas fa-check-circle mr-1"></i> Well Stocked
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row">
                <!-- Vehicle Status Chart -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Vehicle Status Distribution</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-area">
                                <canvas id="vehicleStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personnel Distribution -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Personnel Distribution</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-pie pt-4 pb-2">
                                <canvas id="personnelPieChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
                            <a href="reports/transactions.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Type</th>
                                            <th>Item</th>
                                            <th>Location</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($recentActivities) > 0): ?>
                                            <?php foreach ($recentActivities as $activity): ?>
                                                <tr>
                                                    <td><?php echo $activity['transaction_id']; ?></td>
                                                    <td><?php echo ucfirst($activity['transaction_type']); ?></td>
                                                    <td><?php echo ucfirst($activity['item_type']); ?> #<?php echo $activity['item_id']; ?></td>
                                                    <td><?php echo $activity['to_location'] ? $activity['to_location'] : $activity['from_location']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($activity['transaction_date'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-success">Completed</span>
                                                    </td>
                                                    <td>
                                                        <a href="transaction.php?id=<?php echo $activity['transaction_id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">No recent activities</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart Initialization Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Vehicle Status Chart
    var vehicleCtx = document.getElementById('vehicleStatusChart').getContext('2d');
    var vehicleStatusChart = new Chart(vehicleCtx, {
        type: 'bar',
        data: {
            labels: ['Operational', 'Maintenance', 'Repair', 'Decommissioned'],
            datasets: [{
                label: 'Vehicle Count',
                data: [
                    <?php echo $vehicleData['operational']; ?>, 
                    <?php echo $vehicleData['maintenance']; ?>, 
                    <?php echo $vehicleData['repair']; ?>, 
                    <?php echo $vehicleData['decommissioned']; ?>
                ],
                backgroundColor: [
                    '<?php echo OPERATIONAL_COLOR; ?>',
                    '<?php echo WARNING_COLOR; ?>',
                    '<?php echo ACCENT_COLOR; ?>',
                    '<?php echo CRITICAL_COLOR; ?>'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    stepSize: 1
                }
            }
        }
    });
    
    // Personnel Pie Chart
    var personnelCtx = document.getElementById('personnelPieChart').getContext('2d');
    var personnelPieChart = new Chart(personnelCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'On Leave', 'Training', 'Deployed', 'Retired'],
            datasets: [{
                data: [
                    <?php echo $personnelData['active']; ?>, 
                    <?php echo $personnelData['on_leave']; ?>, 
                    <?php echo $personnelData['training']; ?>, 
                    <?php echo $personnelData['deployed']; ?>, 
                    <?php echo $personnelData['retired']; ?>
                ],
                backgroundColor: [
                    '<?php echo OPERATIONAL_COLOR; ?>',
                    '<?php echo WARNING_COLOR; ?>',
                    '<?php echo SECONDARY_COLOR; ?>',
                    '<?php echo PRIMARY_COLOR; ?>',
                    '<?php echo CRITICAL_COLOR; ?>'
                ],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
</script>

<?php
require_once 'includes/footer.php';
?> 