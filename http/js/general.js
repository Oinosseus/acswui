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
//                           TableRow/ColumnDelete
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

function toggleTableColumnDelete(input_element) {

    // find parenting label element
    var label = input_element.parentElement;
    while (1) {
        if (label.tagName == "LABEL") break;
        label = label.parentElement;
    }

    // determine column index
    var col_index = 0;
    var previous_sibling = label.parentElement.previousElementSibling;
    while (previous_sibling) {
        previous_sibling = previous_sibling.previousElementSibling;
        ++col_index;
    }

    // get table element
    var table = label.parentElement.parentElement.parentElement;

    // assign css class
    if (input_element.checked) {
        label.classList.add('Checked');

        // apply to all columns
        for (var i=0; i < table.children.length; ++i) {
            var row = table.children[i]
            if (row.tagName == "TR") {
                var column = row.children[col_index];
                column.classList.add('ColumnWillBeDeleted');
            }
        }

    } else {
        label.classList.remove('Checked');

        // apply to all columns
        for (var i=0; i < table.children.length; ++i) {
            var row = table.children[i]
            if (row.tagName == "TR") {
                var column = row.children[col_index];
                column.classList.remove('ColumnWillBeDeleted');
            }
        }
    }
}


// ----------------------------------------------------------------------------
//                           LoadingInProgress
// ----------------------------------------------------------------------------

// element - This element will get the LoadingInProgress style applied (element-Id or element-Object)
// in_progress - True if the style shall be applied, False if shall be removed
function loadingInProgress(element, in_progress) {

    var e;
    if (typeof(element) == typeof("abc")) {
        e = document.getElementById(element);
    } else {
        e = element;
    }

    if (in_progress) {
        e.classList.add("LoadingInProgress");
    } else {
        e.classList.remove("LoadingInProgress");
    }
}
