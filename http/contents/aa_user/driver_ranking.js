'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // register event handler for 'show' checkboxes
    for (let uid of SvgUserIds) {
        let chkbx = document.getElementById("show_" + uid);

        // hide/show graphs when checked
        chkbx.addEventListener('click', function() {
            let chkbx = document.getElementById("show_" + uid);
            let pline = document.getElementById("plot_user_" + uid);

            if (chkbx.checked == true) {
                pline.style.visibility = 'visible';
            } else {
                pline.style.visibility = 'hidden';
            }
        });


        let tr = document.getElementById("table_row_user_" + uid);
        // highlight graphs when hovered
        tr.addEventListener('mouseenter', function() {
            let chkbx = document.getElementById("show_" + uid);
            let pline = document.getElementById("plot_user_" + uid);

            if (chkbx.checked == false) {
                pline.style.visibility = 'visible';
            }
            pline.classList.add("highlight");
        });
        tr.addEventListener('mouseleave', function() {
            let chkbx = document.getElementById("show_" + uid);
            let pline = document.getElementById("plot_user_" + uid);

            if (chkbx.checked == false) {
                pline.style.visibility = 'hidden';
            }
            pline.classList.remove("highlight");
        });
    }
});
