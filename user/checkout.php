<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

include "../config/db.php";

$customer_id = $_SESSION["user_id"];
$message = "";
$error = "";
$order_id = 0;


/* =========================================
   SUCCESS MESSAGE
========================================= */

if (isset($_GET["success"]) && isset($_GET["order_id"])) {

    $order_id = intval($_GET["order_id"]);
}


/* =========================================
   GET CUSTOMER DETAILS
========================================= */

$stmt = $conn->prepare(
    "SELECT name, email, phone, address
     FROM customers
     WHERE customer_id = ?"
);

$stmt->bind_param("i", $customer_id);
$stmt->execute();

$customer_result = $stmt->get_result();
$customer = $customer_result->fetch_assoc();


/* =========================================
   GET ACTIVE CART
========================================= */

$stmt = $conn->prepare(
    "SELECT cart_id
     FROM carts
     WHERE customer_id = ?
     AND status = 'active'
     LIMIT 1"
);

$stmt->bind_param("i", $customer_id);
$stmt->execute();

$cart_result = $stmt->get_result();
$cart = $cart_result->fetch_assoc();

$cart_items = [];
$grand_total = 0;
$cart_id = 0;

if ($cart) {

    $cart_id = $cart["cart_id"];

    $stmt = $conn->prepare(
        "SELECT
            ci.cart_item_id,
            ci.book_id,
            ci.quantity,
            b.title,
            b.author,
            b.price,
            b.stock
         FROM cart_items ci
         JOIN books b
             ON ci.book_id = b.book_id
         WHERE ci.cart_id = ?
         ORDER BY ci.cart_item_id"
    );

    $stmt->bind_param("i", $cart_id);
    $stmt->execute();

    $items_result = $stmt->get_result();

    while ($row = $items_result->fetch_assoc()) {

        $row["subtotal"] =
            $row["price"] * $row["quantity"];

        $grand_total += $row["subtotal"];

        $cart_items[] = $row;
    }
}


/* =========================================
   PLACE ORDER
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["place_order"])
) {

    $address = trim($_POST["address"] ?? "");
    $payment_method = $_POST["payment_method"] ?? "";

    /* Validate address */

    if ($address === "") {

        $error = "Delivery address is required.";

    } elseif (
        !in_array(
            $payment_method,
            ["UPI", "Card", "Cash on Delivery"]
        )
    ) {

        $error = "Please select a valid payment method.";

    } elseif (!$cart || count($cart_items) === 0) {

        $error = "Your cart is empty.";

    } else {

        try {

            /* Start database transaction */

            $conn->begin_transaction();


            /* =====================================
               RECHECK STOCK
            ===================================== */

            foreach ($cart_items as $item) {

                $stmt = $conn->prepare(
                    "SELECT stock
                     FROM books
                     WHERE book_id = ?
                     FOR UPDATE"
                );

                $stmt->bind_param(
                    "i",
                    $item["book_id"]
                );

                $stmt->execute();

                $stock_result = $stmt->get_result();
                $stock_row = $stock_result->fetch_assoc();

                if (
                    !$stock_row
                    || $stock_row["stock"] < $item["quantity"]
                ) {

                    throw new Exception(
                        "Insufficient stock for: "
                        . $item["title"]
                    );
                }
            }


            /* =====================================
               CREATE ORDER
            ===================================== */

            $stmt = $conn->prepare(
                "INSERT INTO orders
                (
                    customer_id,
                    cart_id,
                    total,
                    status
                )
                VALUES (?, ?, ?, 'placed')"
            );

            $stmt->bind_param(
                "iid",
                $customer_id,
                $cart_id,
                $grand_total
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Unable to create order."
                );
            }

            $order_id = $conn->insert_id;


            /* =====================================
               CREATE ORDER ITEMS
            ===================================== */

            foreach ($cart_items as $item) {

                $stmt = $conn->prepare(
                    "INSERT INTO order_items
                    (
                        order_id,
                        book_id,
                        quantity,
                        unit_price
                    )
                    VALUES (?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "iiid",
                    $order_id,
                    $item["book_id"],
                    $item["quantity"],
                    $item["price"]
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        "Unable to create order items."
                    );
                }
            }


            /* =====================================
               REDUCE BOOK STOCK
            ===================================== */

            foreach ($cart_items as $item) {

                $stmt = $conn->prepare(
                    "UPDATE books
                     SET stock = stock - ?
                     WHERE book_id = ?"
                );

                $stmt->bind_param(
                    "ii",
                    $item["quantity"],
                    $item["book_id"]
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        "Unable to update stock."
                    );
                }
            }


            /* =====================================
               PAYMENT
            ===================================== */

            if ($payment_method === "Cash on Delivery") {

                $payment_status = "pending";

            } else {

                /*
                 * Mock e-payment:
                 * UPI/Card are treated as successful
                 * for this academic implementation.
                 */

                $payment_status = "successful";
            }


            $transaction_ref =
                "TXN-" .
                strtoupper($payment_method) .
                "-" .
                $order_id .
                "-" .
                time();


            $stmt = $conn->prepare(
                "INSERT INTO payments
                (
                    order_id,
                    method,
                    status,
                    transaction_ref
                )
                VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "isss",
                $order_id,
                $payment_method,
                $payment_status,
                $transaction_ref
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Unable to create payment record."
                );
            }


            /* =====================================
               DELIVERY
            ===================================== */

            $expected_date =
                date(
                    "Y-m-d",
                    strtotime("+5 days")
                );

            $delivery_status = "processing";

            $stmt = $conn->prepare(
                "INSERT INTO deliveries
                (
                    order_id,
                    address,
                    status,
                    expected_date
                )
                VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "isss",
                $order_id,
                $address,
                $delivery_status,
                $expected_date
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Unable to create delivery record."
                );
            }


            /* =====================================
               CONVERT CART
            ===================================== */

            $stmt = $conn->prepare(
                "UPDATE carts
                 SET status = 'converted'
                 WHERE cart_id = ?
                 AND customer_id = ?"
            );

            $stmt->bind_param(
                "ii",
                $cart_id,
                $customer_id
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Unable to update cart status."
                );
            }


            /* =====================================
               COMMIT EVERYTHING
            ===================================== */

            $conn->commit();


            /* Redirect to success page */

            header(
                "Location: checkout.php?success=1&order_id="
                . $order_id
            );

            exit;

        } catch (Exception $e) {

            $conn->rollback();

            $error =
                "Order could not be placed: "
                . $e->getMessage();
        }
    }
}


/* =========================================
   SUCCESS ORDER DETAILS
========================================= */

$success_order = null;

if ($order_id > 0) {

    $stmt = $conn->prepare(
        "SELECT
            o.order_id,
            o.total,
            o.order_date,
            o.status,
            p.method,
            p.status AS payment_status,
            d.status AS delivery_status,
            d.expected_date
         FROM orders o
         JOIN payments p
             ON o.order_id = p.order_id
         JOIN deliveries d
             ON o.order_id = d.order_id
         WHERE o.order_id = ?
         AND o.customer_id = ?"
    );

    $stmt->bind_param(
        "ii",
        $order_id,
        $customer_id
    );

    $stmt->execute();

    $success_result = $stmt->get_result();
    $success_order = $success_result->fetch_assoc();
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

<title>Checkout | BookStore</title>

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
    max-width: 1100px;
    margin: 45px auto;
}

h1 {
    margin-bottom: 25px;
}

/* LAYOUT */

.checkout-layout {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 25px;
}

/* CARD */

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.06);
}

.card h2 {
    margin-bottom: 20px;
}

/* FORM */

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

.form-group textarea {
    width: 100%;
    min-height: 120px;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-family: inherit;
    resize: vertical;
}

.payment-option {
    border: 1px solid #cbd5e1;
    padding: 13px;
    border-radius: 7px;
    margin-bottom: 10px;
    cursor: pointer;
}

.payment-option input {
    margin-right: 8px;
}

/* ITEMS */

.order-item {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 14px 0;
    border-bottom: 1px solid #e5e7eb;
}

.order-item p {
    color: #64748b;
    font-size: 13px;
}

/* SUMMARY */

.summary-row {
    display: flex;
    justify-content: space-between;
    margin: 15px 0;
}

.total {
    border-top: 1px solid #e5e7eb;
    padding-top: 15px;
    font-size: 21px;
    font-weight: 700;
}

/* BUTTON */

.place-order {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 8px;
    background: #22d3ee;
    color: #0f172a;
    font-family: inherit;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    margin-top: 20px;
}

.place-order:hover {
    background: #06b6d4;
}

/* ERROR */

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

/* SUCCESS */

.success-card {
    background: white;
    max-width: 700px;
    margin: 50px auto;
    padding: 40px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.06);
}

.success-card h1 {
    color: #166534;
}

.success-card p {
    margin: 10px 0;
}

.success-details {
    text-align: left;
    margin-top: 25px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 8px;
}

.success-details div {
    display: flex;
    justify-content: space-between;
    margin: 10px 0;
}

.dashboard-btn {
    display: inline-block;
    margin-top: 25px;
    padding: 12px 20px;
    background: #0f172a;
    color: white;
    text-decoration: none;
    border-radius: 7px;
}

/* EMPTY */

.empty {
    background: white;
    padding: 50px;
    text-align: center;
    border-radius: 12px;
}

/* MOBILE */

@media (max-width: 800px) {

    .navbar {
        flex-direction: column;
        gap: 15px;
    }

    .nav-links {
        flex-wrap: wrap;
        justify-content: center;
    }

    .checkout-layout {
        grid-template-columns: 1fr;
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

        <a href="../home.php">Home</a>

        <a href="books.php">Books</a>

        <a href="dashboard.php">Dashboard</a>

        <a href="cart.php">Cart</a>

        <a href="../auth/logout.php">Logout</a>

    </div>

</div>


<div class="container">

<?php if ($success_order): ?>

    <!-- =================================
         ORDER SUCCESS
    ================================== -->

    <div class="success-card">

        <h1>Order Placed Successfully</h1>

        <p>
            Your order has been created successfully.
        </p>

        <div class="success-details">

            <div>
                <span>Order ID</span>
                <strong>
                    #<?php echo $success_order["order_id"]; ?>
                </strong>
            </div>

            <div>
                <span>Total</span>
                <strong>
                    ₹<?php echo number_format(
                        $success_order["total"],
                        2
                    ); ?>
                </strong>
            </div>

            <div>
                <span>Payment Method</span>
                <strong>
                    <?php echo htmlspecialchars(
                        $success_order["method"]
                    ); ?>
                </strong>
            </div>

            <div>
                <span>Payment Status</span>
                <strong>
                    <?php echo htmlspecialchars(
                        $success_order["payment_status"]
                    ); ?>
                </strong>
            </div>

            <div>
                <span>Order Status</span>
                <strong>
                    <?php echo htmlspecialchars(
                        $success_order["status"]
                    ); ?>
                </strong>
            </div>

            <div>
                <span>Delivery Status</span>
                <strong>
                    <?php echo htmlspecialchars(
                        $success_order["delivery_status"]
                    ); ?>
                </strong>
            </div>

            <div>
                <span>Expected Delivery</span>
                <strong>
                    <?php echo htmlspecialchars(
                        $success_order["expected_date"]
                    ); ?>
                </strong>
            </div>

        </div>

        <a
            href="dashboard.php"
            class="dashboard-btn"
        >
            Go to Dashboard
        </a>

    </div>


<?php elseif (!$cart || count($cart_items) === 0): ?>

    <!-- =================================
         EMPTY CART
    ================================== -->

    <div class="empty">

        <h2>Your cart is empty</h2>

        <p>
            Add books to your cart before checkout.
        </p>

        <br>

        <a href="books.php">
            Browse Books
        </a>

    </div>


<?php else: ?>

    <h1>Checkout</h1>

    <?php if ($error !== ""): ?>

        <div class="error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <div class="checkout-layout">

        <!-- =================================
             CUSTOMER INFORMATION
        ================================== -->

        <div class="card">

            <h2>Delivery Information</h2>

            <form method="POST">

                <div class="form-group">

                    <label>
                        Customer Name
                    </label>

                    <input
                        type="text"
                        value="<?php
                            echo htmlspecialchars(
                                $customer["name"]
                            );
                        ?>"
                        readonly
                        style="
                            width:100%;
                            padding:12px;
                            border:1px solid #cbd5e1;
                            border-radius:7px;
                        "
                    >

                </div>


                <div class="form-group">

                    <label>
                        Email
                    </label>

                    <input
                        type="text"
                        value="<?php
                            echo htmlspecialchars(
                                $customer["email"]
                            );
                        ?>"
                        readonly
                        style="
                            width:100%;
                            padding:12px;
                            border:1px solid #cbd5e1;
                            border-radius:7px;
                        "
                    >

                </div>


                <div class="form-group">

                    <label>
                        Delivery Address
                    </label>

                    <textarea
                        name="address"
                        placeholder="Enter your complete delivery address"
                        required
                    ><?php
                        echo htmlspecialchars(
                            $customer["address"] ?? ""
                        );
                    ?></textarea>

                </div>


                <h2>Payment Method</h2>

                <div class="form-group">

                    <label class="payment-option">

                        <input
                            type="radio"
                            name="payment_method"
                            value="UPI"
                            required
                        >

                        UPI

                    </label>


                    <label class="payment-option">

                        <input
                            type="radio"
                            name="payment_method"
                            value="Card"
                        >

                        Card

                    </label>


                    <label class="payment-option">

                        <input
                            type="radio"
                            name="payment_method"
                            value="Cash on Delivery"
                        >

                        Cash on Delivery

                    </label>

                </div>


                <button
                    type="submit"
                    name="place_order"
                    class="place-order"
                >
                    Place Order
                </button>

            </form>

        </div>


        <!-- =================================
             ORDER SUMMARY
        ================================== -->

        <div class="card">

            <h2>Order Summary</h2>

            <?php foreach ($cart_items as $item): ?>

                <div class="order-item">

                    <div>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $item["title"]
                            );
                            ?>
                        </strong>

                        <p>
                            Quantity:
                            <?php
                            echo $item["quantity"];
                            ?>
                        </p>

                    </div>

                    <strong>
                        ₹<?php
                        echo number_format(
                            $item["subtotal"],
                            2
                        );
                        ?>
                    </strong>

                </div>

            <?php endforeach; ?>


            <div class="summary-row">

                <span>Subtotal</span>

                <span>
                    ₹<?php
                    echo number_format(
                        $grand_total,
                        2
                    );
                    ?>
                </span>

            </div>


            <div class="summary-row">

                <span>Delivery</span>

                <span>Free</span>

            </div>


            <div class="summary-row total">

                <span>Total</span>

                <span>
                    ₹<?php
                    echo number_format(
                        $grand_total,
                        2
                    );
                    ?>
                </span>

            </div>

        </div>

    </div>

<?php endif; ?>

</div>

</body>

</html>