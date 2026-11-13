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


/* =========================================
   DELETE CUSTOMER
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_user"])
) {

    $customer_id = intval($_POST["customer_id"]);

    /* Prevent admin from deleting own account */

    if ($customer_id == $_SESSION["user_id"]) {

        $message = "You cannot delete your own admin account.";

    } else {

        /*
         * Check whether customer has orders.
         * Orders use ON DELETE RESTRICT.
         */

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM orders
             WHERE customer_id = ?"
        );

        $stmt->bind_param("i", $customer_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $order_count = $result->fetch_assoc()["total"];

        if ($order_count > 0) {

            $message =
                "This customer cannot be deleted because they have existing orders.";

        } else {

            $stmt = $conn->prepare(
                "DELETE FROM customers
                 WHERE customer_id = ?
                 AND role = 'customer'"
            );

            $stmt->bind_param(
                "i",
                $customer_id
            );

            if ($stmt->execute()) {

                $message = "Customer deleted successfully.";

            } else {

                $message = "Unable to delete customer.";
            }
        }
    }
}


/* =========================================
   GET CUSTOMERS
========================================= */

$result = $conn->query(
    "SELECT
        customer_id,
        name,
        email,
        phone,
        address,
        created_at
     FROM customers
     WHERE role = 'customer'
     ORDER BY customer_id DESC"
);

$customers = [];

while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
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

<title>Manage Users | BookStore</title>

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


/* SIDEBAR */

.layout {
    display: flex;
    min-height: 100vh;
}

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


/* MAIN */

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


/* MESSAGE */

.message {
    background: #dcfce7;
    color: #166534;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}


/* CARD */

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}


/* TABLE */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
}

th {
    background: #f8fafc;
    font-weight: 600;
}

td {
    vertical-align: top;
}

.customer-name {
    font-weight: 600;
}

.secondary {
    color: #64748b;
    font-size: 12px;
    margin-top: 3px;
}


/* DELETE */

.delete-btn {
    background: #fee2e2;
    color: #991b1b;
    border: none;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
}

.delete-btn:hover {
    background: #fecaca;
}


/* EMPTY */

.empty {
    text-align: center;
    padding: 40px;
    color: #64748b;
}


/* MOBILE */

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


<!-- SIDEBAR -->

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

    <a
        href="users.php"
        class="active"
    >
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


<!-- MAIN -->

<main class="main">


    <div class="topbar">

        <h1>
            Manage Users
        </h1>

        <p>
            View and manage registered customers.
        </p>

    </div>


    <?php if ($message !== ""): ?>

        <div class="message">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <div class="card">

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Contact
                        </th>

                        <th>
                            Address
                        </th>

                        <th>
                            Registered
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($customers) > 0): ?>

                    <?php foreach ($customers as $customer): ?>

                        <tr>

                            <td>
                                <?php
                                echo $customer["customer_id"];
                                ?>
                            </td>


                            <td>

                                <div class="customer-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $customer["name"]
                                    );
                                    ?>

                                </div>

                            </td>


                            <td>

                                <div>

                                    <?php
                                    echo htmlspecialchars(
                                        $customer["email"]
                                    );
                                    ?>

                                </div>

                                <?php if (
                                    !empty($customer["phone"])
                                ): ?>

                                    <div class="secondary">

                                        <?php
                                        echo htmlspecialchars(
                                            $customer["phone"]
                                        );
                                        ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php

                                if (
                                    !empty(
                                        $customer["address"]
                                    )
                                ) {

                                    echo nl2br(
                                        htmlspecialchars(
                                            $customer["address"]
                                        )
                                    );

                                } else {

                                    echo "Not provided";
                                }

                                ?>

                            </td>


                            <td>

                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $customer["created_at"]
                                    )
                                );
                                ?>

                            </td>


                            <td>

                                <form
                                    method="POST"
                                    onsubmit="
                                        return confirm(
                                            'Are you sure you want to delete this customer?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="customer_id"
                                        value="<?php
                                            echo $customer[
                                                "customer_id"
                                            ];
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_user"
                                        class="delete-btn"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="6"
                            class="empty"
                        >
                            No customers found.
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