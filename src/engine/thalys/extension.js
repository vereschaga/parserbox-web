var plugin = {
    // keepTabOpen: true,
    hosts: {
        'www.thalys.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.thalys.com/de/en/my-account/my-tickets';
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
                    if (plugin.isSameAccount(params.account)) {
                        provider.complete();
                    } else {
                        plugin.logout(params);
                    }
                } else {
                    plugin.login(params);
                }
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
        if ($('input#login_page_context-connectpassword').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('li#logout-menu-left-item').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return false;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('beforeStart', function () {
            document.location.href = 'https://www.thalys.com/de/en/logout';
        });
    },

    beforeStart: function (params) {
        browserAPI.log("beforeStart");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId == 0
        ) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.thalys.com/de/en/my-account/my-tickets';
            });
            return;
        }

        var form = $('input#login_page_context-connectpassword').closest('form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            var lines = [
                "var findReact = function (dom) {",
                "    for (var key in dom) {",
                "        if (0 == key.indexOf('__reactInternalInstance$')) {",
                "            return dom[key];",
                "        }",
                "    }",
                "    return null;",
                "};",
                "var r = findReact(document.querySelector('input#login_page_context-connectpassword'));",
                "r._currentElement.props.onChange({target: {name: 'userId', value: '" + params.account.login + "'}});",
                "r._currentElement.props.onChange({target: {name: 'password', value: '" + params.account.password + "'}});"
            ];
            provider.eval(lines.join('\n'));
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[type = "submit"]').click();
            });
            setTimeout(function() {
                plugin.checkLoginErrors(params);
            }, 1000);
        } else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('input#search-pnr').closest('form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            var lines = [
                "var findReact = function (dom) {",
                "    for (var key in dom) {",
                "        if (0 == key.indexOf('__reactInternalInstance$')) {",
                "            return dom[key];",
                "        }",
                "    }",
                "    return null;",
                "};",
                "var r = findReact(document.querySelector('input#search-pnr'));",
                "r._currentElement.props.onChange({target: {name: 'pnr', value: '" + properties.ConfNo + "'}});",
                "r._currentElement.props.onChange({target: {name: 'email_lastname', value: '" + properties.Email + "'}});"
            ];
            provider.eval(lines.join('\n'));
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[type = "submit"]').click();
            });
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 1000);
        } else {
            provider.setError(util.errorMessages.itineraryFormNotFound);
        }
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('p.text-error:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
        } else {
            plugin.loginComplete(params);
        }
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId > 0
        ) {
            provider.setNextStep('itLoginComplete', function () {
                document.location.href = 'https://www.thalys.com/de/en/my-account/my-tickets';
            });
            setTimeout(function () {
                plugin.itLoginComplete(params);
            }, 1000);
            return;
        }
        provider.complete();
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
}
