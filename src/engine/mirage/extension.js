var plugin = {

    hosts: {'www.mgmresorts.com': true},

    getStartingUrl: function (params) {
        return 'https://www.mgmresorts.com/account/rewards/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
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
        if ($('span[data-testid="card-name-number"]:visible, span[data-testid="tier-meter-balance-label"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('button:contains("Sign in or join")').length > 0 || $('span[data-testid="page-title"]:contains("Sign in or Join")').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.filter($('span[data-testid="card-name-number"]').text());
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Account) != 'undefined')
            && (account.properties.Account !== '')
            && (number === account.properties.Account));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            if (provider.isMobile) {
                const btn =  $('section button:contains("Sign Out")');
                btn.get(0).click();
            }

            const btn = $('span:contains("Welcome to MGM Rewards!")').parent().find('ul li button:contains("Sign Out")');
            if (btn.length) {
                btn.click();
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form.identity__form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
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
            "triggerInput('input[id = \"email\"]', '" + params.account.login + "');\n"
        );
        form.find('button[data-testid="sign-in-or-join"]').get(0).click();

        util.waitFor({
            selector: '#password:visible',
            success: function () {
                browserAPI.log("submitting saved pass");
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
                    "triggerInput('input[id = \"password\"]', '" + params.account.password + "');"
                );

                provider.setNextStep('checkLoginErrors', function () {
                    $('button[data-testid="sign-in"]').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors();
                    }, 10000);
                });
            },
            fail: function () {
                plugin.checkLoginErrors();
            },
            timeout: 30
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div[data-testid="sign-in-error"]:visible, div#email__message:visible, div.identity__alert-text:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete : function (params) {
        browserAPI.log("loginComplete");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.mgmresorts.com/trips/';
            });
            return;
        }

        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        plugin.itLoginComplete(params);
        /*var confNo = params.account.properties.confirmationNumber;
        var counter = 0;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('div.reservation-detail:has(span:contains("' + confNo + '"))').get(0).scrollIntoView({block: "start"});
            if (link.length > 0) {
                clearInterval(toItineraries);
                link.get(0).scrollIntoView({block: "start"});
                plugin.itLoginComplete(params);
            }// if (link.length > 0)
            if (counter > 30) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);*/
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};