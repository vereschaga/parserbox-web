var plugin = {

    hosts: {
        'www.sceneplus.ca': true,
        'sceneplusb2c.b2clogin.com': true,
        'auth.sceneplus.ca': true,
    },

    cashbackLink: '',

    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.sceneplus.ca/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            $('button span:contains("Sign in")').get(0).click();
        });
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else {
                    plugin.loadLoginForm(params);
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('button span:contains("Sign in")').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('button[aria-label="Account"]').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name = util.filter($('button[aria-label="Account"]').text());
        browserAPI.log("name: " + name);
        return ((typeof (account.properties) !== 'undefined')
            && (typeof (account.properties.Name) !== 'undefined')
            && (account.properties.Name !== '')
            && (0 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.scene.ca/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form[id = "localAccountForm"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // form.find('input[name = "Sign in name"]').val(params.account.login);
        // form.find('input[name = "Password"]').val(params.account.password);
        // angularjs
        provider.eval(
            "function triggerInput(enteredName, enteredValue) {\n" +
            "      const input = document.querySelector(enteredName);\n" +
            "      var createEvent = function(name) {\n" +
            "            var event = document.createEvent('Event');\n" +
            "            event.initEvent(name, true, true);\n" +
            "            return event;\n" +
            "      }\n" +
            "      input.dispatchEvent(createEvent('focus'));\n" +
            "      input.value = enteredValue;\n" +
            "      input.dispatchEvent(createEvent('change'));\n" +
            "      input.dispatchEvent(createEvent('input'));\n" +
            "      input.dispatchEvent(createEvent('blur'));\n" +
            "}\n" +
            "triggerInput('input[name = \"Sign in name\"]', '" + params.account.login + "');\n" +
            "triggerInput('input[name = \"Password\"]', '" + params.account.password + "');"
        );
        provider.setNextStep('checkLoginErrors', function () {
            form.find('#next').get(0).click();

            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.error[style="display: block;"]:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.showFader(' It seems that Scene+ needs to identify this computer before you can update this account. Please follow the instructions on the new tab to get this computer authorized .');
        let counter = 0;
        let login = setInterval(function () {
            browserAPI.log("waiting... " + counter);

            let success = $('span:contains("Good "):visible, a[href="/points"]:visible');
            if (success.length > 0) {
                clearInterval(login);
                provider.logBody("2faSuccess");
                plugin.loginComplete(params);
            }
            
            if (counter > 160) {
                clearInterval(login);
                let questionMessage = $('h2:contains("Check your phone…"):visible');
                if (questionMessage.length) {
                    provider.logBody("2faError");
                    provider.setError(['Where should we send your 2-step verification code?', util.errorCodes.question], true);
                    return true;
                }
            }
            counter++;
        }, 1000);

    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.logBody("loginCompletePage");
        /*if (params.autologin) {
            provider.complete();
            return;
        }*/
        browserAPI.log("Parse account");
        plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);

        let close = $('img[src="/assets/close-x.svg"]');
        if (close.length) {
            close.get(0).click();
        }
        let cookie = $('button#action-button, button#ok-button, button:contains("Accept Cookies")');
        if (cookie.length) {
            cookie.get(0).click();
        }

        setTimeout(function () {
            let counter = 0;
            let loading = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                if ($('#mainContent span:contains("Loading")').length === 0) {
                    clearInterval(loading);
                    plugin.parse(params);
                }
                if (counter > 30) {
                    clearInterval(loading);
                }
                counter++;
            }, 1000);
        }, 2000);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);
        var data = {};

        let name = $('button[aria-label="Account"]').text();
        browserAPI.log("Name: " + name);
        data.Name = name;

        // Balance - PTS
        let balance = util.findRegExp($('a[href="/points"] p').text(), /(\d+)/);
        browserAPI.log("Balance: " + balance);
        data.Balance = balance;

        params.account.properties = data;
        // console.log(params.account.properties);// TODO
        provider.saveProperties(params.account.properties);
        provider.complete();
    }
};