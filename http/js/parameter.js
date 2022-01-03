function toggleParameterCollectionTabVisibility(collection_key, active_key) {

    console.log("HERE " + collection_key + ", " + active_key);

    var tab_container = document.getElementById('ParameterCollectionTabContentContainer' + collection_key);
    for (var i=0; i < tab_container.children.length; ++i) {
        chld = tab_container.children[i]
        chld.style.display = "none";
    }

    var active_tab = document.getElementById('ParameterCollectionTabContainer' + active_key);
    active_tab.style.display = "inline-block";


//     if (chkbx.checked) {
//         value.style.display = "none";
//         inhrt.style.display = "block";
//     } else {
//         value.style.display = "block";
//         inhrt.style.display = "none";
//     }
}
