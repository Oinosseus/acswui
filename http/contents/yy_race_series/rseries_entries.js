'use strict';

function update_img(id_img, id_select, car) {

    // get elements
    var e_img = document.getElementById(id_img);
    var e_sel = document.getElementById(id_select);

    // get skin from slected option
    if (e_sel.selectedIndex == -1)
        return null;
    var skin = e_sel.options[e_sel.selectedIndex].text;

    // update img src
    e_img.src = "acs_content/cars/" + car + "/skins/" + skin + "/preview.jpg";
}
