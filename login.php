<?php
require_once './config/config.php';

// If already logged in with a valid token, go to dashboard index page
if (isLoggedIn() && verifyToken($pdo)) {
    header('Location: index.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Mrs Mill@</title>

    <!-- Bootstrap 5 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            font-family: Inter, -apple-system, BlinkMacSystemFont,
                "Segoe UI", Roboto, Arial, sans-serif;
            background: radial-gradient(circle at top left,
                    #fff5f5 0%, #ffffff 45%, #f7f7f7 100%);
            color: #222;
        }

        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
            position: relative;
            overflow: hidden;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .circle-one {
            width: 420px;
            height: 420px;
            top: -230px;
            left: -180px;
            border: 70px solid rgba(237, 28, 36, 0.04);
        }

        .circle-two {
            width: 350px;
            height: 350px;
            right: -180px;
            bottom: -180px;
            border: 60px solid rgba(237, 28, 36, 0.04);
        }

        .login-container {
            width: 100%;
            max-width: 1080px;
            min-height: 650px;
            background: #ffffff;
            border-radius: 26px;
            overflow: hidden;
            border: 1px solid #eeeeee;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.10);
            position: relative;
            z-index: 2;
        }

        .brand-area {
            min-height: 650px;
            padding: 55px;
            background: linear-gradient(145deg,
                    #ffffff 0%, #fffafa 60%, #fdf2f2 100%);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-right: 1px solid #eeeeee;
        }

        .brand-area::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 7px;
            height: 100%;
            background: #ed1c24;
        }

        .logo-wrapper {
            width: 100%;
            margin-bottom: 35px;
            text-align: center;
        }

        .brand-logo {
            width: 100%;
            max-width: 400px;
            height: auto;
            object-fit: contain;
            display: inline-block;
        }

        .brand-heading {
            text-align: center;
            font-size: 30px;
            font-weight: 800;
            color: #222;
            margin-bottom: 10px;
            letter-spacing: -0.8px;
        }

        .brand-heading span {
            color: #ed1c24;
        }

        .brand-subtitle {
            text-align: center;
            color: #777;
            font-size: 14px;
            line-height: 1.7;
            max-width: 420px;
            margin: auto;
        }

        .millet-decoration {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 35px;
        }

        .millet-line {
            height: 1px;
            width: 70px;
            background: #ed1c24;
            opacity: 0.35;
        }

        .millet-icon {
            color: #ed1c24;
            font-size: 18px;
        }

        .login-area {
            min-height: 650px;
            padding: 55px;
            display: flex;
            align-items: center;
        }

        .login-content {
            width: 100%;
            max-width: 390px;
            margin: auto;
        }

        .small-title {
            color: #ed1c24;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .login-title {
            font-size: 38px;
            font-weight: 800;
            color: #222;
            letter-spacing: -1.5px;
            margin-bottom: 8px;
        }

        .login-description {
            color: #888;
            font-size: 14px;
            margin-bottom: 35px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .input-box {
            position: relative;
            margin-bottom: 21px;
        }

        .input-icon {
            position: absolute;
            left: 17px;
            top: 50%;
            transform: translateY(2px);
            color: #9aa4b2;
            font-size: 16px;
            z-index: 3;
            pointer-events: none;
            transition: 0.2s;
        }

        .form-control-custom {
            height: 54px;
            width: 100%;
            border: 1px solid #d8dee8;
            border-radius: 12px;
            background: #eaf2ff;
            padding-left: 52px;
            padding-right: 52px;
            font-size: 14px;
            color: #222;
            outline: none;
            transition: all 0.25s ease;
        }

        .form-control-custom::placeholder {
            color: #8d98a8;
        }

        .form-control-custom:focus {
            border-color: #ed1c24;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(237, 28, 36, 0.08);
        }

        .input-box:focus-within .input-icon {
            color: #ed1c24;
        }

        .password-button {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(2px);
            border: 0;
            background: transparent;
            color: #9aa4b2;
            cursor: pointer;
            font-size: 17px;
            padding: 5px;
            z-index: 4;
        }

        .password-button:hover {
            color: #ed1c24;
        }

        .login-button {
            width: 100%;
            height: 55px;
            border: none;
            border-radius: 12px;
            background: #ed1c24;
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 10px 25px rgba(237, 28, 36, 0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .login-button:hover {
            background: #d9161e;
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(237, 28, 36, 0.25);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .error-message {
            display: none;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: #fff1f1;
            border: 1px solid #ffd2d2;
            color: #d71920;
            font-size: 13px;
        }

        .error-message.show {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 991px) {
            .login-container {
                max-width: 600px;
            }

            .brand-area {
                min-height: 350px;
                padding: 40px;
            }

            .login-area {
                min-height: auto;
                padding: 45px 40px;
            }

            .brand-logo {
                max-width: 330px;
            }

            .brand-heading {
                font-size: 25px;
            }
        }

        @media (max-width: 575px) {
            .login-page {
                padding: 15px;
            }

            .login-container {
                border-radius: 20px;
            }

            .brand-area {
                min-height: 300px;
                padding: 30px 22px;
            }

            .login-area {
                padding: 35px 25px;
            }

            .brand-logo {
                max-width: 280px;
            }

            .brand-heading {
                font-size: 23px;
            }

            .login-title {
                font-size: 31px;
            }
        }
    </style>

</head>

<body>

    <div class="login-page">

        <div class="circle circle-one"></div>
        <div class="circle circle-two"></div>

        <div class="login-container">
            <div class="row g-0 h-100">

                <!-- LEFT BRAND -->
                <div class="col-lg-6">
                    <div class="brand-area">

                        <div class="logo-wrapper">
                            <img
                                src="<?= ADMIN_URL; ?>uploads/mrs-mill@-logo.png"
                                alt="Mrs Mill@"
                                class="brand-logo">
                        </div>

                        <h1 class="brand-heading">
                            Welcome to <span>Mrs Mill@</span>
                        </h1>

                        <p class="brand-subtitle">
                            Feel the flavour of millets.
                            Manage your business, products and
                            orders from one dashboard.
                        </p>

                        <div class="millet-decoration">
                            <span class="millet-line"></span>
                            <i class="bi bi-flower1 millet-icon"></i>
                            <span class="millet-line"></span>
                        </div>

                    </div>
                </div>

                <!-- RIGHT LOGIN -->
                <div class="col-lg-6">
                    <div class="login-area">
                        <div class="login-content">

                            <div class="small-title">Admin Panel</div>

                            <h2 class="login-title">Login</h2>

                            <p class="login-description">
                                Sign in to continue to your dashboard.
                            </p>

                            <!-- ERROR -->
                            <div id="errorMessage" class="error-message">
                                <i class="bi bi-exclamation-circle-fill"></i>
                                <span id="errorText">
                                    Please enter valid details.
                                </span>
                            </div>

                            <!-- LOGIN FORM -->
                            <form id="loginForm" method="POST" novalidate>

                                <!-- USERNAME -->
                                <div class="input-box">
                                    <label class="form-label" for="username">
                                        Username
                                    </label>
                                    <i class="bi bi-person input-icon"></i>
                                    <input
                                        type="text"
                                        id="username"
                                        name="username"
                                        class="form-control-custom"
                                        placeholder="Enter your username"
                                        autocomplete="username"
                                        required>
                                </div>

                                <!-- PASSWORD -->
                                <div class="input-box">
                                    <label class="form-label" for="password">
                                        Password
                                    </label>
                                    <i class="bi bi-lock input-icon"></i>
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control-custom"
                                        placeholder="Enter your password"
                                        autocomplete="current-password"
                                        required>

                                    <button
                                        type="button"
                                        class="password-button"
                                        id="togglePassword"
                                        aria-label="Show password">
                                        <i class="bi bi-eye" id="eyeIcon"></i>
                                    </button>
                                </div>

                                <!-- LOGIN BUTTON -->
                                <button
                                    type="submit"
                                    class="login-button"
                                    id="loginButton">
                                    <span id="buttonContent">
                                        Login
                                        <i class="bi bi-arrow-right ms-2"></i>
                                    </span>
                                </button>

                            </form>

                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/login.js"></script>

</body>

</html>