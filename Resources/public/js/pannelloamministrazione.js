
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

function conferma(question) {
    var defer = $.Deferred();
    $('<div></div>')
            .html(question)
            .dialog({
                autoOpen: true,
                modal: true,
                width:'auto',
                title: 'Richiesta conferma',
                dialogClass: "noclose",
                buttons: {
                    "Si": function () {
                        defer.resolve("true");//this text 'true' can be anything. But for this usage, it should be true or false.
                        $(this).dialog("close");
                    },
                    "No": function () {
                        defer.resolve("false");//this text 'false' can be anything. But for this usage, it should be true or false.
                        $(this).dialog("close");
                    }
                },
                close: function () {
                    $(this).dialog('destroy').remove()
                }
            });
    return defer.promise();
}

