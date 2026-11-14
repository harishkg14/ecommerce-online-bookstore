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

$message = "";
$error = "";


/* =========================================
   UPDATE ORDER STATUS
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_order"])
) {

    $order_id = intval($_POST["order_id"]);
    $status = $_POST["status"] ?? "";

    $allowed_statuses = [
        "placed",
        "confirmed",
        "packed",
        "shipped",
        "delivered",
        "cancelled"
    ];

    if (
        $order_id <= 0 ||
        !in_array($status, $allowed_statuses)
    ) {

        $error = "Invalid order status.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE orders
             SET status = ?
             WHERE order_id = ?"
        );

        $stmt->bind_param(
            "si",
            $status,
            $order_id
        );

        if ($stmt->execute()) {

            $message = "Order status updated successfully.";

        } else {

            $error = "Unable to update order status.";
        }
    }
}


/* =========================================
   GET ORDERS
========================================= */

$result = $conn->query(
    "SELECT
        o.order_id,
        o.customer_id,
        o.total,
        o.order_date,
        o.status,

        c.name AS customer_name,
        c.email AS customer_email,

        p.method AS payment_method,
        p.status AS payment_status,

        d.status AS delivery_status,
        d.courier,
        d.expected_date

     FROM orders o

     JOIN customers c
        ON o.customer_id = c.customer_id

     LEFT JOIN payments p
        ON o.order_id = p.order_id

     LEFT JOIN deliveries d
        ON o.order_id = d.order_id

     ORDER BY o.order_id DESC"
);

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Manage Orders | BookStore</title>

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
   MESSAGES
================================= */

.message {
    background: #dcfce7;
    color: #166534;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
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
    min-width: 1050px;
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

.placed {
    background: #dbeafe;
    color: #1d4ed8;
}

.confirmed {
    background: #e0e7ff;
    color: #4338ca;
}

.packed {
    background: #fef3c7;
    color: #92400e;
}

.shipped {
    background: #cffafe;
    color: #155e75;
}

.delivered {
    background: #dcfce7;
    color: #166534;
}

.cancelled {
    background: #fee2e2;
    color: #991b1b;
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
   STATUS FORM
================================= */

.status-form {
    display: flex;
    gap: 7px;
    align-items: center;
}

.status-form select {
    padding: 7px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-family: inherit;
    font-size: 12px;
}

.update-btn {
    border: none;
    background: #0f172a;
    color: white;
    padding: 7px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
}

.update-btn:hover {
    background: #1e293b;
}


/* =================================
   MOBILE
================================= */

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

    <a
        href="orders.php"
        class="active"
    >
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

        <h1>
            Manage Orders
        </h1>

        <p>
            View customer orders and update order status.
        </p>

    </div>


    <?php if ($message !== ""): ?>

        <div class="message">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <div class="card">

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Order
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Payment
                        </th>

                        <th>
                            Delivery
                        </th>

                        <th>
                            Expected
                        </th>

                        <th>
                            Order Status
                        </th>

                        <th>
                            Update
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($orders) > 0): ?>

                    <?php foreach ($orders as $order): ?>

                        <tr>


                            <!-- ORDER -->

                            <td>

                                <strong>
                                    #<?php
                                    echo $order["order_id"];
                                    ?>
                                </strong>

                                <div class="secondary">

                                    <?php
                                    echo date(
                                        "d M Y, h:i A",
                                        strtotime(
                                            $order["order_date"]
                                        )
                                    );
                                    ?>

                                </div>

                            </td>


                            <!-- CUSTOMER -->

                            <td>

                                <div class="customer-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $order[
                                            "customer_name"
                                        ]
                                    );
                                    ?>

                                </div>

                                <div class="secondary">

                                    <?php
                                    echo htmlspecialchars(
                                        $order[
                                            "customer_email"
                                        ]
                                    );
                                    ?>

                                </div>

                            </td>


                            <!-- TOTAL -->

                            <td>

                                <strong>

                                    ₹<?php
                                    echo number_format(
                                        $order["total"],
                                        2
                                    );
                                    ?>

                                </strong>

                            </td>


                            <!-- PAYMENT -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $order[
                                        "payment_method"
                                    ] ?? "N/A"
                                );
                                ?>

                                <br>

                                <span
                                    class="status <?php
                                        echo htmlspecialchars(
                                            $order[
                                                "payment_status"
                                            ] ?? "pending"
                                        );
                                    ?>"
                                >

                                    <?php
                                    echo ucfirst(
                                        $order[
                                            "payment_status"
                                        ] ?? "pending"
                                    );
                                    ?>

                                </span>

                            </td>


                            <!-- DELIVERY -->

                            <td>

                                <span
                                    class="status <?php
                                        echo htmlspecialchars(
                                            $order[
                                                "delivery_status"
                                            ] ?? "processing"
                                        );
                                    ?>"
                                >

                                    <?php
                                    echo ucwords(
                                        str_replace(
                                            "_",
                                            " ",
                                            $order[
                                                "delivery_status"
                                            ] ?? "processing"
                                        )
                                    );
                                    ?>

                                </span>

                                <div class="secondary">

                                    <?php
                                    echo $order["courier"]
                                        ? htmlspecialchars(
                                            $order["courier"]
                                          )
                                        : "Courier not assigned";
                                    ?>

                                </div>

                            </td>


                            <!-- EXPECTED DATE -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $order[
                                        "expected_date"
                                    ] ?? "N/A"
                                );
                                ?>

                            </td>


                            <!-- CURRENT STATUS -->

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


                            <!-- UPDATE -->

                            <td>

                                <form
                                    method="POST"
                                    class="status-form"
                                >

                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?php
                                            echo $order[
                                                "order_id"
                                            ];
                                        ?>"
                                    >

                                    <select
                                        name="status"
                                    >

                                        <option
                                            value="placed"
                                            <?php
                                            if (
                                                $order[
                                                    "status"
                                                ]
                                                === "placed"
                                            ) {
                                                echo "selected";
                                            }
                                            ?>
                                        >
                                            Placed
                                        </option>

                                        <option
                                            value="confirmed"
                                            <?php
                                            if (
                                                $order[
                                                    "status"
                                                ]
                                                === "confirmed"
                                            ) {
                                                echo "selected";
                                            }
                                            ?>
                                        >
                                            Confirmed
                                        </option>

                                        <option
                                            value="packed"
                                            <?php
                                            if (
                                                $order[
                                                    "status"
                                                ]
                                                === "packed"
                                            ) {
                                                echo "selected";
                                            }
                                            ?>
                                        >
                                            Packed
                                        </option>

                                        <option
                                            value="shipped"
                                            <?php
                                            if (
                                                $order[
                                                    "status"
                                                ]
                                                === "shipped"
                                            ) {
                                                echo "selected";
                                            }
                                            ?>
                                        >
                                            Shipped
                                        </option>

                                        <option
                                            value="delivered"
                                            <?php
                                            if (
                                                $order[
                                                    "status"
                                                ]
                                                === "delivered"
                                            ) {
                                                echo "selected";
                                            }
                                            ?>
                                        >
                                            Delivered
                                        </option>

                                        <option
                                            value="cancelled"
                                            <?php
                                            if (
                                                $order[
                                                    "status"
                                                ]
                                                === "cancelled"
                                            ) {
                                                echo "selected";
                                            }
                                            ?>
                                        >
                                            Cancelled
                                        </option>

                                    </select>


                                    <button
                                        type="submit"
                                        name="update_order"
                                        class="update-btn"
                                    >
                                        Update
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="8">
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