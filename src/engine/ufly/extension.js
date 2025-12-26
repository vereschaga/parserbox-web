var plugin = {

    hosts: {'www.suncountry.com': true},

    getStartingUrl: function (params) {
        return 'https://www.suncountry.com/';
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
                    if (document.location.href.indexOf('.com/profile') === -1) {
                        provider.setNextStep('start', function () {
                            document.location.href = 'https://www.suncountry.com/profile';
                        });
                        return;
                    }
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
        if ($('button.login-button:visible, button#mobile-login-button:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.logged-in-button-container:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp($('.profile-container .rewards-info').text(), /#\s*(\d+)/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number == account.properties.AccountNumber));
    },

    logout: function (params) {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            var button = $('.logged-in-button-container:visible');
            if (button.length) {
                util.sendEvent(button.get(0), 'click');
                //button.get(0).click();
                button = $('.logged-in-menu button.sign-out');
                if (button.length) {
                    button.get(0).click();
                    setTimeout(function () {
                        plugin.start(params);
                    }, 2000);
                }
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                var btn = $('#label-my-trips');
                if (btn.length) {
                    btn.get(0).click();
                    setTimeout(function () {
                        plugin.getConfNoItinerary(params);
                    }, 2000);
                }
            });
            return;
        }
        // open login form
        var loginOpen = $('button.login-button:visible, button#mobile-login-button:visible');
        if (loginOpen.length) {
            loginOpen.get(0).click();
        }

        if (provider.isMobile) {// prevent loop
            provider.setNextStep('populateLoginForm');
            return;
        }

        plugin.populateLoginForm(params);
    },

    populateLoginForm: function (params) {
        browserAPI.log("populateLoginForm");
        // wait login form
        var form = $('.login-register-container form');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return false;
        }// if (form.length === 0)
        browserAPI.log("submitting saved credentials");
        form.find('input[name="username"]').val(params.account.login);
        form.find('input[name="password"]').val(params.account.password);

        util.sendEvent(form.find('input[name="username"]').get(0), 'input');
        util.sendEvent(form.find('input[name="password"]').get(0), 'input');

        return provider.setNextStep('checkLoginErrors', function () {
            form.find('button.submit-button').get(0).click();
            setTimeout(function () {
                browserAPI.log("force call checkLoginErrors");
                plugin.checkLoginErrors(params);
            }, 5000);
        });
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('.flight-search-widget-container form');
        if (form.length > 0) {
            form.find('input[formcontrolname="lastName"]').val(properties.LastName);
            form.find('input[formcontrolname="reservationCode"]').val(properties.ConfNo);

            util.sendEvent(form.find('input[formcontrolname="lastName"]').get(0), 'input');
            util.sendEvent(form.find('input[formcontrolname="reservationCode"]').get(0), 'input');

            provider.setNextStep('loginComplete', function() {
                form.find('button.continue-button').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('#label-login-combination-error:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};
