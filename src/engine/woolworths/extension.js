var plugin = {
    hideOnStart: true,
    //keepTabOpen: true,
    //clearCache: true,
    hosts : {
        'woolworthsrewards.com.au'     : true,
        'www.woolworthsrewards.com.au' : true
    },

    getStartingUrl : function (params) {
        return 'https://www.woolworthsrewards.com.au';
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
                    // if (plugin.isSameAccount(params.account))
                    //     provider.complete();
                    // else
                    //    plugin.logout(params);
				}
				else
					plugin.loadLoginForm(params);
			}
			if (isLoggedIn === null && counter > 10) {
				clearInterval(start);
                provider.logBody("lastPage");
				var maintenance = $('h2:contains("We are currently busy performing maintenance on our website."):visible');
                if (maintenance.length > 0) {
                    provider.setError([maintenance.text(), util.errorCodes.providerError], true);
                    return;
                }
				provider.setError(util.errorMessages.unknownLoginState);
				return;
			}
			counter++;
		}, 500);
	},

    isLoggedIn : function () {
        browserAPI.log('isLoggedIn');
        if ($("a[href='/login.html']:visible").length > 0) {
            browserAPI.log('isLogged: false');
            return false;
        }
        if ($('a:contains("My account"):visible').length>0) {
            browserAPI.log('isLogged: true');
            return true;
        }
        return null;
    },

    logout : function (params) {
        browserAPI.log('logout');
		return;
		$('a:contains("Logout")').get(0).click();
        setTimeout(function () {
            plugin.start(params);
        }, 2000);
    },

    loadLoginForm: function () {
        browserAPI.log('loadLoginForm');
        provider.logBody("loadLoginForm");
        provider.setNextStep('login', function () {
            var login = $('.primary-nav a[href="/login.html"]:visible');
            if (login.length) {
                provider.eval(
                    "var scope = angular.element(document.querySelector('a[href=\"/login.html\"]')).scope();" +
                    "scope.isActiveMenu('/content/woolworths-rewards/en/home/login.html');"
                );
                setTimeout(function () {
                    login.get(0).click();
                }, 1000);
            }
        });
    },

    login: function (params) {
        browserAPI.log('login');
        provider.logBody("login");
        $('.wowContentContainer.section div.ng-hide').removeClass('ng-hide');
        var form = $('form#form-login');
		if (form.length > 0) {
			browserAPI.log("submitting saved credentials");
			 form.find('input#username').val(params.account.login);
			 //util.sendEvent(form.find('input#username').get(0), 'input');
             util.setInputValue( form.find('input#password'), params.account.password);
			// util.sendEvent(form.find('input#password').get(0), 'input');

            provider.eval("" +
                "var username = '" + params.account.login + "';" +
                "var password = '" + form.find('input#password').val() + "';" +
                "var scope = angular.element(document.querySelector('form[name = \"loginForm\"]')).scope();" +
                "scope.login.username = username;" +
                "scope.login.password = password;" +

                "scope.loginForm.password.$$lastCommittedViewValue = password;" +
                "scope.loginForm.password.$$rawModelValue = password;" +
                "scope.loginForm.password.$modelValue = password;" +
                "scope.loginForm.password.$viewValue = password;" +
                "scope.loginForm.password.$validate();" +

                "scope.loginForm.username.$$lastCommittedViewValue = username;" +
                "scope.loginForm.username.$$rawModelValue = username;" +
                "scope.loginForm.username.$modelValue = username;" +
                "scope.loginForm.username.$viewValue = username;" +
                "scope.loginForm.username.$validate();");

            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
                    form.find('#login-btn').get(0).click();
                    util.waitFor({
                        selector: '.tile:contains("POINTS EARNED"):visible',
                        success: function (elem) {
                            setTimeout(function () {
                                plugin.checkLoginErrors(params);
                            }, 2000);
                        },
                        fail: function () {
                            plugin.checkLoginErrors(params);
                        },
                        timeout: 10
                    });

                    //Request Blocked
                    // var to = 10;
                    // var interval = setInterval(function () {
                    //     if ($('#login-btn:visible').length == 0 || $('#login-btn').is(':not(.active)')) {
                    //         clearInterval(interval);
                    //         plugin.checkLoginErrors(params);
                    //     } else {
                    //         to = to - 1;
                    //         if (to == 0) {
                    //             clearInterval(interval);
                    //             plugin.checkLoginErrors(params);
                    //         }
                    //     }
                    // }, 500);
                }, 500);
            });
		}
		else {
            provider.logBody("lastPage");
            if (util.trim($('body').html()) === '' || $('link[rel="shortcut icon"][href="about:blank"]').length) {
                browserAPI.log("Retry for empty body");
                provider.setNextStep('start', function () {
                    document.location.href = plugin.getStartingUrl(params);
                });
            } else
                provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors : function (params) {
        browserAPI.log('checkLoginErrors');
        var error = $('#error-div font:visible');
        if (error.length && '' !== util.trim(error.text())) {
            if (error.text().indexOf('We had some trouble processing your request') !== -1)
                provider.setError([error.text(), util.errorCodes.providerError], true);
            else
                provider.setError(error.text());
        } else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (params.autologin) {
            provider.complete();
            return;
        }

        plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.logBody("login");
        provider.updateAccountMessage();
        // https://www.woolworthsrewards.com.au/index.html#/my-offers
        browserAPI.log('Current URL: ' + document.location.href);

        var data = {};
        // Balance - Points earned
        var balance = $('.tile:not(.ng-hide) div:contains("POINTS EARNED")').prev('div.orange-color');
        if (balance.length > 0) {
            browserAPI.log("Balance: " + balance.text());
            data.Balance = balance.text();
        } else
            browserAPI.log("Balance is not found");

        // AmountRedeemed - Woolworths Dollars redeemed
        var amountRedeemed = $('.tile:not(.ng-hide) div:contains("Woolworths Dollars redeemed")').prev('div.orange-color');
        if (amountRedeemed.length > 0) {
            browserAPI.log("AmountRedeemed: " + amountRedeemed.text());
            data.AmountRedeemed = amountRedeemed.text();
        } else
            browserAPI.log("AmountRedeemed is not found");

        // FuelDiscounts - CURRENT FUEL DISCOUNTS
        var fuelDiscounts = $('.tile:not(.ng-hide) div:contains("CURRENT FUEL")').prev('div.orange-color');
        if (fuelDiscounts.length > 0) {
            browserAPI.log("FuelDiscounts: " + fuelDiscounts.text());
            data.FuelDiscounts = fuelDiscounts.text();
        } else
            browserAPI.log("FuelDiscounts is not found");

        // DollarsToConvert - WOOLWORTHS DOLLARS TO CONVERT
        var dollarsToConvert = $('.orange-tile:not(.ng-hide) div:contains("WOOLWORTHS DOLLARS TO CONVERT")').prev('div.tile-font-upper');
        if (dollarsToConvert.length > 0) {
            browserAPI.log("DollarsToConvert: " + dollarsToConvert.text());
            data.DollarsToConvert = dollarsToConvert.text();
        } else
            browserAPI.log("DollarsToConvert is not found");


        // save data
        params.data.properties = data;
        //provider.saveTemp(params.data);

        provider.setNextStep('parseCardsAccount', function () {
            document.location.href = 'https://www.woolworthsrewards.com.au/index.html#/cards-account';
            // setTimeout(function () {
            //     util.waitFor({
            //         selector: '.card-primary .card-info h4:visible',
            //         success: function () {
            //             plugin.parseCardsAccount(params);
            //         }
            //     });
            // }, 3000);
        });
    },

    parseCardsAccount: function (params) {
        browserAPI.log("parseCardsAccount");
        provider.logBody("parseCardsAccount");
        //provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);
        var data = params.data.properties;

        // Name
        var name = $('.card-primary .card-info h4');
        if (name.length > 0) {
            name = util.beautifulName(name.text());
            browserAPI.log("Name: " + name);
            data.Name = name;
        } else
            browserAPI.log("Name is not found");

        // CardNumber
        var cardNumber = $('.card-primary .card-info .number');
        if (cardNumber.length > 0) {
            browserAPI.log("CardNumber: " + cardNumber.text());
            data.CardNumber = cardNumber.text();
        } else
            browserAPI.log("CardNumber is not found");

        // Save properties
        params.account.properties = data;
        //console.log(params.account.properties);
        provider.saveProperties(params.account.properties);
        provider.complete();
    }
};