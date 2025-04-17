<?php
require_once '../includes/config.php';
require_once '../includes/header.php';

// Get database connection
$db = getDBConnection();

// Get all transactions
$transactions = [];
try {
    $stmt = $db->query("SELECT * FROM inventory_transactions ORDER BY transaction_date DESC");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
}
?>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Heading -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">Inventory Transactions</h1>
                <a href="<?php echo SITE_URL; ?>index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
            <!-- Transactions Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Transactions</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="transactionsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Item Type</th>
                                    <th>Item ID</th>
                                    <th>Personnel</th>
                                    <th>Date</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($transactions) > 0): ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <?php
                                        // Get personnel name if available
                                        $personnelName = '';
                                        if (!empty($transaction['personnel_id'])) {
                                            try {
                                                $personnelStmt = $db->prepare("SELECT rank, first_name, last_name FROM personnel WHERE personnel_id = :personnel_id");
                                                $personnelStmt->bindParam(':personnel_id', $transaction['personnel_id']);
                                                $personnelStmt->execute();
                                                $personnelData = $personnelStmt->fetch(PDO::FETCH_ASSOC);
                                                if ($personnelData) {
                                                    $personnelName = $personnelData['rank'] . ' ' . $personnelData['first_name'] . ' ' . $personnelData['last_name'];
                                                }
                                            } catch (PDOException $e) {
                                                // Ignore error
                                            }
                                        }
                                        
                                        // Determine location
                                        $location = $transaction['to_location'] ? $transaction['to_location'] : $transaction['from_location'];
                                        ?>
                                        <tr>
                                            <td><?php echo $transaction['transaction_id']; ?></td>
                                            <td><?php echo ucfirst($transaction['transaction_type']); ?></td>
                                            <td><?php echo ucfirst($transaction['item_type']); ?></td>
                                            <td><?php echo $transaction['item_id']; ?></td>
                                            <td><?php echo $personnelName ? $personnelName : 'N/A'; ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($transaction['transaction_date'])); ?></td>
                                            <td><?php echo $location ? $location : 'N/A'; ?></td>
                                            <td>
                                                <span class="badge bg-success">Completed</span>
                                            </td>
                                            <td>
                                                <a href="<?php echo SITE_URL; ?>transaction.php?id=<?php echo $transaction['transaction_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No transactions found.</td>
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

<!-- DataTables JavaScript -->
<script>
$(document).ready(function() {
    $('#transactionsTable').DataTable({
        responsive: true,
        order: [[5, 'desc']], // Sort by date by default
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
});
</script>

<?php
require_once '../includes/footer.php';
?> 