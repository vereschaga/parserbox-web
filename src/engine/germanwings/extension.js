var plugin = {

    hosts: {'www.eurowings.com': true},

    getStartingUrl: function (params) {
        return 'https://www.eurowings.com/skysales/BoomerangSearch.aspx?culture=en-GB';
    },

    start: function (params) {
        browserAPI.log("start");
        setTimeout(function () {
            if (plugin.isLoggedIn()) {
                if (plugin.isSameAccount(params.account)) {
                    provider.complete();
                } else
                    plugin.logout(params);
            } else
                plugin.login(params);
        }, 3000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        var logout = $('a[href *= logout]:visible');
        if ($('form[action = "MySettingsBonus.aspx"]:visible').length > 0 && logout.length == 0 || $('form#SkySales, span:contains("Log in"):visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (logout.length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('div:has(p:contains("Your Boomerang Club number:")) + div > p').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.BoomerangClubNo) != 'undefined')
            && (account.properties.BoomerangClubNo != '')
            && (number == account.properties.BoomerangClubNo));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a.logoutLink').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#SkySales');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "LoginViewLoginControlMember$FIRST_INPUT_CONTROL_GWUser"]').val(params.account.login);
            form.find('input[name = "LoginViewLoginControlMember$SECOND_INPUT_CONTROL_GWUser"]').val(params.account.password);
            form.find('input[name = "LoginViewLoginControlMember$SECOND_INPUT_CONTROL_GWUser"]').closest('.input-block').addClass('focus active');

            // IE workaround
            form.find('input[name = "__EVENTTARGET"]').val('LoginViewLoginControlMember$ButtonSubmit');
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[name *= "$ButtonSubmit"]').get(0).click();
            });
        }
        else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('span#ui-dialog-title-systemErrorMessage,span:contains("Username and password do not match")');
        if (errors.length > 0 && '' != util.trim(errors.text()))
            provider.setError(errors.text());
        else
            provider.complete();
    }
};
