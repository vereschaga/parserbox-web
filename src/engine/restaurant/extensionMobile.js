var plugin = {
    autologin : {
        url : 'https://mobile.restaurant.com/Authenticate/signin?redirecturl=https%3A%2F%2Fmobile.restaurant.com%2FAccount%2FMyAccount',

        start : function() {
            browserAPI.log('start');
            // form is always displayed, simply authorization
            this.login();
        },

        login : function() {
            browserAPI.log('login');
            var $form = $('#signInForm');
            if ($form.length) {
                $('#Email', $form).val(params.login);
                $('#Password', $form).val(params.pass);
                return api.setNextStep('checkLoginErrors', function() {
                    $('input[type="submit"]').click();
                    setTimeout(function() {
                        plugin.autologin.checkLoginErrors();
                    }, 3000);
                });
            }
            api.setError(util.errorMessages.loginFormNotFound);
        },

        checkLoginErrors : function() {
            browserAPI.log('checkLoginErrors');
            // error in alert(), check redirect
            if (-1 != location.href.indexOf('Account/MyAccount'))
                api.complete();
            else
                api.error('The email address/or password is incorrect');
        }
    }
};
