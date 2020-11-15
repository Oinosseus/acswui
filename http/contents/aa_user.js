'use strict';

function loginAction() {

    var username = document.getElementById("username").value;
    var userpass = document.getElementById("userpass").value;

    if (username == "" || userpass == "") {
        alert("Empty username or password not allowed!");
        return;
    }

    // ajax login request
    var ajax = new XMLHttpRequest();
    ajax.open("POST","?NONCONTENT=ncUserLogon&ACTION=login", false);
    ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    ajax.send("USERNAME=" + username + "&PASSWORD=" + userpass);

    // check response
    if (ajax.responseText != "login successful") {
        alert("Login failed!\nCheck username and password.");
    }

    // reload actual page
    // (login/out can change)
    window.location = "";
}

function logoutAction() {
    // ajax logout request
    var ajax = new XMLHttpRequest();
    ajax.open("POST","?NONCONTENT=ncUserLogon&ACTION=logout", false);
    ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    ajax.send("");

    // reload actual page
    // (login/out can change)
    window.location = "";
}

// connect event listener
if (document.getElementById("user_login_form")) {
    document.getElementById("user_login_form").addEventListener("submit", loginAction);
}
if (document.getElementById("user_logout_form")) {
    document.getElementById("user_logout_form").addEventListener("submit", logoutAction);
}
