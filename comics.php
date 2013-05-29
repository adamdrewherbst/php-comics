<?php
//THE MAIN CODE IS AT THE BOTTOM

//phpinfo();
error_reporting(E_ERROR);

function getHTML($path) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$path);
	curl_setopt($ch, CURLOPT_FAILONERROR,1);
	//curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	$retValue = curl_exec($ch);			 
	curl_close($ch);
	return $retValue;
}

//print a DOMNodeList tree
function printnodes($nodes, $level) {
	foreach($nodes as $node) {
		echo $node->nodeName;
		if($node->hasAttributes()) {
			echo ': ';
			foreach($node->attributes as $attr) {
				echo $attr->nodeName.' = '.$attr->nodeValue.'; ';
			}
		}
		echo "\n";
		if(!$node->hasChildNodes()) {
			//echo "&nbsp;&nbsp;(no children)\n";
			continue;
		}
		foreach($node->children as $child) {
			printnodes($child, $level+1);
		}
	}
}

function getNodeValue($xpath, $query) {
	$elem = $xpath->query($query);
	if($elem->length < 1) return '';
	if(!is_object($elem->item(0))) return '';
	return $elem->item(0)->nodeValue;
}

function getAttrValue($xpath, $query, $attr) {
	$elem = $xpath->query($query);
	if($elem->length < 1) return '';
	if(!is_object($elem->item(0))) return '';
	if(!$elem->item(0)->hasAttributes()) return '';
	$attrnode = $elem->item(0)->attributes->getNamedItem($attr);
	if(!is_object($attrnode)) return '';
	return $attrnode->nodeValue;
}

function followlink($node, $file) {
	
	global $numLinks, $numRetrieved;
	
	if(!$node->hasAttributes()) return;
	$href = $node->attributes->getNamedItem('href');
	if($href == null) return;
	$href = $href->nodeValue;
	if($href == null || $href == '') return;
	$host = parse_url($href, PHP_URL_HOST);
	if($host == 'www.washingtonpost.com') return; //this one sucks
	$html = getHTML($href);
	$doc = new DOMDocument();
	$doc->loadHTML($html);
	$xpath = new DOMXPath($doc);
	$img = '';
	
	//echo "<p>checking $href<p>";
	$numLinks++;
	
	$name = 'Unknown';
	$author = 'by Unknown';

	switch($host) {
		case 'comics.washingtonpost.com':
		case 'wpcomics.washingtonpost.com':
		case 'www.uclick.com':
			$img = getAttrValue($xpath, '//div[@id="comic_full"]/img', 'src');
			$ret = getNodeValue($xpath, '//div[@id="comic_title"]/div[@id="name"]/h1');
			if($ret != '') $name = $ret;
			$ret = getNodeValue($xpath, '//div[@id="comic_title"]/div[@id="name"]/div[@class="author"]');
			if($ret != '') $author = $ret;
		break;
		case 'www.washingtonpost.com': break; //this one is too convoluted
			foreach($doc->getElementsByTagName('img') as $imgnode) {
				if(!$imgnode->hasAttributes()) continue;
				$srcattr = $imgnode->attributes->getNamedItem('src');
				//if(is_object($srcattr)) echo '<p>&nbsp;&nbsp;&nbsp;&nbsp;img: '.$srcattr->nodeValue.'<p>';
			}
		break;
		case 'gocomics.com':
		case 'www.gocomics.com':
			$img = getAttrValue($xpath, '//div[@id="content"]//img[@class="strip"]', 'src');
			$ret = getNodeValue($xpath, '//div[@id="content"]//h1/a');
			if($ret != '') $name = $ret;
			$ret = getNodeValue($xpath, '//div[@id="content"]//h1/span');
			if($ret != '') $author = $ret;
		break;
	}
	
	//fwrite($file, '  '.$child->nodeName.' -> '.$child->nodeValue.', '.$child->nodeType.', '.$href.', '.$img."\n");
	if($img == '') return;
	$numRetrieved++;
	
	$str = "<p>$name - $author<br><img src=\"$img\" border=\"0\" alt=\"\">\n";
	echo $str;
	fwrite($file, $str);
}

//THE SCRIPT STARTS RUNNING HERE
$path = 'http://www.washingtonpost.com/entertainment/comics';
$sXML = getHTML($path);
//$sXML = @mb_convert_encoding($sXML, 'HTML-ENTITIES', 'utf-8');

//$implementation = new DOMImplementation();

//this is the DTD spec at the top of the Wash Post comics page 
//$dtd = $implementation->createDocumentType('html',
//        '-//W3C//DTD HTML 4.01 Transitional//EN',
//        'http://www.w3.org/TR/html4/loose.dtd');
		
//$oXML = $implementation->createDocument('', '', $dtd);
$oXML = new DOMDocument();

@$oXML->loadHTML($sXML);

$numLinks = 0;
$numRetrieved = 0;
$dateFormat = 'l, F jS, Y';

$xpath = new DOMXPath($oXML);
//get the last update date from the site
$node = $xpath->query('//meta[@name="eomportal-lastUpdate"]')->item(0);
if(is_object($node) and $node->hasAttributes()) $attr = $node->attributes->getNamedItem('content');
if(is_object($attr)) $date = $attr->nodeValue;
if($date != '') $time = strtotime($date);
if($time > 0) $updateSite = date($dateFormat, $time);
//echo "<p>Site last updated $updateSite<p>\n";

$filename = 'comics.html';
$file = fopen($filename,'c+');
//use the cache file if it is up to date
$line = fgets($file);
if(preg_match('/<p>Washington Post Comics for ([A-Za-z]+.*)<p>/', $line, $matches) and $matches[1] == $updateSite) {
	fseek($file, 0, SEEK_SET);
	$html = fread($file, filesize($filename));
	//echo "<p>Using cache file<p>\n";
	echo $html;
	goto done;
}

//otherwise do the full link-out and store the cache file
ftruncate($file, 0);
fseek($file, 0, SEEK_SET);
fwrite($file, '<p>Washington Post Comics for '.date($dateFormat)."<p>\n\n");
echo '<p>Washington Post Comics for '.date($dateFormat)."<p>\n\n";

$ind = 0;
foreach($xpath->query('//div[@class="module l1-2"]//ul[@class="normal"]/li/a[@href]') as $node) {
	followlink($node, $file);
	$ind++;
	//if($ind > 4) break;
}

//echo "<p>$numRetrieved of $numLinks comics retrieved<p>\n";
done: fclose($file);

?>
