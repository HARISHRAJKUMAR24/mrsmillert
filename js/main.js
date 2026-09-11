

function toggleSidebar() {

    const sidebar = document.getElementById("sidebar");

    sidebar.classList.toggle("show");

}


/* Close sidebar when clicking outside on mobile */

document.addEventListener("click", function (event) {

    if (window.innerWidth <= 1100) {

        const sidebar =
            document.getElementById("sidebar");

        const mobileButton =
            document.querySelector(".mobile-menu");

        if (
            sidebar.classList.contains("show") &&
            !sidebar.contains(event.target) &&
            !mobileButton.contains(event.target)
        ) {

            sidebar.classList.remove("show");

        }

    }

});


/* Close sidebar when clicking menu */

document
    .querySelectorAll(".sidebar-menu a")
    .forEach(function (link) {

        link.addEventListener("click", function () {

            if (window.innerWidth <= 1100) {

                document
                    .getElementById("sidebar")
                    .classList.remove("show");

            }

        });

    });
