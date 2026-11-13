<?php
session_start();
require_once "../config/db.php";

/* Admin access check */
if (!isset($_SESSION['customer_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

/* Update delivery */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_delivery'])) {

    $delivery_id = (int)$_POST['delivery_id'];
    $courier = trim($_POST['courier']);
    $status = $_POST['status'];
    $expected_date = $_POST['expected_date'];

    $allowed_statuses = [
        'processing',
        'packed',
        'shipped',
        'out_for_delivery',
        'delivered'
    ];

    if (!in_array($status, $allowed_statuses, true)) {
        die("Invalid delivery status.");
    }

    $conn->begin_transaction();

try {

    /* Update delivery */

    $stmt = $conn->prepare("
        UPDATE deliveries
        SET courier = ?, status = ?, expected_date = ?
        WHERE delivery_id = ?
    ");

    $stmt->bind_param(
        "sssi",
        $courier,
        $status,
        $expected_date,
        $delivery_id
    );

    $stmt->execute();
    $stmt->close();


    /* Get the order connected to this delivery */

    $stmt = $conn->prepare("
        SELECT order_id
        FROM deliveries
        WHERE delivery_id = ?
    ");

    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $delivery = $result->fetch_assoc();

    $stmt->close();


    /* Synchronize order status */

    if ($delivery) {

        $order_id = $delivery['order_id'];

        $order_status = null;

        if ($status === 'processing') {
            $order_status = 'confirmed';
        }

        elseif ($status === 'packed') {
            $order_status = 'packed';
        }

        elseif ($status === 'shipped') {
            $order_status = 'shipped';
        }

        elseif ($status === 'out_for_delivery') {
            $order_status = 'shipped';
        }

        elseif ($status === 'delivered') {
            $order_status = 'delivered';
        }


        if ($order_status !== null) {

            $stmt = $conn->prepare("
                UPDATE orders
                SET status = ?
                WHERE order_id = ?
            ");

            $stmt->bind_param(
                "si",
                $order_status,
                $order_id
            );

            $stmt->execute();
            $stmt->close();
        }
    }


    $conn->commit();

    header("Location: deliveries.php?updated=1");
    exit;

} catch (Exception $e) {

    $conn->rollback();

    die("Delivery update failed: " . $e->getMessage());
}
}

/* Fetch deliveries */
$sql = "
    SELECT
        d.delivery_id,
        d.order_id,
        d.address,
        d.courier,
        d.status,
        d.expected_date,
        c.name AS customer_name,
        c.phone,
        o.total,
        o.order_date
    FROM deliveries d
    INNER JOIN orders o
        ON d.order_id = o.order_id
    INNER JOIN customers c
        ON o.customer_id = c.customer_id
    ORDER BY d.delivery_id DESC
";

$result = $conn->query($sql);

/* Delivery statistics */
$total_deliveries = 0;
$processing = 0;
$shipped = 0;
$out_for_delivery = 0;
$delivered = 0;

$count_result = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'processing') AS processing,
        SUM(status = 'shipped') AS shipped,
        SUM(status = 'out_for_delivery') AS out_for_delivery,
        SUM(status = 'delivered') AS delivered
    FROM deliveries
");

if ($count_result) {
    $stats = $count_result->fetch_assoc();

    $total_deliveries = (int)$stats['total'];
    $processing = (int)$stats['processing'];
    $shipped = (int)$stats['shipped'];
    $out_for_delivery = (int)$stats['out_for_delivery'];
    $delivered = (int)$stats['delivered'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Delivery Management | BookStore Admin</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background: #f1f5f9;
    color: #0f172a;
}

/* Sidebar */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 240px;
    height: 100vh;
    background: #0f172a;
    color: white;
    padding: 25px 15px;
}

.logo {
    font-size: 24px;
    font-weight: bold;
    text-align: center;
    margin-bottom: 30px;
}

.sidebar a {
    display: block;
    text-decoration: none;
    color: #cbd5e1;
    padding: 13px 15px;
    margin-bottom: 5px;
    border-radius: 8px;
    transition: 0.3s;
}

.sidebar a:hover,
.sidebar a.active {
    background: #1e293b;
    color: #22d3ee;
}

/* Main */

.main {
    margin-left: 240px;
    padding: 30px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header h1 {
    font-size: 28px;
}

.header p {
    color: #64748b;
    margin-top: 5px;
}

/* Message */

.success {
    background: #dcfce7;
    color: #166534;
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

/* Statistics */

.stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.06);
}

.stat-card h3 {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 10px;
}

.stat-card p {
    font-size: 25px;
    font-weight: bold;
}

/* Table */

.table-container {
    background: white;
    border-radius: 12px;
    padding: 20px;
    overflow-x: auto;
    box-shadow: 0 3px 10px rgba(0,0,0,0.06);
}

.table-container h2 {
    margin-bottom: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

th,
td {
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: top;
}

th {
    background: #f8fafc;
    color: #475569;
    font-size: 13px;
}

td {
    font-size: 14px;
}

/* Form */

input,
select {
    width: 100%;
    padding: 8px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    margin-bottom: 7px;
}

button {
    background: #0f172a;
    color: white;
    border: none;
    padding: 9px 13px;
    border-radius: 6px;
    cursor: pointer;
}

button:hover {
    background: #1e293b;
}

/* Status */

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.processing {
    background: #fef3c7;
    color: #92400e;
}

.packed {
    background: #e0f2fe;
    color: #075985;
}

.shipped {
    background: #dbeafe;
    color: #1e40af;
}

.out_for_delivery {
    background: #ede9fe;
    color: #6d28d9;
}

.delivered {
    background: #dcfce7;
    color: #166534;
}

/* Responsive */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 700px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
    }

    .stats {
        grid-template-columns: 1fr;
    }

}

</style>
</head>

<body>

<!-- Sidebar -->

<div class="sidebar">

    <div class="logo">
        BookStore
    </div>

    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="books.php">Books</a>
    <a href="orders.php">Orders</a>
    <a href="payments.php">Payments</a>
    <a href="deliveries.php" class="active">Deliveries</a>
    <a href="dss.php">DSS Analytics</a>
    <a href="../auth/logout.php">Logout</a>

</div>


<!-- Main -->

<div class="main">

    <div class="header">

        <div>
            <h1>Delivery Management</h1>
            <p>Track and manage customer deliveries</p>
        </div>

    </div>


    <?php if (isset($_GET['updated'])): ?>

        <div class="success">
            Delivery details updated successfully.
        </div>

    <?php endif; ?>


    <!-- Statistics -->

    <div class="stats">

        <div class="stat-card">
            <h3>Total Deliveries</h3>
            <p><?php echo $total_deliveries; ?></p>
        </div>

        <div class="stat-card">
            <h3>Processing</h3>
            <p><?php echo $processing; ?></p>
        </div>

        <div class="stat-card">
            <h3>Shipped</h3>
            <p><?php echo $shipped; ?></p>
        </div>

        <div class="stat-card">
            <h3>Out for Delivery</h3>
            <p><?php echo $out_for_delivery; ?></p>
        </div>

        <div class="stat-card">
            <h3>Delivered</h3>
            <p><?php echo $delivered; ?></p>
        </div>

    </div>


    <!-- Delivery Table -->

    <div class="table-container">

        <h2>All Deliveries</h2>

        <table>

            <thead>

                <tr>
                    <th>Delivery ID</th>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Courier</th>
                    <th>Status</th>
                    <th>Expected Date</th>
                    <th>Update</th>
                </tr>

            </thead>

            <tbody>

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($delivery = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            #<?php echo $delivery['delivery_id']; ?>
                        </td>

                        <td>
                            #<?php echo $delivery['order_id']; ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($delivery['customer_name']); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($delivery['phone'] ?? ''); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($delivery['address']); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($delivery['courier'] ?? ''); ?>
                        </td>

                        <td>

                            <span class="status <?php echo $delivery['status']; ?>">
                                <?php
                                echo ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $delivery['status']
                                    )
                                );
                                ?>
                            </span>

                        </td>

                        <td>
                            <?php echo htmlspecialchars($delivery['expected_date'] ?? ''); ?>
                        </td>

                        <td>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="delivery_id"
                                    value="<?php echo $delivery['delivery_id']; ?>"
                                >

                                <input
                                    type="text"
                                    name="courier"
                                    value="<?php echo htmlspecialchars($delivery['courier'] ?? ''); ?>"
                                    placeholder="Courier"
                                >

                                <select name="status">

                                    <option value="processing"
                                        <?php if ($delivery['status'] === 'processing') echo 'selected'; ?>>
                                        Processing
                                    </option>

                                    <option value="packed"
                                        <?php if ($delivery['status'] === 'packed') echo 'selected'; ?>>
                                        Packed
                                    </option>

                                    <option value="shipped"
                                        <?php if ($delivery['status'] === 'shipped') echo 'selected'; ?>>
                                        Shipped
                                    </option>

                                    <option value="out_for_delivery"
                                        <?php if ($delivery['status'] === 'out_for_delivery') echo 'selected'; ?>>
                                        Out for Delivery
                                    </option>

                                    <option value="delivered"
                                        <?php if ($delivery['status'] === 'delivered') echo 'selected'; ?>>
                                        Delivered
                                    </option>

                                </select>

                                <input
                                    type="date"
                                    name="expected_date"
                                    value="<?php echo htmlspecialchars($delivery['expected_date'] ?? ''); ?>"
                                >

                                <button
                                    type="submit"
                                    name="update_delivery"
                                >
                                    Update
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="9">
                        No deliveries found.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>