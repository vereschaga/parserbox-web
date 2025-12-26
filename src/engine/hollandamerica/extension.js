var plugin = {

    hosts: {'/\\w+.hollandamerica.com/': true},

    getStartingUrl: function (params) {
        return 'https://www.hollandamerica.com/en_US/postbooking/mariner-status.html';
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
                        provider.complete();
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
        if ($('form#login-form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Log Out"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        return ((typeof(account.properties) !== 'undefined')
        && (typeof(account.properties.AccountNumber) !== 'undefined')
        && (account.properties.AccountNumber != '')
        && $('p.detail-text-value:contains("' + account.properties.AccountNumber + '")').length);
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a:contains("Log Out"):visible').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                // angularjs
                // provider.eval("angular.reloadWithDebugInfo();");
                provider.eval("window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;");
                var unixtime = Math.round(new Date().getTime() / 1000);
                document.location.href = 'https://book2.hollandamerica.com/secondaryFlow/login?' + unixtime;
            });
            return;
        }
        var form = $('form#login-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // reactjs
            provider.eval(
                "var FindReact = function (dom) {" +
                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                "        return dom[key];" +
                "    }" +
                "    return null;" +
                "};" +
                "FindReact(document.querySelector('input[id *= \"-login-email\"]')).onChange({target:{name:'email', value:'" + params.account.login + "'}});"
                + "FindReact(document.querySelector('input[id *= \"-login-password\"]')).onBlur({target:{name:'password', id:'password', value:'" + params.account.password + "'}});"
            );

            provider.setNextStep('checkLoginErrors', function () {
                form.find('input[type="submit"][value="Log In"], input[type="submit"][value="Ingresar"]').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 4000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.error-summary-wrapper > p');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('preloadItinerariesForm', function() {
                document.location.href = 'https://book2.hollandamerica.com/secondaryFlow/login';
            });
            return;
        }
        provider.complete();
    },

    preloadItinerariesForm: function (params) {
        browserAPI.log("preloadItinerariesForm");
        provider.setNextStep('itinerariesForm', function () {
            // angularjs
            // provider.eval("angular.reloadWithDebugInfo();");
            provider.eval("window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;");
            var unixtime = Math.round(new Date().getTime() / 1000);
            document.location.href = 'https://book2.hollandamerica.com/secondaryFlow/login?' + unixtime;
        });
    },

    itinerariesForm: function (params) {
        browserAPI.log("itinerariesForm");
        var form = $('form#my-account-login-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // angularjs
            var inputCode = (
                'scope = angular.element(document.querySelector("form[name = \'myAccountLogin\']")).scope();' +
                "scope.$apply(function(){" +
                "scope.myAccountLogin.emailMarinerId.$setViewValue('" + params.account.login + "', 'input');" +
                "scope.myAccountLogin.emailMarinerId.$render();" +
                "scope.myAccountLogin.password.$setViewValue('" + params.account.password + "', 'input');" +
                "scope.myAccountLogin.password.$render();" +
                "});"
            );
            provider.eval(inputCode);
            provider.setNextStep('toItineraries', function () {
                form.find('button#LOGINButton').get(0).click();
                setTimeout(function () {
                    plugin.toItineraries(params);
                }, 5000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var select = $('#LOGINBkgInput option[value*="'+ confNo +'"]');
        var link = $('#LOGINButton');
        if (select.length > 0 && link.length > 0) {
            var inputCode = (
                'scope = angular.element(document.querySelector("form[name = \'multiBookingLogin\']")).scope();' +
                "scope.$apply(function(){" +
                "scope.model.bookingNumber = '" + confNo + "';" +
                "});"
            );
            provider.eval(inputCode);
            provider.setNextStep('itLoginComplete', function(){
                link.get(0).click();
                setTimeout(function () {
                    plugin.itLoginComplete(params);
                }, 5000);
            });
        }// if (link.length > 0)
        else
            provider.setError(util.errorMessages.itineraryNotFound);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form[name="loginBookingForm"]');
        if (form.length > 0) {
            var inputCode = (
                'scope = angular.element(document.querySelector("form[name = \'loginBookingForm\']")).scope();' +
                "scope.$apply(function(){" +
                "scope.loginBookingForm.bookingNumber.$setViewValue('" + properties.ConfNo + "', 'input');" +
                "scope.loginBookingForm.bookingNumber.$render();" +
                "scope.loginBookingForm.lastName.$setViewValue('" + properties.LastName + "', 'input');" +
                "scope.loginBookingForm.lastName.$render();" +
                "});"
            );
            provider.eval(inputCode);
            provider.setNextStep('itLoginComplete', function(){
                $('#LOGINFindBkgButton').get(0).click();
                setTimeout(function () {
                    plugin.itLoginComplete(params);
                }, 5000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};