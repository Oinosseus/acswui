
function enableEditCarClassName() {

    // remove edit button
    var btn = document.getElementById('EnableEditCarClassNameButton');
    btn.parentNode.removeChild(btn);

    // replace label with input
    var lbl = document.getElementById('LabelCarClassName');
    var name = lbl.innerHTML;
    var inp = document.createElement('INPUT');
    inp.setAttribute('type', 'text');
    inp.setAttribute('name', 'CarClassName');
    inp.setAttribute('value', name);
    lbl.parentNode.replaceChild(inp, lbl);
    inp.focus();
    inp.select()
}



function enableEditCarClassDescription() {

    // remove edit button
    var btn = document.getElementById('EnableEditCarClassDescriptionButton');
    btn.parentNode.removeChild(btn);

    // remove html description
    var dsc = document.getElementById('CarClassDescriptionHtml');
    dsc.parentNode.removeChild(dsc);

    // display textarea
    var txt = document.getElementById('CarClassDescriptionMarkdown');
    txt.style.display = "block";
}



// Main()
document.addEventListener('DOMContentLoaded', function () {

    var btn1 = document.getElementById('EnableEditCarClassNameButton');
    if (btn1) {
        btn1.addEventListener('click', enableEditCarClassName);
    }

    var btn2 = document.getElementById('EnableEditCarClassDescriptionButton');
    if (btn2) {
        btn2.addEventListener('click', enableEditCarClassDescription);
    }

    var btn3 = document.getElementById('DeleteCarClassButton');
    if (btn3) {
        btn3.addEventListener('click', function() {
            if (window.confirm("Sure to delete carclass?")) {
                btn3.parentNode.submit();
            }
        })
    }
})


// ----------------------------------------------------------------------------
//                               CarClass Records
// ----------------------------------------------------------------------------
function CarClassRecordsDraw(response) {
    var e = document.getElementById("CarClassRecordsList");
    for (let html of JSON.parse(response)) {
        e.insertAdjacentHTML("beforeend", html);
    }
}


function CarClassLoadRecords(button) {
    button.style.visibility = "hidden";
    var car_class_id = button.getAttribute("carClassId");
    var request_url = "index.php?JsonContent=CarClassRecords&CarClassId=" + car_class_id;
    console.log(request_url);
    var xobj = new XMLHttpRequest();
    xobj.overrideMimeType('application/json');
    xobj.open('GET', request_url, true);
    xobj.onreadystatechange = function () {
        if (xobj.readyState == 4 && xobj.status == '200') {
            // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
            CarClassRecordsDraw(xobj.responseText);
        }
    };
    xobj.send(null);
}
