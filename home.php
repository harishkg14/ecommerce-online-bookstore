<?php

include "config/db.php";

/* Get featured books */
$sql = "SELECT 
            b.book_id,
            b.title,
            b.author,
            b.price,
            b.image,
            c.category_name
        FROM books b
        JOIN categories c
            ON b.category_id = c.category_id
        ORDER BY b.book_id DESC
        LIMIT 6";

$result = $conn->query($sql);

/* Get categories */
$category_sql = "SELECT category_id, category_name
                 FROM categories
                 ORDER BY category_name";

$category_result = $conn->query($category_sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>BookStore | Online Bookstore</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
            transition: 0.3s;
        }

        .nav-links a:hover {
            color: #60a5fa;
        }

        .cart-btn {
            background: var(--primary);
            padding: 9px 16px;
            border-radius: 6px;
        }

        .cart-btn:hover {
            background: var(--primary-dark);
            color: white !important;
        }

        /* HERO */

        .hero {
            min-height: 500px;
            display: flex;
            align-items: center;
            padding: 70px 7%;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
        }

        .hero-content {
            max-width: 650px;
        }

        .hero-content h1 {
            font-size: 48px;
            line-height: 1.2;
            color: var(--dark);
            margin-bottom: 20px;
        }

        .hero-content h1 span {
            color: var(--primary);
        }

        .hero-content p {
            font-size: 17px;
            line-height: 1.8;
            margin-bottom: 30px;
            color: #475569;
        }

        .hero-btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            text-decoration: none;
            padding: 13px 24px;
            border-radius: 7px;
            font-weight: 500;
            transition: 0.3s;
        }

        .hero-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* SECTION */

        .section {
            padding: 60px 7%;
        }

        .section-title {
            text-align: center;
            margin-bottom: 35px;
        }

        .section-title h2 {
            font-size: 30px;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .section-title p {
            color: #64748b;
            font-size: 14px;
        }

        /* BOOK CARDS */

        .book-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .book-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            transition: 0.3s;
        }

        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.10);
        }

        .book-image {
            height: 180px;
            background: #e0f2fe;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 14px;
        }

        .book-info {
            padding: 20px;
        }

        .category {
            font-size: 12px;
            color: var(--primary);
            font-weight: 600;
            text-transform: uppercase;
        }

        .book-info h3 {
            color: var(--dark);
            font-size: 18px;
            margin: 8px 0;
        }

        .author {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 12px;
        }

        .price {
            color: var(--success);
            font-size: 18px;
            font-weight: 700;
        }

        .view-btn {
            display: inline-block;
            margin-top: 14px;
            color: var(--primary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        /* CATEGORIES */

        .category-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .category-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            color: var(--dark);
            text-decoration: none;
            transition: 0.3s;
        }

        .category-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
        }

        .category-card h3 {
            font-size: 16px;
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

            .hero {
                padding: 50px 7%;
                text-align: center;
            }

            .hero-content h1 {
                font-size: 36px;
            }

            .book-grid {
                grid-template-columns: 1fr;
            }

            .category-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<!-- NAVBAR -->

<nav class="navbar">

    <a href="home.php" class="logo">
        Book<span>Store</span>
    </a>

    <div class="nav-links">

        <a href="home.php">Home</a>

        <a href="user/books.php">Books</a>

        <a href="#">About</a>

        <a href="#">Contact</a>

        <a href="auth/login.php">Login</a>

        <a href="user/cart.php" class="cart-btn">
            Cart
        </a>

    </div>

</nav>


<!-- HERO -->

<section class="hero">

    <div class="hero-content">

        <h1>
            Discover Your
            <span>Next Great Book</span>
        </h1>

        <p>
            Explore books across fiction, business, technology,
            finance, self-development and academic categories.
            Choose your favourite books and order them online.
        </p>

        <a href="user/books.php" class="hero-btn">
            Browse Books
        </a>

    </div>

</section>


<!-- FEATURED BOOKS -->

<section class="section">

    <div class="section-title">

        <h2>Featured Books</h2>

        <p>
            Explore some of the books available in our bookstore.
        </p>

    </div>


    <div class="book-grid">

        <?php

        if ($result && $result->num_rows > 0) {

            while ($book = $result->fetch_assoc()) {

        ?>

            <div class="book-card">

                <div class="book-image">

                    <?php

                    if (!empty($book['image'])) {

                        echo htmlspecialchars($book['image']);

                    } else {

                        echo "Book Image";

                    }

                    ?>

                </div>

                <div class="book-info">

                    <div class="category">
                        <?php echo htmlspecialchars($book['category_name']); ?>
                    </div>

                    <h3>
                        <?php echo htmlspecialchars($book['title']); ?>
                    </h3>

                    <div class="author">
                        By <?php echo htmlspecialchars($book['author']); ?>
                    </div>

                    <div class="price">
                        ₹<?php echo number_format($book['price'], 2); ?>
                    </div>

                    <a
                        href="user/book.php?id=<?php echo $book['book_id']; ?>"
                        class="view-btn"
                    >
                        View Book →
                    </a>

                </div>

            </div>

        <?php

            }

        } else {

            echo "<p>No books available.</p>";

        }

        ?>

    </div>

</section>


<!-- CATEGORIES -->

<section class="section">

    <div class="section-title">

        <h2>Browse Categories</h2>

        <p>
            Find books based on your interests.
        </p>

    </div>


    <div class="category-grid">

        <?php

        if ($category_result && $category_result->num_rows > 0) {

            while ($category = $category_result->fetch_assoc()) {

        ?>

            <a
                href="user/books.php?category=<?php echo $category['category_id']; ?>"
                class="category-card"
            >

                <h3>
                    <?php echo htmlspecialchars($category['category_name']); ?>
                </h3>

            </a>

        <?php

            }

        }

        ?>

    </div>

</section>


<!-- FOOTER -->

<footer>

    <p>
        © 2026 BookStore. Online Bookstore & Delivery Engine.
    </p>

</footer>


</body>
</html>