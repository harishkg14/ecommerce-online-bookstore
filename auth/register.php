<?php

session_start();

require_once "../config/db.php";

$message = "";
$message_type = "";


/* Registration processing */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");


    /* Required-field validation */

    if (
        $name === "" ||
        $email === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {

        $message = "Please fill in all required fields.";
        $message_type = "error";

    }

    /* Name validation */

    elseif (strlen($name) < 2) {

        $message = "Name must contain at least 2 characters.";
        $message_type = "error";

    }

    /* Email validation */

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    }

    /* Password validation */

    elseif (strlen($password) < 6) {

        $message =
            "Password must contain at least 6 characters.";

        $message_type = "error";

    }

    /* Confirm password */

    elseif ($password !== $confirm_password) {

        $message = "Passwords do not match.";
        $message_type = "error";

    }

    else {

        /*
         * Check whether email already exists.
         */

        $check_sql = "
            SELECT customer_id
            FROM customers
            WHERE email = ?
            LIMIT 1
        ";

        $check_stmt = $conn->prepare($check_sql);

        if (!$check_stmt) {

            $message =
                "Unable to process registration.";

            $message_type = "error";

        } else {

            $check_stmt->bind_param("s", $email);

            $check_stmt->execute();

            $check_result = $check_stmt->get_result();


            if ($check_result->num_rows > 0) {

                $message =
                    "An account with this email already exists.";

                $message_type = "error";

            } else {

                /*
                 * Hash password before storing it.
                 */

                $password_hash =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                /*
                 * New users are always created as customers.
                 *
                 * The role is NOT taken from the form.
                 */

                $role = "customer";


                $insert_sql = "
                    INSERT INTO customers
                    (
                        name,
                        email,
                        password_hash,
                        phone,
                        address,
                        role
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?)
                ";

                $insert_stmt =
                    $conn->prepare($insert_sql);


                if (!$insert_stmt) {

                    $message =
                        "Unable to create your account.";

                    $message_type = "error";

                } else {

                    $insert_stmt->bind_param(
                        "ssssss",
                        $name,
                        $email,
                        $password_hash,
                        $phone,
                        $address,
                        $role
                    );


                    if ($insert_stmt->execute()) {

                        /*
                         * Redirect after successful
                         * registration.
                         */

                        header(
                            "Location: login.php?registered=1"
                        );

                        exit();

                    } else {

                        $message =
                            "Registration failed. Please try again.";

                        $message_type = "error";
                    }


                    $insert_stmt->close();
                }
            }


            $check_stmt->close();
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

    <title>Register | BookStore</title>

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

        /* REGISTER */

        .register-section {
            min-height: calc(100vh - 70px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .register-card {
            width: 100%;
            max-width: 600px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 35px;
            box-shadow: 0 15px 40px rgba(15, 23, 42, 0.08);
        }

        .register-title {
            text-align: center;
            margin-bottom: 25px;
        }

        .register-title h1 {
            color: var(--dark);
            font-size: 30px;
            margin-bottom: 8px;
        }

        .register-title p {
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

        .message.error {
            background: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        /* FORM */

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            color: var(--dark);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 7px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: inherit;
            font-size: 13px;
            outline: none;
        }

        .form-group textarea {
            height: 90px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .register-btn {
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
            margin-top: 5px;
        }

        .register-btn:hover {
            background: var(--primary-dark);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #64748b;
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        /* MOBILE */

        @media (max-width: 600px) {

            .register-card {
                padding: 25px 20px;
            }

            .two-column {
                grid-template-columns: 1fr;
                gap: 0;
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


<!-- REGISTER FORM -->

<section class="register-section">

    <div class="register-card">

        <div class="register-title">

            <h1>Create Account</h1>

            <p>
                Register to shop books and manage your orders.
            </p>

        </div>


        <?php if (!empty($message)) { ?>

            <div class="message <?php echo $message_type; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php } ?>


        <form method="POST" action="register.php">

            <div class="form-group">

                <label for="name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Enter your full name"
                    required
                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                >

            </div>


            <div class="two-column">

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
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="phone">
                        Phone
                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        placeholder="Enter phone number"
                        required
                        value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                    >

                </div>

            </div>


            <div class="form-group">

                <label for="address">
                    Address
                </label>

                <textarea
                    id="address"
                    name="address"
                    placeholder="Enter delivery address"
                    required
                ><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>

            </div>


            <div class="two-column">

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Minimum 6 characters"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm password"
                        required
                    >

                </div>

            </div>


            <button
                type="submit"
                class="register-btn"
            >
                Create Account
            </button>

        </form>


        <div class="login-link">

            Already have an account?

            <a href="login.php">
                Login here
            </a>

        </div>

    </div>

</section>

</body>
</html>