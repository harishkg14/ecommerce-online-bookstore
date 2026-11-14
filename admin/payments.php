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
   GET PAYMENTS
========================================= */

$result = $conn->query(
    "SELECT
        p.payment_id,
        p.order_id,
        p.method,
        p.status,
        p.transaction_ref,
        p.payment_date,

        o.total,
        o.status AS order_status,

        c.name AS customer_name,
        c.email AS customer_email

     FROM payments p

     JOIN orders o
        ON p.order_id = o.order_id

     JOIN customers c
        ON o.customer_id = c.customer_id

     ORDER BY p.payment_id DESC"
);

$payments = [];

while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}


/* =========================================
   PAYMENT STATISTICS
========================================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM payments"
);

$total_payments = $result->fetch_assoc()["total"];


$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM payments
     WHERE status = 'successful'"
);

$successful_payments =
    $result->fetch_assoc()["total"];


$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM payments
     WHERE status = 'pending'"
);

$pending_payments =
    $result->fetch_assoc()["total"];


$result = $conn->query(
    "SELECT COALESCE(SUM(o.total), 0) AS total
     FROM payments p
     JOIN orders o
        ON p.order_id = o.order_id
     WHERE p.status = 'successful'"
);

$successful_value =
    $result->fetch_assoc()["total"];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Payments | BookStore</title>

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
   MAIN
================================= */

.main {
    margin-left: 250px;
    width: calc(100% - 250px);
    padding: 35px;
}

.topbar {
    margin-bottom: 25px;
}

.topbar h1 {
    font-size: 28px;
}

.topbar p {
    color: #64748b;
    margin-top: 5px;
}


/* =================================
   STATISTICS
================================= */

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.stat-card span {
    color: #64748b;
    font-size: 12px;
}

.stat-card h2 {
    margin-top: 7px;
    font-size: 24px;
}


/* =================================
   CARD
================================= */

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
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
    min-width: 1000px;
}

th,
td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
    vertical-align: top;
}

th {
    background: #f8fafc;
    font-weight: 600;
}

.customer-name {
    font-weight: 600;
}

.secondary {
    color: #64748b;
    font-size: 11px;
    margin-top: 3px;
}


/* =================================
   STATUS
================================= */

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
}

.successful {
    background: #dcfce7;
    color: #166534;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

.failed {
    background: #fee2e2;
    color: #991b1b;
}


/* =================================
   MOBILE
================================= */

@media (max-width: 1000px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 850px) {

    .sidebar {
        width: 210px;
    }

    .main {
        margin-left: 210px;
        width: calc(100% - 210px);
    }

}

@media (max-width: 650px) {

    .layout {
        display: block;
    }

    .sidebar {
        position: static;
        width: 100%;
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

    <a
        href="payments.php"
        class="active"
    >
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

        <h1>
            Payment Management
        </h1>

        <p>
            Review payment transactions and payment status.
        </p>

    </div>


    <!-- =================================
         STATISTICS
    ================================== -->

    <div class="stats">


        <div class="stat-card">

            <span>
                Total Payments
            </span>

            <h2>
                <?php
                echo $total_payments;
                ?>
            </h2>

        </div>


        <div class="stat-card">

            <span>
                Successful
            </span>

            <h2>
                <?php
                echo $successful_payments;
                ?>
            </h2>

        </div>


        <div class="stat-card">

            <span>
                Pending
            </span>

            <h2>
                <?php
                echo $pending_payments;
                ?>
            </h2>

        </div>


        <div class="stat-card">

            <span>
                Successful Value
            </span>

            <h2>
                ₹<?php
                echo number_format(
                    $successful_value,
                    2
                );
                ?>
            </h2>

        </div>

    </div>


    <!-- =================================
         PAYMENT TABLE
    ================================== -->

    <div class="card">

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Payment ID
                        </th>

                        <th>
                            Order
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Method
                        </th>

                        <th>
                            Payment Status
                        </th>

                        <th>
                            Transaction Reference
                        </th>

                        <th>
                            Payment Date
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($payments) > 0): ?>

                    <?php foreach (
                        $payments as $payment
                    ): ?>

                        <tr>


                            <!-- PAYMENT ID -->

                            <td>

                                <strong>
                                    #<?php
                                    echo $payment[
                                        "payment_id"
                                    ];
                                    ?>
                                </strong>

                            </td>


                            <!-- ORDER -->

                            <td>

                                <strong>
                                    #<?php
                                    echo $payment[
                                        "order_id"
                                    ];
                                    ?>
                                </strong>

                                <div class="secondary">

                                    Order:
                                    <?php
                                    echo ucfirst(
                                        $payment[
                                            "order_status"
                                        ]
                                    );
                                    ?>

                                </div>

                            </td>


                            <!-- CUSTOMER -->

                            <td>

                                <div class="customer-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $payment[
                                            "customer_name"
                                        ]
                                    );
                                    ?>

                                </div>

                                <div class="secondary">

                                    <?php
                                    echo htmlspecialchars(
                                        $payment[
                                            "customer_email"
                                        ]
                                    );
                                    ?>

                                </div>

                            </td>


                            <!-- AMOUNT -->

                            <td>

                                <strong>

                                    ₹<?php
                                    echo number_format(
                                        $payment["total"],
                                        2
                                    );
                                    ?>

                                </strong>

                            </td>


                            <!-- METHOD -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $payment["method"]
                                );
                                ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status <?php
                                        echo htmlspecialchars(
                                            $payment["status"]
                                        );
                                    ?>"
                                >

                                    <?php
                                    echo ucfirst(
                                        $payment["status"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <!-- TRANSACTION -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $payment[
                                        "transaction_ref"
                                    ]
                                );
                                ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?php
                                echo date(
                                    "d M Y, h:i A",
                                    strtotime(
                                        $payment[
                                            "payment_date"
                                        ]
                                    )
                                );
                                ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="8">
                            No payment records found.
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