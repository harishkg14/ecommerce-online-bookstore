<?php

session_start();

/* CSRF protection */

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];


/* Login check */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}


require_once "../config/db.php";

$customer_id = (int)$_SESSION["user_id"];

$message = "";


/* =========================================================
   CSRF VALIDATION
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $_SESSION["csrf_token"],
            $submitted_token
        )
    ) {
        die("Invalid security token. Please refresh the page and try again.");
    }
}


/* =========================================================
   ADD BOOK TO CART
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["add_to_cart"])
) {

    $book_id = filter_input(
        INPUT_POST,
        "book_id",
        FILTER_VALIDATE_INT
    );

    $quantity = filter_input(
        INPUT_POST,
        "quantity",
        FILTER_VALIDATE_INT
    );


    if (!$book_id || $book_id < 1) {

        $message = "Invalid book.";

    } else {

        if (!$quantity || $quantity < 1) {
            $quantity = 1;
        }


        /* Check book */

        $stmt = $conn->prepare("
            SELECT
                book_id,
                title,
                price,
                stock
            FROM books
            WHERE book_id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "i",
            $book_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $book = $result->fetch_assoc();

        $stmt->close();


        if (!$book) {

            $message = "Book not found.";

        } elseif ((int)$book["stock"] <= 0) {

            $message = "This book is out of stock.";

        } else {


            /* Get active cart */

            $stmt = $conn->prepare("
                SELECT cart_id
                FROM carts
                WHERE customer_id = ?
                AND status = 'active'
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $customer_id
            );

            $stmt->execute();

            $cart_result = $stmt->get_result();

            $cart = $cart_result->fetch_assoc();

            $stmt->close();


            /* Create cart if it doesn't exist */

            if (!$cart) {

                $stmt = $conn->prepare("
                    INSERT INTO carts
                    (customer_id, status)
                    VALUES (?, 'active')
                ");

                $stmt->bind_param(
                    "i",
                    $customer_id
                );

                $stmt->execute();

                $cart_id = $conn->insert_id;

                $stmt->close();

            } else {

                $cart_id = (int)$cart["cart_id"];
            }


            /* Check existing cart item */

            $stmt = $conn->prepare("
                SELECT
                    cart_item_id,
                    quantity
                FROM cart_items
                WHERE cart_id = ?
                AND book_id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "ii",
                $cart_id,
                $book_id
            );

            $stmt->execute();

            $item_result = $stmt->get_result();

            $existing_item = $item_result->fetch_assoc();

            $stmt->close();


            if ($existing_item) {

                $new_quantity =
                    (int)$existing_item["quantity"] + $quantity;


                if ($new_quantity > (int)$book["stock"]) {

                    $message =
                        "Only " .
                        (int)$book["stock"] .
                        " copies are available.";

                } else {

                    $stmt = $conn->prepare("
                        UPDATE cart_items
                        SET quantity = ?
                        WHERE cart_item_id = ?
                    ");

                    $stmt->bind_param(
                        "ii",
                        $new_quantity,
                        $existing_item["cart_item_id"]
                    );

                    $stmt->execute();

                    $stmt->close();

                    $message =
                        "Book quantity updated in cart.";
                }

            } else {

                if ($quantity > (int)$book["stock"]) {
                    $quantity = (int)$book["stock"];
                }


                $stmt = $conn->prepare("
                    INSERT INTO cart_items
                    (
                        cart_id,
                        book_id,
                        quantity
                    )
                    VALUES (?, ?, ?)
                ");

                $stmt->bind_param(
                    "iii",
                    $cart_id,
                    $book_id,
                    $quantity
                );

                $stmt->execute();

                $stmt->close();

                $message =
                    "Book added to cart.";
            }
        }
    }
}


/* =========================================================
   UPDATE CART ITEM
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_cart"])
) {

    $cart_item_id = filter_input(
        INPUT_POST,
        "cart_item_id",
        FILTER_VALIDATE_INT
    );

    $quantity = filter_input(
        INPUT_POST,
        "quantity",
        FILTER_VALIDATE_INT
    );


    if (!$cart_item_id || $cart_item_id < 1) {

        $message = "Invalid cart item.";

    } else {

        if (!$quantity || $quantity < 1) {
            $quantity = 1;
        }


        /*
         * Verify that the cart item belongs to
         * the currently logged-in customer.
         */

        $stmt = $conn->prepare("
            SELECT
                ci.cart_item_id,
                b.stock
            FROM cart_items ci
            INNER JOIN carts c
                ON ci.cart_id = c.cart_id
            INNER JOIN books b
                ON ci.book_id = b.book_id
            WHERE ci.cart_item_id = ?
            AND c.customer_id = ?
            AND c.status = 'active'
            LIMIT 1
        ");

        $stmt->bind_param(
            "ii",
            $cart_item_id,
            $customer_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $item = $result->fetch_assoc();

        $stmt->close();


        if ($item) {

            $stock = (int)$item["stock"];


            if ($stock <= 0) {

                $message =
                    "This book is currently out of stock.";

            } elseif ($quantity > $stock) {

                $quantity = $stock;
            }


            if ($stock > 0) {

                $stmt = $conn->prepare("
                    UPDATE cart_items
                    SET quantity = ?
                    WHERE cart_item_id = ?
                ");

                $stmt->bind_param(
                    "ii",
                    $quantity,
                    $cart_item_id
                );

                $stmt->execute();

                $stmt->close();

                $message =
                    "Cart updated.";
            }

        } else {

            $message =
                "Cart item not found.";
        }
    }
}


/* =========================================================
   REMOVE CART ITEM
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["remove_item"])
) {

    $cart_item_id = filter_input(
        INPUT_POST,
        "cart_item_id",
        FILTER_VALIDATE_INT
    );


    if (!$cart_item_id || $cart_item_id < 1) {

        $message = "Invalid cart item.";

    } else {

        /*
         * Delete only if the item belongs to
         * the logged-in customer's active cart.
         */

        $stmt = $conn->prepare("
            DELETE ci
            FROM cart_items ci
            INNER JOIN carts c
                ON ci.cart_id = c.cart_id
            WHERE ci.cart_item_id = ?
            AND c.customer_id = ?
            AND c.status = 'active'
        ");

        $stmt->bind_param(
            "ii",
            $cart_item_id,
            $customer_id
        );

        $stmt->execute();

        $stmt->close();

        $message =
            "Book removed from cart.";
    }
}


/* =========================================================
   GET ACTIVE CART
   ========================================================= */

$stmt = $conn->prepare("
    SELECT cart_id
    FROM carts
    WHERE customer_id = ?
    AND status = 'active'
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $customer_id
);

$stmt->execute();

$cart_result = $stmt->get_result();

$active_cart = $cart_result->fetch_assoc();

$stmt->close();


$cart_items = [];

$grand_total = 0;


if ($active_cart) {

    $cart_id = (int)$active_cart["cart_id"];


    $stmt = $conn->prepare("
        SELECT
            ci.cart_item_id,
            ci.quantity,
            b.book_id,
            b.title,
            b.author,
            b.price,
            b.stock
        FROM cart_items ci
        INNER JOIN books b
            ON ci.book_id = b.book_id
        WHERE ci.cart_id = ?
        ORDER BY ci.cart_item_id DESC
    ");

    $stmt->bind_param(
        "i",
        $cart_id
    );

    $stmt->execute();

    $result = $stmt->get_result();


    while ($row = $result->fetch_assoc()) {

        $row["subtotal"] =
            (float)$row["price"] *
            (int)$row["quantity"];

        $grand_total +=
            $row["subtotal"];

        $cart_items[] = $row;
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Shopping Cart | BookStore</title>

<link rel="preconnect"
      href="https://fonts.googleapis.com">

<link rel="preconnect"
      href="https://fonts.gstatic.com"
      crossorigin>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
    rel="stylesheet">

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


/* PAGE */

.container {
    width: 90%;
    max-width: 1200px;
    margin: 45px auto;
}

h1 {
    font-size: 30px;
    margin-bottom: 25px;
}


/* MESSAGE */

.message {
    background: #dcfce7;
    color: #166534;

    padding: 14px 18px;

    border-radius: 8px;

    margin-bottom: 20px;
}


/* CART */

.cart-layout {
    display: grid;

    grid-template-columns:
        1fr 330px;

    gap: 25px;
}

.cart-card {
    background: white;

    border-radius: 12px;

    padding: 20px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.06);
}

.cart-item {
    display: flex;

    justify-content: space-between;

    gap: 20px;

    padding: 20px 0;

    border-bottom:
        1px solid #e5e7eb;
}

.book-info h3 {
    margin-bottom: 5px;
}

.book-info p {
    color: #64748b;
    font-size: 14px;
}

.price {
    font-weight: 600;
    margin-top: 8px;
}


/* QUANTITY */

.quantity-form {
    display: flex;

    align-items: center;

    gap: 8px;

    margin-top: 12px;
}

.quantity-form input {
    width: 65px;

    padding: 8px;

    border:
        1px solid #cbd5e1;

    border-radius: 6px;
}


/* BUTTONS */

button {
    border: none;

    padding: 8px 14px;

    border-radius: 6px;

    cursor: pointer;

    font-family: inherit;
}

.update-btn {
    background: #0f172a;
    color: white;
}

.remove-btn {
    background: #fee2e2;
    color: #b91c1c;
}


/* SUMMARY */

.summary h2 {
    margin-bottom: 20px;
}

.summary-row {
    display: flex;

    justify-content: space-between;

    margin: 15px 0;
}

.total {
    font-size: 21px;

    font-weight: 700;

    border-top:
        1px solid #e5e7eb;

    padding-top: 15px;
}

.checkout-btn {
    display: block;

    width: 100%;

    text-align: center;

    background: #22d3ee;

    color: #0f172a;

    text-decoration: none;

    padding: 13px;

    border-radius: 8px;

    font-weight: 600;

    margin-top: 20px;
}


/* EMPTY */

.empty {
    background: white;

    padding: 50px;

    text-align: center;

    border-radius: 12px;
}

.empty a {
    display: inline-block;

    margin-top: 20px;

    background: #0f172a;

    color: white;

    padding: 12px 20px;

    border-radius: 7px;

    text-decoration: none;
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

    .cart-layout {
        grid-template-columns: 1fr;
    }

    .cart-item {
        flex-direction: column;
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

        <a href="../auth/logout.php">
            Logout
        </a>

    </div>

</div>


<!-- MAIN -->

<div class="container">

    <h1>
        Shopping Cart
    </h1>


    <?php if ($message !== ""): ?>

        <div class="message">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <?php if (count($cart_items) > 0): ?>

        <div class="cart-layout">


            <!-- CART ITEMS -->

            <div class="cart-card">

                <?php foreach ($cart_items as $item): ?>

                    <div class="cart-item">

                        <div class="book-info">

                            <h3>

                                <?php
                                echo htmlspecialchars(
                                    $item["title"]
                                );
                                ?>

                            </h3>

                            <p>

                                Author:

                                <?php
                                echo htmlspecialchars(
                                    $item["author"]
                                );
                                ?>

                            </p>

                            <div class="price">

                                ₹<?php
                                echo number_format(
                                    $item["price"],
                                    2
                                );
                                ?>

                            </div>


                            <div class="quantity-form">


                                <!-- UPDATE FORM -->

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php
                                        echo htmlspecialchars(
                                            $csrf_token
                                        );
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="cart_item_id"
                                        value="<?php
                                        echo (int)
                                            $item["cart_item_id"];
                                        ?>"
                                    >

                                    <input
                                        type="number"
                                        name="quantity"
                                        value="<?php
                                        echo (int)
                                            $item["quantity"];
                                        ?>"
                                        min="1"
                                        max="<?php
                                        echo (int)
                                            $item["stock"];
                                        ?>"
                                        required
                                    >

                                    <button
                                        type="submit"
                                        name="update_cart"
                                        class="update-btn"
                                    >
                                        Update
                                    </button>

                                </form>


                                <!-- REMOVE FORM -->

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php
                                        echo htmlspecialchars(
                                            $csrf_token
                                        );
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="cart_item_id"
                                        value="<?php
                                        echo (int)
                                            $item["cart_item_id"];
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="remove_item"
                                        class="remove-btn"
                                    >
                                        Remove
                                    </button>

                                </form>


                            </div>

                        </div>


                        <div>

                            <strong>

                                ₹<?php
                                echo number_format(
                                    $item["subtotal"],
                                    2
                                );
                                ?>

                            </strong>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>


            <!-- SUMMARY -->

            <div class="cart-card summary">

                <h2>
                    Order Summary
                </h2>


                <div class="summary-row">

                    <span>
                        Items
                    </span>

                    <span>
                        <?php
                        echo count($cart_items);
                        ?>
                    </span>

                </div>


                <div class="summary-row">

                    <span>
                        Subtotal
                    </span>

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

                    <span>
                        Delivery
                    </span>

                    <span>
                        Free
                    </span>

                </div>


                <div class="summary-row total">

                    <span>
                        Total
                    </span>

                    <span>

                        ₹<?php
                        echo number_format(
                            $grand_total,
                            2
                        );
                        ?>

                    </span>

                </div>


                <a
                    href="checkout.php"
                    class="checkout-btn"
                >
                    Proceed to Checkout
                </a>

            </div>

        </div>


    <?php else: ?>

        <div class="empty">

            <h2>
                Your cart is empty
            </h2>

            <p>
                Browse our books and add your favourite books to the cart.
            </p>

            <a href="books.php">
                Browse Books
            </a>

        </div>

    <?php endif; ?>


</div>

</body>

</html>