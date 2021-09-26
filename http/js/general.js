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
