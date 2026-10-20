<?php
require_once 'config.php';

echo "<h2>Checking Events Table Columns</h2>";
$result = $conn->query("DESCRIBE events");
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td><strong>" . $row['Field'] . "</strong></td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Sample Data from Events Table</h2>";
$result = $conn->query("SELECT * FROM events LIMIT 1");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "No events found in database.";
}
?>