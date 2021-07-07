<?php 

require_once('oai2client.php');

require "vendor/autoload.php";

function everything_in_tags($string, $tagname)
{
    $pattern = "#<\s*?$tagname\b[^>]*>(.*?)</$tagname\b[^>]*>#s";
    preg_match($pattern, $string, $matches);
    return $matches[1];
}

$oai2 = new OAI2CLient("https://repositorio.colmex.mx/conacyt/oai/oai2.php");

$options = array('from' => null,
'until' => null,
'set' => null,
'resumptionToken' => null,
'metadataPrefix' => 'oai_dc'); 

$result = $oai2->ListRecords($options);

$token = everything_in_tags($result, "resumptionToken");


while($token) {

	$options = array('from' => null,
	'until' => null,
	'set' => null,
	'resumptionToken' => $token,
	'metadataPrefix' => 'oai_dc'); 

	$result .= $oai2->ListRecords($options);


}


echo $result;
