<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

include "../config/db.php";

$customer_id = $_SESSION["user_id"];


/* =========================================
   GET CUSTOMER ORDERS
========================================= */

$stmt = $conn->prepare(
    "SELECT
        o.order_id,
        o.total,
        o.order_date,
        o.status,
        p.method AS payment_method,
        p.status AS payment_status,
        d.status AS delivery_status,
        d.courier,
        d.expected_date,
        d.address
     FROM orders o
     LEFT JOIN payments p
        ON o.order_id = p.order_id
     LEFT JOIN deliveries d
        ON o.order_id = d.order_id
     WHERE o.customer_id = ?
     ORDER BY o.order_id DESC"
);

$stmt->bind_param("i", $customer_id);
$stmt->execute();

$result = $stmt->get_result();

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

<title>My Orders | BookStore</title>

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
    background: #f5f7fb;
    color: #1e293b;
}

/* NAVBAR */

.navbar {
    background: #0f172a;
    padding: 18px 7%;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    color: #22d3ee;
    font-size: 24px;
    font-weight: 700;
}

.nav-links {
    display: flex;
    gap: 25px;
    align-items: center;
}

.nav-links a {
    color: white;
    text-decoration: none;
    font-size: 14px;
}

.nav-links a:hover {
    color: #22d3ee;
}


/* CONTAINER */

.container {
    width: 90%;
    max-width: 1200px;
    margin: 45px auto;
}

h1 {
    margin-bottom: 25px;
}


/* ORDER CARD */

.order-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.06);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 18px;
    border-bottom: 1px solid #e5e7eb;
}

.order-header h2 {
    font-size: 19px;
}

.order-date {
    color: #64748b;
    font-size: 13px;
}


/* ORDER DETAILS */

.order-details {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-top: 20px;
}

.detail-box {
    background: #f8fafc;
    padding: 15px;
    border-radius: 8px;
}

.detail-box span {
    display: block;
    color: #64748b;
    font-size: 12px;
    margin-bottom: 5px;
}

.detail-box strong {
    font-size: 14px;
}


/* STATUS */

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
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

.processing {
    background: #fef3c7;
    color: #92400e;
}

.out_for_delivery {
    background: #dbeafe;
    color: #1d4ed8;
}

.cancelled {
    background: #fee2e2;
    color: #991b1b;
}


/* ADDRESS */

.address {
    margin-top: 20px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 8px;
}

.address strong {
    display: block;
    margin-bottom: 5px;
}

.address p {
    color: #64748b;
    font-size: 14px;
}


/* EMPTY */

.empty {
    background: white;
    padding: 50px;
    text-align: center;
    border-radius: 12px;
}

.empty p {
    color: #64748b;
    margin-top: 10px;
}

.empty a {
    display: inline-block;
    margin-top: 20px;
    padding: 12px 20px;
    background: #0f172a;
    color: white;
    text-decoration: none;
    border-radius: 7px;
}


/* MOBILE */

@media (max-width: 850px) {

    .navbar {
        flex-direction: column;
        gap: 15px;
    }

    .nav-links {
        flex-wrap: wrap;
        justify-content: center;
    }

    .order-details {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 500px) {

    .order-details {
        grid-template-columns: 1fr;
    }

    .order-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

}

</style>

</head>

<body>


<!-- NAVBAR -->

<div class="navbar">

    <div class="logo">
        BookStore
    </div>

    <div class="nav-links">

        <a href="../home.php">
            Home
        </a>

        <a href="books.php">
            Books
        </a>

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="cart.php">
            Cart
        </a>

        <a href="orders.php">
            Orders
        </a>

        <a href="../auth/logout.php">
            Logout
        </a>

    </div>

</div>


<!-- MAIN -->

<div class="container">

    <h1>My Orders</h1>


    <?php if (count($orders) > 0): ?>

        <?php foreach ($orders as $order): ?>

            <div class="order-card">

                <!-- ORDER HEADER -->

                <div class="order-header">

                    <h2>
                        Order #<?php
                        echo $order["order_id"];
                        ?>
                    </h2>

                    <div class="order-date">

                        <?php
                        echo date(
                            "d M Y, h:i A",
                            strtotime($order["order_date"])
                        );
                        ?>

                    </div>

                </div>


                <!-- ORDER DETAILS -->

                <div class="order-details">


                    <div class="detail-box">

                        <span>
                            Order Total
                        </span>

                        <strong>
                            ₹<?php
                            echo number_format(
                                $order["total"],
                                2
                            );
                            ?>
                        </strong>

                    </div>


                    <div class="detail-box">

                        <span>
                            Order Status
                        </span>

                        <strong>

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

                        </strong>

                    </div>


                    <div class="detail-box">

                        <span>
                            Payment
                        </span>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $order["payment_method"]
                            );
                            ?>

                            <br>

                            <span
                                class="status <?php
                                    echo htmlspecialchars(
                                        $order["payment_status"]
                                    );
                                ?>"
                            >
                                <?php
                                echo ucfirst(
                                    $order["payment_status"]
                                );
                                ?>
                            </span>

                        </strong>

                    </div>


                    <div class="detail-box">

                        <span>
                            Delivery
                        </span>

                        <strong>

                            <span
                                class="status <?php
                                    echo htmlspecialchars(
                                        $order["delivery_status"]
                                    );
                                ?>"
                            >
                                <?php
                                echo ucwords(
                                    str_replace(
                                        "_",
                                        " ",
                                        $order["delivery_status"]
                                    )
                                );
                                ?>
                            </span>

                        </strong>

                    </div>

                </div>


                <!-- DELIVERY INFORMATION -->

                <div class="address">

                    <strong>
                        Delivery Information
                    </strong>

                    <p>
                        Address:
                        <?php
                        echo htmlspecialchars(
                            $order["address"]
                        );
                        ?>
                    </p>

                    <p>
                        Courier:
                        <?php
                        echo $order["courier"]
                            ? htmlspecialchars(
                                $order["courier"]
                              )
                            : "Not assigned yet";
                        ?>
                    </p>

                    <p>
                        Expected Delivery:
                        <?php
                        echo htmlspecialchars(
                            $order["expected_date"]
                        );
                        ?>
                    </p>

                </div>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty">

            <h2>No Orders Yet</h2>

            <p>
                You have not placed any orders.
            </p>

            <a href="books.php">
                Browse Books
            </a>

        </div>

    <?php endif; ?>

</div>

</body>

</html>