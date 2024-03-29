// used in SessionOverview

var LaptimeDistributionDiagram = null;
var LaptimeDistributionMaxDelta = 1; // seconds


function LaptimeDistributionDiagramCalculateGauss(x, mean, sigma) {

    // calculate amplitude
    var amplitude = 1 / Math.sqrt(2 * Math.PI * sigma**2);

    // calculate density
//     var sigma_squared = 0;
//     if (x <= mean) sigma_squared  = sigma_left ** 2;
//     else sigma_squared  = sigma_right ** 2;
    var exp = -1 * ((x - mean) ** 2) / (2 * sigma**2);

    return amplitude * Math.exp(exp);
}


function LaptimeDistributionDiagramAddDatasetLine(response) {
    var info = JSON.parse(response);

    // calculate mean value
    var mean = 0;
    for (var i = 0; i < info.Laptimes.length; ++i) {
        mean += info.Laptimes[i];
    }
    mean /= info.Laptimes.length;
    var mean_s = mean / 1000;

    // calculate standard deviation
    var std_dev_s = 0;
    var std_dev_count = 0;
    for (var i = 0; i < info.Laptimes.length; ++i) {
        var laptime_s = info.Laptimes[i] / 1000;

        std_dev_s += (laptime_s - mean_s) ** 2;
        std_dev_count += 1;
    }
    std_dev_s = Math.sqrt(std_dev_s / std_dev_count);

    // calculate diagram points
    var data = new Array();
    var laptime_min = Math.min(...info.Laptimes) / 1000;
    var laptime_max = Math.max(...info.Laptimes) / 1000;
    for (var t=laptime_min; t <= LaptimeDistributionMaxDelta; ) {

        // stop at longest laptime
        if (t >= laptime_max) {
            var y = 100 * LaptimeDistributionDiagramCalculateGauss(laptime_max, mean_s, std_dev_s)
            data.push({x:laptime_max, y:y})
            break;
        }

        // calculate data
        var y = 100 * LaptimeDistributionDiagramCalculateGauss(t, mean_s, std_dev_s)
        data.push({x:t, y:y})

        // dynamic increment
        if (t < 1) t += 0.05;
        else if (t < 10) t += 0.05;
        else t += 0.05;
    }

    // debug info
    console.log(info.User.Name + ": " +
                "Mean=" + mean_s +
                ", Sigma=" + std_dev_s
                )


    var dataset = {
                    type: "line",
                    label: info.User.Name,
                    backgroundColor: info.User.Color + "22",
                    borderColor: info.User.Color + "ff",
                    fill: true,
                    radius: 0,
                    tension: 0.4,
                    data: data
                  };
    LaptimeDistributionDiagram.data.datasets.push(dataset);
    LaptimeDistributionDiagram.update();
}



function LaptimeDistributionDiagramAddDatasetBar(response) {
    var info = JSON.parse(response);
    var dataset = {
                    type: "bar",
                    label: info.User.Name,
                    backgroundColor: info.User.Color + "ff",
                    borderColor: info.User.Color + "ff",
                    data: info.Laptimes,
                    barPercentage: 20
                  };
    LaptimeDistributionDiagram.data.datasets.push(dataset);
    LaptimeDistributionDiagram.update();
}



function LaptimeDistributionDiagramLoadData(button, type) {
    var user_id = button.getAttribute("userId");
    var session_id = button.getAttribute("sessionId");
    button.style.visibility = "hidden";

    var request_url = "index.php?JsonContent=LaptimeDistributionData&SessionId=" + session_id + "&UserId=" + user_id;
    if (type == 'bar') {
        request_url += "&Laptimes=Buckets"
    } else if (type == 'line') {
        request_url += "&Laptimes=Deltas"
    }
    request_url += "&LaptimeDistributionMaxDelta=" + LaptimeDistributionMaxDelta;
    console.log(request_url);

    var xobj = new XMLHttpRequest();
    xobj.overrideMimeType('application/json');
    xobj.open('GET', request_url, true);
    xobj.onreadystatechange = function () {
        if (xobj.readyState == 4 && xobj.status == '200') {
            // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
            if (type == 'bar') {
                LaptimeDistributionDiagramAddDatasetBar(xobj.responseText);
            } else if (type == 'line') {
                LaptimeDistributionDiagramAddDatasetLine(xobj.responseText);
            }
        }
    };
    xobj.send(null);
}



document.addEventListener('DOMContentLoaded', function () {

    // get canvas element
    var canvas_div = document.getElementById("LaptimeDistributionDiagram");
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
    var ax_x_type = canvas.getAttribute("axisType");
    LaptimeDistributionMaxDelta = parseInt(canvas.getAttribute("maxDelta"));

    // create chart
    LaptimeDistributionDiagram = new Chart(canvas, {
        data: {
            datasets: []
        },
        options:{
            scales: {
                    x: {type: ax_x_type,
                        min: (ax_x_type == 'linear') ? 0 : 0.09,
                        max: LaptimeDistributionMaxDelta,
                        title: {
                            display: true,
                            text: ax_x_title
                        }
                    },
                    y: {
                        type: 'linear',
                        min: 0,
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
            }
        }
    });
})
