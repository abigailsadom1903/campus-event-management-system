<?php
// check_tables.php
require_once 'config.php';

echo "<h2>Checking Events Table Structure</h2>";

// Show table structure
$sql = "DESCRIBE events";
$result = $conn->query($sql);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Count columns
$sql = "SELECT COUNT(*) as column_count FROM information_schema.columns 
        WHERE table_schema = DATABASE() AND table_name = 'events'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
echo "<p>Total columns in events table: <strong>" . $row['column_count'] . "</strong></p>";

$conn->close();
?>