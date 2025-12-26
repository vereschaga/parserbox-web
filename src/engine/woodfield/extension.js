var plugin = {

    hosts: {'www.lq.com': true, 'legacy.lq.com': true},
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    getStartingUrl: function(params){
        return 'https://www.lq.com/en/account/account-summary';
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
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
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        var number = $('div.small:contains("Member Number") + div:visible');
		if (number.length > 0 && util.filter(number.text()) != "") {
			browserAPI.log('Logged in');
			return true;
		}
		if ($('form[name = "loginForm"]:visible').length > 0) {
			browserAPI.log('Not logged in');
			return false;
		}
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
		var number = util.filter($('div.small:contains("Member Number") + div:visible').text());
		browserAPI.log('number: ' + number);
		return (((typeof(account.properties) != 'undefined')
			&& (typeof(account.properties.MemberNumber) != 'undefined')
			&& (account.properties.MemberNumber != '')
            && number
			&& (number == account.properties.MemberNumber)));
	},

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a:contains("Logout")').get(0).click();
            setTimeout(function () {
                plugin.loadLoginForm(params);
            }, 3000)
        });
    },

	login: function (params) {
		browserAPI.log("login");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://www.lq.com/en";
            });
            return;
        }// if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0)

		var form = $('form[name = "loginForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "loginEmail"]').val(params.account.login);
            form.find('input[name = "loginLastName"]').val(params.account.login2);
            form.find('input[name = "loginPassword"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
            //     form.find('a[title = "Login"]').get(0).click();
                // angularjs
                provider.eval("var scope = angular.element(document.querySelector('input[name = \"loginEmail\"]')).scope();" +
                    "scope.$apply(function(){" +
                    "scope.signin.returnsId = '" + params.account.login + "';" +
                    "scope.signin.lastName = '" + params.account.login2 + "';" +
                    "scope.signin.password = '" + params.account.password + "';" +
                    "scope.signin.submit();" +
                    "});"
                );
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
		var errors = $('#loginServerError:visible');
		if (errors.length == 0 && !plugin.isLoggedIn())
            provider.setError(['We could not login you to La Quinta website for some reason. Code 10.', util.errorCodes.providerError]);
		if (errors.length > 0 && util.filter(errors.text()) != '')
            provider.setError(util.filter(errors.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log('loginComplete');
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.lq.com/en/account/my-reservations';
            });
            return;
        }
        // autologin complete
        if (params.autologin) {
            plugin.itLoginComplete(params);
            return;
        }
        plugin.loadAccount(params);
    },

    toItineraries: function(params) {
        browserAPI.log('toItineraries');
        var counter = 0;
        var confNo = params.account.properties.confirmationNumber;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('li.block__item:has(span[class *= "confirmation"]:contains("' + confNo + '"))').find('a:contains("View Details")');
            if (link.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                    provider.complete();
                });
            }// if (link.length > 0)
            if (counter > 30) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    getConfNoItinerary: function(params) {
        browserAPI.log('getConfNoItinerary');
        var properties = params.account.properties.confFields;
        // open form
        var formLink = $('a:contains("FIND RESERVATIONS")');
        if (formLink.length > 0)
            formLink.get(0).click();
        var form = $('form[name = "findReservationForm"]');
        if (form.length > 0) {
            form.find('input[name = "confirmationNumber"]').val(properties.ConfNo);
            form.find('input[name = "firstName"]').val(properties.FirstName);
            form.find('input[name = "lastName"]').val(properties.LastName);
            provider.setNextStep('itLoginComplete', function () {
                // angularjs
                provider.eval("var scope = angular.element(document.querySelector('input[name = \"confirmationNumber\"]')).scope();" +
                    "scope.$apply(function(){" +
                    "scope.findReservationData.data.confirmationNumber = '" + properties.ConfNo + "';" +
                    "scope.findReservationData.data.firstName = '" + properties.FirstName + "';" +
                    "scope.findReservationData.data.lastName = '" + properties.LastName + "';" +
                    "});"
                );
                $('button[name = "findReservationButton"]').click();
                provider.complete();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    }

    /*loadAccount: function (params) {
        browserAPI.log("loadAccount");
		// statementsummary.do throws JS error in IE
		// parse statementsummary.do
		//provider.setNextStep('parse');
		//document.location.href = 'https://www.lq.com/lq/returns/members/statements/statementsummary.do';

		// skip statementsummary.do
		plugin.parseFromMain(params);

        var d = new Date();
        var day = d.getDate();
        var month = d.getMonth() + 1;
        var year = d.getFullYear();
        var dateTo = year + ":" + month + ":" + day;
        var dateFrom = (year - 2) + ":" + month + ":" + day;

        provider.setNextStep('parseLastActivity', function () {
            document.location.href = 'https://www.lq.com/lq/returns/members/tablesorting.do?viewFor=' + dateFrom + '+00%3A00%3A00+-+' + dateTo + '+23%3A59%3A59&viewBy=All+Details&x=78&y=1300';
        });
    },

	parseFromMain: function(params) {
        browserAPI.log("parseFromMain");
		var data = {};
		var accInfo = $('div.rmliL p strong');
		var prop = util.trim(accInfo.eq(0).text());
		if (prop.length > 0)
			data.Name = util.beautifulName(prop);
		prop = util.trim(accInfo.eq(1).text());
		if (prop.length > 0)
			data.MemberNumber = prop;
		prop = util.trim(accInfo.eq(2).text());
		if (prop.length > 0)
			data.MemberStatus = prop;
		prop = util.trim(accInfo.eq(3).text());
		if (prop.length > 0)
			data.Balance = prop;
//		provider.saveProperties(data);
        params.data.properties = data;
	},

	parseRow: function(caption) {
		return $('td.account-data-header:contains("' + caption + '") + td').text();
	},

    parse: function (params) {
        browserAPI.log("parse");
        var data = {};
		var property = plugin.parseRow('Nights Needed to Reach Next Member Level');
		if (property.length > 0) {
			data.NightNeeded = util.trim(property);
			browserAPI.log('Nights Needed: ' + data.NightNeeded);
		}
		property = plugin.parseRow('Account Adjustments');
		if (property.length > 0) {
			data.AccountAdjustments = util.trim(property);
			browserAPI.log('Account Adjustments: ' + data.AccountAdjustments);
		}
		property = plugin.parseRow('YTD Points Earned');
		if (property.length > 0) {
			data.YTDPointsEarned = util.trim(property);
			browserAPI.log('YTD Points Earned: ' + data.YTDPointsEarned);
		}
		property = plugin.parseRow('YTD Bonus Points');
		if (property.length > 0) {
			data.YTDBonusPoints = util.trim(property);
			browserAPI.log('YTD Bonus Points: ' + data.YTDBonusPoints);
		}
		property = plugin.parseRow('YTD Points Redeemed');
		if (property.length > 0) {
			data.YTDPointsRedeemed = util.trim(property);
			browserAPI.log('YTD Points Redeemed ' + data.YTDPointsRedeemed);
		}
		property = plugin.parseRow('Current Point Balance');
		if (property.length > 0) {
			data.Balance = util.trim(property);
			browserAPI.log('Balance: ' + data.Balance);
		}
		property = $('div.rmliL p strong:eq(1)').text();
		if (property.length > 0) {
			data.MemberNumber = util.trim(property);
			browserAPI.log('Member Number: ' + data.MemberNumber);
		}
		property = $('div.rmliL p strong:eq(2)').text();
		if (property.length > 0) {
			data.MemberStatus = util.trim(property);
			browserAPI.log("Member Status: " + data.MemberStatus);
		}
		provider.saveProperties(data);
		if (plugin.checkNoItineraries(params)) {
			browserAPI.log("No reservation");
			provider.complete();
			return;
		}
		provider.setNextStep('parseItineraries', function () {
            document.location.href = 'https://www.lq.com/lq/respath/changecancelsearch.do?action=OPEN&mode=view';
        });
    },

	checkNoItineraries: function(params) {
        browserAPI.log("checkNoItineraries");
		if ($('table:contains("no current reservations")').length > 0) {
			params.account.properties.Itineraries = [{ NoItineraries: true }];
			provider.saveProperties(params.account.properties);
			provider.complete();
			return true;
		}
		return false;
	},

    checkNoItinerariesOnItineraries: function(params) {
        browserAPI.log("checkNoItinerariesOnItineraries");
        var error = $('div.errmsg > div.advisory');
        if (error.length) {
            error = error.html();
            if (error.search("unable to locate a reservation") != -1) {
                browserAPI.log("No reservation");
                params.account.properties.Itineraries = [{ NoItineraries: true }];
                provider.saveProperties(params.account.properties);
                provider.complete();
                return true;
            }
        }
        return false;
    },

    parseLastActivity: function(params) {
        browserAPI.log("parseLastActivity");

        // Last Activity
        var nodes = $('table.acc-table tr:has(td)');
        if (nodes.length > 0) {
            nodes.each(function () {
                var lastActivity = util.trim($('td:eq(0)', $(this)).text());
                var points = util.trim($('td:eq(5)', $(this)).text());

                browserAPI.log("Date: " + lastActivity );
                browserAPI.log("Points: " + points );

                if ( (typeof(lastActivity) != 'undefined') && (lastActivity != '')
                        && (typeof(points) != 'undefined') && (points != '')){
                    browserAPI.log("Last Activity: " + lastActivity );
                    // Last Activity
                    params.data.properties.LastActivity = lastActivity;
                    var date = new Date(lastActivity + ' UTC');
                    // ExpirationDate = lastActivity" + "18 month"
                    date.setMonth(date.getMonth() + 18);
                    var unixtime =  date / 1000;
                    if ( date != 'NaN' ){
                        browserAPI.log("ExpirationDate = lastActivity + 18 month");
                        browserAPI.log("Expiration Date: " + date + " Unixtime: " + util.trim(unixtime) );
                        params.data.properties.AccountExpirationDate = unixtime;
                    }
                }
            });
            params.account.properties = params.data.properties;
            provider.saveProperties(params.account.properties);

            if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
                provider.setNextStep('parseItineraries', function () {
                    document.location.href = 'https://www.lq.com/lq/respath/changecancelsearch.do?action=OPEN&mode=view';
                });
            }
            else
                provider.complete();
        }else {
            browserAPI.log("Last Activity not found");
            provider.setNextStep('parseLastActivityFromItineraries', function () {
                document.location.href = 'https://www.lq.com/lq/respath/changecancelsearch.do?action=OPEN&mode=view';
            });
        }
    },

    parseLastActivityFromItineraries: function(params) {
        browserAPI.log("parseLastActivityFromItineraries");
        browserAPI.log("parse Last Activity from Itineraries");
        // last itinerary date (active or Checked Out)
        var lastActivity = util.trim($('table.viewResTable tr td.viewResTableL:contains("Active"),td.viewResTableL:contains("Checked Out")').parent('tr').children('td:eq(1)').text());
        if ((typeof(lastActivity) != 'undefined') && (lastActivity != '')) {
            browserAPI.log("Last Activity: " + lastActivity );
            // Last Activity
            params.data.properties.LastActivity = lastActivity;
            var date = new Date(lastActivity + ' UTC');
            // ExpirationDate = lastActivity" + "18 month"
            date.setMonth(date.getMonth() + 18);
            var unixtime =  date / 1000;
            if ( date != 'NaN' ){
                browserAPI.log("ExpirationDate = lastActivity + 18 month");
                browserAPI.log("Expiration Date: " + date + " Unixtime: " + util.trim(unixtime) );
                params.data.properties.AccountExpirationDate = unixtime;
            }
        }// if ((typeof(lastActivity) != 'undefined') && (lastActivity != ''))
        else
            browserAPI.log("Last Activity not found");

        params.account.properties = params.data.properties;
        provider.saveProperties(params.account.properties);
        if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
            if (document.location.href == 'https://www.lq.com/lq/respath/changecancelsearch.do?action=OPEN&mode=view')
                plugin.parseItineraries(params);
            else {
                provider.setNextStep('parseItineraries', function () {
                    document.location.href = 'https://www.lq.com/lq/respath/changecancelsearch.do?action=OPEN&mode=view';
                });
            }
        }
        else
            provider.complete();
    },

    parseItineraries: function(params){
        browserAPI.log("Parsing itineraries");
//		$('#th_3 a').click();
//		$('#th_2 a').click();
        if (plugin.checkNoItinerariesOnItineraries(params)) return;
		var now = new Date();
		params.data.links = [];
		params.data.cancelled = [];
		var its = $('table.viewResTable tr');
		for (var i = 0; i < its.length; i++) {
			var conf = its.eq(i).find('td.confNumber');
			if (conf.length > 0) {
				var checkIn = new Date(its.eq(i).find('td:eq(1)').text() + ' UTC');
				if (checkIn < now)
					browserAPI.log('Reservation #' + conf.text() + ': CheckIn in past');
				else {
					if (its.eq(i).find('span.resCancelled').length > 0) {
						browserAPI.log('Reservation #' + conf.text() + ': cancelled');
						params.data.cancelled.push(util.trim(conf.text()));
					}
					if (its.eq(i).find('span.resActive').length > 0) {
						var link = conf.find('a').attr('href');
						if (link.indexOf('https://') == -1)
							link = 'https://www.lq.com' + link;
						params.data.links.push(link);
					}
				}
			}
		}
		params.data.Itineraries = [];
		if (params.data.links.length > 0) {
			provider.setNextStep('parseItinerary', function () {
                document.location.href = params.data.links.pop();
            });
		}
		else {
			if (params.data.cancelled.length > 0) {
				params.account.properties.Reservations = [];
				var cancelled = params.data.cancelled;
				for (var ix in cancelled) {
					var cancelledIt = {
						ConfirmationNumber: cancelled[ix],
						Cancelled: true
					};
					params.account.properties.Reservations.push(cancelledIt);
				}
				provider.saveProperties(params.account.properties);
			}
			provider.complete();
		}
    },

	parseItRow: function(caption) {
		return util.trim($('td.content:contains("' + caption + '") + td.content').text());
	},

    parseItinerary: function(params){
        browserAPI.log("parseItinerary");
		var data = {};
		var field = plugin.parseItRow('Guest Name');
		if (field.length > 0) {
			data.GuestNames = field;
			browserAPI.log('Name: ' + field);
		}
		field = plugin.parseItRow('Confirmation Number');
		if (field.length > 0) {
			data.ConfirmationNumber = field;
			browserAPI.log('Conf number: ' + field);
		}
		field = $('td.content:contains("Hotel:") + td.content strong').text();
		if (field.length > 0) {
			data.HotelName = util.trim(field);
			browserAPI.log('Hotel Name: ' + field);
		}
		$('td.content:contains("Hotel:") + td.content *').empty();
		field = util.trim($('td.content:contains("Hotel:") + td.content').text());
		if (field.length > 0) {
			data.Address = field;
			browserAPI.log('Address: ' + field);
		}
		field = plugin.parseItRow('Hotel Phone');
		if (field.length > 0) {
			data.Phone = field;
			browserAPI.log('Phone: ' + field);
		}
		var checkInTime = plugin.parseItRow('Check-In Time');
		var checkOutTime = plugin.parseItRow('Check-Out Time');
		var checkInDate = plugin.parseItRow('Arrive:');
		var checkOutDate = plugin.parseItRow('Depart:');
		browserAPI.log('check in: ' + checkInDate + ' ' + checkInTime);
		browserAPI.log('check in: ' + checkOutDate + ' ' + checkOutTime);
		var checkIn = new Date(checkInDate + ' ' + checkInTime + ' UTC');
		var checkOut = new Date(checkOutDate + ' ' + checkOutTime + ' UTC');
		data.CheckInDate = checkIn.getTime() / 1000;
		data.CheckOutDate = checkOut.getTime() / 1000;
		browserAPI.log('Check In: ' + data.CheckInDate + '  ' + checkIn.toDateString());
		browserAPI.log('Check Out: ' + data.CheckOutDate + '  ' + checkOut.toDateString());
		field = plugin.parseItRow('Number of Rooms');
		if (field.length > 0) {
			data.Rooms = field;
			browserAPI.log('Rooms: ' + field);
		}
		field = plugin.parseItRow('Number of Adults');
		if (field.length > 0) {
			data.Guests = field;
			browserAPI.log('Guests: ' + field);
		}
		field = plugin.parseItRow('Number of Children');
		if (field.length > 0) {
			data.Kids = field;
			browserAPI.log('Kids: ' + field);
		}
		field = plugin.parseItRow('Rate Type:');
		if (field.length > 0) {
			data.RateType = field;
			browserAPI.log('Rate type: ' + field);
		}
		field = plugin.parseItRow('Cancellation:');
		if (field.length > 0) {
			data.CancellationPolicy = field;
			browserAPI.log("Cancellation Policy: " + field);
		}
		field = plugin.parseItRow('Room Type:');
		if (field.length > 0) {
			data.RoomType = field;
			browserAPI.log('Room Type: ' + field);
		}
		field = util.findRegExp($('td.content:contains("Total Amount:") + td.content').text(), /([\d\.]+)\s+[A-Z]+/);
		if (field) {
			data.Cost = field;
			browserAPI.log('Cost: ' + field);
		}
		field = util.findRegExp($('td.content:contains("Estimated Total") + td.content').text(), /([\d\.]+)\s+[A-Z]+/);
		if (field) {
			data.Total = field;
			browserAPI.log('Total: ' + field);
		}
		field = util.findRegExp($('td.content:contains("Total Amount:") + td.content').text(), /[\d\.]+\s+([A-Z]{3})/);
		if (field) {
			data.Currency = field;
			browserAPI.log('Currency: ' + field);
		}
		params.data.Itineraries.push(data);

		if (params.data.links.length == 0) {
			params.account.properties.Reservations = params.data.Itineraries;
			if (params.data.cancelled.length > 0) {
				var cancelled = params.data.cancelled;
				for (var i in cancelled) {
					var cancelledIt = {
						ConfirmationNumber: cancelled[i],
						Cancelled: true
					};
					params.account.properties.Reservations.push(cancelledIt);
				}
			}
			provider.saveProperties(params.account.properties);
			provider.complete();
		}
		else {
			provider.setNextStep('parseItinerary', function () {
                document.location.href = params.data.links.pop();
            });
		}

	}*/
}