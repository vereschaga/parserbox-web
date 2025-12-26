var plugin = {

    hideOnStart: true,
    clearCache: true,
    // keepTabOpen: true, // todo
    hosts: {'www.southwest.com': true, 'global.southwest.com': true},

    getStartingUrl: function (params) {
        return 'https://www.southwest.com/loyalty/myaccount/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        if (params.account.mode == 'confirmation') {
            provider.setNextStep('checkConfirmationNumberInternal', function () {
                document.location.href = "https://www.southwest.com/air/manage-reservation/";
            });
            return;
        }// if (params.account.mode == 'confirmation')
        // Your session has expired
        if ($('ul#errors li:contains("Your session has expired"):visible').length > 0) {
            provider.setNextStep('login', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
            return;
        }// if ($('ul#errors li:contains("Your session has expired"):visible').length > 0)
        if (plugin.isLoggedIn(params)) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout(params);
        } else {
            plugin.login(params);
        }
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('ul li button:contains("Log out"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#pageContent form[class*="form__"]').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        //span[@class="accountNumber"]/span[contains(text(),"RR#")]
        let number = util.findRegExp($('span.accountNumber span:contains("RR#")').text(), /#\s*(\w+)$/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && number
            && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('button:contains("Log out"):visible').click();
            setTimeout(function (){
                plugin.start(params);
            }, 2000);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary', function() {
                document.location.href = 'https://www.southwest.com/air/manage-reservation/';
            });
            return;
        }

        const form = $('#pageContent form[class*="form__"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return false;
        }

        browserAPI.log("submitting saved credentials");
        var input1 = form.find('input#username');
        input1.val(params.account.login);
        var input2 = form.find('input#password');
        input2.val(params.account.password.substring(0, 16));
        util.sendEvent(input1.get(0), 'input');
        util.sendEvent(input2.get(0), 'input');

        // form.find('input#username').val(params.account.login);
        // // truncating password to 16 chars
        // util.setInputValue(form.find('input#password'), params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            if (params.account.password === '') {
                provider.complete();
                return;
            }

            const button = form.find('button[id = "submit"]').get(0);
            util.sendEvent(button, 'click');
            button.click();

            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.login-form--error:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return false;
        }

        plugin.loginComplete(params);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form.confirmation-number-form');
        if (form.length > 0) {
            var input1 = form.find('input#confirmationNumber');
            input1.val(properties.ConfNo);
            var input2 = form.find('input#passengerFirstName');
            input2.val(properties.FirstName);
            var input3 = form.find('input#passengerLastName');
            input3.val(properties.LastName);
            util.sendEvent(input1.get(0), 'input');
            util.sendEvent(input2.get(0), 'input');
            util.sendEvent(input3.get(0), 'input');
            provider.setNextStep('itLoginComplete', function() {
                $('button#form-mixin--submit-button').click();
            });
        } else {
            provider.setError(util.errorMessages.itineraryFormNotFound);
        }
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        //if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
        //    provider.setNextStep('toItineraries');
        //    document.location.href = 'https://www.southwest.com/loyalty/myaccount/trips.html';
        //    return;
        //}

        // parse account
        if (params.autologin) {
            provider.complete();
            return;
        }

        plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("parse");
        var data = {};
        // Balance - Available Pts
        var balance = $('div.availablePointsNumber');
        if (balance.length > 0) {
            data.Balance = util.trim(balance.text());
            browserAPI.log("Balance: " + data.Balance);
        } else
            browserAPI.log("Balance not found");
        // Rapid Rewards Member
        var number = $('span.jb-account_bar_rr_number');
        if (number.length > 0) {
            data.Number = util.findRegExp(number.text(), /\#\s*(\d+)/i);
            browserAPI.log("Rapid Rewards #: " + data.Number);
        } else
            browserAPI.log("Rapid Rewards # not found");
        // Name
        var name = $('span.global_account_bar_login_form_name');
        if (name.length > 0) {
            data.Name = util.trim(util.filter(name.text()));
            browserAPI.log("Name: " + data.Name);
        } else
            browserAPI.log("Name not found");
        // Last Activity
        var lastActivity = $('span.global-account-bar-login-last-activity');
        if (lastActivity.length > 0) {
            data.LastActivity = util.findRegExp(lastActivity.text(), /:\s*([^<]+)/i);
            browserAPI.log("Last Activity: " + data.LastActivity);
        } else
            browserAPI.log("Last Activity not found");

        // Full Name
        $.ajax({
            url: 'https://www.southwest.com/flight/apiSecure/customer/profile?_=' + new Date().getTime(),
            async: false,
            success: function (summaryResponse) {
                browserAPI.log("parse profile info");
                summaryResponse = $(summaryResponse);
                //console.log(summaryResponse);//todo
                if (typeof (summaryResponse[0].fullName) != 'undefined') {
                    data.Name = summaryResponse[0].fullName;
                    browserAPI.log("Name: " + data.Name);
                }// if (typeof (summaryResponse[0].trips) != 'undefined')
            }// success: function (data)
        });// $.ajax({

        params.account.properties = data;
        provider.saveProperties(data);
        //console.log(params.account.properties);//todo

        if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
            if (document.location.href != 'https://www.southwest.com/myaccount/trips/upcoming') {
                provider.setNextStep('parseItineraries', function () {
                    document.location.href = 'https://www.southwest.com/myaccount/trips/upcoming';
                });
            }// if (document.location.href != 'https://www.southwest.com/myaccount/trips/upcoming')
            else
                plugin.parseItineraries(params);
        }// if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries)
        else
            provider.complete();
    },

    parseItineraries: function(params) {
        browserAPI.log("parseItineraries");
        // set Balance
        //params.account.properties.Balance = 'null';
        provider.saveProperties(params.account.properties);
        //// no Itineraries
        //if ( util.findRegExp( $('div#sw_content').text() , /(There are no Upcoming Trips at this moment\.)/i) ) {
        //    params.account.properties.Itineraries = [{ NoItineraries: true }];
        //    //console.log(params.account.properties);
        //    provider.saveProperties(params.account.properties);
        //    provider.complete();
        //    return;
        //}
        //
        params.data.Itineraries = [];
        params.data.Reservations = [];
        params.data.Rentals = [];

        var counter = 0;
        var parseItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            // if the page completely loaded
            if ($('h3:contains("Upcoming Trip"):visible').length > 0) {

                clearInterval(parseItineraries);

                $.ajax({
                    url: 'https://www.southwest.com/flight/apiSecure/upcoming-trips/account-view/summary?_=' + new Date().getTime(),
                    async: false,
                    success: function (summaryResponse) {
                        browserAPI.log("parseItinerary");
                        summaryResponse = $(summaryResponse);
                        //console.log(summaryResponse[0].trips);

                        if (typeof (summaryResponse[0].trips) != 'undefined') {
                            var i = 0;
                            //for(var i = 0; i < summaryResponse[0].trips.length; i++) {
                            for (var itinerary in summaryResponse[0].trips) {
                                if (summaryResponse[0].trips.hasOwnProperty(itinerary)) {
                                    var node = summaryResponse[0].trips[itinerary];
                                    //console.log(node);
                                    browserAPI.log(">>> Itinerary " + i);

                                    if (typeof (node.tripDetailsPath) == 'undefined') {
                                        browserAPI.log(">>> Skip bad itinerary" + i + " " + JSON.stringify(node));
                                        continue;
                                    }

                                    // details link
                                    var detailsLink = 'https://www.southwest.com/flight/apiSecure/upcoming-trips/account-view/' + node.tripDetailsPath;
                                    browserAPI.log("Link -> " + detailsLink);
                                    $.ajax({
                                        url: detailsLink,
                                        async: false,
                                        success: function (data) {
                                            browserAPI.log("parseItinerary");

                                            data = $(data);
                                            //console.log("---------------- data ----------------");
                                            //console.log(data[0].trip.products);
                                            //console.log("---------------- data ----------------");

                                            if (typeof (data[0].trip.products) != 'undefined')
                                                for (var itinerary in data[0].trip.products) {
                                                    if (data[0].trip.products.hasOwnProperty(itinerary)) {
                                                        //console.log(data[0].trip.products[itinerary]);
                                                        switch (data[0].trip.products[itinerary].type) {
                                                            case 'AIR':
                                                                params.data.Itineraries.push(plugin.parseJsonTrip(params, data[0].trip.products[itinerary]));
                                                                break;
                                                            case 'HOTEL':
                                                                params.data.Reservations.push(plugin.parseJsonHotel(params, data[0].trip.products[itinerary]));
                                                                break;
                                                            case 'CAR':
                                                                params.data.Rentals.push(plugin.parseJsonCar(params, data[0].trip.products[itinerary]));
                                                                break;
                                                            default:
                                                                browserAPI.log("Unknown itinerary type -> " + data[0].trip.products[itinerary].type);
                                                        }
                                                    }// if (data.trip.products.hasOwnProperty(itinerary))
                                                }// for (var itinerary in data.trip.products)

                                            browserAPI.log("<<< Itinerary " + i);
                                        }// success: function (data)
                                    });// $.ajax({
                                    i++;
                                }// if (summaryResponse[0].trips.hasOwnProperty(itinerary))
                            }// for(var i = 0; i < summaryResponse[0].trips.length; i++)
                        }// if (typeof (summaryResponse[0].trips) != 'undefined')
                    }// success: function (data)
                });// $.ajax({

                browserAPI.log(">>> success");
                params.account.properties.Itineraries = params.data.Itineraries;
                params.account.properties.Reservations = params.data.Reservations;
                params.account.properties.Rentals = params.data.Rentals;
                console.log(params.account.properties);
                provider.saveProperties(params.account.properties);
                provider.complete();
                return;
            }
            // no Itineraries
            if ($('h3:contains("You have no upcoming trips."):visible').length > 0) {
                browserAPI.log(">>> no Itineraries");
                clearInterval(parseItineraries);
                params.account.properties.Itineraries = [{ NoItineraries: true }];
                params.account.properties.Reservations = [{ NoItineraries: true }];
                params.account.properties.Rentals = [{ NoItineraries: true }];
                //console.log(params.account.properties);
                provider.saveProperties(params.account.properties);
                provider.complete();
                return;
            }
            if (counter > 15) {
                clearInterval(parseItineraries);
                //browserAPI.log(">>> complete");
                params.account.properties.Itineraries = params.data.Itineraries;
                //console.log(params.account.properties);
                provider.saveProperties(params.account.properties);
                provider.complete();
            }
            counter++;
        }, 1000);
    },

    parseJsonCar: function (params, itinerary) {
        browserAPI.log("parseJsonCar");

        var result = {};

        // ConfirmationNumber
        result.Number = itinerary.carCompanyName;
        browserAPI.log("Number: "+ result.Number);
        // RentalCompany
        result.RentalCompany = itinerary.carCompanyName;
        browserAPI.log("RentalCompany: "+ result.RentalCompany);
        // CarType
        result.CarType = itinerary.carType;
        browserAPI.log("CarType: " + result.CarType);
        // CarModel
        result.CarModel = itinerary.carDescription;
        browserAPI.log("CarModel: " + result.CarModel);
        // PickupLocation
        result.PickupLocation = itinerary.pickupLocation;
        browserAPI.log("PickupLocation: " + result.PickupLocation);
        // DropoffLocation
        result.DropoffLocation = (typeof (itinerary.dropOffLocation) != 'undefined') ? itinerary.dropOffLocation : result.PickupLocation;
        browserAPI.log("DropoffLocation: " + result.DropoffLocation);

        // PickupDatetime
        var pickupDatetime = util.findRegExp( itinerary.pickupDateTime, /(.+)\:00\.000$/);
        pickupDatetime = util.trim(pickupDatetime.replace("T", " "));
        browserAPI.log("PickupDatetime: " + pickupDatetime);
        pickupDatetime = new Date(pickupDatetime + ' UTC');
        var unixtime = pickupDatetime / 1000;
        if (!isNaN(unixtime)) {
            browserAPI.log("PickupDatetime: " + pickupDatetime + " Unixtime: " + unixtime);
            result.PickupDatetime = unixtime;
        }else
            browserAPI.log(">>> Invalid PickupDatetime");
        // DropoffDatetime
        var dropoffDatetime = util.findRegExp( itinerary.dropOffDateTime, /(.+)\:00\.000$/);
        dropoffDatetime = util.trim(dropoffDatetime.replace("T", " "));
        browserAPI.log("DropoffDatetime: " + dropoffDatetime);
        dropoffDatetime = new Date(dropoffDatetime + ' UTC');
        unixtime = dropoffDatetime / 1000;
        if (!isNaN(unixtime)) {
            browserAPI.log("DropoffDatetime: " + dropoffDatetime + " Unixtime: " + unixtime);
            result.DropoffDatetime = unixtime;
        }else
            browserAPI.log(">>> Invalid DropoffDatetime");
        // TotalCharge
        result.TotalCharge = itinerary.estimatedTotal;
        browserAPI.log("TotalCharge: " + result.TotalCharge);
        // TotalTaxAmount
        result.TotalTaxAmount = itinerary.taxesAndFees;
        browserAPI.log("TotalTaxAmount: " + result.TotalTaxAmount);
        // RenterName
        result.RenterName = util.beautifulName(itinerary.driverFirstName + " " + itinerary.driverLastName);
        browserAPI.log("RenterName: " + result.RenterName);
        // Currency
        result.Currency = 'USD';
        browserAPI.log("Currency: "+ result.Currency);

        return result;
    },

    parseJsonHotel: function (params, itinerary) {
        browserAPI.log("parseJsonHotel");

        var result = {};

        // ConfirmationNumber
        result.ConfirmationNumber = itinerary.confirmationNumber;
        browserAPI.log("ConfirmationNumber: "+ result.ConfirmationNumber);
        // HotelName
        result.HotelName = itinerary.name;
        browserAPI.log("HotelName: "+ result.HotelName);
        // CheckInDate
        var date = new Date(itinerary.checkinInfo + ' UTC');
        if (date)
            result.CheckInDate = date / 1000;
        browserAPI.log("CheckInDate: " + itinerary.checkinInfo + ' / ' + result.CheckInDate);
        // CheckInDate
        date = new Date(itinerary.checkOutInfo + ' UTC');
        if (date)
            result.CheckOutDate = date / 1000;
        browserAPI.log("CheckOutDate: " + itinerary.checkOutInfo + ' / ' + result.CheckOutDate);
        // Address
        result.Address = itinerary.address + ", " + itinerary.cityStateZipCountry;
        browserAPI.log("Address: "+ result.Address);
        // GuestNames
        result.GuestNames = util.findRegExp(itinerary.guestFirstName + " " + itinerary.guestLastName);
        browserAPI.log("GuestNames: "+ result.GuestNames);
        // AccountNumbers
        result.AccountNumbers = itinerary.guestRrNumber;
        browserAPI.log("AccountNumbers: "+ result.AccountNumbers);
        // Rooms
        result.Rooms = itinerary.numberOfRooms;
        browserAPI.log("Rooms: "+ result.Rooms);
        // CancellationPolicy
        result.CancellationPolicy = itinerary.cancellationPolicy;
        browserAPI.log("CancellationPolicy: "+ result.CancellationPolicy);
        // RoomTypeDescription
        result.RoomTypeDescription = itinerary.roomDescription;
        browserAPI.log("RoomTypeDescription: "+ result.RoomTypeDescription);
        // Taxes
        result.Total = plugin.number_format(itinerary.taxesAndFees, 2);
        browserAPI.log("Total: "+ result.Total);
        // Total
        result.Total = plugin.number_format(itinerary.totalCharges, 2);
        browserAPI.log("Total: "+ result.Total);
        // Currency
        result.Currency = 'USD';
        browserAPI.log("Currency: "+ result.Currency);

        return result;
    },

    parseJsonTrip: function (params, itinerary) {
        browserAPI.log("parseJsonTrip");

        var result = {};
        var DT = null;
        var unixtime = null;

        // RecordLocator
        result.RecordLocator = itinerary.confirmationNumber;
        browserAPI.log("ConfirmationNumber: " + result.RecordLocator);
        // Passengers
        var accountNumbers = [];
        result.Passengers = [];
        if (typeof (itinerary.passengers) != 'undefined')
        for (var passenger in itinerary.passengers) {
            if (itinerary.passengers.hasOwnProperty(passenger)) {
                result.Passengers.push(util.beautifulName(itinerary.passengers[passenger].firstName + ' ' + itinerary.passengers[passenger].lastName));
                if (itinerary.passengers[passenger].rapidRewardsNumber) {
                    console.log('number ' + itinerary.passengers[passenger].rapidRewardsNumber);
                    accountNumbers.push(itinerary.passengers[passenger].rapidRewardsNumber);
                }// if (itinerary.passengers[passenger].rapidRewardsNumber)
            }// if (itinerary.passengers.hasOwnProperty(passenger))
        }// for (var passenger in itinerary.passengers)
        result.Passengers = result.Passengers.join(', ');
        browserAPI.log("Passengers: " + result.Passengers);
        // AccountNumbers
        result.AccountNumbers = accountNumbers.join(', ');
        browserAPI.log("AccountNumbers: " + result.AccountNumbers);

        result.TripSegments = [];
        if (typeof (itinerary.originDestinations) != 'undefined') {
            browserAPI.log("Total " + itinerary.originDestinations.length + " slices were found");
            for (var originDestinations in itinerary.originDestinations) {
                if (itinerary.originDestinations.hasOwnProperty(originDestinations)) {
                    browserAPI.log("Total " + itinerary.originDestinations[originDestinations].segments.length + " segments were found");
                    for (var seg in itinerary.originDestinations[originDestinations].segments) {
                        if (itinerary.originDestinations[originDestinations].segments.hasOwnProperty(seg)) {
                            // segment should be object !!!
                            var segment = {};
                            browserAPI.log(">>> Segment " + seg);
                            // FlightNumber
                            segment.FlightNumber = itinerary.originDestinations[originDestinations].segments[seg].flightNumber;
                            browserAPI.log("FlightNumber: " + segment.FlightNumber);
                            // AirlineName
                            segment.AirlineName = itinerary.originDestinations[originDestinations].segments[seg].airlineName;
                            browserAPI.log("AirlineName: " + segment.AirlineName);
                            // Duration
                            var duration = itinerary.originDestinations[originDestinations].segments[seg].travelTime.split(':');
                            if (typeof (duration[0]) != 'undefined' && typeof (duration[1]) != 'undefined') {
                                segment.Duration = (duration[1].length == 2)
                                    ? duration[0] + ":" + duration[1] : duration[0] + ":0" + duration[1];
                            }
                            browserAPI.log("Duration: " + segment.Duration);
                            // Stops
                            segment.Stops = itinerary.originDestinations[originDestinations].numberOfStops;
                            browserAPI.log("Stops: " + segment.Stops);
                            // DepCode
                            segment.DepCode = itinerary.originDestinations[originDestinations].segments[seg].originAirportCode;
                            browserAPI.log("DepCode: " + segment.DepCode);
                            // DepName
                            segment.DepName = itinerary.originDestinations[originDestinations].segments[seg].originName;
                            browserAPI.log("DepName: " + segment.DepName);
                            // DepDate
                            var depD = itinerary.originDestinations[originDestinations].segments[seg].departureDateTime.split('T');
                            var depDate = depD[0].replace(/\-/g, '/');// ff, safari fix
                            browserAPI.log("depDate: " + depDate);
                            var depTime = depD[1].replace(/-.+/, '');
                            depTime = depTime.replace(/\.\d{3}$/, '');
                            browserAPI.log("depart time: " + depTime);
                            DT = depDate + ' ' + depTime;
                            DT = new Date(DT + ' UTC');
                            unixtime = DT / 1000;
                            if (!isNaN(unixtime)) {
                                browserAPI.log("DepDate: " + depDate + ' ' + depTime + " Unixtime: " + unixtime);
                                segment.DepDate = unixtime;
                            } else
                                browserAPI.log(">>> Invalid DepDate");
                            // ArrCode
                            segment.ArrCode = itinerary.originDestinations[originDestinations].segments[seg].destinationAirportCode;
                            browserAPI.log("ArrCode: " + segment.ArrCode);
                            // ArrName
                            segment["ArrName"] = itinerary.originDestinations[originDestinations].segments[seg].destinationName;
                            browserAPI.log("ArrName: " + segment.ArrName);
                            // ArrDate
                            var arrD = itinerary.originDestinations[originDestinations].segments[seg].arrivalDateTime.split('T');
                            var arrDate = arrD[0].replace(/\-/g, '/');// ff, safari fix
                            browserAPI.log("arrDate: " + arrDate);
                            var arrTime = arrD[1].replace(/-.+/, '');
                            arrTime = arrTime.replace(/\.\d{3}$/, '');
                            browserAPI.log("arrive time: " + arrTime);
                            DT = arrDate + ' ' + arrTime;
                            DT = new Date(DT + ' UTC');
                            unixtime = DT / 1000;
                            if (!isNaN(unixtime)) {
                                browserAPI.log("ArrDate: " + arrDate + ' ' + arrTime + " Unixtime: " + unixtime);
                                segment.ArrDate = unixtime;
                            } else
                                browserAPI.log(">>> Invalid ArrDate");

                            result.TripSegments.push(segment);

                            browserAPI.log("<<< Segment " + seg);
                        }// if (itinerary.originDestinations[originDestinations].segments.hasOwnProperty(seg))
                    }// for (var seg in itinerary.originDestinations[originDestinations].segments)
                }// if (itinerary.originDestinations.hasOwnProperty(originDestinations))
            }// for (var originDestinations in itinerary.originDestinations)
        }// if (typeof (itinerary.originDestinations) != 'undefined')

        return result;
    },

    parseItinerary: function(params){
        browserAPI.log("parseItinerary");
        // parse all itineraries
        var itNumber = 0;
        $('div.trip_itinerary_detail_table_container').each(function () {

            var itinerary = $(this);
            browserAPI.log(">>> Itinerary " + itNumber);

            var result = {};
            var depTime = null;
            var depDate = null;
            var DT = null;
            var arrTime = null;
            var arrDate = null;
            var unixtime = null;

            // RecordLocator
            result.RecordLocator = itinerary.find('span.confirmation_number').text();
            browserAPI.log("ConfirmationNumber: " + result.RecordLocator);
            // Passengers
            var passengerInfo = itinerary.find('table[class *= "passengers_table"]').children('tbody').children('tr');
            result.Passengers = util.findRegExp(plugin.unionArray( passengerInfo.children('th:eq(0)'), ', ', true));
            browserAPI.log("Passengers: "+ result.Passengers);
            // AccountNumbers
            var accountNumbers = passengerInfo.children('td:eq(0)');
            browserAPI.log("accountNumbers: "+ accountNumbers.length);
            var accounts = [];
            for (var an = 0; an < accountNumbers.length; an++) {
                var number = util.findRegExp( accountNumbers.eq(an).text(), /(\d+)/ );
                console.log('number ' + number);
                if (number && number.length > 3)
                    accounts.push(number);
            }// for (var an = 0; an < its.length; an++)
            result.AccountNumbers = accounts.join(', ');
            browserAPI.log("AccountNumbers: "+ result.AccountNumbers);

            result.TripSegments = [];
            // Segments
            var i = 0;
            itinerary.find('table[id *= "airItinerary"]').each(function () {
                var node = $(this);
                //console.log(node);
                browserAPI.log(">>> Slice " + i);
                var subSegments = node.find('td[class = "flightRouting flightRoutingCR1"]');
                var subSegmentsInfo = node.find('td.flightNumberLogo');
                var date = util.findRegExp( node.find('span.travelDateTime').text() , /\,\s*([^<]+)/);
                browserAPI.log(">>> Date: " + date);
                browserAPI.log('>>> Found ' + subSegments.length + ' segments');
                browserAPI.log('>>> Found ' + subSegmentsInfo.length + ' segments info');

                if (subSegments.length == subSegmentsInfo.length) {
                    for (var j = 0; j < subSegments.length; j++) {
                        browserAPI.log(">>> Segment " + j);
                        var segment = {};
                        // FlightNumber
                        segment.FlightNumber = util.findRegExp( subSegmentsInfo.eq(j).find('td.flightNumber strong').text() , /\#?(.+)/);
                        browserAPI.log("FlightNumber: " + segment.FlightNumber);
                        // AirlineName
                        segment.AirlineName = util.findRegExp( subSegmentsInfo.eq(j).find('td.flightLogo img').attr('alt') , /Operated\s*by\s*([^<]+)/i);
                        browserAPI.log("AirlineName: " + segment.AirlineName);
                        // Duration
                        segment.Duration = util.findRegExp( node.find('span.travelFlightDuration').text() , /Time\s*([^<]+)/i);
                        browserAPI.log("Duration: " + segment.Duration);
                        // Stops
                        segment.Stops = util.findRegExp( node.find('span.stops').text() , /(\d+)\s*stop/i);
                        if (segment.Stops === null && util.findRegExp( subSegmentsInfo.find('span.stops').text() , /(Nonstop)/i))
                            segment.Stops = 0;
                        browserAPI.log("Stops: " + segment.Stops);

                        // DepCode
                        segment.DepCode = util.findRegExp( subSegments.eq(j).find('tr:eq(0) td:eq(1)').text() , /\(([A-Z]{3})/);
                        browserAPI.log("DepCode: " + segment.DepCode);
                        // DepName
                        segment.DepName = util.findRegExp( subSegments.eq(j).find('tr:eq(0) td:eq(1) strong').text() , /([^\(]+)/i);
                        browserAPI.log("DepName: " + segment.DepName);
                        if (!segment.DepName) {
                            segment.DepName = util.findRegExp( subSegments.eq(j).find('tr:eq(0) td:eq(1)').text() , / in (.+)\([A-Z]{3}/i);
                            browserAPI.log("DepName: " + segment.DepName);
                        }
                        // DepDate
                        depTime = util.trim(subSegments.eq(j).find('tr:eq(0) td:eq(0)').text());
                        browserAPI.log("depart time: " + depTime);
                        depDate = date;
                        browserAPI.log("depart: " + depDate);
                        DT = date + ' ' + depTime;
                        DT = new Date(DT + ' UTC');
                        unixtime = DT / 1000;
                        if (!isNaN(unixtime)) {
                            browserAPI.log("DepDate: " + depDate + ' ' + depTime + " Unixtime: " + unixtime);
                            segment.DepDate = unixtime;
                        } else
                            browserAPI.log(">>> Invalid DepDate");
                        // ArrCode
                        segment.ArrCode = util.findRegExp( subSegments.eq(j).find('tr:eq(1) td:eq(1)').text() , /\(([A-Z]{3})/);
                        browserAPI.log("ArrCode: " + segment.ArrCode);
                        // ArrName
                        segment.ArrName = util.findRegExp( subSegments.eq(j).find('tr:eq(1) td:eq(1) strong').text() , /([^\(]+)/i);
                        browserAPI.log("ArrName: " + segment.ArrName);
                        if (!segment.ArrName) {
                            segment.ArrName = util.findRegExp( subSegments.eq(j).find('tr:eq(1) td:eq(1)').text() , / in (.+)\([A-Z]{3}/i);
                            browserAPI.log("ArrName: " + segment.ArrName);
                        }
                        // ArrDate
                        var arrTimeText = util.trim(subSegments.eq(j).find('tr:eq(1) td:eq(0)').text());
                        arrTime = util.findRegExp(arrTimeText, /(.+M)/);
                        var nextDay = util.findRegExp(arrTimeText, /(Next\s*Day)/i);
                        browserAPI.log("arrive time: " + arrTime);
                        browserAPI.log("Next Day: " + nextDay);
                        var arrDate = date;
                        browserAPI.log("arrDate: " + arrDate);
                        DT = arrDate + ' ' + arrTime;
                        DT = new Date(DT + ' UTC');
                        if (nextDay) {
                            browserAPI.log("Next day: +" + nextDay);
                            DT.setDate(DT.getDate() + 1);
                            browserAPI.log("Right ArrDate: " + DT);
                        }
                        unixtime = DT / 1000;
                        if (!isNaN(unixtime)) {
                            browserAPI.log("ArrDate: " + arrDate + ' ' + arrTime + " Unixtime: " + unixtime);
                            segment.ArrDate = unixtime;
                        } else
                            browserAPI.log(">>> Invalid ArrDate");

                        result.TripSegments.push(segment);
                        browserAPI.log("<<< Segment " + j);
                    }// for (var j = 0; j < subSegments.length; j++)
                }// if (subSegments.length == subSegmentsInfo.length)
                else
                    browserAPI.log('Skip bad node');

                browserAPI.log("<<< Slice " + i);
                i++;
            });

            console.log(result);
            params.data.Itineraries.push(result);
            browserAPI.log("<<< Itinerary " + itNumber);
            itNumber++;
        });

        if (params.data.links.length == 0) {
            params.account.properties.Itineraries = params.data.Itineraries;
            console.log(params.account.properties);
            provider.saveProperties(params.account.properties);
            provider.complete();
        }
        else {
            if(provider.isMobile){
                var nextLink = params.data.links.pop();
            }
            provider.setNextStep('parseItinerary', function () {
                if(provider.isMobile){
                    document.location.href = 'https://www.southwest.com' + nextLink;
                }else{
                    document.location.href = 'https://www.southwest.com' + params.data.links.pop();
                }
            });
            // save data
            provider.saveTemp(params.data);
        }
    },

    unionArray: function (elem, separator, unique) {
        // $.map not working in IE 8, so iterating through items
        var result = [];
        for (var i = 0; i < elem.length; i++) {
            var text = util.trim(elem.eq(i).text());
            if (text != "" && (!unique || result.indexOf(text) == -1))
                result.push(text);
        }
        return result.join(separator);
    },

    checkConfirmationNumberInternal: function (params) {
        browserAPI.log("checkConfirmationNumberInternal");
        var properties = params.account;
        // browserAPI.log('properties:');
        // browserAPI.log(JSON.stringify(properties));
        var form = $('form.confirmation-number-form');
        if (form.length > 0) {
            var input1 = form.find('input#confirmationNumber');
            input1.val(properties.ConfNo);
            var input2 = form.find('input#passengerFirstName');
            input2.val(properties.FirstName);
            var input3 = form.find('input#passengerLastName');
            input3.val(properties.LastName);
            util.sendEvent(input1.get(0), 'input');
            util.sendEvent(input2.get(0), 'input');
            util.sendEvent(input3.get(0), 'input');
            provider.setNextStep('parseItineraryByConfNoTimeout', function() {
                setTimeout(function () {
                    $('button#form-mixin--submit-button').get(0).click();
                    setTimeout(function () {
                        plugin.parseItineraryByConfNoTimeout(params);
                    }, 3000);
                }, 100);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound, true);
    },

    checkConfirmationNumberInternalClick: function (params) {
        browserAPI.log('checkConfirmationNumberInternalClick');
        $('button#form-mixin--submit-button').click();
        plugin.parseItineraryByConfNoTimeout(params);
    },

    parseItineraryByConfNoTimeout: function (params) {
        browserAPI.log("parseItineraryByConfNoTimeout");
        setTimeout(function () {
            plugin.parseItineraryByConfNo(params);
        }, 2000);
    },

    parseItineraryByConfNoAlaJson: function (params) {
        browserAPI.log("parseItineraryByConfNoAlaJson");
        // errors
        var error = (
            util.findRegExp($('ul#errors').text() , /(We were unable to retrieve your reservation.\.)/i) ||
            util.findRegExp($('div.message_error h2').text(), /(Passenger name entered does not match reservation\.)/i) ||
            util.findRegExp($('div.message_error li.page-error--message').text(), /(We were unable to retrieve your reservation from our database\.)/i)
        );
        if (error) {
            browserAPI.log("Error -> ".error);
            provider.setError(error, true);
            // provider.complete();
            return;
        }

        params.data.Itineraries = [];
        var itNumber = 0;

        var itinerary = $('div.air-reservation:first');

        var result = {};
        var depTime = null;
        var depDate = null;
        var DT = null;
        var arrTime = null;
        var arrDate = null;
        var unixtime = null;

        // RecordLocator
        result.RecordLocator = itinerary.find('div.toolbar--confirmation-number span.confirmation-number--code').text();
        browserAPI.log("ConfirmationNumber: " + result.RecordLocator);
        // Passengers
        var passengers = itinerary.find('div.reservation-name--person-name');
        result.Passengers = plugin.makeUniqueArray(passengers, true);
        // AccountNumbers
        let accounts = itinerary.find('span:contains("Rapid Rewards number"), .passenger-details span:contains("Rapid Rewards®/Acct #")');
        result.AccountNumbers = [];
        for (let i = 0; i < accounts.length; i++) {
            let number = util.findRegExp(accounts.eq(i).text().trim(), /(\w+)$/);
            if (!number || result.AccountNumbers.indexOf(number) !== -1)
                continue;
            result.AccountNumbers.push(number);
        }

        let tickets = itinerary.find('span:contains("Ticket #")');
        result.TicketNumbers = [];
        for (let i = 0; i < tickets.length; i++) {
            let ticket = util.findRegExp(tickets.eq(i).text().trim(), /(\w+)$/);
            if (!ticket || result.TicketNumbers.indexOf(ticket) !== -1)
                continue;
            result.TicketNumbers.push(ticket);
        }

        var cabins = $('td.passenger-details--fares');
        var uniqueCabins = plugin.makeUniqueArray(cabins);

        // TripSegments
        result.TripSegments = [];
        itinerary.find('div.flight-segments--departs').each(function() {
            var node = $(this);
            var segment = {};
            // FlightNumber
            segment.FlightNumber = node.find('span.flight-segments--flight-number').text();
            browserAPI.log("FlightNumber: " + segment.FlightNumber);
            // AirlineName
            segment.AirlineName = 'WN';
            // Aircraft
            segment.Aircraft = node.find('span[class *= "aircraft-section--description"]').text();
            browserAPI.log("Aircraft: " + segment.Aircraft);
            // DepCode
            segment.DepCode = node.find('span.flight-segments--airport-code').text();
            // ArrCode
            segment.ArrCode = node.next().next().find('span.flight-segments--airport-code').text();
            if (segment.ArrCode.length == 0)
                segment.ArrCode = node.next().find('span.flight-segments--airport-code').text();
            // DepDate
            var date = node.closest('section.flight-detail-content').find('span.flight-detail--heading-date').text();
            date = util.findRegExp(date, /(\d+\/\d+\/\d+)\s+/);
            browserAPI.log("departing date: " + date);
            var time1 = node.find('span.time--value').text();
            time1 = util.findRegExp(time1, /Departs\s+(.+)/);
            time1 = time1.replace(/(PM|AM)\b/, ' $1');
            depDate = date + ' ' + time1;
            browserAPI.log("depDate: " + depDate);
            DT = new Date(depDate + ' UTC');
            unixtime = DT / 1000;
            if (!isNaN(unixtime)) {
                browserAPI.log("DepDate: " + depDate + " Unixtime: " + unixtime);
                segment.DepDate = unixtime;
            } else
                browserAPI.log(">>> Invalid DepDate");
            // ArrDate
            var time2 = node.next().next().find('span.time--value').text();
            if (time2.length == 0)
                time2 = node.next().find('span.time--value').text();
            time2 = time2.replace(/(PM|AM)\b/, ' $1');
            time2 = util.findRegExp(time2, /Arrives\s+(.+)/);
            arrDate = date + ' ' + time2;
            browserAPI.log("arrDate: " + depDate);
            DT = new Date(arrDate + ' UTC');
            unixtime = DT / 1000;
            if (!isNaN(unixtime)) {
                browserAPI.log("ArrDate: " + arrDate + " Unixtime: " + unixtime);
                segment.ArrDate = unixtime;
            } else
                browserAPI.log(">>> Invalid DepDate");
            // Duration
            segment.Duration = node.next().next().find('span.flight-segments--total-duration').text();
            browserAPI.log("Duration: " + segment.Duration);

            if (segment.Duration.length === 0) {
                segment.Duration = node.next().find('span.flight-segments--total-duration').text().replace('hr', 'hr ');
                browserAPI.log("Duration: " + segment.Duration);
            }

            // Cabin
            segment.Cabin = uniqueCabins.length == 1 ? uniqueCabins[0] : null;
            browserAPI.log("Cabin: " + segment.Cabin);

            result.TripSegments.push(segment);
        });

        params.data.Itineraries.push(result);

        return false;
    },

    makeUniqueArray: function (collection, beautify) {
        var result = [];
        for (var i = 0; i < collection.length; i++) {
            var text = util.trim(collection.eq(i).text());
            if (text === "" || result.indexOf(text) !== -1)
                continue;
            if (beautify)
                text = util.beautifulName(text);
            result.push(text);
        }
        return result;
    },

    parseItineraryByConfNo: function (params) {
        browserAPI.log("parseItineraryByConfNo");
        // errors
        var error = util.findRegExp( $('ul#errors').text() , /(We were unable to retrieve your reservation from our database\.)/i);
        error = !error ? util.findRegExp( $('ul#errors').text() , /(The confirmation number entered is invalid\.)/i) : error;
        if ( error ) {
            browserAPI.log("Error -> ".error);
            provider.setError(error, true);
            // provider.complete();
            return;
        }

        params.data.Itineraries = [];
        // parse all itineraries
        var itNumber = 0;
        var itinerariesSelector = $('div.trip_itinerary_detail_table_container:visible');
        itinerariesSelector.each(function () {

            var itinerary = $(this);
            browserAPI.log(">>> Itinerary " + itNumber);

            var result = {};
            var depTime = null;
            var depDate = null;
            var DT = null;
            var arrTime = null;
            var arrDate = null;
            var unixtime = null;

            // RecordLocator
            result.RecordLocator = itinerary.find('span.confirmation_number').text();
            browserAPI.log("ConfirmationNumber: " + result.RecordLocator);
            // Passengers
            var passengerInfo = itinerary.find('table[class *= "passengers_table"]').children('tbody').children('tr');
            try {
                result.Passengers = util.beautifulName(plugin.unionArray( passengerInfo.children('th:eq(0)'), ', ', true));
                browserAPI.log("Passengers: "+ result.Passengers);
            } catch (err) {
                browserAPI.log("error: " + err);
            }
            // AccountNumbers
            var accountNumbers = passengerInfo.children('td:eq(0)');
            browserAPI.log("accountNumbers: "+ accountNumbers.length);
            var accounts = [];
            for (var an = 0; an < accountNumbers.length; an++) {
                var number = util.findRegExp( accountNumbers.eq(an).text(), /(\d+)/ );
                console.log('number ' + number);
                if (number && number.length > 3)
                    accounts.push(number);
            }// for (var an = 0; an < its.length; an++)
            result.AccountNumbers = accounts.join(', ');
            browserAPI.log("AccountNumbers: "+ result.AccountNumbers);

            result.TripSegments = [];
            // Segments
            var i = 0;
            itinerary.find('table.airProductItineraryTable:visible').each(function () {
                var node = $(this);
                //console.log(node);
                browserAPI.log(">>> Slice " + i);
                // Flight Segments (time, airport codes and flight #)
                var subSegments = node.find("td[class *= 'itinerary-table--cell'] ol li");
                browserAPI.log('>>> Found ' + subSegments.length / 2 + ' segments');
                for (var j = 0; j < subSegments.length; j = j + 2) {
                    browserAPI.log(">>> Segment " + j / 2);
                    var segment = {};
                    // Flight Summary (date, duration and stops)
                    var subSegmentsInfo = subSegments.eq(j).parents('td[class *= "itinerary-table--cell"]').next('td[class *= "itinerary-table--summary"]:eq(0)');
                    browserAPI.log('>>> Found ' + subSegmentsInfo.length + ' segments info');
                    if (subSegmentsInfo.length == 0) {
                        browserAPI.log('>>> Skip bad node');
                        continue;
                    }
                    var date = util.findRegExp( subSegmentsInfo.find('span[class *= "travel-date"]').text() , /\,\s*([^<]+)/);
                    browserAPI.log('Date: ' + date);
                    // FlightNumber
                    segment.FlightNumber = util.findRegExp( subSegments.eq(j).find('span[class *= "flight-number"] strong').text() , /\#?(.+)/);
                    browserAPI.log("FlightNumber: " + segment.FlightNumber);
                    // AirlineName
                    segment.AirlineName = util.findRegExp( subSegments.eq(j).find('span.flightLogo img').attr('alt') , /Operated\s*by\s*([^<]+)/i);
                    browserAPI.log("AirlineName: " + segment.AirlineName);
                    // Duration
                    segment.Duration = util.findRegExp( subSegmentsInfo.find('span.travelFlightDuration').text() , /Time\s*([^<]+)/i);
                    segment.Duration = segment.Duration.replace(/(?:hours|minutes)/ig, "");
                    browserAPI.log("Duration: " + segment.Duration);
                    // Stops
                    segment.Stops = util.findRegExp( subSegmentsInfo.find('span.stops').text() , /(\d+)\s*stop/i);
                    if (segment.Stops === null && util.findRegExp( subSegmentsInfo.find('span.stops').text() , /(Nonstop)/i))
                        segment.Stops = 0;
                    browserAPI.log("Stops: " + segment.Stops);
                    // DepCode
                    segment.DepCode = util.findRegExp( subSegments.eq(j).find('div:eq(0)').text() , /\(([A-Z]{3})/);
                    browserAPI.log("DepCode: " + segment.DepCode);
                    // DepName
                    segment.DepName = util.findRegExp( subSegments.eq(j).find('div[class *= "routingDetailsStops"]').text() , /(?:Depart|Change\s*to\s*.+\s*in)\s*([^\(]+)/i);
                    browserAPI.log("DepName: " + segment.DepName);
                    // DepDate
                    depTime = util.trim(subSegments.eq(j).find('div[class *= "flight-time"]').text());
                    browserAPI.log("depart time: " + depTime);
                    depDate = date;
                    browserAPI.log("depart: " + depDate);
                    DT = date + ' ' + depTime;
                    DT = new Date(DT + ' UTC');
                    unixtime = DT / 1000;
                    if (!isNaN(unixtime)) {
                        browserAPI.log("DepDate: " + depDate + ' ' + depTime + " Unixtime: " + unixtime);
                        segment.DepDate = unixtime;
                    } else
                        browserAPI.log(">>> Invalid DepDate");
                    // ArrCode
                    segment.ArrCode = util.findRegExp( subSegments.eq(j + 1).find('div:eq(0)').text() , /\(([A-Z]{3})/);
                    browserAPI.log("ArrCode: " + segment.ArrCode);
                    // ArrName
                    segment.ArrName = util.findRegExp( subSegments.eq(j + 1).find('div[class *= "routingDetailsStops"]').text() , /in\s*([^\(]+)/i);
                    browserAPI.log("ArrName: " + segment.ArrName);
                    // ArrDate
                    var arrTimeText = util.trim(subSegments.eq(j + 1).find('div[class *= "flight-time"]').text());
                    arrTime = util.findRegExp(arrTimeText, /(.+M)/);
                    var nextDay = util.findRegExp(arrTimeText, /(Next\s*Day)/i);
                    browserAPI.log("arrive time: " + arrTime);
                    browserAPI.log("Next Day: " + nextDay);
                    var arrDate = date;
                    browserAPI.log("arrDate: " + arrDate);
                    DT = arrDate + ' ' + arrTime;
                    DT = new Date(DT + ' UTC');
                    if (nextDay) {
                        browserAPI.log("Next day: +" + nextDay);
                        DT.setDate(DT.getDate() + 1);
                        browserAPI.log("Right ArrDate: " + DT);
                    }
                    unixtime = DT / 1000;
                    if (!isNaN(unixtime)) {
                        browserAPI.log("ArrDate: " + arrDate + ' ' + arrTime + " Unixtime: " + unixtime);
                        segment.ArrDate = unixtime;
                    } else
                        browserAPI.log(">>> Invalid ArrDate");

                    result.TripSegments.push(segment);
                    browserAPI.log("<<< Segment " + j / 2);
                }// for (var j = 0; j < subSegments.length; j++)
                browserAPI.log("<<< Slice " + i);
                i++;
            });

            //console.log(result);
            params.data.Itineraries.push(result);
            browserAPI.log("<<< Itinerary " + itNumber);
            itNumber++;
            return false;
        });

        // refs #14244
        if (document.location.host === 'global.southwest.com' && itinerariesSelector.length === 0) {
            browserAPI.log("<<< Parse from global.southwest.com");
            plugin.parseItineraryByConfNoGlobal(params);
        }// if (document.location.host == 'global.southwest.com' && itinerariesSelector.length == 0)
        if (document.location.host !== 'global.southwest.com' && itinerariesSelector.length === 0) {
            plugin.parseItineraryByConfNoAlaJson(params);
        }

        params.account.properties.Itineraries = params.data.Itineraries;
        console.log(params.account.properties);
        provider.saveProperties(params.account.properties);
        provider.complete();
    },

    parseItineraryByConfNoGlobal: function (params) {
        browserAPI.log("parseItineraryByConfNoGlobal");

        var it = {};
        // parse all itineraries
        var itNumber = 0;
        var itinerariesSelector = $('div.box2-radius:visible');
        itinerariesSelector.each(function () {

            var itinerary = $(this);
            browserAPI.log(">>> Itinerary " + itNumber);

            var result = {};
            var depTime = null;
            var depDate = null;
            var DT = null;
            var arrTime = null;
            var arrDate = null;
            var unixtime = null;

            // RecordLocator
            result.RecordLocator = itinerary.find('p:contains("Confirmation #") > strong').text();
            browserAPI.log("ConfirmationNumber: " + result.RecordLocator);
            // Passengers
            var passengerInfo = itinerary.find('table[summary *= "table contains the passengers"]').children('tbody').children('tr');
            try {
                result.Passengers = util.beautifulName(plugin.unionArray( passengerInfo.children('th:eq(0)').contents()
                    .filter(function() {
                        return this.nodeType === 3; //Node.TEXT_NODE
                    }), ', ', true));
                browserAPI.log("Passengers: "+ result.Passengers);
            } catch (err) {
                browserAPI.log("error: " + err);
            }
            // AccountNumbers
            var accountNumbers = passengerInfo.children('td:eq(0)');
            browserAPI.log("accountNumbers: "+ accountNumbers.length);
            var accounts = [];
            for (var an = 0; an < accountNumbers.length; an++) {
                var number = util.findRegExp( accountNumbers.eq(an).text(), /(\d+)/ );
                console.log('number ' + number);
                if (number && number.length > 3)
                    accounts.push(number);
            }// for (var an = 0; an < its.length; an++)
            result.AccountNumbers = accounts.join(', ');
            browserAPI.log("AccountNumbers: "+ result.AccountNumbers);

            result.TripSegments = [];
            // Segments
            var i = 0;
            itinerary.find('table#itinerarySummary:visible').each(function () {
                var node = $(this);
                //console.log(node);
                browserAPI.log(">>> Slice " + i);
                // Flight Segments (time, airport codes and flight #)
                var subSegments = node.find('tr');
                browserAPI.log('>>> Found ' + subSegments.length + ' segments');
                for (var j = 0; j < subSegments.length; j++) {
                    browserAPI.log(">>> Segment " + j);
                    var segment = {};
                    // Flight Summary (date, duration and stops)
                    var subSegmentsInfo = $(this);
                    browserAPI.log('>>> Found ' + subSegmentsInfo.length + ' segments info');
                    if (subSegmentsInfo.length == 0) {
                        browserAPI.log('>>> Skip bad node');
                        continue;
                    }
                    var date = util.findRegExp( subSegmentsInfo.find('td:eq(2) > strong').text() , /\,\s*([^<]+)/);
                    browserAPI.log('Date: ' + date);
                    // FlightNumber
                    segment.FlightNumber = util.findRegExp( subSegments.eq(j).find('td:eq(1) > strong:eq(1)').text() , /\#?(.+)/);
                    browserAPI.log("FlightNumber: " + segment.FlightNumber);
                    // // AirlineName
                    // segment.AirlineName = util.findRegExp( subSegments.eq(j).find('span.flightLogo img').attr('alt') , /Operated\s*by\s*([^<]+)/i);
                    // browserAPI.log("AirlineName: " + segment.AirlineName);
                    // Duration
                    segment.Duration = util.findRegExp( subSegmentsInfo.find('td:eq(2) > span:contains("Travel Time"):eq(0)').text() , /Time\s*([^<]+)/i);
                    segment.Duration = segment.Duration.replace(/(?:hours|minutes)/ig, "");
                    browserAPI.log("Duration: " + segment.Duration);
                    // Stops
                    segment.Stops = util.findRegExp( subSegmentsInfo.find('td:eq(2)').text() , /(\d+)\s*stop/i);
                    if (segment.Stops === null && util.findRegExp( subSegmentsInfo.find('td:eq(2)').text() , /(Nonstop)/i))
                        segment.Stops = 0;
                    browserAPI.log("Stops: " + segment.Stops);
                    // DepCode
                    segment.DepCode = util.findRegExp( subSegments.eq(j).find('td:eq(0) > div:eq(0)').text() , /\(([A-Z]{3})/);
                    browserAPI.log("DepCode: " + segment.DepCode);
                    // DepName
                    segment.DepName = util.findRegExp( subSegments.eq(j).find('td:eq(0) > div:eq(0) > span').text() , /(?:Depart|Change\s*to\s*.+\s*in)\s*([^\(]+)/i);
                    browserAPI.log("DepName: " + segment.DepName);
                    // DepDate
                    depTime = util.trim(subSegments.eq(j).find('td:eq(0) > div:eq(0) > strong').text());
                    browserAPI.log("depart time: " + depTime);
                    depDate = date;
                    browserAPI.log("depart: " + depDate);
                    DT = date + ' ' + depTime;
                    DT = new Date(DT + ' UTC');
                    unixtime = DT / 1000;
                    if (!isNaN(unixtime)) {
                        browserAPI.log("DepDate: " + depDate + ' ' + depTime + " Unixtime: " + unixtime);
                        segment.DepDate = unixtime;
                    } else
                        browserAPI.log(">>> Invalid DepDate");
                    // ArrCode
                    segment.ArrCode = util.findRegExp( subSegments.eq(j).find('td:eq(0) > div:eq(1)').text() , /\(([A-Z]{3})/);
                    browserAPI.log("ArrCode: " + segment.ArrCode);
                    // ArrName
                    segment.ArrName = util.findRegExp( subSegments.eq(j).find('td:eq(0) > div:eq(1) > span').text() , /in\s*([^\(]+)/i);
                    browserAPI.log("ArrName: " + segment.ArrName);
                    // ArrDate
                    var arrTimeText = util.trim(subSegments.eq(j).find('td:eq(0) > div:eq(1) > strong').text());
                    arrTime = util.findRegExp(arrTimeText, /(.+M)/);
                    var nextDay = util.findRegExp(arrTimeText, /(Next\s*Day)/i);
                    browserAPI.log("arrive time: " + arrTime);
                    browserAPI.log("Next Day: " + nextDay);
                    var arrDate = date;
                    browserAPI.log("arrDate: " + arrDate);
                    DT = arrDate + ' ' + arrTime;
                    DT = new Date(DT + ' UTC');
                    if (nextDay) {
                        browserAPI.log("Next day: +" + nextDay);
                        DT.setDate(DT.getDate() + 1);
                        browserAPI.log("Right ArrDate: " + DT);
                    }
                    unixtime = DT / 1000;
                    if (!isNaN(unixtime)) {
                        browserAPI.log("ArrDate: " + arrDate + ' ' + arrTime + " Unixtime: " + unixtime);
                        segment.ArrDate = unixtime;
                    } else
                        browserAPI.log(">>> Invalid ArrDate");

                    result.TripSegments.push(segment);
                    browserAPI.log("<<< Segment " + j / 2);
                }// for (var j = 0; j < subSegments.length; j++)
                browserAPI.log("<<< Slice " + i);
                i++;
            });

            //console.log(result);
            params.data.Itineraries.push(result);
            browserAPI.log("<<< Itinerary " + itNumber);
            itNumber++;
            return false;
        });

        return it;
    },

    number_format: function (number, decimals, dec_point, thousands_sep) {  // Format a number with grouped thousands
        // +   original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
        // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
        // +     bugfix by: Michael White (http://crestidg.com)

        var i, j, kw, kd, km;

        // input sanitation & defaults
        if (isNaN(decimals = Math.abs(decimals))) {
            decimals = 2;
        }
        if (dec_point == undefined) {
            dec_point = ",";
        }
        if (thousands_sep == undefined) {
            thousands_sep = ".";
        }

        i = parseInt(number = (+number || 0).toFixed(decimals)) + "";

        if ((j = i.length) > 3) {
            j = j % 3;
        } else {
            j = 0;
        }

        km = (j ? i.substr(0, j) + thousands_sep : "");
        kw = i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + thousands_sep);
        //kd = (decimals ? dec_point + Math.abs(number - i).toFixed(decimals).slice(2) : "");
        kd = (decimals ? dec_point + Math.abs(number - i).toFixed(decimals).replace(/-/, 0).slice(2) : "");

        return km + kw + kd;
    }


};
