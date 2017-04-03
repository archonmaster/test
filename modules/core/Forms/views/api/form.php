<script>

    setTimeout(function(){

        if (!window.FormData) return;

        var form        = document.getElementById("{{ $options['id'] }}"),
            msgsuccess  = form.getElementsByClassName("form-message-success").item(0),
            msgfail     = form.getElementsByClassName("form-message-fail").item(0),
            disableForm = function(status) {
                for(var i=0, max=form.elements.length;i<max;i++) form.elements[i].disabled = status;
            },
            success     = function(){
                
                if (msgsuccess) {
                    UIkit.notify($(msgsuccess).text(), {status:'success'});
                } else {
                    alert("@lang('Form submission was successfull.')");
                }

                disableForm(false);
            },
            fail        = function(){
                if (msgfail) {
                    UIkit.notify($(msgfail).text(), {status:'danger'});
                } else {
                    alert("@lang('Form submission failed.')");
                }

                disableForm(false);
            };

        if (msgsuccess) msgsuccess.style.display = "none";
        if (msgfail)    msgfail.style.display = "none";

        form.addEventListener("submit", function(e) {
            var $this = $(this);
            var parent_modal = $this.parents(".uk-modal");
            
            e.preventDefault();            
            
            var valid = true;
            $(this).find("[data-required]").each(function(i, e) {
                e = $(e);
                if (e.val() == "") {
                    e.addClass("uk-form-danger");
                    valid = false;
                }
            });
            if (!valid) return false;
            
            <?php if ($options["recaptcha"]) : ?>
            var recaptcha = grecaptcha.getResponse(RECAPTCHA[$(form).find("[recaptcha]").attr("id")]);
            if (recaptcha == "") {
                UIkit.notify("Пройдите тест на робота", {status:'danger'});
                return false;
            }
            <?php endif; ?>

            if (msgsuccess) msgsuccess.style.display = "none";
            if (msgfail)    msgfail.style.display = "none";

            var xhr = new XMLHttpRequest(), data = new FormData(form);

            xhr.onload = function(){

                if (this.status == 200 && this.responseText!='false') {
                    success();
                    form.reset();
                    <?php if ($options["recaptcha"]) : ?>
                    grecaptcha.reset(RECAPTCHA[$(form).find("[recaptcha]").attr("id")]);
                    <?php endif; ?>
                    if (parent_modal.length > 0) {
                        UIkit.modal(parent_modal).hide();
                    }
                    @if(isset($options["callback_success"]))
                    {{ $options["callback_success"] }}
                    @endif
                } else {
                    fail();
                }
            };

            disableForm(true);

            xhr.open('POST', "@route('/api/forms/submit/'.$name)", true);
            xhr.send(data);

        }, false);

    }, 100);

</script>

<form id="{{ $options["id"] }}" name="{{ $name }}" class="{{ $options["class"] }}" action="@route('/api/forms/submit/'.$name)" method="post" onsubmit="return false;">
<input type="hidden" name="__csrf" value="{{ $options["csrf"] }}">
@if(isset($options["mailsubject"])):
<input type="hidden" name="__mailsubject" value="{{ $options["mailsubject"] }}">
@endif