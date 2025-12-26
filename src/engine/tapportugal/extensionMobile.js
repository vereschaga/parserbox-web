var plugin = {
    hosts: {'www.flytap.com': true, 'book.flytap.com': true},
    mobileUserAgent: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Safari/537.36",
    autologin: {
        url: "https://www.flytap.com/en-us/client-area?v=m",
        // сброс кэша
        clearCache: true,

        start: function (params) {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.autologin.isLoggedIn();
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.autologin.isSameAccount(params.account))
                            provider.autologin.complete();
                        else
                            plugin.autologin.logout();
                    } else {
                        provider.setNextStep('login', function () {
                            browserAPI.log("loadLoginForm");
                            document.location.href = 'https://www.flytap.com/en-us/login';
                        });
                    }
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
            browserAPI.log("isLoggedIn");
            if ($('a.user[href *= login]').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('a.link-logout, js-navigation-logout').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            return null;
        },

        isSameAccount: function (account) {
            browserAPI.log("isSameAccount");
            var clientNumber = util.findRegExp($('div.client-number div.js-profile-tp').text(), /(\d+)/);
            var name = util.findRegExp($('h1.heading-tap--1').text(), /My \s*([^<]+)/i);

            return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.Name) != 'undefined')
                && (account.properties.Name != '')
                && name
                && (name.toLowerCase() == account.properties.Name.toLowerCase()));

            // return ((typeof (account.properties) != 'undefined')
            //     && (typeof (account.properties.AccountNumber) != 'undefined')
            //     && (account.properties.AccountNumber != '')
            //     && clientNumber
            //     && (clientNumber.toLowerCase() == account.properties.Name.toLowerCase()));
        },

        logout: function () {
            browserAPI.log("logout");
            provider.setNextStep('start', function () {
                $('a.link-logout, js-navigation-logout').click();
            });
        },

        login: function (params) {
            browserAPI.log("login");
            var form = $('form#js-login-account');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input#login-user-account').val(params.account.login);
                form.find('input#login-pass-account').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button#login-save-account-submit').get(0).click();
                });
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var errorList = $('li.error-item:visible');
            var error = [];
            if (errorList.length > 0) {
                errorList.each(function () {
                    error.push($(this).text());
                });
            }
            if (errors.length > 0)
                provider.setError(error.toString());
            else
                provider.complete();
        }

    }
};
