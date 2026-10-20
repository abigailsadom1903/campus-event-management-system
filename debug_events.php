<?php
// debug_table_structure.php
require_once 'config.php';

echo "<h2>Checking Events Table Structure</h2>";

// Show events table structure
$sql = "DESCRIBE events";
$result = $conn->query($sql);

echo "<h3>Events Table Columns:</h3>";
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

// Show actual data with all columns
echo "<h3>Sample Event Data (First 5 rows):</h3>";
$sql = "SELECT * FROM events LIMIT 5";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    // Get column names
    $fields = $result->fetch_fields();
    echo "<tr>";
    foreach ($fields as $field) {
        echo "<th>" . $field->name . "</th>";
    }
    echo "</tr>";
    
    // Reset pointer and get data
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No events found in database!";
}

// Check users table too
echo "<h2>Checking Users Table Structure</h2>";
$sql = "DESCRIBE users";
$result = $conn->query($sql);

echo "<h3>Users Table Columns:</h3>";
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

$conn->close();
?>