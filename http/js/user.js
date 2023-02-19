function DrawGarage(div_id, response) {
    var e = document.getElementById(div_id);
    loadingInProgress(e, false);
    for (let html of JSON.parse(response)) {
        e.insertAdjacentHTML("beforeend", html);
    }
}

function LoadGarage(button, div_id, deprercated) {
    button.style.visibility = "hidden";
    loadingInProgress(div_id, true);
    var request_url = "index.php?JsonContent=LoadUserGarage&UserId=" + button.getAttribute('userId') + "&Deprecated=" + deprercated;
    console.log(request_url);
    var xobj = new XMLHttpRequest();
    xobj.overrideMimeType('application/json');
    xobj.open('GET', request_url, true);
    xobj.onreadystatechange = function () {
        if (xobj.readyState == 4 && xobj.status == '200') {
            // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
            DrawGarage(div_id, xobj.responseText);
        }
    };
    xobj.send(null);
}

