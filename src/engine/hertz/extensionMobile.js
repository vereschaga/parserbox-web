var plugin = {
    autologin : {
        url : 'https://www.hertz.com/rentacar/member/login',

        cashbackLinkMobile : false,

        start : function() {
            browserAPI.log('start');
            setTimeout(function() {
                if (plugin.autologin.isLoggedIn())
                    if (plugin.autologin.isSameAccount())
                        plugin.autologin.finish();
                    else
                        plugin.autologin.logout();
                else
                    plugin.autologin.login();
            }, 2000);
        },

        isLoggedIn : function() {
            browserAPI.log('isLoggedIn');
            if ($('.mobiMyAccount, a[href*="/submitLogout.do"]').length) {
                browserAPI.log('logged in');
                return true;
            }
            if ($('form[name="submitLogin"], #loginLink').length) {
                browserAPI.log('not logged in');
                return false;
            }
            provider.setError(util.errorMessages.unknownLoginState);
        },

        isSameAccount : function() {
            browserAPI.log('isSameAccount');
            return (typeof(params.properties) != 'undefined' &&
            typeof(params.properties.Name) != 'undefined' &&
            params.properties.Name != '' &&
            $('.mobiMyAccount:contains("' + params.properties.Name.toUpperCase() + '")').length > 0);
        },

        login : function() {
            browserAPI.log('login');
            var $form = $('form[name="submitLogin"]');
            if ($form.length) {
                $('input[name="loginId"]', $form).val(params.login);
                $('input[name="password"]', $form).val(params.pass);
                return api.setNextStep('checkLoginErrors', function() {
                    $('#loginBtn').click();
                    setTimeout(plugin.autologin.checkLoginErrors, 5000);
                });
            }
            provider.setError(util.errorMessages.loginFormNotFound);
        },

        checkLoginErrors: function () {
            browserAPI.log('checkLoginErrors');
            let $errors = $('div#error-list li, .field-error-list');
            if ($errors.length && util.trim($errors.text()) !== '') {
                api.error($errors.text());
                return;
            }
            this.finish();
        },

        logout : function() {
            api.setNextStep('loadLoginForm', function() {
                document.location.href = '/rentacar/emember/submitLogout.do';
            });
        },

        loadLoginForm : function() {
            api.setNextStep('login', function() {
                document.location.href = plugin.autologin.url;
            });
        },

        finish : function() {
            api.complete();
        }

    }
};
