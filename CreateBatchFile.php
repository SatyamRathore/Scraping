<?php 

	$path 						= "cd C:\Users\Satyam\Desktop\Upwork\Michele";
	
	$fileName					= "3_Scraper.php";
	// $fileName					= "2.Product_Details_Scraper_old.php";
	
	$totalInstance		= 10;
	$limit						= ceil(3000/$totalInstance);
	// $limit						= ceil(10/$totalInstance);
	
	$batchfileString	= '';
	
	$batchfileString 	= $path."\r\n";
	
	for($i = 1; $i<=$totalInstance; $i++){
		
		$argument1 			= (($i*1)-1)*$limit;
		$argument2 			= $limit;
		// $argument3			=	'oxylab';
		// $argument3			=	'netnut';
		// $argument3			=	'proxyempire';
		// $argument3			=	'smartproxy';
		$argument3			=	'privateproxy';
		
		$batchfileString .= "start cmd /k \"php {$fileName} {$argument1} {$argument2} {$argument3} && EXIT\""."\r\n";

	}
	
	if(file_put_contents('RunAllInstance.bat',$batchfileString)){
		echo 'Batch file generated ';
	}
?>