'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // register event handler for 'show' checkboxes
    for (let uid of SvgUserIds) {
        let chkbx = document.getElementById("show_" + uid);
        if (chkbx == null) continue;

        // hide/show graphs when checked
        chkbx.addEventListener('click', function() {
            let chkbx = document.getElementById("show_" + uid);
            let pline = document.getElementById("plot_distribution_user_" + uid);

            if (chkbx.checked == true) {
                console.log("VISIBLE uid=" + uid);
                pline.style.visibility = 'visible';
            } else {
                console.log("HIDDEN uid=" + uid);
                pline.style.visibility = 'hidden';
            }
        });

        // highlight graphs when hovered
        let tr = document.getElementById("table_row_user_" + uid);
        tr.addEventListener('mouseenter', function() {
            let chkbx = document.getElementById("show_" + uid);
            let pline = document.getElementById("plot_distribution_user_" + uid);

            if (chkbx.checked == false) {
                pline.style.visibility = 'visible';
            }
            pline.classList.add("highlight");
        });
        tr.addEventListener('mouseleave', function() {
            let chkbx = document.getElementById("show_" + uid);
            let pline = document.getElementById("plot_distribution_user_" + uid);

            if (chkbx.checked == false) {
                pline.style.visibility = 'hidden';
            }
            pline.classList.remove("highlight");
        });
    }
});
