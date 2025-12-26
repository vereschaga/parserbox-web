var plugin = {
    hideOnStart: true,
    clearCache: true,
    //keepTabOpen: true, // todo

    hosts: {
        'www.iberia.com'  : true,
        'login.iberia.com': true
    },

    myCardURL: 'https://www.iberia.com/fi/en/mi-iberia/#/IBPHOM/',
    myAviosURL: 'https://www.iberia.com/us/iberiaplus/my-iberia-plus/#!/IBAVIO',

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getFocusTab: function(account, params){
        return true;
    },

    getStartingUrl: function (params) {
        return plugin.myCardURL;
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function() {
            document.location.href = plugin.myAviosURL;
        });
    },

    isAndroid: function() {
        return provider.isMobile && applicationPlatform === 'android';
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));

        plugin.fixedLogin(params);

        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

            if (
                $('p:contains("Se ha producido un intento de acceso no permitido a la sesión")').length > 0
                || $('strong:contains("Expired session")').length > 0
            ) {
                if (document.location.href !== plugin.myCardURL) {
                    provider.setNextStep('checkNumber', function() {
                        document.location.href = plugin.myCardURL;
                    });
                    return;
                }
            }

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

                provider.logBody("lastPage");
                // maintenance
                let error = $('p:contains("At this moment, our online services are not available as result of the high number of accesses."):visible');
                /*
                 * The page you have requested is not available
                 *
                 * We suggest you review the internet address requested as it may be incorrect. If it is correct we suggest that you try again after a few minutes, or select the Iberia page in for your country.
                 *
                 * We thank you and apologise for the inconvenience. Back to home
                 */
                if (error.length === 0)
                    error = $('p:contains("We suggest you review the internet address requested as it may be incorrect."):visible, p:contains("We suggest you check the internet address you requested as it must be incorrect."):visible');
                if (error.length > 0 && util.trim(error.text()) !== "") {
                    provider.setError([error.text(), util.errorCodes.providerError], true);
                    return false;
                }

                // sometimes site do not redirect on the login page
                browserAPI.log(">>> retry");
                let retry = $.cookie("iberia.com_retry_" + params.account.login);
                browserAPI.log(">>> retry number: " + retry);
                if ((retry === null || typeof (retry) === 'undefined') || retry < 3) {
                    if (retry === null || typeof (retry) === 'undefined') {
                        retry = 0;
                    }
                    provider.logBody("lastPage-retry_" + retry);
                    browserAPI.log(">>> Retry: " + retry);
                    retry++;
                    $.cookie("iberia.com_retry_" + params.account.login, retry, { expires: 0.01, path: '/', domain: '.iberia.com', secure: true });
                    // plugin.loadLoginForm(params);
                    provider.setNextStep('start', function() {
                        // document.location.href = provider.myCardURL;
                        document.location.href = "https://www.iberia.com/integration/ibplus/login/?referralURL=https%3A%2F%2Fwww.iberia.com%2Fus%2F&noState=true";
                    });
                    return;
                }

                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    checkNumber: function (params) {
        browserAPI.log("checkNumber");
        if (plugin.isSameAccount(params.account))
            plugin.loginComplete(params);
        else
            plugin.logout(params.account);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        browserAPI.log("[Current URL]: " + document.location.href);
        if (
            $('a[href *= logoff], .logOut-action').length > 0
            && $('.ib-table-card, #loggedUserName:visible').length > 0
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('span:contains("Iberia Plus number") + span').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if (
            $('form[id = "loginPage:theForm"]:visible').length > 0
            || $('form#loginFormLayout:visible').length > 0 // old
        ) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name = util.filter( $('#loggedUserName').text());
        browserAPI.log("name: " + name);

        return ((typeof(account.properties) != 'undefined')
                && (typeof(account.properties.Name) != 'undefined')
                && name
                && (name.toLowerCase() === account.properties.Name.toLowerCase()));

        // let number = util.findRegExp( $('p:contains("Card number:")').parent().next('.ib-table-card__data').text() , /(IB\s*\d+)/i);
        // browserAPI.log("number: " + number);
        // return ((typeof(account.properties) != 'undefined')
        //     && (typeof(account.properties.Number) != 'undefined')
        //     && number
        //     && (number === account.properties.Number));
    },

    logout: function (account) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function() {
            let logoutLink = $('a[href *= logoff], .logOut-action');

            if (logoutLink.length) {
                browserAPI.log("logout -> click by link");
                logoutLink.get(0).click();
            } else {
                browserAPI.log("logout -> go to url");
                document.location.href = "https://login.iberia.com/secur/logout.jsp?retUrl=https://www.iberia.com/us/";
            }
        });
    },

    fixedLogin: function (params) {
        browserAPI.log("fixedLogin");
        if (!/@/.test(params.account.login)) {
            params.account.login = params.account.login.toString().replace(/^IB\s*/i, '');
        }
    },

    login: function (params) {
        browserAPI.log("login");

        if (
            typeof(params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = "http://www.iberia.com/manage-my-booking/?language=en";
            });
            return;
        }
        // IE not working properly
        if (!!navigator.userAgent.match(/Trident\/\d\./)) {
            provider.eval('jQuery.noConflict()');
        }



        // new form
        let form = $('form[id = "loginPage:theForm"]:visible');

        if (form.length === 0) {
            if ($('a[href *= logoff], .logOut-action').length > 0) {
                provider.logBody("login, form not found");
                plugin.loadLoginForm(params);
                return;
            }
            provider.logBody("lastPage");
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        // angularjs 10
        provider.eval(
            "function triggerInput(enteredName, enteredValue) {\n" +
            "      const input = document.querySelector(enteredName);\n" +
            "      var createEvent = function(name) {\n" +
            "            var event = document.createEvent('Event');\n" +
            "            event.initEvent(name, true, true);\n" +
            "            return event;\n" +
            "      }\n" +
            "      input.dispatchEvent(createEvent('click'));\n" +
            "      input.value = enteredValue;\n" +
            "      input.dispatchEvent(createEvent('change'));\n" +
            "      input.dispatchEvent(createEvent('input'));\n" +
            "      input.dispatchEvent(createEvent('blur'));\n" +
            "      input.dispatchEvent(createEvent('focus'));\n" +
            "}\n" +
            "triggerInput('input[name = \"loginPage:theForm:loginEmailInput\"]', '" + params.account.login + "');\n" +
            "triggerInput('input[name = \"loginPage:theForm:loginPasswordInput\"]', '" + params.account.password + "');"
        );

        util.sendEvent(form.find('input[name = "loginPage:theForm:loginEmailInput"]').get(0), 'click');
        util.sendEvent(form.find('input[name = "loginPage:theForm:loginEmailInput"]').get(0), 'input');
        util.sendEvent(form.find('input[name = "loginPage:theForm:loginEmailInput"]').get(0), 'change');
        util.sendEvent(form.find('input[name = "loginPage:theForm:loginEmailInput"]').get(0), 'blur');
        util.sendEvent(form.find('input[name = "loginPage:theForm:loginEmailInput"]').get(0), 'focus');

        util.sendEvent(form.find('input[name = "loginPage:theForm:loginPasswordInput"]').get(0), 'click');
        util.sendEvent(form.find('input[name = "loginPage:theForm:loginPasswordInput"]').get(0), 'input');
        util.sendEvent(form.find('input[name = "loginPage:theForm:loginPasswordInput"]').get(0), 'change');
        util.sendEvent(form.find('input[name = "loginPage:theForm:loginPasswordInput"]').get(0), 'blur');
        util.sendEvent(form.find('input[name = "loginPage:theForm:loginPasswordInput"]').get(0), 'focus');


        provider.setNextStep('preCheckLoginErrors', function () {
            form.find('input[id = "loginPage:theForm:loginSubmit"]').click();

            setTimeout(function () {
                const error = $('#userErrorLabel:visible');

                if (error.length) {
                    provider.setError(error.text(), true);
                }
            }, 5000);
        });
    },

    preCheckLoginErrors: function (params) {
        browserAPI.log("preCheckLoginErrors");
        browserAPI.log("[Current URL]: " + document.location.href);
        util.waitFor({
            selector: '#loggedUserName:visible',
            success: function() {
                plugin.checkLoginErrors(params);
            },
            fail: function() {
                plugin.checkLoginErrors(params);
            },
            timeout: 10
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        browserAPI.log("[Current URL]: " + document.location.href);

        // Sorry, your session has expired.
        if ($('strong:contains("Expired session"):visible').length > 0) {
            plugin.start(params);
            return;
        }
        provider.logBody("checkLoginErrors");
        if (!plugin.isLoggedIn(params)) {

            browserAPI.log("checkLoginErrors -> check errors");

            let error = $('p:contains("The combination of the number of Iberia Plus card and the password that you have entered is not correct"):visible, p:contains("The email and password combination you have entered is incorrect. Please check the email and enter the correct password again."):visible');
            if (error.length > 0) {
                error = util.findRegExp( error.text(), /\)?\s*([^(]+)/i);
                provider.setError(error, true);
                return;
            }
            if (error.length === 0)
                error = $('p:contains("The Iberia Plus card number and password combination you have entered are incorrect. Please check the Iberia Plus number indicated and enter the correct password again."):visible, p:contains("Invalid format"):visible');
            if (error.length === 0)
                error = $('p:contains("PIN, should contain 6 characters")');
            if (error.length === 0)
                error = $('p:contains("PIN, should contain 6 characters")');
            if (error.length === 0)
                error = $('p:contains("Access to the Iberia Plus personal area is currently blocked for the indicated number.")');
            if (error.length === 0)
                error = $('p:contains("A timeout has occurred while connecting Iberia Plus"):visible');
            // Change PIN number
            if (error.length === 0) {
                error = $('h1:contains("Change PIN number"), h1:contains("Cambio de PIN")');
                if (error.length > 0) {
                    provider.setError(["Iberia Plus website is asking you to change your pin, until you do so we would not be able to retrieve your account information.", util.errorCodes.invalidPassword], true);
                    return;
                }
                error = $('h2:contains("Reset password"):visible');
                if (error.length > 0) {
                    provider.setError(["Iberia Plus website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", util.errorCodes.invalidPassword], true);
                    return;
                }
            }
            if (error.length > 0 && util.trim(error.text()) !== "") {
                provider.setError(error.text(), true);
                return;
            }
            error = $('#dialog1Desc').find('p:contains("An error has occurred in the Login."):visible');
            if (error.length === 0) {
                error = $('p:contains("Sorry, an error has occurred. Please try again later."):visible');
            }
            if (error.length) {
                provider.setError([error.text(), util.errorCodes.providerError], true);
                return;
            }

            // Incorrect password
            if (error.length === 0 && $('input#pin-number.error:visible').length) {
                provider.setError('The Iberia Plus card number and password combination you have entered are incorrect. Please check the Iberia Plus number indicated and enter the correct password again.', true);
                return;
            }

            error = $('div.errorDiv:not([class *= "hide"]):not([style *= "display: none"]) > label, div#userErrorController > label');

            if (error.length === 0 || util.filter(error.text()) === '') {
                error = $('label#userErrorLabel:visible');
            }

            if (error.length > 0 && util.filter(error.text()) !== '') {
                let message = util.filter(error.text());
                if (
                    /The Iberia Plus card number and password combination you have entered are incorrect\./.test(message)
                    || /The Iberia plus number and password combination you entered is not correct\./.test(message)
                    || /Sorry, you can\'t use the email entered\. Please try again with your Iberia Plus number\./.test(message)
                    || /Oops. You can\'t sign in to your Iberia Plus account\. Please contact Customer Services\./.test(message)
                    || /La combinación de número de Iberia Plus y contraseña que has introducido no es correcta\./.test(message)
                    || /La combinación de correo electrónico y contraseña que has introducido no es correcta\./.test(message)
                    || /Alguno de los datos no es válido\. Por favor, comprueba que los hayas introducido correctamente y/.test(message)
                    || /No hemos podido conectarte\.\s*Es posible que alguno de los datos que has introducido no sea correcto/.test(message)
                    || /Login has failed\.\s*Some of the details you entered may be incorrect, or the email might not be registered/.test(message)
                    || message === 'El formato no es válido'
                    || message === 'Formato incorrecto'
                    || message === 'Tu contraseña ha caducado. Debes crear una nueva.'
                    || message === 'Your password has expired. You must create a new one.'
                    || message === 'Este número de Iberia Plus no se corresponde con ningún usuario. Por favor, inténtalo con tu e-mail de acceso.'
                ) {
                    provider.setError([message, util.errorCodes.invalidPassword], true);
                    return;
                }

                browserAPI.log('>> Error: ' + message);
                provider.complete();

                return;
            }

        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        browserAPI.log("[Current URL]: " + document.location.href);
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function(){
                document.location.href = 'https://www.iberia.com/us/manage-my-booking/?language=en&market=us&channel=COM#!/mytrps';
            });
            return;
        }

        // parse account
        if (params.autologin) {
            provider.complete();
            return;
        }

         plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        browserAPI.log("[Current URL]: " + document.location.href);
        provider.logBody('loadAccount');

        if (document.location.href !== plugin.myCardURL) {
            provider.setNextStep('loadPropertiesV2', function () {
                document.location.href = plugin.myCardURL;
            });

            setTimeout(function () {
                plugin.loadPropertiesV2(params);
            }, 3000);
            return;
        }// if (document.location.href != url)

        plugin.loadPropertiesV2(params);
    },

    loadPropertiesV2: function (params) {
        plugin.parsePropertiesV2(params);
    },

    parsePropertiesV2: function (params) {
        browserAPI.log("parsePropertiesV2");
        browserAPI.log('current url: ' + document.location.href);
        provider.logBody("parsePropertiesV2");
        provider.updateAccountMessage();
        var data = {};


        plugin.ajaxRequest('https://miiberia.services.aws.iberia.com/iberia-plus/v1/iberia-plus', 'GET', null, false, parse, function (response) {
            browserAPI.log('Parse Props error: ' + response);
        });

        function parse(response) {
            browserAPI.log('Parse Props success');
            // console.log("---------------- data ----------------");
            // console.log(response);
            // console.log("---------------- data ----------------");
            if (!response || typeof response.data === 'undefined') {
                browserAPI.log('response.data undefined');
                return;
            }
            let account = response.data.avios.account;
            // Card Number
            if (account.frequent_flyer_number.length > 0) {
                let number = util.trim(util.findRegExp(account.frequent_flyer_number, /^[0]*(\d+)/i) || account.frequent_flyer_number);
                browserAPI.log("Number: " + number);
                data.Number = number;
            } else
                browserAPI.log("Number not found");
            // Card level
            if (account.level.length > 0) {
                data.Level = util.beautifulName(account.level);
                browserAPI.log("Level: " + data.Level);
            } else
                browserAPI.log("Level not found");
            // Date of joining - 2014-09-28
             if (account.loyalty_date.length > 0) {
                data.Since = util.trim(account.loyalty_date);
                browserAPI.log("Since: " + data.Since);
            } else
                browserAPI.log("Since not found");
            // Expiry date
            /*var cardExpiry = $('p:contains("Valid until:")').parent().next('td').find('.ib-heading--block');
            if (cardExpiry.length > 0) {
                data.CardExpiry = util.trim(cardExpiry.text());
                browserAPI.log("Card Expiry: " + data.CardExpiry);
            } else
                browserAPI.log("Expiry date not found");*/
            if (typeof account.avios !== 'undefined') {
                browserAPI.log("Balance: " + account.avios);
                data.Balance = util.trim(account.avios);
            } else
                browserAPI.log("Balance not found");
            // Name
            if (account.name.length > 0) {
                let name = util.beautifulName(account.name + ' '+account.second_surname);
                browserAPI.log("Name: " + name);
                data.Name = name;
            } else
                browserAPI.log("Name not found");
            // Lifetime Elite Points - Elite Points History
            if (typeof account.elite !== 'undefined') {
                data.LifetimeElitePoints = account.elite;
                browserAPI.log("Lifetime Elite Points: " + data.LifetimeElitePoints);
            } else
                browserAPI.log("Lifetime Elite Points not found");
            // Elite Points
            if (typeof account.level_status.elite.current_points !== 'undefined') {
                data.ElitePoints = account.level_status.elite.current_points;
                browserAPI.log("Elite Points: " + data.ElitePoints);
            } else
                browserAPI.log("Elite Points not found");
            // Flights
            if (typeof account.level_status.flights.current_flights !== 'undefined') {
                data.Flights = util.trim(account.level_status.flights.current_flights);
                browserAPI.log("Flights: " + data.Flights);
            } else
                browserAPI.log("Flights not found");

            params.data.properties = data;

            // Expiration date  // refs #3167
            // TODO: Enable History
            if (!provider.isMobile) {
                if (document.location.href === plugin.myCardURL) {
                    plugin.parseHistory(params);
                    return;
                }
                provider.saveTemp(params.data);
                provider.setNextStep('parseHistory', function () {
                    document.location.href = plugin.myCardURL;//'https://www.iberia.com/us/iberiaplus/my-avios/';
                });
            } else {
                params.account.properties = params.data.properties;
                //provider.saveTemp(params.data);
                provider.saveProperties(params.account.properties);
                plugin.stepItineraries(params);
            }
        }
    },

    parseProperties: function (params) {
        browserAPI.log("parseProperties");
        browserAPI.log('current url: ' + document.location.href);
        provider.logBody("parseProperties");
        provider.updateAccountMessage();
        var data = {};

        // Card Number
        var number = $('p:contains("number:")').parent().next('td').find('.ib-heading--block');
        if (number.length > 0) {
            number = util.trim(util.findRegExp( number.text() , /(IB\s*\d+)/i) || number.text());
            browserAPI.log("Number: " + number);
            data.Number = number;
        } else
            browserAPI.log("Number not found");
        // Card level
        var level = $('.ib-card-logo .ib-heading--block.ib-heading--bold').eq(0);
        if (level.length > 0) {
            data.Level = util.trim(level.text());
            browserAPI.log("Level: " + data.Level);
        } else
            browserAPI.log("Level not found");
        // Date of joining
        var since = $('p:contains("Member since:")').parent().next('td').find('.ib-heading--block');
        if (since.length > 0) {
            data.Since = util.trim(since.text());
            browserAPI.log("Since: " + data.Since);
        } else
            browserAPI.log("Since not found");
        // Expiry date
        var cardExpiry = $('p:contains("Valid until:")').parent().next('td').find('.ib-heading--block');
        if (cardExpiry.length > 0) {
            data.CardExpiry = util.trim(cardExpiry.text());
            browserAPI.log("Card Expiry: " + data.CardExpiry);
        } else
            browserAPI.log("Expiry date not found");

        // Balance - Your Avios
        // #loggedUserAvios:visible
        var balance = util.findRegExp($('.bold.txt-15 > a[title="How to enjoy your Avios"]').text(), /^([\d.,\-\s]+)$/);
        if (!balance) {
            balance = util.findRegExp($('#loggedUserAvios:visible').text(), /^([\d.,\-\s]+)$/);
            if (!balance) {
                balance = util.findRegExp($('.ib-card-logo__data .ib-heading--primary:visible:contains("Avios")').text(), /^([\d.,\-\s]+)/);
            }
        }
        if (balance) {
            browserAPI.log("Balance: " + balance);
            data.Balance = util.trim(balance);
        } else
            browserAPI.log("Balance not found");
        // Name
        var name = $('span:contains("Hello") span');
        if (name.length > 0) {
            name = util.beautifulName(util.trim(name.text()));
            browserAPI.log("Name: " + name);
            data.Name = name;
        } else
            browserAPI.log("Name not found");
        // Lifetime Elite Points
        let lifetimeElitePoints = util.findRegExp($('p:contains("Elite Points since ")').text(), /^([\d.,\-\s]+)\s*Elite Points/);
        if (lifetimeElitePoints) {
            data.LifetimeElitePoints = util.trim(lifetimeElitePoints);
            browserAPI.log("Lifetime Elite Points: " + data.LifetimeElitePoints);
        } else
            browserAPI.log("Lifetime Elite Points not found");
        // Elite Points
        let elitePoints = util.findRegExp($('span:contains("Your Elite Points:")').text(), /:\s*([\d.,\-\s]+)/);
        if (elitePoints) {
            data.ElitePoints = util.trim(elitePoints);
            browserAPI.log("Elite Points: " + data.ElitePoints);
        } else
            browserAPI.log("Elite Points not found");
        // Flights
        let flights = util.findRegExp($('span:contains("Your flights:")').text(), /:\s*([\d.,\-\s]+)/);
        if (flights) {
            data.Flights = util.trim(flights);
            browserAPI.log("Flights: " + data.Flights);
        } else
            browserAPI.log("Flights not found");

        params.data.properties = data;

        // Expiration date  // refs #3167
        // TODO: Enable History
        if (!provider.isMobile) {
            if (document.location.href === plugin.myCardURL) {
                plugin.parseHistory(params);
                return;
            }
            provider.saveTemp(params.data);
            provider.setNextStep('parseHistory', function () {
                document.location.href = plugin.myCardURL;//'https://www.iberia.com/us/iberiaplus/my-avios/';
            });
        } else {
            params.account.properties = params.data.properties;
            //provider.saveTemp(params.data);
            provider.saveProperties(params.account.properties);
            plugin.stepItineraries(params);
        }
    },

    dateFormatUTC: function (dateStr, isObjectOrUnix) {
        browserAPI.log('dateFormatUTC');
        // 2019-12-16T00:00:00.000
        var date = dateStr.match(/(\d{4})-(\d+)-(\d+)T/);
        var year = date[1], month = date[2], day = date[3];
        var dateObject = new Date(Date.UTC(year, month - 1, day, 0, 0, 0, 0));
        var unixTime = dateObject.getTime() / 1000;
        if (!isNaN(unixTime)) {
            browserAPI.log('Date: ' + dateObject + ' UnixTime: ' + unixTime);
            return isObjectOrUnix ? dateObject : unixTime;
        }
        return null;
    },

    getCookie: function (name) {
        var matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([.$?*|{}()\[\]\\\/+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
    },

    ajaxRequest: function (url = '', method = 'POST', data = null, withCredentials = true, callback, callbackError) {
        browserAPI.log('plugin.ajax');
        browserAPI.log(url + ' => ' + method + ' ' + (data ? JSON.stringify(data) : null));
        return $.ajax({
            url: url /*+ '?_=' + new Date().getTime()*/,
            type: method,
            beforeSend: function (request) {
                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                request.setRequestHeader('Cache-Control', 'no-cache');
                request.setRequestHeader('Pragma', 'no-cache');
                request.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
                request.setRequestHeader('Authorization', 'Bearer ' + plugin.getCookie('IBERIACOM_SSO_ACCESS'));
                request.setRequestHeader('x-salesforce-token', plugin.getCookie('IBERIACOM_SSO_ACCESS_SALESFORCE'));
            },
            data: (data ? JSON.stringify(data) : null),
            crossDomain: true,
            dataType: 'json',
            cache: true,
            async: false,
            xhrFields: {
                withCredentials: withCredentials
            },
            success: function (response) {
                if (response) {
                    callback(response);
                } else {
                    browserAPI.log('ajax error, response null');
                    callbackError(response);
                }
            },
            error: function (xhr, status) {
                browserAPI.log('ajax error' + xhr.responseText);
                callbackError(xhr.responseText);
            }
        });
    },

    fetchRequest: async function (url, method = 'POST', data = null, withCredentials = true,  callback, callbackError) {
        browserAPI.log('plugin.fetch');
        browserAPI.log(url + ' => ' + method + ' ' + (data ? JSON.stringify(data) : null));
         if (typeof Promise !== 'undefined') {
            browserAPI.log('Fetch supported');
            await fetch(url, {
                method: method,
                headers: {
                    "accept-language": "en-US",
                    'Accept': 'application/json, text/plain, *!/!*',
                    'Content-Type': 'application/json;charset=UTF-8',
                    'Authorization': 'Bearer ' + plugin.getCookie('IBERIACOM_SSO_ACCESS'),
                    "x-observations-current-page": "null",
                    "x-observations-origin-page": "null",
                    "x-request-appversion": "9.1.0",
                    "x-request-device": "unknown|chrome|100.0.4896.60",
                    "x-request-osversion": "mac|mac-os-x-15",
                    "cache-control": "no-cache"
                },
                body: (data ? JSON.stringify(data) : null),
                mode: 'cors',
                cache: 'no-cache',
                credentials: "include",
                referrerPolicy: "strict-origin",
                referrer: "https://www.iberia.com/",
            })
                .then(async response => await response.json())
                .then(callback)
                .catch(callbackError);
        } else {
            browserAPI.log('Fetch not supported!');
            plugin.ajaxRequest(url, method, data, withCredentials, callback, callbackError);
         }
    },

    parseHistory: function (params) {
        browserAPI.log("parseHistory");
        provider.updateAccountMessage();
        let startDate = params.account.historyStartDate;
        browserAPI.log("historyStartDate: " + startDate);
        params.data.properties.HistoryRows = [];

        let token = $.cookie('IBERIACOM_SSO_ACCESS');
        let parts = token.split('.');
        let loyaltyCard = null;
        parts.forEach(function (part) {
            try {
                let decoded = atob(part);
                let json = JSON.parse(decoded);
                if (typeof (json.sub) === 'undefined') {
                    return;
                }
                loyaltyCard = json.sub;
            } catch (e) {
                browserAPI.log(e);
            }
        });

        browserAPI.log("[loyaltyCard]: " + loyaltyCard);

        if (!loyaltyCard) {
            browserAPI.log("[WARNING]: loyaltyCard not found");
            return;
        }

        // var awCode = document.createElement( 'script' );
        // awCode.id = 'historyAW';
        // document.getElementsByTagName('head')[0].appendChild(awCode);
      //   provider.eval('' +
      //         'fetch("https://ibisservices.iberia.com/api/agl/v2/members/' + loyaltyCard +'/programmes/IBP/schemes/accounts/transactions", {' +
      //         '"headers": {' +
      //           '"accept": "application/json, text/plain, */*",'+
      //           '"accept-language": "en-US",' +
      //           '"authorization": "Bearer ' + plugin.getCookie('IBERIACOM_SSO_ACCESS') + '",' +
      //           '"cache-control": "no-cache",' +
      //           '"pragma": "no-cache",' +
      //           '},' +
      //             '"referrer": "https://www.iberia.com/",' +
      //           '"referrerPolicy": "strict-origin",' +
      //           '"body": null,' +
      //           '"body": null,' +
      //           '"method": "GET",' +
      //           '"mode": "cors",' +
      //           '"credentials": "include"' +
      //       '})' +
      //   '.then((response) => {' +
      //                 'console.log("---------------- success ----------------");'+
      //         'console.log(response);' +
      //       '$(\'#historyAW\').text(JSON.stringify(response));' +
      //   '})' +
      //   '.then((data) => {' +
      //                 'console.log("---------------- fail ----------------");'+
      //     'console.log(data);' +
      //     '$(\'#historyAW\').text(JSON.stringify(data));' +
      //   '});' +
      // '');
      //
      //   let response = $.parseJSON($('#historyAW').text());
      //   parse(response);

        plugin.fetchRequest('https://ibisservices.iberia.com/api/agl/v2/members/' + loyaltyCard + '/programmes/IBP/schemes/accounts/transactions', 'GET', null, true, parse, function(response) {
            browserAPI.log('Parse History error: ' + response);
            console.error('Parse History error: ' + response);
            //alert('error: ' + JSON.stringify(response));
            plugin.stepItineraries(params);
        });

        function parse(response) {
            browserAPI.log('Parse History success');
            // console.log("---------------- data ----------------");
            // console.log(response);
            // console.log("---------------- data ----------------");
            if (!response || typeof response.transactions === 'undefined') {
                plugin.stepItineraries(params);
                return;
            }
            response = response.transactions;

            /*
            for (let i in response) {
                if (!response.hasOwnProperty(i)) {
                    continue;
                }

                let dateStr = response[i].dateMade;
                let postDate = plugin.dateFormatUTC(dateStr);

                if (postDate === null) {
                    browserAPI.log("Skip bad node");
                    continue;
                }

                let description = '';
                if (typeof (response[i].description) !== 'undefined') {
                    description = util.filter(response[i].description);
                }

                let currencyCode = util.filter(response[i].monetaryAmount.currency.currencyCode);
                let avios = null;
                if (currencyCode === 'AVIOS') {
                    avios = response[i].monetaryAmount.amount;
                }

                if (
                    avios === 0
                    || util.stristr(description, 'CADUCIDAD AVIOS POR INACTIVIDAD')
                    || util.stristr(description, 'Combinar mis Avios')
                    || util.stristr(description, 'Anulación reserva de vuelo con Avios')
                    || util.stristr(description, 'Anulación reserva con Avios del vuelo')
                    || util.stristr(description, 'ANULADO')
                    || util.stristr(description, 'TRANSFERS')
                    || util.stristr(description, 'TRANSFERENCIA')
                ) {
                    browserAPI.log("skip transaction: " + JSON.stringify(response[i]));

                    continue;
                }

                browserAPI.log("Expiration date");
                let lastActivityDate = new Date(postDate * 1000);
                // Last Activity
                let lastActivity = lastActivityDate.getUTCFullYear();
                if ((lastActivityDate.getUTCMonth() + 1) < 10)
                    lastActivity = '0' + (lastActivityDate.getUTCMonth() + 1) + "/" + lastActivity;
                else
                    lastActivity = (lastActivityDate.getUTCMonth() + 1) + "/" + lastActivity;
                if (lastActivityDate.getUTCDate() < 10)
                    lastActivity = '0' + lastActivityDate.getUTCDate() + "/" + lastActivity;
                else
                    lastActivity = lastActivityDate.getUTCDate() + "/" + lastActivity;

                browserAPI.log("Last Activity: " + lastActivity);
                params.data.properties.LastActivity = lastActivity;

                // Expiration date
                let exp = lastActivityDate.setFullYear(lastActivityDate.getFullYear() + 3);
                let unixtime = exp / 1000;
                if ( lastActivityDate != 'NaN' && !isNaN(unixtime) ) {
                    browserAPI.log("ExpirationDate = lastActivity + 3 years");
                    browserAPI.log("Expiration Date: " + new Date(unixtime * 1000) + " Unixtime: " + util.trim(unixtime) );
                    params.data.properties.AccountExpirationDate = unixtime;
                }

                break;
            }
            */

            for (var i in response) {
                if (!response.hasOwnProperty(i)) {
                    continue;
                }
                let dateStr = response[i].dateMade;
                let postDate = plugin.dateFormatUTC(dateStr);
                if (startDate > 0 && postDate < startDate) {
                    browserAPI.log("break at date " + dateStr + " " + postDate);
                    break;
                }
                if (postDate === null) {
                    browserAPI.log("Skip bad node");
                    continue;
                }
                let description = '';
                if (typeof (response[i].description) !== 'undefined') {
                    description = util.filter(response[i].description);
                }
                let sector = util.filter(response[i].sectorType);
                let elitePoints = null;

                if (typeof (response[i].transactionSummaries) !== 'undefined') {
                    response[i].transactionSummaries.forEach(function (summary, j) {
                        if (summary.monetaryAmount.currency.currencyCode === 'TIER_POINTS') {
                            elitePoints = summary.monetaryAmount.amount;
                        }
                    });
                }

                let currencyCode = util.filter(response[i].monetaryAmount.currency.currencyCode);
                let avios = null;
                if (currencyCode === 'AVIOS') {
                    avios = response[i].monetaryAmount.amount;
                }

                let row = {
                    'Date'        : postDate,
                    'Description' : description,
                    'Sector'      : sector,
                    'Avios'       : avios,
                    'Elite Points': elitePoints
                };
                params.data.properties.HistoryRows.push(row);
            }
            params.account.properties = params.data.properties;
            //provider.saveProperties(params.account.properties);
            plugin.stepItineraries(params);
        }
    },

    stepItineraries: function (params) {
        browserAPI.log("stepItineraries");

        if (!plugin.isAndroid() && typeof (params.account.parseItineraries) === 'boolean' && params.account.parseItineraries) {
            provider.saveTemp(params.data);
            provider.setNextStep('beforeParseItineraries', function () {
                document.location.href = "https://www.iberia.com/?language=es";
            });
            return;
        }
        params.account.properties = params.data.properties;
        provider.saveProperties(params.account.properties);
        plugin.itLoginComplete(params);
    },

    beforeParseItineraries: function(params) {
        browserAPI.log('beforeParseItineraries');
        browserAPI.log('params.data:');
        browserAPI.log(JSON.stringify(params.data));
        provider.saveTemp(params.data);
        provider.setNextStep('parseItineraries', function() {
            document.location.href = 'https://www.iberia.com/us/manage-my-booking/?language=en&market=us&channel=COM';
        });
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        let confNo = params.account.properties.confirmationNumber;
        var link = $('.ib-reserves-list__code--number:contains("' + confNo + '")').closest('header').next('footer').find('a[title="Go to See or Manage"]');

        if (link.length === 0) {
            provider.setError(util.errorMessages.itineraryNotFound);
            return;
        }

        link.get(0).click();
        plugin.itLoginComplete(params);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log('getConfNoItinerary');
        let properties = params.account.properties.confFields;
        let form = $('form[name = "formBooking"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.itineraryFormNotFound);
            return;
        }

        form.find('input[name = "pnr"]').val(properties.ConfNo);
        form.find('input[name = "surname"]').val(properties.LastName);
        provider.setNextStep('itLoginComplete', function(){
            form.submit();
        });
    },

    itLoginComplete: function (params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    },

    parseItineraries: function (params) {
        browserAPI.log("parseItineraries");
        browserAPI.log('current url: ' + document.location.href);
        browserAPI.log('params.data:');
        browserAPI.log(JSON.stringify(params.data));
        // now all itins parsed on Spanish site
        //provider.updateAccountMessage();
        let bookingList = plugin.getBookingsData() || [];
        browserAPI.log("Bookings list:");
        browserAPI.log(JSON.stringify(bookingList));
        if (!bookingList) {
            plugin.itLoginComplete(params);
            return;
        }
        try {
            if ((typeof bookingList.errors !== 'undefined' && typeof bookingList.errors[0] !== 'undefined')) {

                let error = bookingList.errors[0].reason || null;
                browserAPI.log(`error = ${error}`);
                if (
                    error === 'Bookings not found with IberiaPlus user'
                    || error === 'No se han encontrado reservas asociadas al usuario IberiaPlus'
                    || error === "Il n'existe aucune réservation associée à votre carte"
                ) {
                    browserAPI.log(`set NoItineraries => true`);
                    params.account.properties.Itineraries = [{NoItineraries: true}];
                    provider.saveProperties(params.account.properties);
                    plugin.itLoginComplete(params);
                    return;
                } else {
                    browserAPI.log(`error = ${error}`);
                    plugin.itLoginComplete(params);
                    return;
                }
            }
        } catch (e) {
            console.log(`error = ${e}`);
            plugin.itLoginComplete(params);
        }
        browserAPI.log("Saved properties:");
        provider.saveProperties(params.account.properties);
        params.data.Itineraries = [];
        if (typeof bookingList != 'undefined') {
            browserAPI.log('Booking ' + bookingList.length + ' found');
            for (const booking of bookingList) {
                let surname = booking.surname;
                let locator = booking.locator;
                if (surname && locator) {
                    plugin.getBookingDataJson(locator, surname, function (data) {
                        params.data.Itineraries.push(
                            plugin.parseItinerary(data)
                        );
                    }, function () {
                        browserAPI.log('Error booking data');
                    });
                }
            }
            params.account.properties = params.data.properties;
            browserAPI.log("Stringify properties:");
            browserAPI.log(JSON.stringify(params.account.properties));
            params.account.properties.Itineraries = params.data.Itineraries;
            provider.saveProperties(params.account.properties);
            plugin.itLoginComplete(params);
        } else
            plugin.itLoginComplete(params);

        /*setTimeout(function () {
            params.data.Itineraries = [];
            plugin.stepItinerary(params, bookingList);
        }, 1000);*/
    },

    getBookingDataJson: function (locator, surname, callback, error) {
        browserAPI.log('getBookingDataJson');
        let token = $.cookie('IBERIACOM_SSO_ACCESS');
        if (!token) {
            return null;
        }
        var data = null;
        plugin.ajaxRequest('https://ibisservices.iberia.com/api/sse-orm/rs/v2/order/import', 'POST', {
            locator: locator,
            surname: surname
        }, true, function (response) {
            browserAPI.log('success');
            data = response;
            //browserAPI.log(JSON.stringify(data));
            callback(data);
        }, function (response) {
            browserAPI.log(`response.status = ${response.status}`);
            data = response;
            browserAPI.log(JSON.stringify(data));
            error();
        });
    },

    /**
     * @deprecated
     * @param confNo
     * @param callback
     * @param error
     */
    stepItinerary: function(params, bookingList) {
        browserAPI.log("stepItinerary");
        if (bookingList.length === 0) {
            browserAPI.log(`bookingList.length === 0, complete`);

            //params.account.properties = params.data.properties;
            params.account.properties.Itineraries = params.data.Itineraries;
            provider.saveProperties(params.account.properties);
            plugin.itLoginComplete(params);
            return;
        }
        browserAPI.log(`Total ${bookingList.length} reservations were found`);
        var booking = bookingList.pop();
        //params.data.bookings = bookingList;
        //provider.saveTemp(params.data);
        let lastName = booking.surname;
        let confNo = booking.locator;
        if (lastName && confNo) {
            plugin.getBookingData(confNo, function (data) {
                params.data.Itineraries.push(
                    plugin.parseItinerary(JSON.parse(data))
                );
                params.account.properties.Itineraries = params.data.Itineraries;
                provider.saveProperties(params.account.properties);
                browserAPI.log('Back to the list');
                document.location.href = 'https://www.iberia.com/us/manage-my-booking/?language=en&market=us&channel=COM#!/mytrps';
                setTimeout(function () {
                    plugin.stepItinerary(params, bookingList);
                }, 2000);
            }, function () {
                browserAPI.log('Error booking data');
                // refs #20948
                if (bookingList.length === 0 || bookingList.length === 1) {
                    plugin.itLoginComplete(params);
                }
            });
        }
    },

    /**
     * @deprecated
     * @param confNo
     * @param callback
     * @param error
     */
    getBookingData: function(confNo, callback, error) {
        browserAPI.log(`getBookingData -> ${confNo}`);
        var link = $('.ib-reserves-list__code--number:contains("' + confNo + '")').closest('header').next('footer').find('a[title="Go to See or Manage"]');
        if (link.length > 0) {
            var script = document.createElement("script");
            script.textContent = '' +
                '            let oldXHROpen = window.XMLHttpRequest.prototype.open;\n' +
                '            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {\n' +
                '                this.addEventListener("load", function() {\n' +
                '                    if (/\\/import/g.exec(url)) {\n' +
                '                        console.log(method + " -> " + this.responseText);\n' +
                '                        localStorage.setItem("awData", this.responseText);\n' +
                '                    }\n' +
                '                });\n' +
                '                return oldXHROpen.apply(this, arguments);\n' +
                '            };';
            (document.getElementsByTagName('head')[0] || document.body).appendChild(script);
            link.get(0).click();
            // now all itins parsed on Spanish site
            provider.updateAccountMessage();
            util.waitFor({
                selector: "h1.ib-background-banner__title:visible, span:contains('We are very sorry but there has a been change'):visible",
                success: function() {
                    console.log('success');
                    var data = localStorage.getItem('awData');
                    localStorage.removeItem('awData');
                    callback(data);
                },
                fail: function() {
                    console.log('error');
                    error();
                    localStorage.removeItem('awData');
                },
                timeout: 40
            });
        } else {
            browserAPI.log(`Link not found -> ${confNo}`);
            error();
        }
    },

    parseItinerary: function(data) {
        browserAPI.log("parseItinerary");
        var result = {};
        for (const ref of typeof data.order.bookingReferences != 'undefined' ? data.order.bookingReferences : []) {
            if (ref.provider === 'IB') {
                result.RecordLocator = ref.reference;
            }
        }
        result.Passengers = [];
        result.AccountNumbers = [];
        for (const pas of typeof data.passengers != 'undefined' ? data.passengers : []) {
            result.Passengers.push(util.beautifulName(pas.personalInfo.name + ' ' + pas.personalInfo.surname));
            if (pas.frequentFlyerInfo && pas.frequentFlyerInfo.company && pas.frequentFlyerInfo.number
                && result.AccountNumbers.find(element => element != (pas.frequentFlyerInfo.company + ' ' + pas.frequentFlyerInfo.number)))
                result.AccountNumbers.push(pas.frequentFlyerInfo.company + ' ' + pas.frequentFlyerInfo.number);
        }
        result.TicketNumbers = [];
        for (const tickets of typeof data.tickets != 'undefined' ? data.tickets : []) {
            for (const ticket of typeof tickets.ticketNumbers != 'undefined' ? tickets.ticketNumbers : []) {
                result.TicketNumbers.push(ticket);
            }
        }

        const convertMinsToHrsMins = (mins) => {
            let h = Math.floor(mins / 60);
            let m = Math.round(mins % 60);
            h = (h < 10) ? ('0' + h) : (h);
            m = (m < 10) ? ('0' + m) : (m);
            return `${h}:${m}`;
        };

        result.TripSegments = [];
        for (const slice of typeof data.order.slices != 'undefined' ? data.order.slices : []) {
            for (const seg of typeof slice.segments != 'undefined' ? slice.segments : []) {
                var segment = {};
                segment.AirlineName = typeof seg.flight.operationalCarrier.code != 'undefined' ? seg.flight.operationalCarrier.code : null;
                if (segment.AirlineName == null)
                    continue;
                segment.FlightNumber = (typeof seg.flight.operationalFlightNumber != 'undefined' && seg.flight.operationalFlightNumber !== '') ? seg.flight.operationalFlightNumber : seg.flight.marketingFlightNumber;
                segment.Operator = typeof seg.flight.operationalCarrier.name != 'undefined' ? seg.flight.operationalCarrier.name : null;

                segment.DepCode = typeof seg.departure.code != 'undefined' ? seg.departure.code : null;
                segment.DepName = typeof seg.departure.name != 'undefined' ? seg.departure.name : null;
                var date = typeof seg.departureDateTime != 'undefined' ? seg.departureDateTime.replaceAll('-', '/') : null;
                var unixtime = new Date(date + ' UTC') / 1000;
                if (!isNaN(unixtime)) {
                    browserAPI.log("DepDate: " + date + " Unixtime: " + unixtime);
                    segment.DepDate = unixtime;
                } else
                    browserAPI.log(">>> Invalid DepDate");

                segment.ArrCode = typeof seg.arrival.code != 'undefined' ? seg.arrival.code : null;
                segment.ArrName = typeof seg.arrival.name != 'undefined' ? seg.arrival.name : null;
                date = typeof seg.arrivalDateTime != 'undefined' ? seg.arrivalDateTime.replaceAll('-', '/') : null;
                unixtime = new Date(date + ' UTC') / 1000;
                if (!isNaN(unixtime)) {
                    browserAPI.log("ArrDate: " + date + " Unixtime: " + unixtime);
                    segment.ArrDate = unixtime;
                } else
                    browserAPI.log(">>> Invalid DepDate");

                segment.Cabin = typeof seg.cabin.type != 'undefined' ? seg.cabin.type : null;
                segment.BookingClass = typeof seg.cabin.code != 'undefined' ? seg.cabin.code : null;
                segment.Aircraft = typeof seg.flight.aircraft.description != 'undefined' ? seg.flight.aircraft.description : null;
                segment.Duration = typeof seg.duration != 'undefined' ? convertMinsToHrsMins(seg.duration) : null;

                var seats = [];
                for (const seat of typeof data.order.orderItems != 'undefined' ? data.order.orderItems : []) {
                    if (seat.type === 'seat' && seg.id === seat.segmentId) {
                        seats.push(seat.row + '' + seat.column);
                    }
                }
                segment.Seats = seats.join(', ');
                result.TripSegments.push(segment);
            }
        }
        if (data.order.price.currency) {
            result.TotalCharge = data.order.price.total;
            result.Currency = data.order.price.currency;
            result.TotalTaxAmount = data.order.price.fare;
        }
        browserAPI.log(JSON.stringify(result));
        return result;
    },

    getBookingsData:  function () {
        browserAPI.log("getBookingsData");
        let token = $.cookie('IBERIACOM_SSO_ACCESS');
        if (!token) {
            return null;
        }
        var data = null;
          plugin.ajaxRequest('https://ibisservices.iberia.com/api/sse-orm/rs/v2/order/bookings', 'GET', null, true, function (response) {
            browserAPI.log('success');
            data = response;
        }, function (response) {
            browserAPI.log(`response.status = ${response.status}`);
            data = response;
        });

        // let headers = {
        //     'Accept': '*/*',
        //     'Authorization': `Bearer ${token}`,
        //     'Content-Type': 'application/json'
        // };
        // $.ajax({
        //     async: false,
        //     type: 'GET',
        //     url: 'https://ibisservices.iberia.com/api/sse-orm/rs/v2/order/bookings',
        //     headers: headers,
        //     xhr: plugin.getXMLHttp,
        //     success: function (response) {
        //         browserAPI.log('success');
        //         data = response;
        //     },
        //     error: function (response) {
        //         browserAPI.log(`response.status = ${response.status}`);
        //         data = response;
        //     }
        // });
        return data;
    },

    getXMLHttp: function () {
        if (typeof content !== 'undefined' && content && content.XMLHttpRequest) {
            return new content.XMLHttpRequest();
        }
        return new XMLHttpRequest();
    },

    unionArray: function (elem, separator, unique) {
        // $.map not working in IE 8, so iterating through items
        var result = [];
        for (var i = 0; i < elem.length; i++) {
            var text = util.trim(elem.eq(i).text());
            if (text != "" && (!unique || result.indexOf(text) == -1)) {
                result.push(text);
            }
        }
        return result.join(separator);
    }
};
