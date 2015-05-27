
function opendialogrisultato() {
    $("#risultato").dialog({modal: true, maxHeight: 600, width: 800, dialogClass: "no-close",
        buttons: [
            {
                text: "Chiudi",
                click: function () {
                    $(this).dialog("close");
                }
            }
        ]});
}

$(document).keydown(function (e) {
    // ESCAPE key pressed
    if (e.keyCode == 27) {
        e.preventDefault();
        jQuery('#risultato').dialog('close');
    }
    if (e.keyCode == 13) {
        e.preventDefault();
        if (currentfunction) {
            $("#adminpanel" + currentfunction).click();
            currentfunction = "";
        }
    }
});

$("#symfonycommand").focusin(function () {
    currentfunction = "symfonycommand";
});
$("#symfonycommand").focusout(function () {
    //currentfunction = "";
});

$("#unixcommand").focusin(function () {
    currentfunction = "unixcommand";
});
$("#unixcommand").focusout(function () {
    //currentfunction = "";
});

jQuery('body')
        .bind(
                'click',
                function (e) {
                    if ($('#risultato').is(':visible') &&
                            jQuery('#risultato').dialog('isOpen')
                            && !jQuery(e.target).is('.ui-dialog, a')
                            && !jQuery(e.target).closest('.ui-dialog').length
                            ) {
                        jQuery('#risultato').dialog('close');
                    }
                }
        );