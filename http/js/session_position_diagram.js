var SessionPositionDiagramChart = null;
var SessionPositionDiagramSessionId = null;
var SessionPositionDiagramDrivers = null;


function SessionPositionDiagramReceiveData(response) {
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

    var dia_type = canvas.getAttribute("diagramType");
    var data = JSON.parse(response);

    for (let user_id in data.Positions.Data) {

        // collect data
        var chart_data = new Array();
        for (let lap_nr in data.Positions.Data[user_id]) {
            if (data.Positions.Data[user_id][lap_nr].Place > 0) {

                var y = 0;
                if (dia_type == "place") {
                    y = data.Positions.Data[user_id][lap_nr].Place;
                } else if (dia_type == "gap") {
                    y = data.Positions.Data[user_id][lap_nr].Gap / -1000;
                }

                var point =  {'x': lap_nr, 'y': y};
                chart_data.push(point);
            }
        }

        // draw data
        var dataset = {
                        type: "line",
                        label: data.UserInfo[user_id].Name,
                        backgroundColor: data.UserInfo[user_id].Color,
                        borderColor: data.UserInfo[user_id].Color,
                        data: chart_data,
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
    var dia_type = canvas.getAttribute("diagramType");

//     // calculate y-axis-categories
//     var y_axis_categories = new Array();
//     for (var i = 1; i <= positions; ++i) {
//         y_axis_categories.push(i);
//     }

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
                        reverse: (dia_type == "place") ? true : false,
//                         type: 'category',
//                         labels: y_axis_categories,
                        max: (dia_type == "place") ? positions : 0,
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
    canvas_div.style.height = "" + (positions * 30 + 100) + "px";

    // request general session info
    var request_url = "index.php?JsonContent=SessionData&SessionId=" + SessionPositionDiagramSessionId + "&Request=DriverPositions";
    console.log(request_url);
    var xobj = new XMLHttpRequest();
    xobj.overrideMimeType('application/json');
    xobj.open('GET', request_url, true);
    xobj.onreadystatechange = function () {
        if (xobj.readyState == 4 && xobj.status == '200') {
            // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
            SessionPositionDiagramReceiveData(xobj.responseText);
        }
    };
    xobj.send(null);
})
