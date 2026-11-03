<?php
require_once 'config.php';

if (isset($_GET['term'])) {
    $term = $conn->real_escape_string($_GET['term']);
    
    // Search by product name or composition
    $sql = "SELECT * FROM products 
            WHERE (product_name LIKE '%$term%' OR composition LIKE '%$term%') 
            ORDER BY product_name ASC LIMIT 10";
            
    $result = $conn->query($sql);
    $products = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = array(
                'id' => $row['id'],
                'label' => $row['product_name'] . ' (' . $row['composition'] . ')',
                'sale_rate' => $row['sale_rate'],
                'stock' => $row['current_stock']
            );
        }
    }
    
    echo json_encode($products);
}
?>