$(function(){

    // set initial checkboxes
    $("section input").prop("checked", function(){return $(this).is(".checked")});

    // conflict resolver
    resolveConflict = function(elem){
        var conflict = $(elem).data('conflict');
        if (conflict){
            $.each(conflict, function(){
                var c = $("#ch-"+this);
                if (c.prop("checked")){
                    c.prop("checked", false).next().fadeOut(200).fadeIn(200).fadeOut(200).fadeIn(200).fadeOut(200).fadeIn(200);
                }
            });
        }
    }

    // click on a checkbox
    $("input").change(function(){
        if ($(this).prop("checked")){
            resolveConflict(this);
        }
        $("#result").hide()
    });

    // toggle checkboxes per sat
    $("header a").click(function(e){
        e.preventDefault();
        var $inputs = $(this).parents("section").find("input");
        var toggle = $inputs.not(":checked").length ? true : false;
        $inputs.prop("checked", toggle);
        if (toggle){
            $inputs.each(function(){resolveConflict(this)});
        }
    });

    // submit request
    $("button").click(function(){
        var data = $("input:checked").map(function(){
            return this.id.replace(/ch\-/,"");
        }).get().join(";");
        if (!data || data=="s") return;
        $.ajax(".", {
            beforeSend:     function(x,y){$("#result").hide(); $("code").html(""); $("button").attr({disabled:"disabled"})},
            cache:          false,
            complete:       function(x,y){$("button").removeAttr("disabled")},
            data:           {data:data},
            dataTypeString: "text",
            error:          function(jq,data){$("code").html(textStatus); $("#result").show()},
            success:        function(data){$("code").html(data); $("#result").show()},
            type:           "POST"
        });
    });

});
