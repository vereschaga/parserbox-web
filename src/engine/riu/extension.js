var plugin = {

    hosts: {'www.riu.com': true},

    getStartingUrl: function (params) {
        return 'https://www.riu.com/en/riu-class/index.jsp';
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
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else {
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
        if ($('strong:contains("Log in"):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#rcUser-name:visible').length > 0) {
            browserAPI.log("LoggedIn");
            $('#rcUser-name').click();
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // const number = util.findRegExp($('p:contains("Riu Class -")').text(), /-\s*(\d+)/);
        const number = $('span.number-rc').text();
        browserAPI.log("number: " + number);
        return typeof account.properties !== 'undefined'
            && typeof account.properties.Number !== 'undefined'
            && account.properties.Number !== ''
            && number === account.properties.Number;
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            $('#logout').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");

        function openLoginForm() {
            browserAPI.log("Trying to open login form...");            
            // open login form
            let signIn = $('strong:contains("Log in"), #dialog button[name="buttonName"]:contains("Log in"), button[name="buttonName"]:contains("Log in"), button[name="Login"]');
            browserAPI.log('signIn.length: ' + signIn.length);
            provider.logBody("openLoginForm");
            if (signIn.length) {
                signIn.get(0).click();
            }
        }

        openLoginForm();

        // wait login form
        let counter = 0;

        let login = setInterval(function () {
            const input =  $('#login_input_input');
            browserAPI.log("waiting... " + counter);
            if (input.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");

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
                    "triggerInput('input[id = \"riu-class-number\"], input[id = \"login_input_input\"]', '" + params.account.login + "');\n" +
                    "triggerInput('input[id = \"riu-class-password\"], input[id = \"password_input_input\"]', '" + params.account.password + "');"
                );

                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(function () {
                        $('button[name="buttonName"][type="submit"]').click();

                        setTimeout(function () {
                            plugin.checkLoginErrors(params)
                        }, 10000)
                    }, 500)
                });
            } else if (input.length == 0 && counter == 10) {
                openLoginForm();
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
        const errors = $('p.u-color-danger:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId > 0
        ) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.riu.com/en/riu-class/my-riuclass/mis-reservas/index.jsp';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        util.waitFor({
            selector: 'span[ng-bind = "item.codigo"]:contains("' + confNo + '")',
            success: function(elem) {
                var link = elem.closest('div.row').find('a:contains("Check details")');
                if (link.length > 0) {
                    provider.setNextStep('itLoginComplete', function() {
                        link.get(0).click();
                    });
                    setTimeout(function() {
                        plugin.itLoginComplete(params);
                    }, 2000);
                } else {
                    provider.setError(util.errorMessages.itineraryNotFound);
                }
            },
            fail: function() {
                provider.setError(util.errorMessages.itineraryNotFound);
            }
        });
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};