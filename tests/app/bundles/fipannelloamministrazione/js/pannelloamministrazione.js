
function opendialogrisultato() {
    $("#risultato").dialog(
        {modal: true, maxHeight: 600, width: 800, dialogClass: "no-close",
            buttons: [
            {
                text: "Chiudi",
                click: function () {
                    $(this).dialog("close");
                }
            }
            ]}
    );
}

$("body").on(
    "keydown", function (e) {
        switch (e.which) {
        case 27:
            {// ESCAPE key pressed
                e.preventDefault();
                //e.stopPropagation();
                if ($("#risultato").is(":visible")) {
                    jQuery('#risultato').dialog('close');
                }
                break;
            }
            case 13:
                {// ENTER key pressed
                    if (currentfunction == "dialogconferma") {
                        //Fa il suo normalmente
                    } else {
                        if (currentfunction) {
                            e.preventDefault();
                            //e.stopPropagation();
                            $("#adminpanel" + currentfunction).click();
                            currentfunction = "";
                        }
                    }
                        break;
            }
            default:
                return;
        }
    }
);

//Per gestire l'enter
$("#symfonycommand").focusin(
    function () {
        currentfunction = "symfonycommand";
    }
);

$("#unixcommand").focusin(
    function () {
        currentfunction = "unixcommand";
    }
);

$("#bundlename").focusin(
    function () {
        currentfunction = "";
    }
);
$("#entityform").focusin(
    function () {
        currentfunction = "";
    }
);
$("#entityfile").focusin(
    function () {
        currentfunction = "";
    }
);
$("#entitybundle").focusin(
    function () {
        currentfunction = "";
    }
);

$("#unixcommand").focusout(
    function () {
        //currentfunction = "";
    }
);

jQuery('body')
        .bind(
            'click',
            function (e) {
                if ($('#risultato').is(':visible') 
                    && jQuery('#risultato').dialog('isOpen')
                    && !jQuery(e.target).is('.ui-dialog, a')
                    && !jQuery(e.target).closest('.ui-dialog').length
                ) {
                    jQuery('#risultato').dialog('close');
                }
            }
        );

function conferma(question) {
    currentfunction = "dialogconferma";
    var defer = $.Deferred();
    $('<div></div>')
            .html(question)
            .dialog(
                {
                    autoOpen: true,
                    modal: true,
                    width: 'auto',
                    title: 'Richiesta conferma',
                    dialogClass: "noclose",
                    open: function () {
                        $(this).parents('.ui-dialog-buttonpane button:eq(0)').focus();
                    },
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
                        $(this).dialog('destroy').remove();
                    }
                }
            );
            return defer.promise();
}
