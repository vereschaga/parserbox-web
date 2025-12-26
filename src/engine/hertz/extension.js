var plugin = {

    hosts: {
        'www.hertz.com'          : true,
        'www.hertz.co.uk'        : true,
        'hertz-prod.us.auth0.com': true,
    },
    
    cashbackLink: '',
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function(){
            document.location.href = plugin.getStartingUrl(params);
        });
    },

	getStartingUrl: function(params) {
		return "https://www.hertz.com/rentacar/reservation/home";
	},

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

	start: function(params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();

            if (isLoggedIn !== null && counter === 2) {
                let welcome = $('#headerWelcomeBox');
                if (welcome.length > 0) {
                    welcome.click();
                }
            }

            if (isLoggedIn !== null && counter > 3) {
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
        if ($('#logOut, #headerWelcomeBox').length > 0) {
			browserAPI.log("LoggedIn");
			return true;
		}
		if ($('#loginLink, #loginWidgetLoginButton, a:contains("Login/Sign-Up"):visible').length > 0 || $('div.login-box form:visible').length > 0) {
			browserAPI.log('not logged in');
			return false;
		}
        return null;
	},

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.findRegExp($('span.memberNumber, div[data-testid = "dropdownItem"] span:contains("#")').text(), /#\s*(\d+)/i);
        browserAPI.log("number: " + number);
        return typeof account.properties !== 'undefined'
               && typeof account.properties.MembershipNumber !== 'undefined'
               && account.properties.MembershipNumber !== ''
               && number === account.properties.MembershipNumber;
    },

    logout: function (params) {
        browserAPI.log("logout");
		provider.setNextStep('preLogin');
        let logout = $('#logOut a');

        if (logout.length === 0) {
            logout = $('.login-out a');
        }

        if (logout.length > 0) {
            logout.get(0).click();
            return;
        }

        let welcome = $('#headerWelcomeBox');
        if (logout.length === 0 && welcome.length > 0) {
            $('#headerWelcomeLogoutItem').get(0).click();

            setTimeout(function () {
                plugin.start(params);
            }, 3000);

            return;
        }

        document.location.href = 'https://www.hertz.com/rentacar/emember/submitLogout.do';
	},

	preLogin: function(params) {
        browserAPI.log("preLogin");
		provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
	},

	login: function(params){
		browserAPI.log("login");
		// open login form
        const loginBtn = $('#loginLink, #loginWidgetLoginButton, a:contains("Login/Sign-Up"):visible');

        if (loginBtn.length) {
            loginBtn.get(0).click();
        }

        // wait login form
        let counter = 0;
        let loginInterval = setInterval(function () {
            let form = $('form[name = "homeLogin"]:visible, div.login-box form:visible');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(loginInterval);
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "loginId"], input[id = "email"]').val(params.account.login);
                form.find('input[name = "password"], input[id = "password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('#loginBtn, #loginButton, #btn-login').click();
                    setTimeout(function () {
                        if ($('div#error-list li, #error-message:visible').length) {
                            plugin.checkLoginErrors(params);
                        }
                    }, 5000)
                });
                return;
            }

            let reactForm = $('#login-popdown-container:visible');
            if (reactForm.length > 0) {
                clearInterval(loginInterval);
                browserAPI.log("submitting saved credentials -> react form");

                function triggerInput(selector, enteredValue) {
                    const input = document.querySelector(selector);
                    const createEvent = function(name) {
                        var event = document.createEvent('Event');
                        event.initEvent(name, true, true);
                        return event;
                    };
                    input.dispatchEvent(createEvent('focus'));
                    input.value = enteredValue;
                    input.dispatchEvent(createEvent('change'));
                    input.dispatchEvent(createEvent('input'));
                    input.dispatchEvent(createEvent('blur'));
                }
                triggerInput('#loginUsername', '' + params.account.login );
                triggerInput('#loginPassword', '' + params.account.password);

                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(function () {
                        // util.sendEvent(form.find('button[id = "loginFormLoginButton"]').get(0), 'click');

                        const input = document.querySelector('#loginFormLoginButton');
                        const createEvent = function(name) {
                            var event = document.createEvent('Event');
                            event.initEvent(name, true, true);
                            return event;
                        };
                        input.dispatchEvent(createEvent('click'));

                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 7000);
                    }, 1000);
                });

                return;
            }

            if (counter > 10) {
                clearInterval(loginInterval);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
	},

	checkLoginErrors: function(params){
        browserAPI.log("checkLoginErrors");
        let errors = $('div#error-list li, div[class *= “LoginForm-module__notificationText__“]:visible, #error-message:visible');

		if (errors.length > 0 && util.filter(errors.text()) !== '') {
			provider.setError(errors.text());
		    return;
        }

        plugin.loginComplete(params);
	},

	loginComplete: function(params) {
        browserAPI.log("loginComplete");
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
			provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.hertz.com/rentacar/reservation/home?startRes=Y&forceResHomePage=Y&defaultTab=2';
            });
			return;
		}
        if (typeof(params.account.fromPartner) == 'string') {
            // refs #11711
            if (document.location.host == "www.hertz.co.uk") {
                // don't reopen page
                var info = { message: 'warning', reopen: false, style: 'none'};
                browserAPI.send("awardwallet", "info", info);
            }
            else
                setTimeout(provider.close, 1000);
        }
		provider.complete();
	},

	toItineraries: function(params) {
        browserAPI.log("toItineraries");
        provider.complete();
        return;
		var confNo = params.account.properties.confirmationNumber;
		var link = $('a.member-res-search:contains("' + confNo + '")');
		if (link.length > 0) {
			provider.complete();
			link[0].click();
		}
		else
            provider.setError(util.errorMessages.itineraryNotFound);
	}
}