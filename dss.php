<?php

session_start();
require_once "../config/db.php";

/* Admin access check */

if (!isset($_SESSION['customer_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}


/* =========================================================
   1. CART ABANDONMENT RATE
   ========================================================= */

$cart_sql = "
    SELECT
        COUNT(*) AS total_carts,
        SUM(status = 'abandoned') AS abandoned_carts
    FROM carts
";

$cart_result = $conn->query($cart_sql);
$cart_data = $cart_result->fetch_assoc();

$total_carts = (int)$cart_data['total_carts'];
$abandoned_carts = (int)$cart_data['abandoned_carts'];

$cart_abandonment_rate = 0;

if ($total_carts > 0) {
    $cart_abandonment_rate =
        ($abandoned_carts / $total_carts) * 100;
}


/* =========================================================
   2. CUSTOMER LTV
   =========================================================

   LTV is calculated here as:

   Total non-cancelled order value
   grouped by customer.

   The dashboard displays the average customer
   lifetime value across customers with orders.
*/

$ltv_sql = "
    SELECT
        AVG(customer_total) AS average_ltv
    FROM
    (
        SELECT
            customer_id,
            SUM(total) AS customer_total
        FROM orders
        WHERE status != 'cancelled'
        GROUP BY customer_id
    ) AS customer_values
";

$ltv_result = $conn->query($ltv_sql);
$ltv_data = $ltv_result->fetch_assoc();

$average_ltv = (float)($ltv_data['average_ltv'] ?? 0);


/* =========================================================
   3. TOTAL REVENUE
   ========================================================= */

$revenue_result = $conn->query("
    SELECT
        COALESCE(SUM(total), 0) AS revenue
    FROM orders
    WHERE status != 'cancelled'
");

$revenue_data = $revenue_result->fetch_assoc();

$total_revenue = (float)$revenue_data['revenue'];


/* =========================================================
   4. TOTAL ORDERS
   ========================================================= */

$order_result = $conn->query("
    SELECT
        COUNT(*) AS total_orders
    FROM orders
    WHERE status != 'cancelled'
");

$order_data = $order_result->fetch_assoc();

$total_orders = (int)$order_data['total_orders'];


/* =========================================================
   5. TOP-SELLING BOOKS
   ========================================================= */

$top_books_sql = "
    SELECT
        b.title,
        b.author,
        SUM(oi.quantity) AS units_sold,
        SUM(oi.quantity * oi.unit_price) AS sales_value
    FROM order_items oi
    INNER JOIN books b
        ON oi.book_id = b.book_id
    INNER JOIN orders o
        ON oi.order_id = o.order_id
    WHERE o.status != 'cancelled'
    GROUP BY
        b.book_id,
        b.title,
        b.author
    ORDER BY units_sold DESC
    LIMIT 5
";

$top_books = $conn->query($top_books_sql);


/* =========================================================
   6. ORDERS BY CATEGORY
   ========================================================= */

$category_sql = "
    SELECT
        c.category_name,
        COUNT(DISTINCT oi.order_id) AS order_count,
        SUM(oi.quantity) AS units_sold
    FROM order_items oi
    INNER JOIN books b
        ON oi.book_id = b.book_id
    INNER JOIN categories c
        ON b.category_id = c.category_id
    INNER JOIN orders o
        ON oi.order_id = o.order_id
    WHERE o.status != 'cancelled'
    GROUP BY
        c.category_id,
        c.category_name
    ORDER BY order_count DESC
";

$category_result = $conn->query($category_sql);


/* =========================================================
   7. PAYMENT METHOD DISTRIBUTION
   ========================================================= */

$payment_sql = "
    SELECT
        method,
        COUNT(*) AS payment_count,
        SUM(status = 'successful') AS successful_count
    FROM payments
    GROUP BY method
    ORDER BY payment_count DESC
";

$payment_result = $conn->query($payment_sql);


/* =========================================================
   8. ORDER STATUS DISTRIBUTION
   ========================================================= */

$order_status_sql = "
    SELECT
        status,
        COUNT(*) AS order_count
    FROM orders
    GROUP BY status
    ORDER BY order_count DESC
";

$order_status_result = $conn->query($order_status_sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>DSS Analytics | BookStore Admin</title>

<style>

/* =========================================================
   GLOBAL
   ========================================================= */

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


/* =========================================================
   SIDEBAR
   ========================================================= */

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


/* =========================================================
   MAIN
   ========================================================= */

.main {
    margin-left: 240px;
    padding: 30px;
}

.header {
    margin-bottom: 25px;
}

.header h1 {
    font-size: 28px;
}

.header p {
    color: #64748b;
    margin-top: 6px;
}


/* =========================================================
   KPI CARDS
   ========================================================= */

.kpi-grid {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}

.kpi-card {
    background: white;

    padding: 22px;

    border-radius: 12px;

    box-shadow:
        0 3px 10px rgba(0,0,0,0.06);
}

.kpi-card h3 {
    color: #64748b;

    font-size: 14px;

    margin-bottom: 10px;
}

.kpi-value {
    font-size: 28px;

    font-weight: bold;

    color: #0f172a;
}

.kpi-description {
    color: #94a3b8;

    font-size: 12px;

    margin-top: 8px;
}


/* =========================================================
   CONTENT GRID
   ========================================================= */

.content-grid {
    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;

    margin-bottom: 20px;
}

.card {
    background: white;

    border-radius: 12px;

    padding: 22px;

    box-shadow:
        0 3px 10px rgba(0,0,0,0.06);
}

.card h2 {
    font-size: 19px;

    margin-bottom: 18px;
}


/* =========================================================
   TABLE
   ========================================================= */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;
}

th,
td {
    padding: 12px;

    border-bottom:
        1px solid #e2e8f0;

    text-align: left;
}

th {
    background: #f8fafc;

    color: #475569;

    font-size: 13px;
}

td {
    font-size: 14px;
}


/* =========================================================
   METRIC BOX
   ========================================================= */

.metric-box {
    background: #f8fafc;

    border-radius: 10px;

    padding: 18px;

    margin-bottom: 12px;
}

.metric-label {
    color: #64748b;

    font-size: 13px;

    margin-bottom: 6px;
}

.metric-number {
    font-size: 25px;

    font-weight: bold;
}


/* =========================================================
   STATUS
   ========================================================= */

.status {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}

.placed {
    background: #fef3c7;
    color: #92400e;
}

.confirmed {
    background: #dbeafe;
    color: #1e40af;
}

.packed {
    background: #e0f2fe;
    color: #075985;
}

.shipped {
    background: #ede9fe;
    color: #6d28d9;
}

.delivered {
    background: #dcfce7;
    color: #166534;
}

.cancelled {
    background: #fee2e2;
    color: #991b1b;
}


/* =========================================================
   FOOTER NOTE
   ========================================================= */

.dss-note {
    background: #eff6ff;

    border-left: 4px solid #2563eb;

    padding: 15px;

    border-radius: 6px;

    color: #1e3a8a;

    font-size: 13px;

    line-height: 1.6;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1100px) {

    .kpi-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .content-grid {
        grid-template-columns: 1fr;
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

    .kpi-grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>


<!-- =====================================================
     SIDEBAR
     ===================================================== -->

<div class="sidebar">

    <div class="logo">
        BookStore
    </div>

    <a href="dashboard.php">
        Dashboard
    </a>

    <a href="users.php">
        Users
    </a>

    <a href="books.php">
        Books
    </a>

    <a href="orders.php">
        Orders
    </a>

    <a href="payments.php">
        Payments
    </a>

    <a href="deliveries.php">
        Deliveries
    </a>

    <a href="dss.php" class="active">
        DSS Analytics
    </a>

    <a href="../auth/logout.php">
        Logout
    </a>

</div>


<!-- =====================================================
     MAIN
     ===================================================== -->

<div class="main">


    <div class="header">

        <h1>
            DSS Analytics
        </h1>

        <p>
            Decision Support System for management analysis
        </p>

    </div>


    <!-- =================================================
         KPI CARDS
         ================================================= -->

    <div class="kpi-grid">


        <div class="kpi-card">

            <h3>
                Total Revenue
            </h3>

            <div class="kpi-value">
                ₹<?php echo number_format($total_revenue, 2); ?>
            </div>

            <div class="kpi-description">
                Non-cancelled orders
            </div>

        </div>


        <div class="kpi-card">

            <h3>
                Total Orders
            </h3>

            <div class="kpi-value">
                <?php echo $total_orders; ?>
            </div>

            <div class="kpi-description">
                Completed and active orders
            </div>

        </div>


        <div class="kpi-card">

            <h3>
                Cart Abandonment Rate
            </h3>

            <div class="kpi-value">
                <?php echo number_format(
                    $cart_abandonment_rate,
                    2
                ); ?>%
            </div>

            <div class="kpi-description">
                Abandoned carts / total carts
            </div>

        </div>


        <div class="kpi-card">

            <h3>
                Average Customer LTV
            </h3>

            <div class="kpi-value">
                ₹<?php echo number_format(
                    $average_ltv,
                    2
                ); ?>
            </div>

            <div class="kpi-description">
                Average non-cancelled order value per customer
            </div>

        </div>


    </div>


    <!-- =================================================
         TOP BOOKS + CATEGORY
         ================================================= -->

    <div class="content-grid">


        <!-- TOP SELLING BOOKS -->

        <div class="card">

            <h2>
                Top-Selling Books
            </h2>

            <div class="table-wrapper">

                <table>

                    <thead>

                    <tr>

                        <th>
                            Book
                        </th>

                        <th>
                            Units Sold
                        </th>

                        <th>
                            Sales
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php if ($top_books && $top_books->num_rows > 0): ?>

                        <?php while ($book = $top_books->fetch_assoc()): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $book['title']
                                        );
                                        ?>
                                    </strong>

                                    <br>

                                    <small>
                                        <?php
                                        echo htmlspecialchars(
                                            $book['author']
                                        );
                                        ?>
                                    </small>

                                </td>

                                <td>
                                    <?php
                                    echo $book['units_sold'];
                                    ?>
                                </td>

                                <td>
                                    ₹<?php
                                    echo number_format(
                                        $book['sales_value'],
                                        2
                                    );
                                    ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="3">
                                No sales data available.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- ORDERS BY CATEGORY -->

        <div class="card">

            <h2>
                Orders by Category
            </h2>

            <div class="table-wrapper">

                <table>

                    <thead>

                    <tr>

                        <th>
                            Category
                        </th>

                        <th>
                            Orders
                        </th>

                        <th>
                            Units
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php if ($category_result && $category_result->num_rows > 0): ?>

                        <?php while ($category = $category_result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $category['category_name']
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo $category['order_count'];
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo $category['units_sold'];
                                    ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="3">
                                No category data available.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


    </div>


    <!-- =================================================
         PAYMENT + ORDER STATUS
         ================================================= -->

    <div class="content-grid">


        <!-- PAYMENT METHODS -->

        <div class="card">

            <h2>
                Payment Method Distribution
            </h2>

            <div class="table-wrapper">

                <table>

                    <thead>

                    <tr>

                        <th>
                            Method
                        </th>

                        <th>
                            Transactions
                        </th>

                        <th>
                            Successful
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php if ($payment_result && $payment_result->num_rows > 0): ?>

                        <?php while ($payment = $payment_result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $payment['method']
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo $payment['payment_count'];
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo $payment['successful_count'];
                                    ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="3">
                                No payment data available.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- ORDER STATUS -->

        <div class="card">

            <h2>
                Order Status Distribution
            </h2>

            <div class="table-wrapper">

                <table>

                    <thead>

                    <tr>

                        <th>
                            Status
                        </th>

                        <th>
                            Orders
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php if ($order_status_result && $order_status_result->num_rows > 0): ?>

                        <?php while ($status = $order_status_result->fetch_assoc()): ?>

                            <tr>

                                <td>

                                    <span class="status <?php
                                        echo htmlspecialchars(
                                            $status['status']
                                        );
                                    ?>">

                                        <?php
                                        echo ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $status['status']
                                            )
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td>
                                    <?php
                                    echo $status['order_count'];
                                    ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="2">
                                No order data available.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


    </div>


    <!-- =================================================
         DSS EXPLANATION
         ================================================= -->

    <div class="card">

        <h2>
            DSS Metrics Used
        </h2>

        <div class="metric-box">

            <div class="metric-label">
                Cart Abandonment Rate
            </div>

            <div class="metric-number">
                <?php
                echo number_format(
                    $cart_abandonment_rate,
                    2
                );
                ?>%
            </div>

        </div>


        <div class="metric-box">

            <div class="metric-label">
                Customer Lifetime Value
            </div>

            <div class="metric-number">
                ₹<?php
                echo number_format(
                    $average_ltv,
                    2
                );
                ?>
            </div>

        </div>


        <div class="dss-note">

            <strong>
                Decision Support:
            </strong>

            These aggregate metrics help management
            evaluate sales performance, customer value,
            cart abandonment, product demand,
            category performance, payment behaviour,
            and order fulfilment status.

        </div>

    </div>


</div>

</body>

</html>