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
   ADD BOOK
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_book"])
) {

    $category_id = intval($_POST["category_id"]);
    $title = trim($_POST["title"]);
    $author = trim($_POST["author"]);
    $description = trim($_POST["description"]);
    $price = floatval($_POST["price"]);
    $stock = intval($_POST["stock"]);
    $image = trim($_POST["image"]);

    if (
        $category_id <= 0 ||
        $title === "" ||
        $author === "" ||
        $price < 0 ||
        $stock < 0
    ) {

        $error = "Please enter valid book details.";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO books
            (
                category_id,
                title,
                author,
                description,
                price,
                stock,
                image
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "isssdis",
            $category_id,
            $title,
            $author,
            $description,
            $price,
            $stock,
            $image
        );

        if ($stmt->execute()) {

            $message = "Book added successfully.";

        } else {

            $error = "Unable to add book.";
        }
    }
}


/* =========================================
   UPDATE BOOK
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_book"])
) {

    $book_id = intval($_POST["book_id"]);
    $category_id = intval($_POST["category_id"]);
    $title = trim($_POST["title"]);
    $author = trim($_POST["author"]);
    $description = trim($_POST["description"]);
    $price = floatval($_POST["price"]);
    $stock = intval($_POST["stock"]);
    $image = trim($_POST["image"]);

    if (
        $book_id <= 0 ||
        $category_id <= 0 ||
        $title === "" ||
        $author === "" ||
        $price < 0 ||
        $stock < 0
    ) {

        $error = "Please enter valid book details.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE books
             SET
                category_id = ?,
                title = ?,
                author = ?,
                description = ?,
                price = ?,
                stock = ?,
                image = ?
             WHERE book_id = ?"
        );

        $stmt->bind_param(
            "isssdisi",
            $category_id,
            $title,
            $author,
            $description,
            $price,
            $stock,
            $image,
            $book_id
        );

        if ($stmt->execute()) {

            $message = "Book updated successfully.";

        } else {

            $error = "Unable to update book.";
        }
    }
}


/* =========================================
   DELETE BOOK
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_book"])
) {

    $book_id = intval($_POST["book_id"]);

    /*
     * Books referenced by cart_items or order_items
     * cannot be deleted because of foreign keys.
     */

    $stmt = $conn->prepare(
        "SELECT
            (SELECT COUNT(*)
             FROM cart_items
             WHERE book_id = ?) +
            (SELECT COUNT(*)
             FROM order_items
             WHERE book_id = ?) AS usage_count"
    );

    $stmt->bind_param(
        "ii",
        $book_id,
        $book_id
    );

    $stmt->execute();

    $usage_result = $stmt->get_result();
    $usage = $usage_result->fetch_assoc()["usage_count"];

    if ($usage > 0) {

        $error =
            "This book cannot be deleted because it is used in cart or order records.";

    } else {

        $stmt = $conn->prepare(
            "DELETE FROM books
             WHERE book_id = ?"
        );

        $stmt->bind_param(
            "i",
            $book_id
        );

        if ($stmt->execute()) {

            $message = "Book deleted successfully.";

        } else {

            $error = "Unable to delete book.";
        }
    }
}


/* =========================================
   GET CATEGORIES
========================================= */

$categories_result = $conn->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_name"
);

$categories = [];

while (
    $category = $categories_result->fetch_assoc()
) {
    $categories[] = $category;
}


/* =========================================
   GET BOOKS
========================================= */

$books_result = $conn->query(
    "SELECT
        b.book_id,
        b.category_id,
        b.title,
        b.author,
        b.description,
        b.price,
        b.stock,
        b.image,
        b.created_at,
        c.category_name
     FROM books b
     JOIN categories c
        ON b.category_id = c.category_id
     ORDER BY b.book_id DESC"
);

$books = [];

while (
    $book = $books_result->fetch_assoc()
) {
    $books[] = $book;
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

<title>Manage Books | BookStore</title>

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
    margin-bottom: 25px;
}

.card h2 {
    margin-bottom: 20px;
}


/* =================================
   FORM
================================= */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.form-group {
    margin-bottom: 5px;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 7px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-family: inherit;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.add-btn {
    margin-top: 18px;
    background: #22d3ee;
    color: #0f172a;
    border: none;
    padding: 12px 20px;
    border-radius: 7px;
    cursor: pointer;
    font-family: inherit;
    font-weight: 600;
}


/* =================================
   BOOK TABLE
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
    vertical-align: top;
}

th {
    background: #f8fafc;
}

.book-title {
    font-weight: 600;
}

.author {
    color: #64748b;
    font-size: 12px;
    margin-top: 3px;
}

.stock-good {
    color: #166534;
    font-weight: 600;
}

.stock-low {
    color: #b45309;
    font-weight: 600;
}

.stock-out {
    color: #b91c1c;
    font-weight: 600;
}


/* =================================
   ACTIONS
================================= */

.action-form {
    display: inline;
}

.edit-btn,
.delete-btn {
    border: none;
    padding: 7px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
}

.edit-btn {
    background: #dbeafe;
    color: #1d4ed8;
}

.delete-btn {
    background: #fee2e2;
    color: #991b1b;
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

    .form-grid {
        grid-template-columns: 1fr;
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

    <a
        href="books.php"
        class="active"
    >
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

        <h1>
            Manage Books
        </h1>

        <p>
            Add, update and manage the bookstore catalogue.
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


    <!-- =================================
         ADD BOOK
    ================================== -->

    <div class="card">

        <h2>
            Add New Book
        </h2>

        <form method="POST">

            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <select
                        name="category_id"
                        required
                    >

                        <option value="">
                            Select Category
                        </option>

                        <?php foreach (
                            $categories as $category
                        ): ?>

                            <option
                                value="<?php
                                    echo $category[
                                        "category_id"
                                    ];
                                ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $category[
                                        "category_name"
                                    ]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Book Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Author
                    </label>

                    <input
                        type="text"
                        name="author"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Price
                    </label>

                    <input
                        type="number"
                        name="price"
                        min="0"
                        step="0.01"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Stock
                    </label>

                    <input
                        type="number"
                        name="stock"
                        min="0"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Image Filename
                    </label>

                    <input
                        type="text"
                        name="image"
                        placeholder="example.jpg"
                    >

                </div>


                <div class="form-group full">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        placeholder="Enter book description"
                    ></textarea>

                </div>

            </div>


            <button
                type="submit"
                name="add_book"
                class="add-btn"
            >
                Add Book
            </button>

        </form>

    </div>


    <!-- =================================
         BOOK LIST
    ================================== -->

    <div class="card">

        <h2>
            Book Catalogue
        </h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Book
                        </th>

                        <th>
                            Category
                        </th>

                        <th>
                            Price
                        </th>

                        <th>
                            Stock
                        </th>

                        <th>
                            Image
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($books as $book): ?>

                    <tr>

                        <td>
                            <?php
                            echo $book["book_id"];
                            ?>
                        </td>


                        <td>

                            <div class="book-title">

                                <?php
                                echo htmlspecialchars(
                                    $book["title"]
                                );
                                ?>

                            </div>

                            <div class="author">

                                <?php
                                echo htmlspecialchars(
                                    $book["author"]
                                );
                                ?>

                            </div>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $book["category_name"]
                            );
                            ?>

                        </td>


                        <td>

                            ₹<?php
                            echo number_format(
                                $book["price"],
                                2
                            );
                            ?>

                        </td>


                        <td>

                            <?php

                            if ($book["stock"] == 0) {

                                $stock_class = "stock-out";

                            } elseif ($book["stock"] <= 5) {

                                $stock_class = "stock-low";

                            } else {

                                $stock_class = "stock-good";
                            }

                            ?>

                            <span
                                class="<?php
                                    echo $stock_class;
                                ?>"
                            >

                                <?php
                                echo $book["stock"];
                                ?>

                            </span>

                        </td>


                        <td>

                            <?php

                            if (
                                !empty(
                                    $book["image"]
                                )
                            ) {

                                echo htmlspecialchars(
                                    $book["image"]
                                );

                            } else {

                                echo "Not set";
                            }

                            ?>

                        </td>


                        <td>

                            <a
                                href="edit_book.php?id=<?php
                                    echo $book["book_id"];
                                ?>"
                                class="edit-btn"
                                style="
                                    text-decoration:none;
                                    display:inline-block;
                                "
                            >
                                Edit
                            </a>


                            <form
                                method="POST"
                                class="action-form"
                                onsubmit="
                                    return confirm(
                                        'Are you sure you want to delete this book?'
                                    );
                                "
                            >

                                <input
                                    type="hidden"
                                    name="book_id"
                                    value="<?php
                                        echo $book["book_id"];
                                    ?>"
                                >

                                <button
                                    type="submit"
                                    name="delete_book"
                                    class="delete-btn"
                                >
                                    Delete
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

</div>

</body>

</html>