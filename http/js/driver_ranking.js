var DriverRankingDiagramChart = null;



function loadDriverRankingData(driver_id, button) {

    if (button) {
        button.style.visibility = "hidden";
    }

    var request_url = "index.php?JsonContent=DriverRankingData&UserId=" + driver_id;
    console.log(request_url);
    var xobj = new XMLHttpRequest();
    xobj.overrideMimeType('application/json');
    xobj.open('GET', request_url, true);
    xobj.onreadystatechange = function () {
        if (xobj.readyState == 4 && xobj.status == '200') {
            // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
            drawDriverRankingData(xobj.responseText);
        }
    };
    xobj.send(null);
}



function drawDriverRankingData(response) {
//     console.log(response);

    var data = JSON.parse(response);

    var dataset = {
                    type: "line",
                    label: data.User.Name,
                    backgroundColor: data.User.Color,
                    borderColor: data.User.Color,
                    data: data.Ranking,
                    radius: 1,
                    borderWidth: 5,
                };
    DriverRankingDiagramChart.data.datasets.push(dataset);
    DriverRankingDiagramChart.update();
}



document.addEventListener('DOMContentLoaded', function () {

    // get canvas element
    var canvas_div = document.getElementById("DriverRankingDiagram");
    var canvas = null;
    for (var i=0; i < canvas_div.children.length; ++i) {
        var chld = canvas_div.children[i]
        if (chld.tagName == "CANVAS") {
            canvas = chld;
            break;
        }
    }

    // create chart
    DriverRankingDiagramChart = new Chart(canvas, {
        type: "line",
        data: {
            datasets: []
        },
        options: {
            scales: {
                    x: {type: 'linear',
//                         min: 0,
//                         max: 30,
                        title: {
                            display: true,
                            text: canvas.getAttribute("axYTitle")
                        }
                    },
                    y: {
//                         type: 'category',
//                         labels: y_axis_categories,
                        // max: 20,
                        // min: 0,
//                         bottom: 10,
//                         top: 1,
//                         reversed: true,
                        title: {
                            display: true,
                            text: canvas.getAttribute("axXTitle")
                        }
                    }
            },
            plugins: {
                title: {
                    display: true,
                    text: canvas.getAttribute("title")
                }
            },
            maintainAspectRatio: false // This will fix the height
        }
    });

    // y-scaling
    canvas_div.style.height = "400px";

    // load first user
    var user_id = canvas.getAttribute("currentUser");
    if (user_id != 0) loadDriverRankingData(user_id, 0);
})
