<?php
error_reporting(0);
if ($argc > 1){
	$dir = $argv[1];
}

$allowPref = array(
	'Package',
	'Section',
	'Installed-Size',
	'Maintainer',
	'Architecture',
	'Version',
	'Depends',
	'Filename',
	'Size',
	'MD5sum',
	'SHA1',
	'SHA256',
	'SHA512',
	'Description',
	'Name',
	'Author',
	'Depiction',
	'Source',
	'Priority',
	'Essential',
	'Pre-Depends',
	'Recommends',
	'Suggests',
	'Conflicts',
	'Provides',
	'Replaces',
	'Enhances',
	'Origin',
	'Bugs',
	'Homepage',
	'Website',
	'Icon'
);


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
$package_array = array();
$tmp_array = array();
$tmp_key_array = array();
foreach ($files as $v) {
	unset($tmp_array,$tmp_key);
    $tmp_array = GetControlInfo($v);
	if(!is_array($tmp_array))
		continue;
	$tmp_key = array_keys($tmp_array);
	$tmp_key = $tmp_key[0];
	foreach ($tmp_array[$tmp_key] as $k=>$v){
		$package_array[$tmp_key][$k] = $v;
		$tmp_key_array[$tmp_key][] = $k;
	}
	$i++;
	$process_num = intval($i/$file_count*100);
	if($process_num>$now_process_num){
		$now_process_num = $process_num;
		echo "读取控制信息：{$process_num}%\r\n";
	}
}

$i = 0;
$now_process_num = 0;
foreach ($tmp_key_array as $k=>$v){
	$newv = quickSort($v);
	foreach($newv as $kk=>$vv){
		//echo $k."\r\n";
		$packages_content .= $package_array[$k][$vv].PHP_EOL;
	}
	$i++;
	$process_num = intval($i/$file_count*100);
	if($process_num>$now_process_num){
		$now_process_num = $process_num;
		echo "正在生成文件：{$process_num}%\r\n";
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
	$package_array = makePackage($full_filename,$control_string);
	return $package_array;
}

function makePackage($filename,$control_string){
	global $allowPref;
	$control_fields = explode("\n", $control_string);
	$control = array();
	foreach ($control_fields as $field) {
		if (!strlen(trim($field)))
			continue;
		$tmp = explode(":", $field, 2);
		if(trim($tmp[1]) && stripos($field,':') !== false){
			$tmp_last = $tmp;
			$control[$tmp[0]] = trim($tmp[1]);
		}elseif(stripos($field,':') === false)
			$control[$tmp_last[0]] .= PHP_EOL .$tmp[0];
	}
	if (!isset($control['Package']) || !isset($control['Version']))
		return false;
	if(trim($control['Depiction']))
		$control['Depiction'] = str_ireplace('http://repo.auxiliumdev.com/','https://lwlsw.github.io/repo/',$control['Depiction']);
	$control['Size'] = filesize($filename);
	$control['MD5sum'] = md5_file($filename);
	$control['SHA256'] = hash_file('sha256',$filename);
	$control['SHA512'] = hash_file('sha512',$filename);
	if ($control['MD5sum'] === false)
		return false;
	unset($control['Filename']);
	$control["Filename"] = $filename;
	
	$control_sort = array();
	foreach($allowPref as $v){
		if($control[$v]){
			$control_sort[$v] = $control[$v];
		}
	}
	
	foreach ($control_sort as $k => $v)
		if(is_numeric($k))
			$packages_string .= $v . "\n";
		else
			$packages_string .= $k . ": " . $v . "\n";
	
	$packages_Array[$control['Package']][$control['Version']] = $packages_string;
	return $packages_Array;
}

//版本号排序
function quickSort($arr) {
    //先判断是否需要继续进行
    $length = count($arr);
    if($length <= 1) {
        return $arr;
    }
    //选择第一个元素作为基准
    $base_num = $arr[0];
    //遍历除了标尺外的所有元素，按照大小关系放入两个数组内
    //初始化两个数组
    $left_array = array();  //小于基准的
    $right_array = array();  //大于基准的
    for($i=1; $i<$length; $i++) {
        if(version_compare($base_num,$arr[$i]) < 0) {
            //放入左边数组
            $left_array[] = $arr[$i];
        } else {
            //放入右边
            $right_array[] = $arr[$i];
        }
    }
    //再分别对左边和右边的数组进行相同的排序处理方式递归调用这个函数
    $left_array = quickSort($left_array);
    $right_array = quickSort($right_array);
    //合并
    return array_merge($left_array, array($base_num), $right_array);
}