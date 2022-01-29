function TrackRecordsDraw(response) {
    var e = document.getElementById("TrackRecordsList");
    for (let html of JSON.parse(response)) {
        e.insertAdjacentHTML("beforeend", html);
    }
}


function TrackLoadRecords(button) {
    button.style.visibility = "hidden";
    var track_id = button.getAttribute("trackId");
    var request_url = "index.php?JsonContent=TrackRecords&TrackId=" + track_id;
    console.log(request_url);
    var xobj = new XMLHttpRequest();
    xobj.overrideMimeType('application/json');
    xobj.open('GET', request_url, true);
    xobj.onreadystatechange = function () {
        if (xobj.readyState == 4 && xobj.status == '200') {
            // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
            TrackRecordsDraw(xobj.responseText);
        }
    };
    xobj.send(null);
}
