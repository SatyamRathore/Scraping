<?php

	define("PPUSERNAME","y1sin");
	define("PPPASSWORD","ufji9db3");
	define("PPPROXYURL","169.197.83.75:7336");

	require_once('./db.php');

	$db = db_connection();

	$HTMLFiles 				  = "HTMLFiles";
	$DetailPageFolder 	= "$HTMLFiles/ListingPages";
	
	$CookieFolder = "Cookie";
	
	if(!is_dir($HTMLFiles))											{ 	mkdir($HTMLFiles);								}
	if(!is_dir($DetailPageFolder))							{ 	mkdir($DetailPageFolder);					}
	
	if(!is_dir($CookieFolder))									{ 	mkdir($CookieFolder);							}

	if(isset($argv[1]) && $argv[1] == 'privateproxy'){
		$is_proxy = 'privateproxy';
		change_privateproxy();
	}
	
	$cookie_file = "$CookieFolder/Cookie.txt";

	$Select = "SELECT Category1, Category2, Category3, Category3URL FROM tbl_category2 WHERE Extracted = 0";

	if($dbRecords = $db->query($Select)){
		
		if($dbRecords->num_rows > 0){
			
			while($dbRows = $dbRecords->fetch_object()){

				$Category1 		  = $dbRows->Category1;
				$Category2 		  = $dbRows->Category2;
				$Category3 		  = $dbRows->Category3;
				$Category3URL 	= $dbRows->Category3URL;
			
				$category1save 	 = trim(str_replace(array('/'),array('-'),$Category1));
				$category2save 	 = trim(str_replace(array('/'),array('-'),$Category2));
				$category3save 	 = trim(str_replace(array('/'),array('-'),$Category3));
				
				writeToLog("Category1: $Category1; Category2: $Category2; Category3: $Category3...");
				
				$ListingPageFileName = "$DetailPageFolder/{$category1save}_{$category2save}_{$category3save}.html";

				if(is_file($ListingPageFileName)){
					writeToLog("Detail Page File Exists...");
					$listingpagehtml = file_get_contents($ListingPageFileName);
				}else{
					$listingpagehtml = ListingPage_download($Category3URL,$ListingPageFileName);
				}
				
				ListingPageParse($listingpagehtml,$Category1,$Category2,$Category3);
				
				$update = "UPDATE tbl_category2 SET Extracted = 1 WHERE Category1 = '$Category1' AND Category2 = '$Category2' AND Category3URL = '$Category3URL'";
				
				if(!$db->query($update)){
					writeToLog($db->error.PHP_EOL);
				}else{
					writeToLog("...Done".PHP_EOL);
				}
				
			}
		}
	}

	function ListingPage_download($Category3URL,$ListingPageFileName){
	
		global $db,$proxy,$is_proxy,$cookie_file,$ppProxyPassword,$Category1Folder,$userAgent;
	
		$ch = curl_init();
		
		curl_setopt($ch, 	CURLOPT_URL,$Category3URL);
		curl_setopt($ch,	CURLOPT_FOLLOWLOCATION,	1);
		curl_setopt($ch, 	CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, 	CURLOPT_SSL_VERIFYHOST, false);
		
		if($is_proxy == 'privateproxy'){
			curl_setopt($ch,  CURLOPT_PROXY, "$proxy");				
			curl_setopt($ch,  CURLOPT_PROXYUSERPWD, $ppProxyPassword);
		}
		
		curl_setopt($ch,	CURLOPT_RETURNTRANSFER,	1);
		curl_setopt($ch,	CURLOPT_BINARYTRANSFER,	1);			
		curl_setopt($ch,	CURLOPT_ENCODING,	'gzip');
		curl_setopt($ch, 	CURLOPT_CONNECTTIMEOUT, 20); 
		curl_setopt($ch, 	CURLOPT_TIMEOUT, 20);
		curl_setopt($ch,	CURLOPT_COOKIEJAR,"./{$cookie_file}");
		curl_setopt($ch,	CURLOPT_COOKIEFILE,"./{$cookie_file}");
		curl_setopt($ch,	CURLOPT_HEADER,0);
		curl_setopt($ch,	CURLOPT_HTTPHEADER, array(
	
		'Host: www.allvetsupply.com',
		'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/113.0',
		'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
		'Accept-Language: en-US,en;q=0.5',
		'Accept-Encoding: gzip, deflate, br',
		'DNT: 1',
		'Connection: keep-alive',
		'Upgrade-Insecure-Requests: 1',
		'Sec-Fetch-Dest: document',
		'Sec-Fetch-Mode: navigate',
		'Sec-Fetch-Site: none',
		'Sec-Fetch-User: ?1',
		'Pragma: no-cache',
		'Cache-Control: no-cache'

		));
		$result			=	curl_exec($ch);								
		$httpCode		=	curl_getinfo($ch,	CURLINFO_HTTP_CODE);
		
		writeToLog("Listing Page http: $httpCode...");
		
		if( ($httpCode	==	200) && ($result != '') ){
			
			if( (stripos($result,'/distil_r_captcha.html') !== false) || (stripos($result,'<div id="px-captcha">') !== false) || (stripos($result,'Access to this page has been denied') !== false) || (stripos($result,'http://auth.contentkeeper.com/login.html') !== false)||(stripos($result,'<title>Welcome To Zscaler Directory Authentication</title>') !== false)){
				writeToLog('Captch found ');
			
				if($is_proxy == 'privateproxy'){
					change_privateproxy();
				}
				
				return ListingPage_download($Category3URL,$ListingPageFileName);		
			}
			else{
				file_put_contents($ListingPageFileName,$result);			
				return $result;				
			}
			
		}else{

			if ($is_proxy == 'privateproxy'){
				change_privateproxy();
			}
			
			return ListingPage_download($Category3URL,$ListingPageFileName);			
		}
	}
	
	function ListingPageParse($listingpagehtml,$Category1,$Category2,$Category3){
		
		global $db;
		
		if(preg_match('#<div id="kipaging"></div><script>(.*?)</script>#is',$listingpagehtml,$main)){
		
			if(isset($main[1])){
				
				preg_match_all('#arrObjects.push\(new pObj\((.*?)\)#is',$main[1],$data);
				
				$counter = 0;
				
				for($i=0; $i<count($data[1]); $i++){
					
					preg_match_all('#"(.*?)"#is',$data[1][$i],$details);
					
					$ProductName  =  "";
					$ProductURL   =  "";
					
					if(isset($details[1][2]))		{		$ProductName  =  trim($db->real_escape_string(html_entity_decode(strip_tags($details[1][2]))));		}
					if(isset($details[1][3]))		{		$ProductURL   =  "https://www.allvetsupply.com/".trim($details[1][3]);														}
					
					$db->query("SET NAMES UTF8");
					
					if($ProductURL != ''){
						
						$Insert = "INSERT IGNORE INTO tbl_product_listing(Category1, Category2, Category3, ProductName, ProductURL)VALUES('$Category1','$Category2','$Category3','$ProductName','$ProductURL')";
					
						if(!$db->query($Insert)){
							writeToLog($db->error.PHP_EOL);
						}else{
							$counter++;
						}
					}
				}
				writeToLog("$counter Records Insert in tbl_product_listing");
				
			}
		}else{
			
			writeToLog("Listing not found");
			
		}
		
		
	}

?>