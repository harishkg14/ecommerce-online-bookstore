<?php

session_start();

/* CSRF protection */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

include "../config/db.php";


/* CHECK BOOK ID */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: books.php");
    exit();

}

$book_id = intval($_GET['id']);


/* FETCH BOOK */

$sql = "SELECT
            b.book_id,
            b.title,
            b.author,
            b.description,
            b.price,
            b.stock,
            b.image,
            c.category_name
        FROM books b
        JOIN categories c
            ON b.category_id = c.category_id
        WHERE b.book_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $book_id);

$stmt->execute();

$result = $stmt->get_result();


/* BOOK NOT FOUND */

if ($result->num_rows === 0) {

    header("Location: books.php");
    exit();

}

$book = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo htmlspecialchars($book['title']); ?> | BookStore
    </title>

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
            color: white;
            padding: 18px 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
        }


        .logo {
            font-size: 24px;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }


        .logo span {
            color: #60a5fa;
        }


        .nav-links {
            display: flex;
            gap: 28px;
            align-items: center;
        }


        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }


        .nav-links a:hover {
            color: #60a5fa;
        }


        .cart-btn {
            background: var(--primary);
            padding: 9px 16px;
            border-radius: 6px;
        }


        /* PAGE */

        .page {
            padding: 60px 7%;
        }


        .breadcrumb {
            margin-bottom: 25px;
            font-size: 13px;
        }


        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }


        .book-container {
            max-width: 1050px;
            margin: auto;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 40px;
            display: grid;
            grid-template-columns: 40% 60%;
            gap: 45px;
        }


        /* BOOK IMAGE */

        .book-image {
            min-height: 430px;
            background: #e0f2fe;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            text-align: center;
            color: #64748b;
        }


        .book-image img {
            max-width: 100%;
            max-height: 380px;
            object-fit: contain;
            border-radius: 6px;
        }


        /* DETAILS */

        .category {
            display: inline-block;
            color: var(--primary);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }


        .book-details h1 {
            color: var(--dark);
            font-size: 34px;
            line-height: 1.3;
            margin-bottom: 10px;
        }


        .author {
            color: #64748b;
            font-size: 15px;
            margin-bottom: 25px;
        }


        .description-title {
            color: var(--dark);
            font-size: 17px;
            margin-bottom: 8px;
        }


        .description {
            color: #64748b;
            line-height: 1.8;
            font-size: 14px;
            margin-bottom: 25px;
        }


        .price {
            color: var(--success);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }


        .stock {
            font-size: 13px;
            margin-bottom: 25px;
        }


        .available {
            color: var(--success);
        }


        .out {
            color: var(--danger);
        }


        /* CART FORM */

        .cart-form {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }


        .quantity-label {
            font-size: 14px;
            font-weight: 500;
        }


        .quantity {
            width: 75px;
            padding: 11px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
        }


        .add-btn {
            border: none;
            background: var(--primary);
            color: white;
            padding: 12px 22px;
            border-radius: 6px;
            font-family: inherit;
            font-weight: 500;
            cursor: pointer;
        }


        .add-btn:hover {
            background: var(--primary-dark);
        }


        .back-btn {
            display: inline-block;
            margin-top: 25px;
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
        }


        .back-btn:hover {
            color: var(--primary);
        }


        /* FOOTER */

        footer {
            background: var(--dark);
            color: #cbd5e1;
            padding: 30px 7%;
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
                gap: 15px;
            }


            .page {
                padding: 35px 5%;
            }


            .book-container {
                grid-template-columns: 1fr;
                padding: 25px;
                gap: 30px;
            }


            .book-image {
                min-height: 300px;
            }


            .book-details h1 {
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

        <a href="../home.php">
            Home
        </a>

        <a href="books.php">
            Books
        </a>

        <a href="#">
            About
        </a>

        <a href="#">
            Contact
        </a>

        <a href="../auth/login.php">
            Login
        </a>

        <a href="cart.php" class="cart-btn">
            Cart
        </a>

    </div>

</nav>



<!-- BOOK DETAILS -->

<section class="page">


    <div class="breadcrumb">

        <a href="../home.php">
            Home
        </a>

        →

        <a href="books.php">
            Books
        </a>

        →

        Book Details

    </div>



    <div class="book-container">


        <!-- IMAGE -->

        <div class="book-image">

            <?php

            /*
             * Images will be connected later.
             * For now, display the filename.
             */

            echo htmlspecialchars($book['image']);

            ?>

        </div>



        <!-- DETAILS -->

        <div class="book-details">


            <div class="category">

                <?php

                echo htmlspecialchars($book['category_name']);

                ?>

            </div>



            <h1>

                <?php

                echo htmlspecialchars($book['title']);

                ?>

            </h1>



            <div class="author">

                By

                <?php

                echo htmlspecialchars($book['author']);

                ?>

            </div>



            <h3 class="description-title">

                About this book

            </h3>



            <p class="description">

                <?php

                echo nl2br(
                    htmlspecialchars($book['description'])
                );

                ?>

            </p>



            <div class="price">

                ₹<?php

                echo number_format(
                    $book['price'],
                    2
                );

                ?>

            </div>



            <?php if ($book['stock'] > 0) { ?>


                <div class="stock available">

                    <?php

                    echo $book['stock'];

                    ?>

                    copies available

                </div>



                <!-- ADD TO CART FORM -->

                <form
                    action="cart.php"
                    method="POST"
                    class="cart-form"
                >

                    <!-- CSRF TOKEN -->

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($csrf_token); ?>"
                    >


                    <!-- BOOK ID -->

                    <input
                        type="hidden"
                        name="book_id"
                        value="<?php echo $book['book_id']; ?>"
                    >


                    <label
                        for="quantity"
                        class="quantity-label"
                    >

                        Quantity

                    </label>


                    <input
                        type="number"
                        id="quantity"
                        name="quantity"
                        class="quantity"
                        value="1"
                        min="1"
                        max="<?php echo $book['stock']; ?>"
                        required
                    >


                    <button
                        type="submit"
                        name="add_to_cart"
                        class="add-btn"
                    >

                        Add to Cart

                    </button>

                </form>


            <?php } else { ?>


                <div class="stock out">

                    This book is currently out of stock.

                </div>


            <?php } ?>



            <a
                href="books.php"
                class="back-btn"
            >

                ← Back to Books

            </a>


        </div>


    </div>


</section>



<footer>

    <p>

        © 2026 BookStore. Online Bookstore & Delivery Engine.

    </p>

</footer>


</body>

</html>