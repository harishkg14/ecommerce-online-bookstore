<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    header("Location: ../user/dashboard.php");
    exit;
}

include "../config/db.php";


/* =========================================
   DASHBOARD STATISTICS
========================================= */

/* Customers */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM customers
     WHERE role = 'customer'"
);

$total_customers = $result->fetch_assoc()["total"];


/* Books */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM books"
);

$total_books = $result->fetch_assoc()["total"];


/* Orders */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM orders"
);

$total_orders = $result->fetch_assoc()["total"];


/* Revenue */

$result = $conn->query(
    "SELECT COALESCE(SUM(total), 0) AS revenue
     FROM orders
     WHERE status != 'cancelled'"
);

$total_revenue = $result->fetch_assoc()["revenue"];


/* Pending Orders */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE status IN
     ('placed', 'confirmed', 'packed', 'shipped')"
);

$pending_orders = $result->fetch_assoc()["total"];


/* Active Carts */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM carts
     WHERE status = 'active'"
);

$active_carts = $result->fetch_assoc()["total"];


/* =========================================
   RECENT ORDERS
========================================= */

$recent_orders = $conn->query(
    "SELECT
        o.order_id,
        c.name,
        o.total,
        o.status,
        o.order_date
     FROM orders o
     JOIN customers c
        ON o.customer_id = c.customer_id
     ORDER BY o.order_id DESC
     LIMIT 5"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Admin Dashboard | BookStore</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
    rel="stylesheet"
>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "Poppins", sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}


/* =================================
   LAYOUT
================================= */

.layout {
    display: flex;
    min-height: 100vh;
}


/* =================================
   SIDEBAR
================================= */

.sidebar {
    width: 250px;
    background: #0f172a;
    padding: 25px 18px;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
}

.logo {
    color: #22d3ee;
    font-size: 24px;
    font-weight: 700;
    text-align: center;
    margin-bottom: 35px;
}

.admin-label {
    color: #94a3b8;
    font-size: 12px;
    text-align: center;
    margin-bottom: 25px;
}

.sidebar a {
    display: block;
    color: #cbd5e1;
    text-decoration: none;
    padding: 13px 15px;
    border-radius: 7px;
    margin-bottom: 7px;
    font-size: 14px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #1e293b;
    color: #22d3ee;
}

.logout {
    margin-top: 30px;
    color: #fca5a5 !important;
}


/* =================================
   MAIN CONTENT
================================= */

.main {
    margin-left: 250px;
    width: calc(100% - 250px);
    padding: 35px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.topbar h1 {
    font-size: 28px;
}

.welcome {
    color: #64748b;
    font-size: 14px;
}


/* =================================
   STAT CARDS
================================= */

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.stat-card span {
    color: #64748b;
    font-size: 13px;
}

.stat-card h2 {
    margin-top: 8px;
    font-size: 27px;
}


/* =================================
   SECTIONS
================================= */

.section {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.section h2 {
    margin-bottom: 20px;
}


/* =================================
   QUICK ACTIONS
================================= */

.actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.action {
    background: #f8fafc;
    padding: 18px;
    border-radius: 8px;
    text-decoration: none;
    color: #1e293b;
    border: 1px solid #e2e8f0;
}

.action:hover {
    border-color: #22d3ee;
}

.action strong {
    display: block;
    margin-bottom: 5px;
}

.action span {
    color: #64748b;
    font-size: 13px;
}


/* =================================
   TABLE
================================= */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
}

th {
    background: #f8fafc;
}

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
}

.placed {
    background: #dbeafe;
    color: #1d4ed8;
}

.confirmed {
    background: #e0e7ff;
    color: #4338ca;
}

.shipped {
    background: #cffafe;
    color: #155e75;
}

.delivered {
    background: #dcfce7;
    color: #166534;
}


/* =================================
   MOBILE
================================= */

@media (max-width: 900px) {

    .sidebar {
        width: 210px;
    }

    .main {
        margin-left: 210px;
        width: calc(100% - 210px);
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .actions {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 650px) {

    .sidebar {
        position: static;
        width: 100%;
        min-height: auto;
    }

    .layout {
        display: block;
    }

    .main {
        margin-left: 0;
        width: 100%;
        padding: 20px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="layout">


<!-- =================================
     SIDEBAR
================================= -->

<aside class="sidebar">

    <div class="logo">
        BookStore
    </div>

    <div class="admin-label">
        ADMIN PANEL
    </div>

    <a
        href="dashboard.php"
        class="active"
    >
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

    <a href="dss.php">
        DSS & Reports
    </a>

    <a
        href="../auth/logout.php"
        class="logout"
    >
        Logout
    </a>

</aside>


<!-- =================================
     MAIN
================================= -->

<main class="main">


    <div class="topbar">

        <div>

            <h1>
                Admin Dashboard
            </h1>

            <div class="welcome">

                Welcome,
                <?php
                echo htmlspecialchars(
                    $_SESSION["name"]
                );
                ?>

            </div>

        </div>

    </div>


    <!-- =================================
         STATISTICS
    ================================== -->

    <div class="stats">


        <div class="stat-card">

            <span>
                Total Customers
            </span>

            <h2>
                <?php
                echo $total_customers;
                ?>
            </h2>

        </div>


        <div class="stat-card">

            <span>
                Total Books
            </span>

            <h2>
                <?php
                echo $total_books;
                ?>
            </h2>

        </div>


        <div class="stat-card">

            <span>
                Total Orders
            </span>

            <h2>
                <?php
                echo $total_orders;
                ?>
            </h2>

        </div>


        <div class="stat-card">

            <span>
                Total Revenue
            </span>

            <h2>
                ₹<?php
                echo number_format(
                    $total_revenue,
                    2
                );
                ?>
            </h2>

        </div>


        <div class="stat-card">

            <span>
                Pending Orders
            </span>

            <h2>
                <?php
                echo $pending_orders;
                ?>
            </h2>

        </div>


        <div class="stat-card">

            <span>
                Active Carts
            </span>

            <h2>
                <?php
                echo $active_carts;
                ?>
            </h2>

        </div>

    </div>


    <!-- =================================
         QUICK ACTIONS
    ================================== -->

    <div class="section">

        <h2>
            Quick Actions
        </h2>

        <div class="actions">

            <a
                href="users.php"
                class="action"
            >

                <strong>
                    Manage Users
                </strong>

                <span>
                    View and manage customers.
                </span>

            </a>


            <a
                href="books.php"
                class="action"
            >

                <strong>
                    Manage Books
                </strong>

                <span>
                    Add, edit and remove books.
                </span>

            </a>


            <a
                href="orders.php"
                class="action"
            >

                <strong>
                    Manage Orders
                </strong>

                <span>
                    View and update orders.
                </span>

            </a>


            <a
                href="payments.php"
                class="action"
            >

                <strong>
                    Payments
                </strong>

                <span>
                    Review payment records.
                </span>

            </a>


            <a
                href="deliveries.php"
                class="action"
            >

                <strong>
                    Deliveries
                </strong>

                <span>
                    Track delivery records.
                </span>

            </a>


            <a
                href="dss.php"
                class="action"
            >

                <strong>
                    DSS & Reports
                </strong>

                <span>
                    Analyze business performance.
                </span>

            </a>

        </div>

    </div>


    <!-- =================================
         RECENT ORDERS
    ================================== -->

    <div class="section">

        <h2>
            Recent Orders
        </h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Order ID
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Date
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if ($recent_orders->num_rows > 0): ?>

                    <?php while (
                        $order = $recent_orders->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>
                                #<?php
                                echo $order["order_id"];
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $order["name"]
                                );
                                ?>
                            </td>

                            <td>
                                ₹<?php
                                echo number_format(
                                    $order["total"],
                                    2
                                );
                                ?>
                            </td>

                            <td>

                                <span
                                    class="status <?php
                                        echo htmlspecialchars(
                                            $order["status"]
                                        );
                                    ?>"
                                >
                                    <?php
                                    echo ucfirst(
                                        $order["status"]
                                    );
                                    ?>
                                </span>

                            </td>

                            <td>
                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $order["order_date"]
                                    )
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="5">
                            No orders found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

</div>

</body>

</html>