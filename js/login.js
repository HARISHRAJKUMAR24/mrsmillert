/* =========================================================
   MRS MILL@ — LOGIN UX
   File: ./js/login.js
   Uses window.ADMIN_URL (injected by login.php)
   ========================================================= */

(function () {
    "use strict";

    /* =========================================
       BASE URL
    ========================================= */

    // Fallback to "./" if ADMIN_URL is not defined
    const BASE_URL =
        (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
            ? window.ADMIN_URL
            : "./";

    /* =========================================
       DOM REFERENCES
    ========================================= */

    const form           = document.getElementById("loginForm");
    const usernameInput  = document.getElementById("username");
    const passwordInput  = document.getElementById("password");
    const togglePassword = document.getElementById("togglePassword");
    const eyeIcon        = document.getElementById("eyeIcon");
    const button         = document.getElementById("loginButton");
    const buttonContent  = document.getElementById("buttonContent");
    const error          = document.getElementById("errorMessage");
    const errorText      = document.getElementById("errorText");

    /* =========================================
       BUTTON STATES
    ========================================= */

    const BUTTON_DEFAULT = `
        Login
        <i class="bi bi-arrow-right ms-2"></i>
    `;

    const BUTTON_LOADING = `
        <span class="spinner"></span>
        <span>Signing in...</span>
    `;

    function setButtonLoading(isLoading) {
        button.disabled = isLoading;
        buttonContent.innerHTML = isLoading
            ? BUTTON_LOADING
            : BUTTON_DEFAULT;
    }

    /* =========================================
       ERROR DISPLAY
    ========================================= */

    function showError(message) {
        errorText.textContent = message;
        error.classList.add("show");
    }

    function clearError() {
        error.classList.remove("show");
    }

    /* =========================================
       PASSWORD SHOW / HIDE
    ========================================= */

    if (togglePassword && passwordInput && eyeIcon) {

        togglePassword.addEventListener("click", function () {

            const isPassword = passwordInput.type === "password";

            passwordInput.type = isPassword ? "text" : "password";

            eyeIcon.className = isPassword
                ? "bi bi-eye-slash"
                : "bi bi-eye";

            togglePassword.setAttribute(
                "aria-label",
                isPassword ? "Hide password" : "Show password"
            );
        });
    }

    /* =========================================
       CLEAR ERROR WHILE TYPING
    ========================================= */

    document
        .querySelectorAll(".form-control-custom")
        .forEach(function (input) {

            input.addEventListener("input", clearError);
        });

    /* =========================================
       CLIENT-SIDE VALIDATION
    ========================================= */

    function validate(username, password) {

        if (!username) {
            showError("Please enter your username.");
            usernameInput.focus();
            return false;
        }

        if (!password) {
            showError("Please enter your password.");
            passwordInput.focus();
            return false;
        }

        return true;
    }

    /* =========================================
       FORM SUBMIT — CALL AJAX ENDPOINT
    ========================================= */

    form.addEventListener("submit", function (event) {

        event.preventDefault();
        clearError();

        const username = usernameInput.value.trim();
        const password = passwordInput.value.trim();

        if (!validate(username, password)) {
            return;
        }

        setButtonLoading(true);

        const formData = new FormData();
        formData.append("username", username);
        formData.append("password", password);

        // ✅ Uses ADMIN_URL for the endpoint
        const endpoint = BASE_URL + "ajax/login.php";

        fetch(endpoint, {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        })
        .then(function (response) {

            return response.json().catch(function () {
                return {
                    success: false,
                    message: "Unexpected server response."
                };
            });
        })
        .then(function (data) {

            if (data && data.success) {

                // ✅ Uses ADMIN_URL for the dashboard redirect
                window.location.href = BASE_URL + "index.php";
                return;
            }

            showError(
                (data && data.message) ||
                "Invalid username or password."
            );

            setButtonLoading(false);
        })
        .catch(function () {

            showError(
                "Unable to connect to server. Please try again."
            );

            setButtonLoading(false);
        });
    });

})();