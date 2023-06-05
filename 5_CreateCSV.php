<?php

	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	
	ini_set('memory_limit','-1');

	require("./db.php");

	$db = db_connection();

	$count	=	1;
	
	if(! is_dir("./CSVFiles")){		mkdir("./CSVFiles");	}
	
	echo"Generating CSV file\t\n";
	
	$filename	=	date('Y_m_d').'_Allvetsupply.csv';	
	$csvFile 	= fopen('./CSVFiles/'.$filename,'w');

	fwrite($csvFile,'"Scrape Date","Scrape Time","Store Name","Product Brand","Product Name","Product URL","Product Image URL","Product UPC","Product Model","Manufacturer #","Product Price","In Stock"'."\r\n");
	
	
	$query								=	"SELECT * FROM `vw_data`";
	$result								=	$db->query($query);		
	
	while($row	=	$result->fetch_object()){
		
		$ScrapeDate						=	str_replace('"','""',$row->ScrapeDate);
		$ScrapeTime						=	str_replace('"','""',$row->ScrapeTime);
		$StoreName          	=	str_replace('"','""',$row->StoreName);
		$ProductBrand         =	str_replace('"','""',$row->ProductBrand);
		$ProductName        	=	str_replace('"','""',$row->ProductName);
		$ProductURL						=	str_replace('"','""',$row->ProductURL);
		$ProductImageURL			=	str_replace('"','""',$row->ProductImageURL);
		$ProductUPC         	=	str_replace('"','""',$row->ProductUPC);
		$ProductModel       	=	str_replace('"','""',$row->ProductModel);
		$ManufacturerNumbmer  =	str_replace('"','""',$row->ManufacturerNumbmer);
		$ProductPrice         =	str_replace('"','""',$row->ProductPrice);
		$InStock              =	str_replace('"','""',$row->InStock);

		fwrite($csvFile,"\"$ScrapeDate\",\"$ScrapeTime\",\"$StoreName\",\"$ProductBrand\",\"$ProductName\",\"$ProductURL\",\"$ProductImageURL\",\"$ProductUPC\",\"$ProductModel\",\"$ManufacturerNumbmer\",\"$ProductPrice\",\"$InStock\""."\r\n");

		echo $count."\r";

		$count++;
		
	}

	fclose($csvFile);
	
	writeToLog("$count Records Inserted in CSV File");
	
	
?>	