var plugin = {
    clearCache: true,

    hosts: {
        'www.ncl.com': true,
        'www.ncl.eu': true,
        'sso.ncl.com': true
    },

    getStartingUrl: function (params) {
        return "https://www.ncl.com/shorex/login";
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (plugin.isLoggedIn()) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
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
        if (
            $('a[href*="/logout"]').length > 0
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if (
            $('.login-main form').length > 0
            || $('div#login input[name = "input_username"]').length > 0
        ) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        // browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp($('div:contains("Latitude Number:")').text(), /Latitude Number:\s*(\d+)/);
        browserAPI.log("number: " + number);
        return (
            (typeof (account.properties) != 'undefined')
            && (typeof (account.properties.MemberNumber) != 'undefined')
            && (account.properties.MemberNumber != '')
            && (number == account.properties.MemberNumber)
        );
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            let baseUrl = util.findRegExp(document.location.href, /^(.+?\/\d+)\/my-account/);
            if (baseUrl) {
                document.location.href = baseUrl + '/logout';
            } else {
                document.location.href = 'https://www.ncl.com/shorex/logout';
            }
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('loadLoginForm2', function () {
            document.location.href = 'https://www.ncl.com/shorex/login';
        });
    },

    loadLoginForm2: function (params) {
        browserAPI.log("loadLoginForm2");
        if (/shorex\/login/.test(document.location.href)) {
            plugin.start(params);
        } else {
            setTimeout(function () {
                provider.setNextStep('start', function () {
                    document.location.href = 'https://www.ncl.com/shorex/login';
                });
            }, 2000);
        }
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('div#login input[name = "input_username"]').closest('form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            let loginInput = form.find('input[name = "input_username"]');
            loginInput.val(params.account.login);
            util.sendEvent(loginInput.get(0), 'input');

            setTimeout(function () {
                let passwordInput = form.find('input[name = "input_password"]');
                passwordInput.val(params.account.password);
                util.sendEvent(passwordInput.get(0), 'input');

                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button[name = "login_btn"]').click();
                });
            }, 2000);
        } else {
            provider.logBody('login');
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        var errors = $('div#error_d:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '') {
            provider.setError(errors.text());
        } else {
            plugin.loginComplete(params);
        }
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (
            typeof (params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId > 0
        ) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        // for debug only
        // browserAPI.log("params: " + JSON.stringify(params));
        setTimeout(function () {
            var confNo = params.account.properties.confirmationNumber;
            var link = util.findRegExp(document.location.href, /^(.+?)\/my-account.*?$/);
            if (link) {
                provider.setNextStep('itLoginComplete', function () {
                    // https://www.ncl.com/fr/en/shorex/216154914/43316639/home
                    document.location.href = link + '/' + confNo + '/home';
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};
