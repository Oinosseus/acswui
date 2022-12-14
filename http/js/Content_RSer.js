
function enableEditRSerSeasonName() {

    // remove edit button
    var btn = document.getElementById('EnableEditRSerSeasonNameButton');
    btn.parentNode.removeChild(btn);

    // replace label with input
    var lbl = document.getElementById('LabelRSerSeasonName');
    var name = lbl.innerHTML;
    var inp = document.createElement('INPUT');
    inp.setAttribute('type', 'text');
    inp.setAttribute('name', 'RSerSeasonName');
    inp.setAttribute('value', name);
    lbl.parentNode.replaceChild(inp, lbl);
    inp.focus();
    inp.select()
}



// Main()
document.addEventListener('DOMContentLoaded', function () {

    var btn1 = document.getElementById('EnableEditRSerSeasonNameButton');
    if (btn1) {
        btn1.addEventListener('click', enableEditRSerSeasonName);
    }
})
