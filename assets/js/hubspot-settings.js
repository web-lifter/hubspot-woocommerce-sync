document.addEventListener("DOMContentLoaded", function() {
    const connectButton = document.getElementById("hubspot-connect-btn");

    if (connectButton) {
        connectButton.addEventListener("click", function(event) {
            event.preventDefault();
            const connectUrl = connectButton.getAttribute("data-url");
            window.location.href = connectUrl;
        });
    }
});