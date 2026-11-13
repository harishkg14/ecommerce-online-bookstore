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

$book_id = intval($_GET["id"] ?? 0);

if ($book_id <= 0) {
    header("Location: books.php");
    exit;
}

$message = "";
$error = "";


/* =========================================
   UPDATE BOOK
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_book"])
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
   GET CATEGORIES
========================================= */

$categories_result = $conn->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_name"
);

$categories = [];

while ($category = $categories_result->fetch_assoc()) {
    $categories[] = $category;
}


/* =========================================
   GET BOOK
========================================= */

$stmt = $conn->prepare(
    "SELECT
        book_id,
        category_id,
        title,
        author,
        description,
        price,
        stock,
        image
     FROM books
     WHERE book_id = ?"
);

$stmt->bind_param("i", $book_id);
$stmt->execute();

$result = $stmt->get_result();
$book = $result->fetch_assoc();

if (!$book) {
    header("Location: books.php");
    exit;
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

<title>Edit Book | BookStore</title>

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


/* LAYOUT */

.layout {
    display: flex;
    min-height: 100vh;
}


/* SIDEBAR */

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


/* CARD */

.card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 900px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}


/* MESSAGES */

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


/* FORM */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
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
    min-height: 130px;
    resize: vertical;
}


/* BUTTONS */

.buttons {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}

.save-btn {
    background: #22d3ee;
    color: #0f172a;
    border: none;
    padding: 12px 22px;
    border-radius: 7px;
    cursor: pointer;
    font-family: inherit;
    font-weight: 600;
}

.cancel-btn {
    background: #e2e8f0;
    color: #1e293b;
    padding: 12px 22px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 14px;
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

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
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


<!-- MAIN -->

<main class="main">

    <div class="topbar">

        <h1>
            Edit Book
        </h1>

        <p>
            Update the selected book information.
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

        <form method="POST">

            <div class="form-grid">


                <!-- CATEGORY -->

                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <select
                        name="category_id"
                        required
                    >

                        <?php foreach (
                            $categories as $category
                        ): ?>

                            <option
                                value="<?php
                                    echo $category[
                                        "category_id"
                                    ];
                                ?>"
                                <?php
                                if (
                                    $book["category_id"]
                                    ==
                                    $category["category_id"]
                                ) {
                                    echo "selected";
                                }
                                ?>
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


                <!-- TITLE -->

                <div class="form-group">

                    <label>
                        Book Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        value="<?php
                            echo htmlspecialchars(
                                $book["title"]
                            );
                        ?>"
                        required
                    >

                </div>


                <!-- AUTHOR -->

                <div class="form-group">

                    <label>
                        Author
                    </label>

                    <input
                        type="text"
                        name="author"
                        value="<?php
                            echo htmlspecialchars(
                                $book["author"]
                            );
                        ?>"
                        required
                    >

                </div>


                <!-- PRICE -->

                <div class="form-group">

                    <label>
                        Price
                    </label>

                    <input
                        type="number"
                        name="price"
                        min="0"
                        step="0.01"
                        value="<?php
                            echo htmlspecialchars(
                                $book["price"]
                            );
                        ?>"
                        required
                    >

                </div>


                <!-- STOCK -->

                <div class="form-group">

                    <label>
                        Stock
                    </label>

                    <input
                        type="number"
                        name="stock"
                        min="0"
                        value="<?php
                            echo htmlspecialchars(
                                $book["stock"]
                            );
                        ?>"
                        required
                    >

                </div>


                <!-- IMAGE -->

                <div class="form-group">

                    <label>
                        Image Filename
                    </label>

                    <input
                        type="text"
                        name="image"
                        value="<?php
                            echo htmlspecialchars(
                                $book["image"] ?? ""
                            );
                        ?>"
                        placeholder="example.jpg"
                    >

                </div>


                <!-- DESCRIPTION -->

                <div class="form-group full">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                    ><?php
                        echo htmlspecialchars(
                            $book["description"] ?? ""
                        );
                    ?></textarea>

                </div>

            </div>


            <div class="buttons">

                <button
                    type="submit"
                    name="update_book"
                    class="save-btn"
                >
                    Save Changes
                </button>

                <a
                    href="books.php"
                    class="cancel-btn"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</main>

</div>

</body>

</html>