<?php
include "msg.php";


function MsgErr($msg, $filename) {
 $image  = imagecreate(200, 30); /* Create a black image */
 $tc = imagecolorallocate($image, 255, 255, 255);
 $bgc  = imagecolorallocate($image, 0, 0, 0);
 imagefilledrectangle($image, 0, 0, 200, 30, $bgc);
 /* Output an errmsg */
 imagestring($image, 2, 50, 2, $msg, $tc);
 imagestring($image, 2, 5, 15, $filename, $tc);
 return $image;
}

//----------------------------------------------------------------
function OutputPdf($filename, $resize, $width) {
//echo "OutputPdf($filename, $resize, $width)<br>\n";
//echo "is_writable(dirname($filename))=".is_writable(dirname($filename))."<br>\n";
//echo "extension_loaded('imagick')=".extension_loaded('imagick')."<br>\n";
//exit;
 if (!empty($width) && $resize != 'no') {
	if (extension_loaded('imagick')) {
		$thumb = dirname($filename).DIRECTORY_SEPARATOR.'thumb-'.basename($filename,'.pdf').'.png';
		if (!file_exists($thumb) && is_writable(dirname($thumb))) {
			try {
				$im = new Imagick($filename."[0]"); // 0-first page, 1-second page
				$im->setImageColorspace(255); // prevent image colors from inverting
				$im->setImageFormat('png');
				$im->thumbnailimage($width, $width, true);
				$im->writeImage($thumb);
			} catch (Exception $e) { }
		}
		if (file_exists($thumb)) {
			header('Location: '.GetFullUrl($thumb));
			exit;
		}
	}
 }	
 header('Location: '.GetFullUrl(Configuracao('DIR_IMAGENS')).'/pdf.png');
}

//----------------------------------------------------------------
function Load(&$image, $filename) {
  $image_info = @getimagesize($filename);
  $image_type = $image_info[2];
  if( $image_type == IMAGETYPE_JPEG ) {
	$image = @imagecreatefromjpeg($filename);
  } elseif( $image_type == IMAGETYPE_GIF ) {
	$image = @imagecreatefromgif($filename);
  } elseif( $image_type == IMAGETYPE_PNG ) {
	$image = @imagecreatefrompng($filename);
  }

  if (empty($image)) { /* See if it failed */
	$image = MsgErr('Error loading:', $filename);
	return false;
  }

  return true;
}

function Save($image, $filename, $image_type=IMAGETYPE_JPEG, $compression=75, $permissions=null) {
  if( $image_type == IMAGETYPE_JPEG ) {
	imagejpeg($image,$filename,$compression);
  } elseif( $image_type == IMAGETYPE_GIF ) {
	imagegif($image,$filename);
  } elseif( $image_type == IMAGETYPE_PNG ) {
	imagepng($image,$filename);
  }
  if( $permissions != null) {
	chmod($filename,$permissions);
  }
}

function OutputImg($image, $image_type=IMAGETYPE_JPEG) {
  if( $image_type == IMAGETYPE_JPEG ) {
	imagejpeg($image);
  } elseif( $image_type == IMAGETYPE_GIF ) {
	imagegif($image);
  } elseif( $image_type == IMAGETYPE_PNG ) {
	imagepng($image);
  }
}

function GetWidth($image) {
  return imagesx($image);
}

function GetHeight($image) {
  return imagesy($image);
}

function Resize($image, $width, $height) {
  $new_image = imagecreatetruecolor($width, $height);
  imagecopyresampled($new_image, $image, 0, 0, 0, 0, $width, $height, GetWidth($image), GetHeight($image));
  return $new_image;
}

function RatioToHeight($image, $height) {
  $ratio = $height / GetHeight($image);
  return $ratio;
}

function RatioToWidth($image, $width) {
  $ratio = $width / GetWidth($image);
  return $ratio;
}

function RatioToBoth($image, $width, $height) {
  $ratioW = $width / GetWidth($image);
  $ratioH = $height / GetHeight($image);
  return min($ratioW, $ratioH);
}

function Scale($image, $scale) {
  $width = GetWidth($image) * $scale/100;
  $height = Getheight($image) * $scale/100; 
  return Resize($image, $width, $height);
}

//---main-------------------------------------------------------------------------
$file = isset($_REQUEST['file']) ? $_REQUEST['file'] : '';
if(empty($file) || !is_file($file))
	exit;

$exibiu = false;
$new_width = isset($_REQUEST['width']) ? $_REQUEST['width'] : '';
$new_height = isset($_REQUEST['height']) ? $_REQUEST['height'] : '';
$resize = isset($_REQUEST['resize']) ? $_REQUEST['resize'] : '';
$sExt = substr($file, strlen($file)-4);
if ($sExt == ".pdf") {
    OutputPdf($file, $resize, $new_width);
    $exibiu = true;
}
else if ($sExt == ".jpg" && $resize != 'no') {
    if (function_exists('gd_info') && extension_loaded('gd')) {
//print_r(getimagesize($file)); exit;
        $path = isset($_REQUEST['path']) ? $_REQUEST['path'] : '';
        try {
                ob_start();
                if (Load($image, $path.$file)) 
                {
						if (!empty($new_height) || !empty($new_width))
						{
							if (empty($new_height))
								$ratio = RatioToWidth($image, $new_width);
							else if (empty($new_width))
								$ratio = RatioToHeight($image, $new_height);
							else
								$ratio = RatioToBoth($image, $new_width, $new_height);
//$imagesize=getimagesize($file); $width=$imagesize[0]; $height=$imagesize[1];
//echo "width=$width, height=$height ; new_width=$new_width, new_height=$new_height ; scale=$scale \n"; exit;
							$image = Scale($image, $ratio*100);
						}
//Save($image,'/tmp/save.jpg');
                        header('Content-Type: image/jpeg');
                        OutputImg($image);
                        // Tudo certo entao exibe a imagem tratada
                        ob_end_flush();
                        $exibiu = true;
                }
        } catch (Exception $e) {
                ob_end_clean();
        }
    }
}

if (!$exibiu)
    header('Location: '.GetFullUrl($file));
?>
