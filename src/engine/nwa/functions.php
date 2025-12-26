<?php

//function CheckBalanceNwa( $sLogin, $sPassword, &$nBalance, &$nErrorCode, &$sErrorMessage, $IsRedirect, &$arProperties )
//{
//	$nErrorCode = ACCOUNT_PROVIDER_ERROR;
//	$sErrorMessage = "Delta and Northwest have joined forces. From now on you need to check your miles with Delta"; /*checked*/
//	return;
//  $http = new TCurlBrowser();
//  unset( $http->p_header["Accept-Encoding"] );
//
//  $arg = array(
//    "URL"=>"https://www.nwa.com/cgi-bin/wp_acctsum.pro",
//	"LinkURL"=>"https://www.nwa.com/worldperks/acctlink/",
//	"BuyURL"=>"https://www.nwa.com/worldperks/purchasemiles/buy/step1",
//	"RedeemURL"=>"http://res.nwa.com",
//	"MerchandiseURL"=>"http://www.skymall.com/northwestredemption/homepage.htm",
//	"AuctionURL"=>"http://auctions.nwa.com/",
//    "RequestMethod"=>"POST",
//    "PostValues"=>array(
//        "account"=>$sLogin[0],
//        "WpNum"=>"",
//        "LastName"=>"",
//        "Pin"=>"",
//        "name"=>$sLogin[1],
//        "pin"=>$sPassword,
//        "x"=>32,
//        "y"=>8
//      )
//  );
//
//  if (isset($IsRedirect) and $IsRedirect)
// 	 return $arg;
//
//  $http->redirect = false;
//  $http->open( $arg );
//
//  if(preg_match("/account\s+number\s+is\s+incorrect/i", $http->body, $match) ) {
//    $nErrorCode = ACCOUNT_INVALID_PASSWORD;
//    $sErrorMessage = "Account number is incorrect";
//    return;
//  }
//
//  if(preg_match("/PIN\s+does\s+not\s+match\s+WorldPerks\s+account\s+information\s+on\s+file/i", $http->body, $match) ) {
//    $nErrorCode = ACCOUNT_INVALID_PASSWORD;
//    $sErrorMessage = "PIN does not match WorldPerks account information on file";
//    return;
//  }
//
//  if(preg_match("/A PIN does not exist for this account, please use our Create PIN function to assign one/i", $http->body, $match) ) {
//    $nErrorCode = ACCOUNT_INVALID_PASSWORD;
//    $sErrorMessage = "A PIN does not exist for this account, please use our Create PIN function to assign one.";
//    return;
//  }
//
//   if(preg_match("/The account number you entered is not valid, please change it as needed and re-submit/i", $http->body, $match) ) {
//    $nErrorCode = ACCOUNT_INVALID_PASSWORD;
//    $sErrorMessage = "The account number you entered is not valid, please change it as needed and re-submit.";
//    return;
//  }
//
//  if(preg_match("/\<\!\-\-avail\_miles\-\-\><b>(\d+[\.\,]?\d*)<\/b>/ims", $http->body, $match) ) {
//    $nBalance = str_replace( ",", "", $match[1] );
//    $nErrorCode = ACCOUNT_CHECKED;
//  }
//
//  // ext props
//  if( preg_match( "/>Account Number:<\/span><\/td>\s*<td class=\"arialBlack11\">\s*<!--wp_num-->([^<]+)<\/td>/ims", $http->body, $arMatches ) )
//  	$arProperties["Number"] = $arMatches[1];
//  if( preg_match( "/<td class=\"arialBlack11\">\s*<!\-\-current_status\-\->[^<]*<B>([^<]+)<\/B>/ims", $http->body, $arMatches ) )
//  	$arProperties["Status"] = $arMatches[1];
//  if( preg_match( "/<!\-\-current_year_elite_miles\-\-><b>([^<]+)<\/b>\s*<\/td>/ims", $http->body, $arMatches ) )
//  	$arProperties["EliteMiles"] = $arMatches[1];
//  if( preg_match( "/<!\-\-current_year_eqs_count\-\->([^<]+)\s*<\/td>/ims", $http->body, $arMatches ) )
//  	$arProperties["EliteSegments"] = $arMatches[1];
//  if( preg_match( "/<!\-\-banked_miles\-\->([^<]+)\s*<\/FONT>/ims", $http->body, $arMatches ) )
//  	$arProperties["TotalMiles"] = $arMatches[1];
//  if( preg_match( "/<!\-\-locked_miles\-\->([^<]+)\s*<\/td>/ims", $http->body, $arMatches ) )
//  	$arProperties["LockedMiles"] = $arMatches[1];
//  else
//	if(preg_match("/Locked_Miles_Comment/ims", $http->body))
//	  $arProperties["LockedMiles"] = "";
//  if( preg_match( "/<!\-\-avail_miles\-\-><b>([^<]+)<\/b>\s*<\/td>/ims", $http->body, $arMatches ) )
//  	$arProperties["AvailableMiles"] = $arMatches[1];
//  if( preg_match( "/<!\-\-total_miles\-\->([^<]+)\s*<\/td>/ims", $http->body, $arMatches ) )
//  	$arProperties["TotalMileage"] = $arMatches[1];
//  if( preg_match( "/<!\-\-expire_miles\-\->(\d+)([a-z]{3})(\d{4})\s*<\/td>/ims", $http->body, $arMatches ) ){
//	$d = strtotime($arMatches[2]." ".$arMatches[1].", ".$arMatches[3]);
//	if($d !== false)
//		$arProperties["AccountExpirationDate"] = $d;
//  }
//  // end ext props
//  if( $nErrorCode == ACCOUNT_CHECKED ){
//	// expiration
//	$arg = array(
//	  "URL"=>"https://www.nwa.com/cgi-bin/wp_acctsum.pro?req_start_date=04012006&req_end_date=".date("mdY")."&req_type=B&show_acct_summ=+++Show+Account+Summary+++&select_another_sort=2",
//		"RequestMethod"=>"GET",
//	);
//	$http->open( $arg );
//	if(preg_match("/<FONT SIZE=2 class='arial11363636'>(\d{1,2})(\w{3})(\d{4})/ims", $http->body, $arMatches)){
//	  $arProperties["LastActivity"] = ucfirst(strtolower($arMatches[2]))." ".$arMatches[1].", ".$arMatches[3];
//	  if(!isset($arProperties['AccountExpirationDate']))
//		$arProperties["AccountExpirationDate"] = strtotime("+2 year", strtotime($arMatches[2]." ".$arMatches[1].", ".$arMatches[3]));
//	}
//	else
//	  if(preg_match("/No Mileage Activity Found/ims", $http->body))
//		$arProperties["LastActivity"] = "";
//    $arProperties["Itineraries"] = CheckItinerariesNwa($http, $sLogin, $sPassword);
//  }else{
//  	// check for joining with delta
//  	if(preg_match("/WorldPerks Balance Transfer/ims", $http->body) && preg_match("/No Mileage Activity Found/ims", $http->body)){
//  		$nBalance = 0;
//  		$nErrorCode = ACCOUNT_WARNING;
//  		$sErrorMessage = "It appears that your Northwest WorldPerks Points have been transferred to your Delta account.";
//  	}
//  }
//
//  return;
//}
//
//function CheckItinerariesNwa(&$http, $sLogin, $sPassword){
//	$arg = array(
//    	"URL"=>"https://www.nwa.com/cgi-bin/res_info.pro",
//		"RequestMethod"=>"GET",
//    );
//  	$http->redirect = true;
//  	$http->open( $arg );
//	$doc = new DOMDocument();
//	$nErrorLevel = error_reporting( E_ALL ^ E_WARNING );
//	$doc->loadHTML( $http->body );
//	error_reporting( $nErrorLevel );
//#  	$doc->save("/mnt/projects/nwa.xml");
//    $xpath=new DOMXPath($doc);
//  	$nodes = $xpath->query("//a[contains(text(), 'Details&gt&gt')]/@href");
//  	$result = array();
//  	for( $n = 0; $n < $nodes->length; $n++ ){
//		$it = CheckItineraryNwa($http, $nodes->item($n)->nodeValue);
//		if(isset($it))
//		  $result[] = $it;
//  	}
//  	return $result;
//}
//
//function CheckItineraryNwa( &$http, $sURL, $sDate = null ){
//	$arg = array(
//    	"URL"=>$sURL,
//		"RequestMethod"=>"GET",
//    );
//  	$http->open( $arg );
//	if(preg_match("/This program is temporarily unavailable/ims", $http->body))
//	  return null;
//	$doc = new DOMDocument();
//	$nErrorLevel = error_reporting( E_ALL ^ E_WARNING );
//	$doc->loadHTML( $http->body );
//	error_reporting( $nErrorLevel );
//  //	$doc->save("/mnt/projects/nwa0.xml");
//    $xpath=new DOMXPath($doc);
//    $result = array();
//	if(preg_match("/(\d+) days until departure/ims", $http->body, $matches))
//		$baseDate = strtotime("+{$matches[1]} day");
//	else
//		$baseDate = time();
//    $tables = $xpath->query("//table[tr[2]/td[1]/strong[contains(text(), 'Date:')]]");
//    for( $n = 0; $n < $tables->length; $n++ ){
//    	$arSegment = array();
//    	$text = CleanXMLValue($tables->item($n)->nodeValue);
//    	if( preg_match("/Date:(.+)Flight:/ims", $text, $arMatch ) )
//    		$arSegment["Date"] = strtotime( trim( $arMatch[1] ), $baseDate );
//    	if( preg_match("/Flight:(.+)Departs:/ims", $text, $arMatch ) )
//    		$arSegment["FlightNumber"] = trim( $arMatch[1] );
//    	if( preg_match("/Departs:(.+)\((\w{3})\) at(.+)Arrives:/ims", $text, $arMatch ) ){
//    		if( isset( $arSegment["Date"] ) ){
//    			$arSegment["DepDate"] = ConvertDateNwa( trim( $arMatch[3] ), $arSegment["Date"] );
//    			if( $arSegment["DepDate"] === false )
//    				unset($arSegment["DepDate"]);
//    		}
//    		$arSegment["DepName"] = trim( $arMatch[1] );
//    		$arSegment["DepCode"] = trim( $arMatch[2] );
//    	}
//    	if( preg_match("/Arrives:(.+)\((\w{3})\) at(.+)Class of Service:/ims", $text, $arMatch ) ){
//    		if( isset( $arSegment["Date"] ) ){
//    			$arSegment["ArrDate"] = ConvertDateNwa( trim( $arMatch[3] ), $arSegment["Date"] );
//    			if( $arSegment["ArrDate"] === false )
//    				unset($arSegment["ArrDate"]);
//    		}
//    		$arSegment["ArrName"] = trim( $arMatch[1] );
//    		$arSegment["ArrCode"] = trim( $arMatch[2] );
//    	}
//    	if( preg_match("/Class of Service:(.+)Seat:/ims", $text, $arMatch ) )
//    		$arSegment["Class"] = trim( $arMatch[1] );
//    	if( preg_match("/Seat:(.+)Flight Duration:/ims", $text, $arMatch ) )
//    		$arSegment["Seat"] = trim( $arMatch[1] );
//    	if( preg_match("/Flight Duration:(.+)Approximate Miles:/ims", $text, $arMatch ) )
//    		$arSegment["Flight Duration"] = trim( $arMatch[1] );
//    	if( preg_match("/Approximate Miles:(.+)Meal Service:/ims", $text, $arMatch ) )
//    		$arSegment["Approximate Miles"] = trim( $arMatch[1] );
//    	if( preg_match("/Meal Service:(.+)Aircraft:/ims", $text, $arMatch ) )
//    		$arSegment["Meal Service"] = trim( $arMatch[1] );
//    	if( preg_match("/Aircraft:\s+(\w[^\r\n]+)/ims", $text, $arMatch ) )
//    		$arSegment["Aircraft"] = trim( $arMatch[1] );
//    	if( preg_match("/CO Confirmation Number:\s+(\w{6})/ims", $text, $arMatch ) )
//    		$arSegment["CO Confirmation Number"] = trim( $arMatch[1] );
//    	if(count($arSegment) > 0)
//    		$result[] = $arSegment;
//    }
//	$arIt = array();
//	$arIt["TripSegments"] = $result;
//    $nodes = $xpath->query("//table/tr[td/strong[contains(text(), 'NWA Confirmation Number:')]]/td[2]");
//	if($nodes->length > 0)
//	  $arIt["RecordLocator"] = CleanXMLValue($nodes->item(0)->nodeValue);
//    $nodes = $xpath->query("//table/tr[td/strong[contains(text(), 'Passenger Name')]]/following::tr[1]/td[1]");
//	if($nodes->length > 0)
//	  $arIt["Passengers"] = CleanXMLValue($nodes->item(0)->nodeValue);
//    return $arIt;
//}
//
//function ConvertDateNwa( $sDate, $dBaseDate ){
//	$sDate = trim($sDate);
//	if( preg_match('/^(.+)\s+on\s+(.+)$/ims', $sDate, $arMatch) )
//		$s = trim( $arMatch[2] . " " . $arMatch[1] );
//	else
//		$s = date( DATE_FORMAT, $dBaseDate ) . " " . $sDate;
//	$d = strtotime( $s, $dBaseDate );
//	if(($d !== false) && ((time() - $d) > (SECONDS_PER_DAY * 30)))
//		$d = strtotime("+1 year", $d);
//	return $d;
//}
//
//function CheckConfirmationNumberNwa($arFields, &$it){
//	$http=new TCurlBrowser();
////	$http->debug = true;
//	$arg = array(
//		"URL"=>"http://www.nwa.com/",
//		"RequestMethod"=>"GET"
//	);
//	$http->open( $arg );
//	$arg = array(
//		"URL"=>"https://www.nwa.com/cgi-bin/res_info.pro",
//		"RequestMethod"=>"POST",
//		"PostValues" => array(
//			"viewResType" => urldecode("Confirmation+%23"),
//			"Pnr" => $arFields["ConfNo"],
//			"WpNum" => "",
//			"EtktNum" => "",
//			"Pin" => "",
//			"save_pin" => "",
//			"MemberName" => "",
//			"LastName" => $arFields["LastName"],
//			"StartIndex" => "0",
//			"referer" => "1",
//		),
//	);
//	$http->open( $arg );
//	if(preg_match("/<\!\-\-error_msg\-\->\s*<P><FONT COLOR=\"#CC0000\"><STRONG>([^<]+)<\/STRONG>/ims", $http->body, $arMatch))
//		return $arMatch[1];
//	if(!preg_match("/<A HREF=\"([^\"]+)\"[^\>]*>([^<]+)<\/A>\s*<small>\(view receipt\)<\/small>/ims", $http->body, $arMatch)){
//		return "Receipt link not found";
//	}
//	$it = CheckItineraryNwa($http, $arMatch[1]);
//	return null;
//}
//
//function GetConfirmationFieldsNwa(){
//	return array(
//		"ConfNo" => array(
//			"Caption" => "Confirmation #",
//			"Type" => "string",
//			"Size" => 20,
//			"Required" => true,
//		),
//		"LastName" => array(
//			"Type" => "string",
//			"Size" => 40,
//			"Value" => $this->GetUserField('LastName'),
//			"Required" => true,
//		)
//	);
//}
