var plugin = {

    hosts: {'www.ipic.com': true},

    getStartingUrl: function (params) {
        return 'https://www.ipic.com/account/activity';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                } else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        // var signIn = $('button:contains("Sign In")');
        if ($('form:has(input#emailAddress--LogIn):visible').length > 0) {
            // signIn.click();
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('button:contains("Sign Out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $('span:contains("Member Id") + span').text();
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.MemberNumber) != 'undefined')
            && (account.properties.MemberNumber != '')
            && number
            && (number == account.properties.MemberNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('button:contains("Sign Out")').get(0).click();
            setTimeout(function () {
                plugin.loadLoginForm(params);
            }, 3000);
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
        var form = $('form:has(input#emailAddress--LogIn):visible');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        form.find('#emailAddress--LogIn').val(params.account.login);
        form.find('#password--LogIn').val(params.account.password);

        util.sendEvent(form.find('#emailAddress--LogIn').get(0), 'input');
        util.sendEvent(form.find('#password--LogIn').get(0), 'input');

        provider.setNextStep('checkLoginErrors', function () {
            form.find('button:contains("Log In")').click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 3000);
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.alert-error:visible, div.validation-message:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }

};