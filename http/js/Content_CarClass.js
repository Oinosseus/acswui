
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
})
