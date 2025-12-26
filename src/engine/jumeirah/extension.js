var plugin = {

    hosts: {'www.jumeirah.com': true},

    getStartingUrl: function (params) {
        return 'https://www.jumeirah.com/en/login';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params);
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
        var logged = $('a.logged-in-user')
        if (provider.isMobile){
            logged = $('div.mobile-user-card-detail:visible')
        }
        if (logged.length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#account-login-form').length > 0 || $('div.login-link:contains("LOG IN")').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        var number = $('div.card-bottom-section').find('div.content').text()
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && number
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        var logout = $('a.logged-in-user')[0];
        if (provider.isMobile){
            logout = $('button.navbar-toggler')[0];
        }
        logout.click();
        setTimeout(function () {
            provider.setNextStep('loadLoginForm', function () {
                $('div.logout').find('a:contains("LOG OUT")')[0].click();
            });
        },1000);
    },

    loadLoginForm : function (params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        setTimeout(function() {
            var form = $("#account-login-form");
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                // reactjs
                provider.eval(
                    "var setValue = function (name, value) {" +
                    "let input = document.querySelector('input[name = ' + name + ']');" +
                    "let lastValue = input.value;" +
                    "input.value = value;" +
                    "let event = new Event('input', { bubbles: true });" +
                    "event.simulated = true;" +
                    "let tracker = input._valueTracker;" +
                    "if (tracker) {" +
                    "   tracker.setValue(lastValue);" +
                    "}" +
                    "input.dispatchEvent(event);" +
                    "};" +
                    "setValue('userName', '" + params.account.login + "');" +
                    "setValue('password', '" + params.account.password + "');"
                );
                setTimeout(function () {
                    form.find('input[type = "checkbox"]').prop('checked', true);
                }, 1000);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button.account-login-button').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors();
                    }, 2000);
                });

            } else {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        },2000);
    },
    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.fields-empty:visible');
        if (errors.length > 0) {
            provider.setError(errors.text());
        } else {
            plugin.loginComplete(params);
        }
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};