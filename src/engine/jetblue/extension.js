var plugin = {

    hosts: {
        'myflights.jetblue.com': true,
        'book.jetblue.com': true,
        'trueblue.jetblue.com': true,
        'www.jetblue.com': true,
        'mobile.jetblue.com': true
    },

    getStartingUrl: function (params) {
        return 'https://trueblue.jetblue.com/dashboard';
    },

    loadLoginForm: function () {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('start', function () {
            setTimeout(function () {
                document.location.href = plugin.getStartingUrl();
            }, 5000);
        });
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
                provider.logBody("loginPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log('isLoggedIn');
        if ($('input[name="identifier"]:visible').length > 0) {
            browserAPI.log('not LoggedIn');
            return false;
        }
        if ($('span:contains("#"):visible + span.value').text().length > 0) {
            browserAPI.log('loggedIn');
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $('span:contains("#"):visible + span.value').text();
        browserAPI.log("number: " + number);

        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log('logout');
        let profile = $('a.profile-container');
        if (profile.length) {
            profile.get(0).click();
            setTimeout(function () {
                provider.setNextStep('loadLoginForm', function () {
                    $('a[data-qaid="signOut"]:contains("Sign Out")').get(0).click();
                });
            }, 500);
        }
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form[action="/signin"]');

        if (form.length ===  0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        let login = form.find('input[name="identifier"]');
        login.val(params.account.login);
        util.sendEvent(login.get(0), 'click');
        util.sendEvent(login.get(0), 'input');
        util.sendEvent(login.get(0), 'blur');
        util.sendEvent(login.get(0), 'change');
        let pass = form.find('input[name="credentials.passcode"]');
        pass.val(params.account.password);
        util.sendEvent(pass.get(0), 'click');
        util.sendEvent(pass.get(0), 'input');
        util.sendEvent(pass.get(0), 'blur');
        util.sendEvent(pass.get(0), 'change');

        provider.setNextStep('checkLoginErrors', function () {
            form.find('input[value="Sign in"]').click();
            setTimeout(function () {
                plugin.autologin.checkLoginErrors();
            }, 3000);
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('.o-form-error-container.o-form-has-errors:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(util.trim(errors.text()));
        else
            provider.complete();
    },

};
