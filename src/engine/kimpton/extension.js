var plugin = {


    hosts: {'www.kimptonhotels.com': true, 'gc.synxis.com': true},

    getStartingUrl: function (params) {
        return "https://www.kimptonhotels.com/my-karma/dashboard";
    },

    isMobile: function () {
        return (typeof(api) !== 'undefined') && (typeof(api.getDepDate) === 'function') && (api.getDepDate() instanceof Date);
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href *= "sign-out"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[action *= sign-in]').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        browserAPI.log("Can't determine login state");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('h2.karma-member-name').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("logout");
        if (plugin.isMobile()) {
            browserAPI.log("Mobile");
            api.setNextStep('login', function () {
                $('a:contains("Sign Out")').get(0).click();
            });
        } else {
            provider.setNextStep('login');
            $('a:contains("Sign Out")').get(0).click();
        }
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[action *= sign-in]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "signin[email]"]').val(params.account.login);
            form.find('input[name = "signin[password]"]').val(params.account.password);
            if (plugin.isMobile()) {
                browserAPI.log("Mobile");
                api.setNextStep('checkLoginErrors', function () {
                    $('span:contains("Sign In")').get(0).click();
                });
            } else {
                provider.setNextStep('checkLoginErrors');
                $('span:contains("Sign In")').get(0).click();
            }
        }
        else {
            browserAPI.log("Login form not found");
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function (params) {
        var errors = $('label.error');
        if (errors.length == 0)
            errors = $('p.message');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('itLoginComplete');
            document.location.href = 'https://www.kimptonhotels.com/my-karma/stays';
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
//        var form = $('form#XbeForm');
//        if (form.length > 0) {
//            form.find('input[name = "V55$C1$EmailTextbox"]').val(params.account.login);
//            form.find('input[name = "V55$C1$PasswordTextbox"]').val(params.account.password);
//            provider.setNextStep('checkItLoginErrors');
//            $('#V55_C1_EmailSearchButton').click();
//        }
//        else
//            provider.setError('Form not found');
    },

//    checkItLoginErrors: function (params) {
//        var errors = $('div.ErrorMsg');
//        if (errors.length > 0) {
//            provider.setError(errors.text());
//        }
//        else {
//            var confNo = params.account.properties.confirmationNumber;
//            var select = $("input[value=" + confNo.toUpperCase() + "] ~ input.SelectSearchRez");
//            if (select.length > 0) {
//                provider.setNextStep('itLoginComplete');
//                select.click();
//            }
//            else
//                provider.setError('Itinerary not found');
//        }
//    },

    itLoginComplete: function (params) {
        provider.complete();
    }
}
