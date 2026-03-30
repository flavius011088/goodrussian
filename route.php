<?php

//die('TEMP NOT AVAILABLE');

//K0XA's SERVER CONF FILE

//нужно только для ограничений предпросмотра на сервере

//если вас смущает, можете убрать

if (file_exists('/local/php/local_conf.php')) {

	include_once('/local/php/local_conf.php');
	include_once('/local/php/rtools_security.php');

}



$d = '.__route';

$assert['encoding']=file_exists("$d/encoding.txt")?explode("\n", trim(file_get_contents("$d/encoding.txt"))):NULL;

$assert['head']=file_exists("$d/head.txt")?file_get_contents("$d/head.txt"):'';

$assert['begin']=file_exists("$d/begin.txt")?file_get_contents("$d/begin.txt"):'';

$assert['end']=file_exists("$d/end.txt")?file_get_contents("$d/end.txt"):'';



$p = $_SERVER['QUERY_STRING'];
//die($p);

//некоторые дефолтные конфигурации apache дают '&' вместо '?' при rewrite..
//и добавляют еще раз имя скрипта
//подстрахуемся
$p = str_replace('route.php&', '', $p);
$p = str_replace('route.php', '', $p);

$routes = file_get_contents("$d/route.txt");


list($p, $routed_file) = find_best_match($routes, $p);

// Basic Protection

$forbidden_regex = array('^\.\./', '\./\.', '^\.__route/.*$', '^route\.php', '^\.htaccess', '\.\.');
foreach ($forbidden_regex as $re) {

	if(preg_match("#$re#", $p)) {
	       die_not_found($p.'<!--.denied-->');
	}
}



if (!file_exists($p)  || is_dir($p)) {
	send_guessed_headers_by_filename($routed_file);
	try {
	    $page = file_get_contents($routed_file);
        } catch (Exception $e) {
            if (is_dir($p)) {
		try {
	          $p .= 'index.html';
                  $page = file_get_contents($p);
                } catch (Exception $e) {
                   die_not_found($p.'<!--indexnotfound-->');
                }
	    }
	}

} else {
	send_guessed_headers_by_filename($p);
	$page = file_get_contents($p);



}






if ($assert['encoding'] != NULL and sizeof($assert['encoding']) == 2) 
{
    $page = fix_encoding($page, $assert['encoding'][0], $assert['encoding'][1]);
}

if ($assert['head']) $page = preg_replace('#<head>#i', '<head>'.$assert['head'], $page, 1);


//jkeks 🎂 hello ;) 
//if ($assert['head']) $page = preg_replace('#</head>#i', $assert['head'].'</head>', $page, 1);

//if ($assert['begin']) $page = preg_replace('#<body>#i','<body>'.$assert['begin'], $page);
if ($assert['begin']) $page = preg_replace('#(<body(.*?)>)#i','${1}'.$assert['begin'], $page, 1);

if ($assert['end']) $page = preg_replace('#</body>#i', $assert['end'].'</body>', $page, 1);







//SAPE на сервере k0xa'и нельзя подключить в целях безопасности :)

//у вас же все будет работать ОК

if (!defined('K0XA_SERVER')) {



	if (file_exists($d.'/sape.php') && file_exists($d.'/sape_conf.php')) {

		include_once($d.'/sape_conf.php');

		include_once($d.'/sape.php');
//              $sape = new SAPE_client(array('charset'=>'utf-8', 'force_show_code' => true));
                $sape = new SAPE_client(array('charset'=>'utf-8'));

		$page = preg_replace('#\{SAPE\((\d+)\)\}#e', '$sape->return_links($1)', $page);

	}



}






//OUTPUT
echo $page;


function fix_encoding($html, $convert_from='utf-8', $convert_to_encoding='windows-1251')
{
    if(!$convert_from)
    {
       $convert_from = mb_detect_encoding($html);
    }

    if(($convert_from != '') && ($convert_to_encoding!='') )
    {
        $html = mb_convert_encoding($html, $convert_from, $convert_to_encoding);    
    }

    return $html;
}


function send_guessed_headers_by_filename($filename='') {
 $extensions = array(
   'js'=>'application/javascript',
   'html'=>'text/html',
   'htm'=>'text/html',

  'jpg'=>'image/jpeg',
  'jpeg'=>'image/jpeg',
  'png'=>'image/png',
  'apng'=>'image/apng',
  'avif'=>'image/avif',
  'gif'=>'image/gif',
  'svg'=>'image/svg+xml',
  'webp'=>'image/webp',
  'bmp'=>'image/bmp',
  'ico'=>'image/x-icon',
  'tif'=>'image/tiff',
  'tiff'=>'image/tiff',


  'css'=>'text/css',
);
   $content_type = 'text/html';
 
   if (preg_match('#.+\.(\S+)$#', $filename, $matches)) {
        $file_extension = $matches[1];
        foreach($extensions as $ext=>$cont) {
          if ($ext == strtolower($file_extension)) {
             $content_type = $cont;
             break;
          }
        }
   } 

header("Content-Type: $content_type; charset=UTF-8");

}


//21/12/2922: LITTLE FIX: to fix downloads started not from '/' (root)
function find_best_match($routes, $p='') {
 $routed_file = '';

	if(!$p) {
		 if (preg_match('#^/\s*=>\s*(\S+)\s*#', $routes, $match)) {
			$p = '/';
			$routed_file = $match[1];
		 }
		 else if (file_exists('./index.html')) {
		     $p = '/';
		     $routed_file = './index.html';
		 }
		 //*take the very first rule from route; will point to 'first_link'
		 else if (preg_match('#^/(.+?)\s*=>\s*(\S+)\s*#s', $routes, $matched)) {
			$p = $matched[1];
//die($p);
			$routed_file = $matched[2];
                 }
        } else {

           $regex  = preg_quote($p).'\s*=>\s*(.*)';

             if (preg_match("#\s/$regex#", $routes, $matches)) {

                 $routed_file = trim($matches[1]);

                 if (!file_exists($routed_file)) {
                        if (file_exists($p.'.html')) {
			   $routed_file = $p.'.html';
                        }
			elseif (is_dir($p)) {
                	    if (!preg_match('#/$#', $p)) {
				$p .= '/';
			    }
                            $routed_file = $p . 'index.html';
                            if (!file_exists($routed_file)) {
                   		     die_not_found($p.'<!--indexnotfound:1-->');
		            }


                        } else {
                            //fix
                            die_not_found($p.'<!--.notfound-->');
                        }
                  }

              } else {
			
			if (file_exists($p.'.html')) {
			   $routed_file = $p.'.html';
			}
			elseif (is_dir($p)) {
                	      if (!preg_match('#/$#', $p)) {
			         $p .= '/';
			      }
                 	      $routed_file = $p . 'index.html';
                              if (!file_exists($routed_file)) {
                   		     die_not_found($p."<!--indexnotfound:2;p=$p;r=$routed_file-->");
		              }

                         }


	       }


        }

//  return $routed_file ? array($p, $routed_file) : die_not_found($p.'<!--.notfnd-->');
  return $routed_file ? array($p, $routed_file) : array($p, $p);
}


function die_not_found($page) {

header("HTTP/1.1 404 Not Found");

$text = <<< EOH

<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">

<html><head>

<title>404 Not Found</title>

</head><body>

<h1>Not Found</h1>

<p>The requested URL /$page was not found on this server.</p>

<hr>

<address>Apache/2.6.18 (Ubuntu) Server at $_SERVER[HTTP_HOST] Port 80</address>

</body></html>

EOH

;

die($text);

}

?>


