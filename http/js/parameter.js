function toggleParameterCollectionTabVisibility(collection_key, active_key) {

    console.log("HERE " + collection_key + ", " + active_key);

    var tab_container = document.getElementById('CollectionContainerLevel4ChildContainer' + collection_key);
    for (var i=0; i < tab_container.children.length; ++i) {
        chld = tab_container.children[i]
        chld.style.display = "none";
    }

    var active_tab = document.getElementById('CollectionContainerLevel4ChildContainerTab' + active_key);
    active_tab.style.display = "inline-block";
}


function toggleParamWeatherGraphic(param_key) {

    // retrieve selected weather image
    var select = document.getElementById('ParameterWeatherGraphic' + param_key);
    var weather_img = select.options[select.selectedIndex].getAttribute('img_src');

    // replace new img src
    var img = select.previousElementSibling;
    img.setAttribute('src', weather_img);
}
