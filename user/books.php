<?php

include "../config/db.php";

/* Search and category filter */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;

/* Build query */
$sql = "SELECT
            b.book_id,
            b.title,
            b.author,
            b.description,
            b.price,
            b.stock,
            b.image,
            c.category_id,
            c.category_name
        FROM books b
        JOIN categories c
            ON b.category_id = c.category_id
        WHERE 1=1";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (b.title LIKE ? OR b.author LIKE ?)";
    $search_value = "%" . $search . "%";
    $params[] = $search_value;
    $params[] = $search_value;
    $types .= "ss";
}

if ($category > 0) {
    $sql .= " AND b.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

$sql .= " ORDER BY b.title ASC";

/* Prepare query */
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();

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

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Books | BookStore</title>

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

        /* PAGE HEADER */

        .page-header {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            padding: 55px 7%;
            text-align: center;
        }

        .page-header h1 {
            font-size: 38px;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .page-header p {
            color: #64748b;
        }

        /* FILTER */

        .filter-section {
            padding: 35px 7% 10px;
        }

        .filter-box {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 220px;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: inherit;
            outline: none;
        }

        .search-input:focus {
            border-color: var(--primary);
        }

        .category-select {
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: inherit;
            min-width: 180px;
        }

        .filter-btn {
            border: none;
            background: var(--primary);
            color: white;
            padding: 12px 22px;
            border-radius: 6px;
            font-family: inherit;
            cursor: pointer;
            font-weight: 500;
        }

        .filter-btn:hover {
            background: var(--primary-dark);
        }

        .clear-btn {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #64748b;
            padding: 0 10px;
            font-size: 14px;
        }

        /* BOOK SECTION */

        .books-section {
            padding: 35px 7% 70px;
        }

        .result-info {
            margin-bottom: 20px;
            color: #64748b;
            font-size: 14px;
        }

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
            height: 210px;
            background: #e0f2fe;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #64748b;
            text-align: center;
            font-size: 13px;
        }

        .book-info {
            padding: 20px;
        }

        .category {
            color: var(--primary);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .book-info h2 {
            color: var(--dark);
            font-size: 19px;
            margin: 8px 0;
        }

        .author {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .description {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .book-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .price {
            color: var(--success);
            font-size: 18px;
            font-weight: 700;
        }

        .stock {
            font-size: 12px;
            margin-top: 5px;
        }

        .stock.available {
            color: var(--success);
        }

        .stock.out {
            color: var(--danger);
        }

        .view-btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }

        .view-btn:hover {
            background: var(--primary-dark);
        }

        .empty {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 50px;
            text-align: center;
            color: #64748b;
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

            .book-grid {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 30px;
            }

            .filter-box {
                flex-direction: column;
            }

            .search-input,
            .category-select,
            .filter-btn {
                width: 100%;
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

        <a href="#">About</a>

        <a href="#">Contact</a>

        <a href="../auth/login.php">Login</a>

        <a href="cart.php" class="cart-btn">
            Cart
        </a>

    </div>

</nav>


<!-- PAGE HEADER -->

<section class="page-header">

    <h1>Explore Our Books</h1>

    <p>
        Find books that match your interests and discover your next read.
    </p>

</section>


<!-- SEARCH AND FILTER -->

<section class="filter-section">

    <form method="GET" action="books.php" class="filter-box">

        <input
            type="text"
            name="search"
            class="search-input"
            placeholder="Search by book title or author..."
            value="<?php echo htmlspecialchars($search); ?>"
        >

        <select name="category" class="category-select">

            <option value="0">
                All Categories
            </option>

            <?php

            if ($category_result && $category_result->num_rows > 0) {

                while ($cat = $category_result->fetch_assoc()) {

            ?>

                <option
                    value="<?php echo $cat['category_id']; ?>"
                    <?php
                    echo ($category == $cat['category_id'])
                        ? 'selected'
                        : '';
                    ?>
                >
                    <?php echo htmlspecialchars($cat['category_name']); ?>
                </option>

            <?php

                }

            }

            ?>

        </select>

        <button type="submit" class="filter-btn">
            Search
        </button>

        <a href="books.php" class="clear-btn">
            Clear
        </a>

    </form>

</section>


<!-- BOOKS -->

<section class="books-section">

    <div class="result-info">

        <?php echo $result->num_rows; ?> book(s) found

    </div>


    <?php if ($result->num_rows > 0) { ?>

        <div class="book-grid">

            <?php while ($book = $result->fetch_assoc()) { ?>

                <div class="book-card">

                    <div class="book-image">

                        <?php

                        echo htmlspecialchars($book['image']);

                        ?>

                    </div>


                    <div class="book-info">

                        <div class="category">

                            <?php
                            echo htmlspecialchars(
                                $book['category_name']
                            );
                            ?>

                        </div>


                        <h2>

                            <?php
                            echo htmlspecialchars(
                                $book['title']
                            );
                            ?>

                        </h2>


                        <div class="author">

                            By
                            <?php
                            echo htmlspecialchars(
                                $book['author']
                            );
                            ?>

                        </div>


                        <div class="description">

                            <?php

                            $description =
                                $book['description'];

                            if (strlen($description) > 100) {

                                echo htmlspecialchars(
                                    substr($description, 0, 100)
                                ) . "...";

                            } else {

                                echo htmlspecialchars(
                                    $description
                                );

                            }

                            ?>

                        </div>


                        <div class="book-bottom">

                            <div>

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
                                        <?php echo $book['stock']; ?>
                                        available
                                    </div>

                                <?php } else { ?>

                                    <div class="stock out">
                                        Out of stock
                                    </div>

                                <?php } ?>

                            </div>


                            <a
                                href="book.php?id=<?php echo $book['book_id']; ?>"
                                class="view-btn"
                            >
                                View Book
                            </a>

                        </div>

                    </div>

                </div>

            <?php } ?>

        </div>

    <?php } else { ?>

        <div class="empty">

            <h3>No books found</h3>

            <p>
                Try another search or category.
            </p>

        </div>

    <?php } ?>

</section>


<!-- FOOTER -->

<footer>

    <p>
        © 2026 BookStore. Online Bookstore & Delivery Engine.
    </p>

</footer>


</body>
</html>