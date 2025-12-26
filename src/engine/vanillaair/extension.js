var plugin = {
    hosts: {'www.vanilla-air.com': true},

    getStartingUrl: function (params) {
        return 'https://www.vanilla-air.com/en/my/auth/login.html';
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
        if ($('form.ng-pristine').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        // if ($('a[href *= logout]').length > 0) {
            // browserAPI.log("LoggedIn");
            // return true;
        // }
        return null;
    },

    isSameAccount: function (account) {
		browserAPI.log("isSameAccount");
		return false;
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        // var number = util.findRegExp( $('li:contains("ThankYou Account")').text(), /Account\s*([^<]+)/i);
        // browserAPI.log("number: " + number);
        // return ((typeof(account.properties) != 'undefined')
            // && (typeof(account.properties.AccountNumber) != 'undefined')
            // && (account.properties.AccountNumber != '')
            // && (number == account.properties.AccountNumber));
    },

    // logout: function () {
        // browserAPI.log("logout");
        // provider.setNextStep('start', function () {
            // document.location.href = 'https://www.thankyou.com/logout.jspx';
        // });
    // },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form.ng-pristine');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            util.sendEvent(form.find('input[formcontrolname="email"]').val(params.account.login).get(0), 'input');
            util.sendEvent(form.find('input[formcontrolname="password"]').val(params.account.password).get(0), 'input');
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[type="submit"]').get(0).click();
				setTimeout(function(){
					plugin.checkLoginErrors();
				},5000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('.mat-input-error');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            //provider.setNextStep('toItineraries', function() {
            //    document.location.href = 'https://www.vanilla-air.com/en/my/user/index.html';
            //});
            plugin.toItineraries(params);
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('td.dashboard_table_pnr_number_head_days:contains("'+ confNo +'")');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function(){
                    provider.eval("var windowOpen = window.open; window.open = function(url){windowOpen(url, '_self');}");
                    link.next('td.dashboard_table_pnr_number_head_buttons').find('span >a').get(0).click();
                });
            }
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};