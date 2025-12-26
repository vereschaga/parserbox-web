var plugin = {

    hosts : {
        'ffhy.loyaltyplus.aero' : true,
        'uzairways.com'         : true,
        'www.uzairways.com'     : true
    },

    getStartingUrl : function (params) {
        return 'http://ffhy.loyaltyplus.aero/loyalty/home.seam';
    },

    start : function (params) {
        browserAPI.log('start');
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account)) {
                provider.complete();
            } else {
                plugin.logout(params);
            }

        } else {
            plugin.login(params);
        }
    },

    isLoggedIn : function () {
        browserAPI.log('isLoggedIn');
        if ($('#login').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }

        if ($('#logoutmenu').length) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }

        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount : function (account) {
        browserAPI.log('isSameAccount');
        var number = util.findRegExp($('p:contains("Membership #:")', '#summary_left').text(), /Membership #:\s*([^<]+)/i);

        return (
            'undefined' != typeof account.properties
            && 'undefined' != typeof account.properties.Membership
            && '' != account.properties.Membership
            && number == account.properties.Membership
        );
    },

    logout : function () {
        browserAPI.log('logout');
        provider.setNextStep('start', function () {
            document.location.href = 'http://ffhy.loyaltyplus.aero/loyalty/main.seam?actionOutcome=logoff';
        });
    },

    login : function (params) {
        browserAPI.log('login');
        var $form = $('#login');
        if ($form.length) {
            $('input[name="login:usernameDecorate:username"]', $form).val(params.account.login);
            $('input[name="login:passwordDecorate:password"]', $form).val(params.account.password);
            $('select[name="login:langDecorate:lang"]').val('en');
            $('input[name="login:login"]', $form).val('Account Login');

            provider.setNextStep('checkLoginErrors', function () {
                browserAPI.log('login: submit');
                $('input[name="login:login"]', $form).click();
            });

        } else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors : function () {
        browserAPI.log('checkLoginErrors');
        var $errors = $('div.errors li');
        if ($errors.length) {
            provider.setError($errors.text());
        } else {
            provider.complete();
        }
    }

};