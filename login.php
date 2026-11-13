<?php

session_start();

require_once "../config/db.php";

$message = "";
$message_type = "";


/* Registration success message */

if (isset($_GET['registered']) && $_GET['registered'] == 1) {

    $message = "Registration successful. You can now login.";
    $message_type = "success";
}


/* Login processing */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";


    /* Basic validation */

    if ($email === "" || $password === "") {

        $message = "Please enter your email and password.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    } else {

        /*
         * Prepared statement prevents SQL injection.
         */

        $sql = "
            SELECT
                customer_id,
                name,
                email,
                password_hash,
                role
            FROM customers
            WHERE email = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $message = "Unable to process login. Please try again.";
            $message_type = "error";

        } else {

            $stmt->bind_param("s", $email);

            $stmt->execute();

            $result = $stmt->get_result();


            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();


                /* Verify hashed password */

                if (password_verify($password, $user["password_hash"])) {


                    /*
                     * Create a new session ID after successful login.
                     */

                    session_regenerate_id(true);


                    /*
                     * Store user information in session.
                     *
                     * user_id is retained for compatibility.
                     * customer_id is also stored because the
                     * protected pages use this variable.
                     */

                    $_SESSION["user_id"] =
                        (int)$user["customer_id"];

                    $_SESSION["customer_id"] =
                        (int)$user["customer_id"];

                    $_SESSION["name"] =
                        $user["name"];

                    $_SESSION["email"] =
                        $user["email"];

                    $_SESSION["role"] =
                        $user["role"];


                    /*
                     * Redirect according to role.
                     */

                    if ($user["role"] === "admin") {

                        header(
                            "Location: ../admin/dashboard.php"
                        );

                        exit();

                    } else {

                        header(
                            "Location: ../user/dashboard.php"
                        );

                        exit();
                    }

                } else {

                    $message =
                        "Incorrect email or password.";

                    $message_type = "error";
                }

            } else {

                $message =
                    "Incorrect email or password.";

                $message_type = "error";
            }


            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Login | BookStore</title>

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
            background: linear-gradient(
                135deg,
                #eff6ff,
                #dbeafe
            );
            min-height: 100vh;
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

        .home-link {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .home-link:hover {
            color: #60a5fa;
        }

        /* LOGIN */

        .login-section {
            min-height: calc(100vh - 70px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .login-card {
            width: 100%;
            max-width: 430px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 35px;
            box-shadow: 0 15px 40px rgba(15, 23, 42, 0.08);
        }

        .login-title {
            text-align: center;
            margin-bottom: 25px;
        }

        .login-title h1 {
            color: var(--dark);
            font-size: 30px;
            margin-bottom: 8px;
        }

        .login-title p {
            color: #64748b;
            font-size: 13px;
        }

        /* MESSAGE */

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .message.success {
            background: #dcfce7;
            color: var(--success);
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        /* FORM */

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: var(--dark);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 7px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: inherit;
            font-size: 13px;
            outline: none;
        }

        .form-group input:focus {
            border-color: var(--primary);
        }

        .login-btn {
            width: 100%;
            border: none;
            background: var(--primary);
            color: white;
            padding: 13px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        .login-btn:hover {
            background: var(--primary-dark);
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #64748b;
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 600px) {

            .login-card {
                padding: 25px 20px;
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

    <a href="../home.php" class="home-link">
        Home
    </a>

</nav>


<!-- LOGIN -->

<section class="login-section">

    <div class="login-card">

        <div class="login-title">

            <h1>Welcome Back</h1>

            <p>
                Login to continue shopping.
            </p>

        </div>


        <?php if (!empty($message)) { ?>

            <div class="message <?php echo $message_type; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php } ?>


        <form method="POST" action="login.php">

            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            </div>


            <button
                type="submit"
                class="login-btn"
            >
                Login
            </button>

        </form>


        <div class="register-link">

            Don't have an account?

            <a href="register.php">
                Create Account
            </a>

        </div>

    </div>

</section>

</body>
</html>