var plugin = {

    hosts: {'preferredhotelgroup.com': true, 'preferredhotels.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://preferredhotels.com/iprefer/members/update-profile';
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
        var login = $('.login-name-container:visible .login-name');
        if (login.length)
            login.get(0).click();
        if ($('a[href *= "/iprefer/logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#login:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('span:contains("Member Number:") > strong').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.MemberIPreferID) != 'undefined')
            && (account.properties.MemberIPreferID !== '')
            && (number === account.properties.MemberIPreferID));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            var logout = $('a[href *= "/iprefer/logout"]');
            if (logout.length) {
                logout.get(0).click();
                setTimeout(function () {
                    logout = $('button:contains("Logout"):visible');
                    if (logout.length) {
                        logout.get(0).click();
                    }
                }, 1000);
            }
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        // wait login form
        var counter = 0;
        var login = setInterval(function () {
            var form = $('form#login:visible');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "ip-login-email"]').val(params.account.login);
                form.find('input[name = "ip-login-password"]').val(params.account.password);

                util.sendEvent(form.find('input[name = "ip-login-email"]').get(0), 'input');
                util.sendEvent(form.find('input[name = "ip-login-email"]').get(0), 'blur');
                util.sendEvent(form.find('input[name = "ip-login-email"]').get(0), 'change');
                util.sendEvent(form.find('input[name = "ip-login-email"]').get(0), 'click');

                util.sendEvent(form.find('input[name = "ip-login-password"]').get(0), 'input');
                util.sendEvent(form.find('input[name = "ip-login-password"]').get(0), 'blur');
                util.sendEvent(form.find('input[name = "ip-login-password"]').get(0), 'change');
                util.sendEvent(form.find('input[name = "ip-login-password"]').get(0), 'click');

                // reactjs
                provider.eval(
                    "function triggerInput(selector, enteredValue) {\n" +
                    "      let input = document.querySelector(selector);\n" +
                    "      input.dispatchEvent(new Event('focus'));\n" +
                    "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
                    "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
                    "      nativeInputValueSetter.call(input, enteredValue);\n" +
                    "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
                    "      input.dispatchEvent(inputEvent);\n" +
                    "}\n" +
                    "triggerInput('input[name = \"ip-login-email\"]', '" + params.account.login + "');\n" +
                    "triggerInput('input[name = \"ip-login-password\"]', '" + params.account.password + "');"
                );

                setTimeout(function () {
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('button:contains("Log In")').get(0).click();
                    });
                }, 4000);

            }
            if (counter > 20) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('p.text-danger.error:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};
