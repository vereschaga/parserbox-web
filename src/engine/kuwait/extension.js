var plugin = {

    hosts: {
        '/\\w+\\.frequentflyer\\.aero/': true,
        'kuwaitairways.com': true,
        'www.kuwaitairways.com': true
    },

    getStartingUrl: function (params) {
        return  'https://oasisclub.frequentflyer.aero/pub/#/main/not-authenticated/';
    },

    loadLoginForm: function (params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log('waiting... ' + counter);
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
            }
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log('isLoggedIn');
        if ($('#logoutBtn').length > 0) {
            return true;
        } else
        if ($('h2:contains("Login to your Oasis Club Account")').length > 0) {
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        var name = $('#memberFirstNameText').text() + ' ' + $('#memberLastNameText').text();
        return ((typeof (account.properties) !== 'undefined')
                && (typeof (account.properties.Name) !== 'undefined')
                && (account.properties.Name !== '' && name)
                && (name.trim().toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            var logout = $('#logoutBtn');
            if (logout.length > 0)
                logout.get(0).click();
            else
                provider.setError('Can not make logout');
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId == 0
        ) {
            provider.setNextStep('getConfNoItinerary', function() {
                document.location.href = 'https://www.kuwaitairways.com/en/manage-booking';
            });
            return;
        }

        var form = $('form[name="loginForm"]');
        if (form.length > 0) {
            form.find('input#username').val(params.account.login);
            form.find('input#password').val(params.account.password);

            // angularjs
            provider.eval('var scope = angular.element(document.querySelector(\'form[name="loginForm"]\')).scope();'
                    + 'scope.vm.login("login", "' + params.account.login + '", "' + params.account.password + '");'
                    );

            plugin.loginCheckErrors(params);
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    loginCheckErrors: function (params) {
        browserAPI.log('loginCheckErrors');
        var counter = 0;
        var start = setInterval(function () {
            var error = $('#alert-message');
            if (error.length > 0 && util.trim(error.text()) !== '') {
                clearInterval(start);
                provider.setError(util.trim(error.text()));
            } else
            if (counter > 10) {
                clearInterval(start);
                if ($('#logoutBtn:contains("Logout")').length > 0) {
                    provider.complete();
                } else {
                    provider.setError(util.trim(error.text()));
                }
            }
            counter++;
        }, 400);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form#aspnetForm');
        if (form.length > 0) {
            form.find('input[name = "bookref2"]').val(properties.ConfNo);
            form.find('input[name = "lastname2"]').val(properties.LastName);
            provider.setNextStep('itLoginComplete', function () {
                form.find('input#btnMngBooking').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};
