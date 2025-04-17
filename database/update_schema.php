<?php
// Database update script - Adds position field to personnel table
require_once '../includes/config.php';

// Get database connection
$db = getDBConnection();

try {
    // Check if position column already exists
    $stmt = $db->prepare("SHOW COLUMNS FROM personnel LIKE 'position'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Add position column
        $db->exec("ALTER TABLE personnel ADD COLUMN position VARCHAR(100) AFTER unit");
        echo "Position column added successfully to personnel table.<br>";
        
        // Update existing records with position values based on their specialization
        $updateRecords = $db->prepare("
            UPDATE personnel SET position = 
            CASE 
                WHEN rank LIKE '%Colonel%' THEN 'Unit Commander'
                WHEN rank LIKE '%Captain%' THEN 'Battalion Leader'
                WHEN rank LIKE '%Lieutenant%' THEN 'Squad Leader'
                WHEN rank LIKE '%Sergeant%' THEN 'Chief Mechanic'
                ELSE 'Specialist'
            END
            WHERE position IS NULL");
        $updateRecords->execute();
        
        $updatedRows = $updateRecords->rowCount();
        echo "{$updatedRows} personnel records updated with position values.<br>";
    } else {
        echo "Position column already exists in personnel table. No changes needed.<br>";
    }
    
    echo "Database schema update completed successfully.";
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?> 