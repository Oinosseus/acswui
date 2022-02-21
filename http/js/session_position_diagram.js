var SessionPositionDiagramChart = null;
var SessionPositionDiagramSessionId = null;
var SessionPositionDiagramDrivers = null;


function SessionPositionDiagramFillData(response) {
    for (let info of JSON.parse(response)) {
        var data = new Array();
        for (let i=0; i < info.Positions.length; ++i) data.push(info.Positions[i]);

        var dataset = {
                        type: "line",
                        label: info.User.Name,
                        backgroundColor: info.User.Color,
                        borderColor: info.User.Color,
                        data: info.Positions,
                        radius: 1,
                        borderWidth: 5,
                    };
        SessionPositionDiagramChart.data.datasets.push(dataset);
        SessionPositionDiagramChart.update();
    }
}


document.addEventListener('DOMContentLoaded', function () {

    // get canvas element
    var canvas_div = document.getElementById("SessionPositionDiagram");
    var canvas = null;
    for (var i=0; i < canvas_div.children.length; ++i) {
        var chld = canvas_div.children[i]
        if (chld.tagName == "CANVAS") {
            canvas = chld;
            break;
        }
    }

    // get transported variables
    var diagram_title = canvas.getAttribute("title");
    var ax_y_title = canvas.getAttribute("axYTitle");
    var ax_x_title = canvas.getAttribute("axXTitle");
    SessionPositionDiagramSessionId = canvas.getAttribute("sessionId");
    var positions  = canvas.getAttribute("positions");

    // calculate y-axis-categories
    var y_axis_categories = new Array();
    for (var i = 1; i <= positions; ++i) {
        y_axis_categories.push(i);
    }

    // create chart
    SessionPositionDiagramChart = new Chart(canvas, {
        data: {
            datasets: []
        },
        options:{
            scales: {
                    x: {type: 'linear',
                        min: 0,
//                         max: 30,
                        title: {
                            display: true,
                            text: ax_x_title
                        }
                    },
                    y: {
                        type: 'category',
                        labels: y_axis_categories,
//                         max: -1,
//                         min: -1 * positions,
//                         bottom: 10,
//                         top: 1,
//                         reversed: true,
                        title: {
                            display: true,
                            text: ax_y_title
                        }
                    }
            },
            plugins: {
                title: {
                    display: true,
                    text: diagram_title
                }
            },
            maintainAspectRatio: false // This will fix the height
        }
    });

    // y-scaling
    canvas_div.style.height = "" + (positions * 40) + "px";

    // request general session info
    var request_url = "index.php?JsonContent=SessionData&SessionId=" + SessionPositionDiagramSessionId + "&Request=DriverPositions";
    console.log(request_url);
    var xobj = new XMLHttpRequest();
    xobj.overrideMimeType('application/json');
    xobj.open('GET', request_url, true);
    xobj.onreadystatechange = function () {
        if (xobj.readyState == 4 && xobj.status == '200') {
            // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
            SessionPositionDiagramFillData(xobj.responseText);
        }
    };
    xobj.send(null);
})
