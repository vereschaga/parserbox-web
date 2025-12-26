var plugin = {

    hosts: {'www.nh-hotels.com': true},

    getStartingUrl: function (params) {
        return 'https://www.nh-hotels.com/en/discovery/home';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else {
                    if (provider.isMobile) {
                        provider.setNextStep('login', function () {
                            document.location.href = 'https://m.nh-hotels.com/nhrewards/login';
                        });
                        return;
                    }

                    provider.setNextStep('login', function () {
                        document.location.href = 'https://www.nh-hotels.com/en/nhdiscovery/login';
                    });
                    // plugin.login(params);
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

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a:contains("Log out"):visible').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[name = "loginRewardsForm"]').length > 0
            // mobile
            || $('a[href *= "login"]:visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp( $('span:contains("Member Nº:")').text(), /Member Nº:\s*([\d]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            var logout = $('a:contains("Log out")');
            if (logout.length)
                logout.get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        // const btn = $('span[data-target="#m-modal-header-login"], [data-target="#m-modal-header-login"], a[title="Log in"]');
        // browserAPI.log("btn length: " + btn.length);
        //
        // if (btn.length > 0) {
        //     btn.get(0).click();
        // }

        let form;
        form = $('form[name = "loginNHRewardsForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "user"]').val(params.account.login);
            form.find('input[name = "pass"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#btnLoginAccess').get(0).click();
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 2000)
            });
        }
        else {
            form = $('form[name = "loginRewardsForm"]');

            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"], input[id = "login-user"]').val(params.account.login);
            form.find('input[name = "password"], input[id = "login-password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#btnLoginForm, #submit-login, button[type="submit"]').get(0).click();
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 2000)
            });
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.js-error-login.text-color-red:visible, p.error:visible, div.error:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.nh-hotels.com/discovery/my-bookings';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        var link = $('a[href *= "?signature=' + params.account.properties.confirmationNumber + '"]');
        if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function(){
                link.get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.itineraryNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
