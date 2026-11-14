<?php

session_start();

include "../config/db.php";

/* Protect page */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$customer_id = $_SESSION['user_id'];
$customer_name = $_SESSION['name'];

/* Total orders */

$order_sql = "SELECT COUNT(*) AS total_orders
              FROM orders
              WHERE customer_id = ?";

$order_stmt = $conn->prepare($order_sql);
$order_stmt->bind_param("i", $customer_id);
$order_stmt->execute();

$order_result = $order_stmt->get_result();
$order_data = $order_result->fetch_assoc();

$total_orders = $order_data['total_orders'];


/* Total spending */

$spending_sql = "SELECT COALESCE(SUM(total), 0) AS total_spending
                 FROM orders
                 WHERE customer_id = ?
                 AND status != 'cancelled'";

$spending_stmt = $conn->prepare($spending_sql);
$spending_stmt->bind_param("i", $customer_id);
$spending_stmt->execute();

$spending_result = $spending_stmt->get_result();
$spending_data = $spending_result->fetch_assoc();

$total_spending = $spending_data['total_spending'];


/* Active cart */

$cart_sql = "SELECT COUNT(*) AS active_carts
             FROM carts
             WHERE customer_id = ?
             AND status = 'active'";

$cart_stmt = $conn->prepare($cart_sql);
$cart_stmt->bind_param("i", $customer_id);
$cart_stmt->execute();

$cart_result = $cart_stmt->get_result();
$cart_data = $cart_result->fetch_assoc();

$active_carts = $cart_data['active_carts'];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Dashboard | BookStore</title>

    <link rel="preconnect"
          href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --dark: #0f172a;
            --text: #334155;
            --light: #f8fafc;
            --white: #ffffff;
            --border: #e2e8f0;
            --success: #16a34a;
            --danger: #dc2626;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light);
            color: var(--text);
        }

        /* NAVBAR */

        .navbar {
            background: var(--dark);
            padding: 18px 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            color: white;
            text-decoration: none;
            font-size: 24px;
            font-weight: 700;
        }

        .logo span {
            color: #60a5fa;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 13px;
        }

        .nav-links a:hover {
            color: #60a5fa;
        }

        .logout {
            background: #dc2626;
            padding: 8px 14px;
            border-radius: 6px;
        }

        /* PAGE */

        .dashboard {
            padding: 50px 7%;
            min-height: calc(100vh - 70px);
        }

        .welcome {
            margin-bottom: 35px;
        }

        .welcome h1 {
            color: var(--dark);
            font-size: 32px;
            margin-bottom: 8px;
        }

        .welcome p {
            color: #64748b;
            font-size: 14px;
        }

        /* STAT CARDS */

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 25px;
        }

        .stat-card h3 {
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .stat-card .number {
            color: var(--dark);
            font-size: 28px;
            font-weight: 700;
        }

        .stat-card.blue .number {
            color: var(--primary);
        }

        .stat-card.green .number {
            color: var(--success);
        }

        /* QUICK LINKS */

        .section-title {
            color: var(--dark);
            font-size: 21px;
            margin-bottom: 18px;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .quick-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 25px;
            text-decoration: none;
            transition: 0.3s;
        }

        .quick-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
        }

        .quick-card h3 {
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 17px;
        }

        .quick-card p {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }

        .quick-card span {
            display: inline-block;
            margin-top: 15px;
            color: var(--primary);
            font-size: 13px;
            font-weight: 600;
        }

        /* FOOTER */

        footer {
            background: var(--dark);
            color: #cbd5e1;
            padding: 25px 7%;
            text-align: center;
            font-size: 13px;
        }

        /* MOBILE */

        @media (max-width: 768px) {

            .navbar {
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .stats,
            .quick-links {
                grid-template-columns: 1fr;
            }

            .dashboard {
                padding: 35px 5%;
            }

            .welcome h1 {
                font-size: 27px;
            }

        }

    </style>

</head>

<body>

<!-- NAVBAR -->

<nav class="navbar">

    <a href="../home.php" class="logo">
        Book<span>Store</span>
    </a>

    <div class="nav-links">

        <a href="../home.php">Home</a>

        <a href="books.php">Books</a>

        <a href="cart.php">Cart</a>

        <a href="orders.php">Orders</a>

        <a href="../auth/logout.php" class="logout">
            Logout
        </a>

    </div>

</nav>


<!-- DASHBOARD -->

<main class="dashboard">

    <div class="welcome">

        <h1>
            Welcome, <?php echo htmlspecialchars($customer_name); ?>
        </h1>

        <p>
            Manage your books, cart and orders from your dashboard.
        </p>

    </div>


    <!-- STATISTICS -->

    <div class="stats">

        <div class="stat-card blue">

            <h3>
                Total Orders
            </h3>

            <div class="number">
                <?php echo $total_orders; ?>
            </div>

        </div>


        <div class="stat-card green">

            <h3>
                Total Spending
            </h3>

            <div class="number">
                ₹<?php echo number_format($total_spending, 2); ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>
                Active Carts
            </h3>

            <div class="number">
                <?php echo $active_carts; ?>
            </div>

        </div>

    </div>


    <!-- QUICK LINKS -->

    <h2 class="section-title">
        Quick Access
    </h2>


    <div class="quick-links">

        <a href="books.php" class="quick-card">

            <h3>
                Browse Books
            </h3>

            <p>
                Explore the bookstore catalogue,
                search for books and filter by category.
            </p>

            <span>
                View Books →
            </span>

        </a>


        <a href="cart.php" class="quick-card">

            <h3>
                Shopping Cart
            </h3>

            <p>
                Review the books you have selected
                and continue to checkout.
            </p>

            <span>
                Open Cart →
            </span>

        </a>


        <a href="orders.php" class="quick-card">

            <h3>
                My Orders
            </h3>

            <p>
                View your previous orders and
                track delivery status.
            </p>

            <span>
                View Orders →
            </span>

        </a>

    </div>

</main>


<footer>

    <p>
        © 2026 BookStore. Online Bookstore & Delivery Engine.
    </p>

</footer>

</body>
</html>