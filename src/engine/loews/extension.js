var plugin = {

    hosts: {
        'www.loewshotels.com': true,
        'login.loewshotels.com': true
    },

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.loewshotels.com/account';
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
        if ($('body.login form:has(input[id = "email"])').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a.logout[href *= "/account/logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        //var number = util.findRegExp($('p.account-number').text(), /:\s*([\d+]+)/i);
        //browserAPI.log("number: " + number);
        var name = $('div.profile:eq(0) > p').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (name == account.properties.Name));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            var logout = $('a.youfirst-signout[href *= "/account/logout"]:visible');
            if (logout.length)
                logout.get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (
            typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId == 0
        ) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://gc.synxis.com/xbe/rez.aspx?chain=19776&start=searchres&template=CBE&shell=CBE';
            });
            return;
        }

        var form = $('body.login form:has(input[id = "email"])');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[id = "email"]').val(params.account.login);
            form.find('input[id = "password"]').val(params.account.password);
            // vue.js
            provider.eval(
                'function createNewEvent(eventName) {' +
                'var event;' +
                'if (typeof(Event) === "function") {' +
                '    event = new Event(eventName);' +
                '} else {' +
                '    event = document.createEvent("Event");' +
                '    event.initEvent(eventName, true, true);' +
                '}' +
                'return event;' +
                '}'+
                'var email = document.querySelector(\'input[id = "email"]\');' +
                'email.dispatchEvent(createNewEvent(\'input\')); email.dispatchEvent(createNewEvent(\'change\'));' +
                'var pass = document.querySelector(\'input[id = "password"]\');' +
                'pass.dispatchEvent(createNewEvent(\'input\')); pass.dispatchEvent(createNewEvent(\'change\'));'
            );

            provider.setNextStep('checkLoginErrors', function () {
                form.find('input#btn-login').click();
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 5000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('input[id = "btn-login"]');
        if (errors.length > 0 && errors.attr('data-content').length > 0) {
            provider.setError(errors.attr('data-content'));
        }
        else
            plugin.loginComplete(params);
    },

    getConfNoItinerary: function (params) {//todo: need to check
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('div[id = "V155_C1_LocateCustomerCntrl_SearchPanel"]');
        if (form.length > 0) {
            form.find('input[id = "V155_C1_LocateCustomerCntrl_ConfirmTextbox"]').val(properties.ConfNo);
            form.find('input[id = "V155_C1_LocateCustomerCntrl_EmailConfirmTextBox"]').val(properties.Email);
            provider.setNextStep('itLoginComplete', function() {
                form.find('input[name = "V155$C1$LocateCustomerCntrl$ConfirmSearchButton"]').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    loginComplete: function(params) {//todo: need to check
        browserAPI.log("loginComplete");
        if (
            typeof(params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId > 0
        ) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.loewshotels.com/account/stays';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var link = $('.stays-dashboard__stay-code:contains("' + confNo + '")').parent().next('.stays-dashboard__stay-row').find('button');
        if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function () {
                $([document.documentElement, document.body]).animate({
                    scrollTop: link.offset().top - 200
                }, 500);
                link.get(0).click();
            });
        }// if (link.length > 0)
        else
            provider.setError(util.errorMessages.itineraryNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
