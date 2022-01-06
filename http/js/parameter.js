function toggleParameterInheritance(key) {
    var chkbx = document.getElementById('ParameterInheritValueCheckbox_' + key);
    var value = document.getElementById('ParameterValueInput_' + key);
    var inhrt = document.getElementById('ParameterValueInherited_' + key);

    if (chkbx.checked) {
        value.style.display = "none";
        inhrt.style.display = "block";
    } else {
        value.style.display = "block";
        inhrt.style.display = "none";
    }

    // set inherited style
    var param_label = document.getElementById('ParameterContainerLabel' + key);
    var param_unit = document.getElementById('ParameterContainerUnit' + key);
    var param_ichkbx = document.getElementById('ParameterContainerDerivedCheckbox' + key);
    if (chkbx.checked) {
        param_label.classList.add('ParameterIsInherited');
        if (param_unit) param_unit.classList.add('ParameterIsInherited');
        param_ichkbx.classList.add('ParameterIsInherited');
    } else {
        param_label.classList.remove('ParameterIsInherited');
        if (param_unit) param_unit.classList.remove('ParameterIsInherited');
        param_ichkbx.classList.remove('ParameterIsInherited');
    }
}


function toggleParameterAccessability(key, derived_accessability) {
    var input = document.getElementById('ParameterAccessability_' + key);
    var hdden = document.getElementById('ParameterDerivedAccessabilityHidden_' + key);
    var visible = document.getElementById('ParameterAccessabilityVisible_' + key);
    var editable = document.getElementById('ParameterAccessabilityEditable_' + key);

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


function toggleParameterCollectionTabVisibility(collection_key, active_key) {

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
