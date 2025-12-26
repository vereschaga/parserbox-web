var plugin = {

    hosts: {'/\\w+\\.otel\\.com/': true},
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return  'https://www.otel.com/bookings/upcoming/';
    },

    fromCashback: function (params) {
        browserAPI.log("fromCashback");
        plugin.loadLoginForm(params);
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
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
        if ($('div.user-connection__form, form[action *= "login"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('form[action *= "logout"] a, div.header__title:contains("My Trips"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        var name = $('div.dashboard-sidebar__user-name').text();
        browserAPI.log("name: " + name.trim());
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.Name) != 'undefined')
                && (account.properties.Name != ''
                        && name)
                && (name.trim().toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("logout");
        if (provider.isMobile)
            provider.setNextStep('logoutMobile', function () {
                document.location.href = 'https://www.otel.com';
            });
        else {
            provider.setNextStep('loadLoginForm', function () {
                var logout = $('form[action *= "logout"] a');
                logout.get(0).click();
            });
        }
    },

    logoutMobile: function () {
        browserAPI.log("logout");
        $('div.hamburger-menu').click();
        provider.setNextStep('loadLoginForm', function () {
            var logout = $('form[action *= "logout"] a');
            logout.get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('div.user-connection__form, form[action *= "login"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            var email = form.find('input[name = "login"]');
            email.val(params.account.login);
            email.focus().change().blur();
            util.sendEvent(email.get(0), 'input');
            util.sendEvent(email.get(0), 'change');
            form.find('button[name = "login_submit"]').get(0).click();
            setTimeout(function(){
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('input[name = "password"]').val(params.account.password);
                    form.find('button[name = "login_submit"]').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000)
                });
            }, 3000);
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var error = $('div.user-connection__form-errors > div:visible, ul.errorlist:visible');
        if (error.length > 0 && util.trim(error.text()) != "")
            provider.setError(util.trim(error.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                plugin.toItineraries(params);
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var link = $('a[href*="/voucher/' + confNo + '"]:contains("Print Voucher")').prev('a[href*="/bookings/"]');
        if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function () {
                link.removeAttr('target').get(0).click();
            });
        } else {
            provider.setError(util.errorMessages.itineraryNotFound);
        }
    },

    itLoginComplete: function (params) {
        provider.complete();
    }
};
