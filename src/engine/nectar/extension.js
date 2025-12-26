var plugin = {
    hideOnStart: true,
    // keepTabOpen: true,//todo

    hosts: {
        'www.nectar.com': true,
        'account.sainsburys.co.uk': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.nectar.com/account';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log('Current URL: ' + document.location.href);
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("Browser -> " + JSON.stringify(util.detectBrowser()));
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null && counter > 3) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else
                    plugin.login(params);
            }// if (isLoggedIn !== null)

            if (isLoggedIn === null && counter > 15) {
                provider.logBody("lastPage");
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }

            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if (
            $('.collectorIdFormContainer:visible').length > 0
            || $('h3:contains("Enter your Nectar card number")').length > 0
            || $("#loginForm:visible").length > 0
            || $('button:contains("Sign in")').length > 0
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.accountOption:contains("Log out"):visible, .accountPage__logoutButton:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = $('.nectarCard_cardNumber, .accountPage__card').text();
        number = number.replace(/\s/g, '');
        browserAPI.log("number: " + number);
        return (typeof (account.properties) != 'undefined')
               && (typeof (account.properties.Number) != 'undefined')
               && (account.properties.Number !== '')
               && number
               && number.indexOf(account.properties.Number) !== -1;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            let logout = $('.accountOption:contains("Log out"):visible, .accountPage__logoutButton:visible');
            if (logout.length) {
                logout.click();
                setTimeout(function () {
                    logout = $('.confirmLogoutModal button:contains("Log out"):visible');
                    logout.click();
                }, 1000);
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");

        plugin.passwordForm(params);
        return;

        let form = $("#collectorId:visible").closest("form:visible");
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
        // reactjs
        provider.eval(
            "var FindReact = function (dom) {" +
            "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
            "        return dom[key];" +
            "    }" +
            "    return null;" +
            "};" +
            "FindReact(document.getElementById('collectorId')).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});"
        );
        provider.setNextStep('passwordForm', function () {
            form.find('button:contains("Continue")').click();

            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000);
        });
    },

    passwordForm: function (params) {
        browserAPI.log('passwordForm');
        let form = $("#loginForm:visible");
        if (form.length > 0) {

            if (
                params.account.login2 == null || !/@/.test(params.account.login2)
            ) {
                provider.setError(["To update this Nectar account you need to fill in the ‘Email’ field and update your password to your ID password. To do so, please click the ‘Edit’ button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", util.errorCodes.providerError]);
                return;
            }

            // reactjs
            provider.eval(
                "function triggerInput(enteredName, enteredValue) {\n" +
                "  const input = document.getElementById(enteredName);\n" +
                "  const lastValue = input.value;\n" +
                "  input.value = enteredValue;\n" +
                "  const event = new Event(\"input\", { bubbles: true });\n" +
                "  const tracker = input._valueTracker;\n" +
                "  if (tracker) {\n" +
                "    tracker.setValue(lastValue);\n" +
                "  }\n" +
                "  input.dispatchEvent(event);\n" +
                "}\n" +
                "triggerInput('username', '" + params.account.login2 + "');\n" +
                "triggerInput('password', '" + params.account.password + "')"
            );
            // provider.eval(
            //     "var input = document.querySelector('#password');"+
            //     "input.dispatchEvent(new Event('focus'));\n" +
            //     "input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));"+
            //     "var nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;"+
            //     "nativeInputValueSetter.call(input, \"" + params.account.password + "\");" +
            //     "var inputEvent = new Event(\"input\", { bubbles: true });" +
            //     "input.dispatchEvent(inputEvent);"
            // );
            return provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
                    form.find('button:contains("Log in")').click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 4000);
                }, 500);
            });
        }

        let password = $("#nectarPassword:visible");
        if (password.length === 0) {
            browserAPI.log('Current URL: ' + document.location.href);
            provider.logBody("passwordFormNotFoundPage");

            // todo: debug
            // Account #
            $.ajax({
                type: 'GET',
                url: 'https://www.nectar.com/customer-management-api/customer/card',
                async: false,
                headers: headers,
                dataType: 'json',
                success: function (response) {
                    browserAPI.log("card: " + JSON.stringify(response));
                    // Number
                    data.Number = response.number;
                    browserAPI.log('Number: '+ data.Number);
                },
                error: function (response) {
                    browserAPI.log("error card: " + JSON.stringify(response));
                }
            });

            if (plugin.isLoggedIn(params)) {
                plugin.checkLoginErrors(params);
                return;
            }

            provider.setError(util.errorMessages.passwordFormNotFound);
            return;
        }
        // reactjs
        provider.eval(
            "var FindReact = function (dom) {" +
            "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
            "        return dom[key];" +
            "    }" +
            "    return null;" +
            "};" +
            "FindReact(document.getElementById('nectarPassword')).onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
        );
        provider.setNextStep('checkLoginErrors', function () {
            password.closest('.formContainer, form').find('button:contains("Log in securely")').click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 4000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        provider.logBody("checkLoginErrorsPage");

        let errors = $('.fieldError:visible');
        // new form
        if (errors.length === 0) {
            errors = $('div[data-testid="notification-message"]:visible');
        }

        if (errors.length === 0) {
            errors = $('p:contains("For security reasons, your account is currently blocked."):visible');
        }

        if (errors.length > 0) {
            let message = errors.text();
            browserAPI.log("[Error]: " + message);

            if (
                /Sorry, that wasn't the right card number and\/or password\. Please check and try again\./.test(message)
                || /This Nectar card is not yet registered to New Nectar or password is incorrect\./.test(message)
                || /That email or password doesn’t look right\./.test(message)
            ) {
                provider.setError([message, util.errorCodes.invalidPassword], true);
                return;
            }

            if (/For security reasons, your account is currently blocked\./.test(message)) {
                provider.setError([message, util.errorCodes.lockout], true);
                return;
            }

            provider.complete();
            return;
        }

        if ($('input[data-testid="OTP_FIELD"]:visible').length > 0) {
            if (!provider.isMobile) {
                if (params.autologin)
                    provider.setError(['It seems that Nectar needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your Nectar authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
                else {
                    provider.setError(['It seems that Nectar needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Nectar authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
                }

                return;
            }

            provider.command('show', function () {
                provider.showFader('Message from AwardWallet: In order to log in into this account please identify this device and click the “Continue” button. Once logged in, sit back and relax, we will do the rest.', true);/*review*/
                provider.setNextStep('loginComplete', function () {
                    browserAPI.log("waiting answers...");
                    let counter = 0;
                    let waitingAnswers = setInterval(function () {
                        browserAPI.log("waiting... " + counter);

                        if (
                            counter > 180
                        ) {
                            clearInterval(waitingAnswers);
                            provider.setError(['Message from AwardWallet: In order to log in into this account please identify this device and click the “Continue” button. Once logged in, sit back and relax, we will do the rest.', util.errorCodes.providerError], true);
                            return;
                        }// if (error.length > 0 && error.text().trim() != '')
                        if (
                            $('input[data-testid="OTP_FIELD"]:visible').length === 0
                        ) {
                            clearInterval(waitingAnswers);

                            if (provider.isMobile) {
                                provider.command('hide', function () {
                                });
                            }

                            plugin.loginComplete(params);
                        }
                        counter++;
                    }, 500);
                });
            });

            return;
        }// if ($('input[data-testid="OTP_FIELD"]:visible').length > 0)

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        if (params.autologin) {
            browserAPI.log("only autologin");
            provider.complete();
            return;
        }

        plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.logBody("parsePage");
        let data = {};
        let headers = {
            "Accept"      : "application/json",
            "pianoChannel": "JS-WEB",
        };
        $.ajax({
            type: 'GET',
            url: 'https://www.nectar.com/customer-management-api/customer',
            async: false,
            headers: headers,
            dataType: 'json',
            success: function (response) {
                browserAPI.log("customer: " + JSON.stringify(response));
                // Name
                let name = response.firstName + " " + response.lastName;
                data.Name = util.beautifulName((name));
                browserAPI.log('Name: '+ data.Name);
            },
            error: function (response) {
                browserAPI.log("error customer: " + JSON.stringify(response));
            }
        });
        // Balance - You have (Your points total)
        $.ajax({
            type: 'GET',
            url: 'https://www.nectar.com/balance-api/balance',
            async: false,
            headers: headers,
            dataType: 'json',
            success: function (response) {
                browserAPI.log("balance: " + JSON.stringify(response));
                // Balance - You have (Your points total)
                data.Balance = response.current;
                browserAPI.log('Balance: '+ data.Balance);
                // Nectar points, worth £15.25
                if (typeof (response.currentCurrencyValue) != 'undefined') {
                    data.BalanceWorth = '£' + (response.currentCurrencyValue / 100);
                    browserAPI.log('BalanceWorth: '+ data.BalanceWorth);
                }
            },
            error: function (response) {
                browserAPI.log("error balance: " + JSON.stringify(response));
            }
        });
        // Account #
        $.ajax({
            type: 'GET',
            url: 'https://www.nectar.com/customer-management-api/customer/card',
            async: false,
            headers: headers,
            dataType: 'json',
            success: function (response) {
                browserAPI.log("card: " + JSON.stringify(response));
                // Number
                data.Number = response.number;
                browserAPI.log('Number: '+ data.Number);
            },
            error: function (response) {
                browserAPI.log("error card: " + JSON.stringify(response));
            }
        });
        // Expiration date
        $.ajax({
            type: 'GET',
            url: 'https://www.nectar.com/nectar-shared-transactions-api/transactions?pageSize=20',
            async: false,
            headers: headers,
            dataType: 'json',
            success: function (response) {
                let items = response.items;
                // browserAPI.log("rows:" + JSON.stringify(items));
                for (const item in items) {
                    if (!items.hasOwnProperty(item)) {
                        browserAPI.log('skip wrong node');

                        continue;
                    }
                    browserAPI.log("row:" + JSON.stringify(items[item]));
                    let lastActivity = items[item].transactionDate;
                    browserAPI.log('Last Activity: ' + lastActivity);

                    if (lastActivity) {
                        data.LastActivity = lastActivity;
                        browserAPI.log('LastActivity: '+ data.LastActivity);

                        let date = new Date(lastActivity);
                        if (date !== null && date.getTime()) {
                            date.setMonth(date.getMonth() + 12);
                            let unixtime = date / 1000;
                            if (!isNaN(unixtime)) {
                                browserAPI.log("ExpirationDate = lastActivity + 12 months");
                                browserAPI.log("Expiration Date: " + date + " UnixTime: " + unixtime);
                                data.AccountExpirationDate = unixtime;
                            }

                            return;
                        }
                        else
                            browserAPI.log("Invalid Expiration Date");
                    }// if (lastActivity)
                }
            },
            error: function (response) {
                browserAPI.log("error Expiration date: " + JSON.stringify(response));
            }
        });

        params.data = data;
        params.account.properties = params.data;
        provider.saveProperties(params.account.properties);
        provider.complete();
    },

    english_ordinal_suffix: function (dt) {
        return dt.getDate() + (dt.getDate() % 10 == 1 && dt.getDate() != 11 ? 'st' : (dt.getDate() % 10 == 2 && dt.getDate() != 12 ? 'nd' : (dt.getDate() % 10 == 3 && dt.getDate() != 13 ? 'rd' : 'th')));
    },

};