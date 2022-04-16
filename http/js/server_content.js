function DrawPopulars(div_id, response) {
    var e = document.getElementById(div_id);
    for (let html of JSON.parse(response)) {
        e.insertAdjacentHTML("beforeend", html);
    }
}


function LoadPopularTracks(button) {
    button.style.visibility = "hidden";
    var request_url = "index.php?JsonContent=ListPopularTracks";
    console.log(request_url);
    var xobj = new XMLHttpRequest();
    xobj.overrideMimeType('application/json');
    xobj.open('GET', request_url, true);
    xobj.onreadystatechange = function () {
        if (xobj.readyState == 4 && xobj.status == '200') {
            // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
            DrawPopulars("PopularTracks", xobj.responseText);
        }
    };
    xobj.send(null);
}


function LoadPopularCarClasses(button) {
    button.style.visibility = "hidden";
    var request_url = "index.php?JsonContent=ListPopularCarClasses";
    console.log(request_url);
    var xobj = new XMLHttpRequest();
    xobj.overrideMimeType('application/json');
    xobj.open('GET', request_url, true);
    xobj.onreadystatechange = function () {
        if (xobj.readyState == 4 && xobj.status == '200') {
            // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
            DrawPopulars("PopularCarClasses", xobj.responseText);
        }
    };
    xobj.send(null);
}

