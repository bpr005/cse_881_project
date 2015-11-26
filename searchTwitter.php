<?php
error_reporting(E_ALL ^ E_NOTICE);

// ---------------------------------------------------------
// Include Twitter Libraries
// ---------------------------------------------------------

require 'tmhOAuth.php';
require 'tmhUtilities.php';

// ---------------------------------------------------------
// Define a function for processing a search_idStrings file
// ---------------------------------------------------------
function process_searchQuery_file($inputFilename, $outputFilename)
{
	// Print the CSV file headers
	$dataHeaders = "id_str,created_at,text,new_retweet_count,new_favorite_count\n";
	// Caution: overwrites any current data in the file
	if (file_put_contents($outputFilename, $dataHeaders) === FALSE)
	{
		// FALSE indicates that an error occurred during the fwrite operation
	}

	$params = array();
	$searchStringFile = fopen($inputFilename, "r") or die("Unable to open the file of ID strings!");
	while(!feof($searchStringFile))
	{
		$fileLine = fgets($searchStringFile);
		if ((strcmp($fileLine, PHP_EOL) != 0) & (strcmp($fileLine, "\n") != 0) & (strcmp($fileLine, "\r") != 0))
		{
			$params['id'] = "{$fileLine}";
		
			$url = "https://api.twitter.com/1.1/statuses/lookup.json";
			$tmhOAuth->request('GET', $url, $params);

			if ($tmhOAuth->response['code'] == 200) 
			{
				$data = json_decode($tmhOAuth->response['response'], true);
				// print_r($data);
    			foreach ($data as $tweet) 
				{
					// Attempts to replace all newline characters in the tweets with an empty string
					$newlineChars = array( PHP_EOL, '\n', '\r' );
					// $data['text'] = str_replace(PHP_EOL, '', $data['text']);
					$tweet['text'] = str_replace($newlineChars, '', $tweet['text']);
				
					// Substitude the HTML comma code for any comma in text
					$tweet['text'] = str_replace(',', '&#44;', $tweet['text']); 
		
					$outputString = "{$tweet['id_str']}" . "," . "{$tweet['created_at']}" . "," . "{$tweet['text']}" . ",";
					$outputString = "{$outputString}" . "{$tweet['retweet_count']}" . ",";
					$outputString = "{$outputString}" . "{$tweet['favorite_count']}" . ",";
		
					if (file_put_contents($outputFilename, $outputString, FILE_APPEND) === FALSE)
					{
						// FALSE indicates that an error occurred during the fwrite operation
					}
		
					// End the data record
					if (file_put_contents($outputFilename, "\n", FILE_APPEND) === FALSE)
					{
						// FALSE indicates that an error occurred during the fwrite operation
					}
    			}
			} 
			else 
			{
				$data = htmlentities($tmhOAuth->response['response']);
				echo 'There was an error.' . PHP_EOL;
				var_dump($tmhOAuth);
			}
		}
	}
	fclose($searchStringFile);
}

// ------------------------------------------
// Get the authentication information
// ------------------------------------------

$secretFile = "auth.token";
$fh = fopen($secretFile, 'r');
$secretArray = array();

while (!feof($fh)) {
	$line = fgets($fh);
	$array = explode( ':', $line );
	$secretArray[trim($array[0])] =trim($array[1]) ;
}
fclose($fh);
$tmhOAuth = new tmhOAuth($secretArray);

// ------------------------------------------
// Search for the specified tweets
// ------------------------------------------

/*
 * Expects as input one of two filename formats: 
 * (1) search_idStrings_date('Y-m-d-hisT').txt
 * (2) search_input_date('Y-m-d-hisT').txt
 * DO NOT MESS WITH THIS FORMAT!
 */
$inputFile = $argv[1];
$filenameParts = explode("_", $inputFile);

// Determine which filename format was provided
if (strcmp($filenameParts[1], 'idStrings') == 0)
{
	// Case: Input file is a set of at most 180 search queries, each of which can contain up to 100 tweet ID strings
	// Create the file to which the data will be written
	// TODO: edit the suffix to match the input file of ID strings to search
	$outputFile = "search_API_output_" . "{$filenameParts[2]}";
	$outputFile = substr($outputFile, 0, -4);
	$outputFile = "{$outputFile}" . ".csv";
	
	// Check how to call another PHP function in the same file
	process_searchQuery_file($inputFile, $outputFile);
}
elseif (strcmp($filenameParts[1], 'input') == 0)
{
	// Case: Input file is a list of of search_idStrings files to be processed at 16 minute intervals
	$searchFileListFile = fopen($inputFile, "r") or die("Unable to the open the file containing the list of search files!");
	while(!feof($searchFileListFile))
	{
		// TODO: check that this variable does not include the newline character
		$filename = fgets($searchFileListFile);
		$filenameParts = explode("_", $filename);
		
		// Create the file to which the data will be written
		// TODO: edit the suffix to match the input file of ID strings to search
		$outputFile = "search_API_output_" . "{$filenameParts[2]}";
		$outputFile = substr($outputFile, 0, -4);
		$outputFile = "{$outputFile}" . ".csv";

		process_searchQuery_file($filename, $outputFile);
		
		// Pause the program for 16 minutes to abide by the Twitter API Rate Limit
		sleep(960);
	}
	fclose($inputFile);
}
else
{
	echo "INCORRECT INPUT FILE FORMAT!";
	// TODO: quit the program with an error message to the console
}

?>
