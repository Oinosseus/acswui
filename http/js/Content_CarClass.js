
function allowEditCarClassName() {

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

document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('EnableEditCarClassNameButton');
    if (btn) {
        btn.addEventListener('click', allowEditCarClassName);
    }
})
