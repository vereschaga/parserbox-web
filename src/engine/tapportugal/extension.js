var plugin = {

    hosts: {'www.flytap.com': true, 'book.flytap.com': true},
    mobileUserAgent: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Safari/537.36",

    cashbackLink      : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.flytap.com/en-us/customer-area/my-profile';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
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
            if (isLoggedIn === null && counter > 30) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 30)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('button.js-header-login-cta:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('button.root-header__menu-list-item__cta.js-logout-cta').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp( $('div.js-profile-tp').text(), /([^\|]+)/).replace(/^TP\s*/i, "");
        browserAPI.log("number: " + number);
        return ((typeof(account.AccountNumber) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && number
            && (number.toLowerCase() == number.properties.AccountNumber.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("logout");
/*
        if (provider.isMobile) {
            provider.setNextStep('start', function () {
                $.ajax({
                    url: 'https://www.flytap.com/api/LogoutAjax?sc_mark=US&sc_lang=en-US',
                    type: "POST",
                    async: false,
                    success: function (data) {
                        //data = $(data);
                        //console.log(data);
                        document.location.href = plugin.getStartingUrl(params);
                    }// success: function (data)
                });// $.ajax({
            });
        }
        else {
            */
            provider.setNextStep('loadLoginForm', function () {
                $('button.root-header__menu-list-item__cta.js-logout-cta').get(0).click();
            });
        // }
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                if(document.location.href === 'https://www.flytap.com/en-us/')
                    plugin.getConfNoItinerary(params);
                else
                    document.location.href = 'https://www.flytap.com/en-us/';
            });
            return;
        }

        const form = $('form#js-login-account');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        const login = params.account.login.replace(/^TP\s*/i, "");
        form.find('input[id = "login-user-account"]').val(login);
        form.find('input[id = "login-pass-account"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            $('#login-save-account-submit').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('#login-user-account-modal-error:visible');

        if (errors.length == 0)
            errors = $('#login-pass-account-modal-error:visible');
        if (errors.length == 0)
            errors = $('li.error-item:visible');

        if (errors.length > 0 && util.trim(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.flytap.com/en-us/customer-area/my-bookings';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var link = $('.booked-code > strong:contains("' + confNo + '")');
        if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function () {
                link.closest('.flights-info-content').find('button[id^="seeReservationDetails-"]').get(0).click();
            });
        }// if (link.length > 0)
        else
            provider.setError(util.errorMessages.itineraryNotFound);
    },


    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        setTimeout(function () {
            $('div.multitab__item-label:contains("My Trips")').click();
            var form = $('form[aria-label="Find an existing booking form"]');
            if (form.length > 0) {
                // angularjs
                provider.eval(
                    "function triggerInput(enteredName, enteredValue) {\n" +
                    "      const input = document.getElementById(enteredName);\n" +
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
                    "triggerInput('pnr', '" + properties.ConfNo + "');\n" +
                    "triggerInput('last_name', '" + properties.LastName + "');"
                );

                provider.setNextStep('itLoginComplete', function () {
                    setTimeout(function () {
                        //form.find('button:contains("Find Booking")').get(0).click();
                        var url = 'https://myb.flytap.com/my-bookings/details/' + properties.ConfNo + '/' + properties.LastName + '?source=flytap&market=us&language=en';
                        window.open(url, '_self');
                        plugin.itLoginComplete(params);
                    }, 300);
                });
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.itineraryFormNotFound);
        }, 1000);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
