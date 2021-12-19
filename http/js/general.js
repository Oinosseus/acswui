// ----------------------------------------------------------------------------
//                           HoverPreviewImage
// ----------------------------------------------------------------------------

document.addEventListener('DOMContentLoaded', function () {
    for (let e of document.getElementsByClassName('HoverPreviewImage')) {

        // replace by hover image
        e.addEventListener('mouseover', function() {
            var src = e.getAttribute('src');
            src = src.replace(".png", ".hover.png");
            e.setAttribute('src', src);
        });

        // replace by normal image
        e.addEventListener('mouseout', function() {
            var src = e.getAttribute('src');
            src = src.replace(".hover.png", ".png");
            e.setAttribute('src', src);
        });
    }
})



// ----------------------------------------------------------------------------
//                           TableRowDelete
// ----------------------------------------------------------------------------

function toggleTableRowDelete(input_element) {

    // find parenting tr element
    var tr = input_element.parentElement;
    while (1) {
        if (tr.tagName == "TR") break;
        tr = tr.parentElement;
    }

    // find parenting label element
    var label = input_element.parentElement;
    while (1) {
        if (label.tagName == "LABEL") break;
        label = label.parentElement;
    }

    // assign css class
    if (input_element.checked) {
        tr.classList.add('RowWillBeDeleted');
        label.classList.add('Checked');
    } else {
        tr.classList.remove('RowWillBeDeleted');
        label.classList.remove('Checked');
    }
}



// ----------------------------------------------------------------------------
//                             Parameter Scripts
// ----------------------------------------------------------------------------

function toggleParameterInheritance(key_snake) {
    var chkbx = document.getElementById('ParameterInheritValueCheckbox_' + key_snake);
    var value = document.getElementById('ParameterValueInput_' + key_snake);
    var inhrt = document.getElementById('ParameterValueInherited_' + key_snake);

    if (chkbx.checked) {
        value.style.display = "none";
        inhrt.style.display = "block";
    } else {
        value.style.display = "block";
        inhrt.style.display = "none";
    }
}

function toggleParameterAccessability(key_snake, derived_accessability) {
    var input = document.getElementById('ParameterAccessability_' + key_snake);
    var hdden = document.getElementById('ParameterDerivedAccessabilityHidden_' + key_snake);
    var visible = document.getElementById('ParameterAccessabilityVisible_' + key_snake);
    var editable = document.getElementById('ParameterAccessabilityEditable_' + key_snake);

    var next_accessability = parseInt(input.value) + 1;
    if (next_accessability > derived_accessability) {
        next_accessability = 0;
    }

    if (next_accessability == 1) {  // set to visible
        input.value = 1;
        hdden.style.display = "none";
        visible.style.display = "block";
        editable.style.display = "none";
    } else if (next_accessability == 2) {  // set to editable
        input.value = 2;
        hdden.style.display = "none";
        visible.style.display = "none";
        editable.style.display = "block";
    } else {  // set to hidden
        input.value = 0;
        hdden.style.display = "block";
        visible.style.display = "none";
        editable.style.display = "none";
    }
}
