<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

// Get database connection
$db = getDBConnection();

// Get transaction ID from URL
$transactionId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Retrieve transaction data
$transaction = null;
$relatedItem = null;
$personnel = null;

try {
    // Get transaction details
    $transactionStmt = $db->prepare("SELECT * FROM inventory_transactions WHERE transaction_id = :transaction_id");
    $transactionStmt->bindParam(':transaction_id', $transactionId);
    $transactionStmt->execute();
    $transaction = $transactionStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        setFlashMessage('danger', 'Transaction not found.');
        redirect('index.php');
    }
    
    // Get related item details based on item_type
    switch ($transaction['item_type']) {
        case 'vehicle':
            $itemStmt = $db->prepare("SELECT * FROM vehicles WHERE vehicle_id = :item_id");
            $itemStmt->bindParam(':item_id', $transaction['item_id']);
            $itemStmt->execute();
            $relatedItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
            $itemName = $relatedItem ? $relatedItem['vehicle_code'] . ' - ' . $relatedItem['name'] : 'Unknown Vehicle';
            $itemLink = 'vehicles/details.php?id=' . $transaction['item_id'];
            break;
            
        case 'ammunition':
            $itemStmt = $db->prepare("SELECT * FROM ammunition WHERE ammo_id = :item_id");
            $itemStmt->bindParam(':item_id', $transaction['item_id']);
            $itemStmt->execute();
            $relatedItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
            $itemName = $relatedItem ? $relatedItem['name'] . ' (' . $relatedItem['caliber'] . ')' : 'Unknown Ammunition';
            $itemLink = 'ammunition/details.php?id=' . $transaction['item_id'];
            break;
            
        default:
            $itemName = ucfirst($transaction['item_type']) . ' #' . $transaction['item_id'];
            $itemLink = '#';
    }
    
    // Get personnel details if personnel_id exists
    if (!empty($transaction['personnel_id'])) {
        $personnelStmt = $db->prepare("SELECT * FROM personnel WHERE personnel_id = :personnel_id");
        $personnelStmt->bindParam(':personnel_id', $transaction['personnel_id']);
        $personnelStmt->execute();
        $personnel = $personnelStmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    redirect('index.php');
}
?>

<!-- Transaction Details Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Transaction Details</h2>
                <a href="javascript:history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Transaction #<?php echo $transaction['transaction_id']; ?> - 
                        <?php echo ucfirst($transaction['transaction_type']); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <h5>Transaction Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">Transaction ID</th>
                                        <td><?php echo $transaction['transaction_id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Type</th>
                                        <td><?php echo ucfirst($transaction['transaction_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Date & Time</th>
                                        <td><?php echo date('F d, Y h:i A', strtotime($transaction['transaction_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Quantity</th>
                                        <td><?php echo !empty($transaction['quantity']) ? $transaction['quantity'] : 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <span class="badge bg-success">Completed</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-4">
                                <h5>Item Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">Item Type</th>
                                        <td><?php echo ucfirst($transaction['item_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Item ID</th>
                                        <td><?php echo $transaction['item_id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Item Name</th>
                                        <td>
                                            <a href="<?php echo $itemLink; ?>"><?php echo $itemName; ?></a>
                                        </td>
                                    </tr>
                                    <?php if (!empty($transaction['from_location'])): ?>
                                    <tr>
                                        <th>From Location</th>
                                        <td><?php echo $transaction['from_location']; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($transaction['to_location'])): ?>
                                    <tr>
                                        <th>To Location</th>
                                        <td><?php echo $transaction['to_location']; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($personnel): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-4">
                                <h5>Personnel Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="15%">Service Number</th>
                                        <td><?php echo $personnel['service_number']; ?></td>
                                        <th width="15%">Rank</th>
                                        <td><?php echo $personnel['rank']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Name</th>
                                        <td colspan="3">
                                            <a href="personnel/details.php?id=<?php echo $personnel['personnel_id']; ?>">
                                                <?php echo $personnel['first_name'] . ' ' . $personnel['last_name']; ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Position</th>
                                        <td><?php echo $personnel['position']; ?></td>
                                        <th>Unit</th>
                                        <td><?php echo $personnel['unit']; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($transaction['notes'])): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-4">
                                <h5>Notes</h5>
                                <div class="card">
                                    <div class="card-body">
                                        <?php echo nl2br($transaction['notes']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <a href="reports/transactions.php" class="btn btn-primary">
                            <i class="fas fa-list"></i> View All Transactions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?> 