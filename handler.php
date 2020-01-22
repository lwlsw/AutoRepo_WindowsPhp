<?php
error_reporting(0);
if ($argc > 1){
	$dir = $argv[1];
}
$packname = 'Packages';
$handler = opendir($dir);
while (($filename = readdir($handler)) !== false) {
	//务必使用!==，防止目录下出现类似文件名“0”等情况
	if ($filename != "." && $filename != "..") {
		if(substr(strrchr($filename, '.'), 1)=='deb'){
			$files[] = $filename ;
		}
	}
}
closedir($handler);

echo "生成中，请稍后...\r\n";

unlink($packname);
unlink("{$packname}.bz2");
$packages_content = '';
$file_count = count($files);

$i = 0;
$now_process_num = 0;
foreach ($files as $v) {
	unset($string);
    $string = GetControlInfo($v);
	$packages_content .= $string.PHP_EOL;
	$i++;
	$process_num = intval($i/$file_count*100);
	if($process_num>$now_process_num){
		$now_process_num = $process_num;
		echo "已生成{$process_num}%\r\n";
	}
}

file_put_contents($packname, $packages_content);
file_put_contents("{$packname}.bz2", bzcompress($packages_content));

function GetControlInfo($filename){
	global $dir;
	unlink('tmp\control.tar');
	unlink('tmp\control');
	
	exec('bin\ar\ar -t "' . $dir . '\\' . $filename . '"',$file_array);
	foreach($file_array as $v){
		if(stripos($v,'control')!==false){
			$tar_name = $v;
		}
	}
	
//	$re = system('bin\ar\ar -x ' . $dir . '\\' . $filename . ' ' . $tar_name,$code);
	exec('bin\ar\ar -x "' . $dir . '\\' . $filename . '" ' .$tar_name);
//	echo $filename . "\r\n";
	exec('bin\7z\7z e -otmp -aoa '.$tar_name);
	if(is_file('tmp\control.tar')){
		$tmp_tar = 'tmp\control.tar';
	}else{
		$handler = opendir('tmp');
		while (($tmpfilename = readdir($handler)) !== false) {
			if ($tmpfilename != "." && $filename != "..") {
				if(stripos($tmpfilename,'tmp')!==false){
					$tmp_tar = 'tmp\\'.$tmpfilename;
					break;
				}
			}
		}
	}
	exec('bin\7z\7z e -otmp -aoa ' . $tmp_tar);
	$control_string = file_get_contents('tmp\control');
	unlink($tmp_tar);
	unlink('tmp\control');
	unlink($tar_name);
	$full_filename = './' . $dir . '/' . $filename;
	$package_string = makePackage($full_filename,$control_string);
	return $package_string;
}

function makePackage($filename,$control_string){
	$control_fields = explode("\n", $control_string);
	$control = array();
	foreach ($control_fields as $field) {
		if (!strlen($field))
			continue;
		$tmp = explode(":", $field, 2);
		$control[$tmp[0]] = trim($tmp[1]);
	}
	if (!isset($control['Package']) || !isset($control['Version']))
		return false;
	$control['Size'] = filesize($filename);
	$control['MD5sum'] = md5_file($filename);
	$control['SHA256'] = hash_file('sha256',$filename);
	$control['SHA512'] = hash_file('sha512',$filename);
	if ($control['MD5sum'] === false)
		return false;
	$control["Filename"] = $filename;
	foreach ($control as $k => $v)
		$packages_string .= $k . ": " . $v . "\n";
	
	return $packages_string;
}