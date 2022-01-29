// ----------------------------------------------------------------------------
//                             Collisions
// ----------------------------------------------------------------------------

function SessionOverviewDrawCollisions(response) {
    var data = JSON.parse(response);
    var tbl = document.getElementById("SessionCollisions");

    // create header row
    var tbl_tr = document.createElement("tr");
    for (let head of data.Header) {
        var tbl_th = document.createElement("th");
        tbl_th.innerText = head;
        tbl_tr.appendChild(tbl_th);
    }
    tbl.append(tbl_tr);

    // create data rows
    for (let row of data.Rows) {
        var tbl_tr = document.createElement("tr");
        for (let cell of row) {
            var tbl_td = document.createElement("td");
            tbl_td.insertAdjacentHTML('afterbegin', cell);
            tbl_tr.appendChild(tbl_td);
        }
        tbl.append(tbl_tr);
    }

}


function SessionOverviewLoadCollisions(button) {
    button.style.visibility = "hidden";


    var session_id = button.getAttribute("sessionId");

    // request general session info
    var request_url = "index.php?JsonContent=SessionData&SessionId=" + SessionPositionDiagramSessionId + "&Request=Collisions";
    console.log(request_url);
    var xobj = new XMLHttpRequest();
    xobj.overrideMimeType('application/json');
    xobj.open('GET', request_url, true);
    xobj.onreadystatechange = function () {
        if (xobj.readyState == 4 && xobj.status == '200') {
            // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
            SessionOverviewDrawCollisions(xobj.responseText);
        }
    };
    xobj.send(null);
}


// ----------------------------------------------------------------------------
//                                 Laps
// ----------------------------------------------------------------------------

function SessionOverviewDrawLaps(response) {
    var data = JSON.parse(response);
    var tbl = document.getElementById("SessionLaps");

    // create header row
    var tbl_tr = document.createElement("tr");
    for (let head of data.Header) {
        var tbl_th = document.createElement("th");
        tbl_th.insertAdjacentHTML('beforeend', head);
        tbl_tr.appendChild(tbl_th);
    }
    tbl.append(tbl_tr);

    // create data rows
    for (let row of data.Rows) {
        tbl.insertAdjacentHTML('beforeend', row);
    }

}


function SessionOverviewLoadLaps(button) {
    button.style.visibility = "hidden";


    var session_id = button.getAttribute("sessionId");

    // request general session info
    var request_url = "index.php?JsonContent=SessionData&SessionId=" + SessionPositionDiagramSessionId + "&Request=Laps";
    console.log(request_url);
    var xobj = new XMLHttpRequest();
    xobj.overrideMimeType('application/json');
    xobj.open('GET', request_url, true);
    xobj.onreadystatechange = function () {
        if (xobj.readyState == 4 && xobj.status == '200') {
            // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
            SessionOverviewDrawLaps(xobj.responseText);
        }
    };
    xobj.send(null);
}
